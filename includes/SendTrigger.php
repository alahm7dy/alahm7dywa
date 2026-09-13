<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SendTrigger {

    private $prefix = 'mpedia_wagateway';
    private $api_key, $admin_recipients, $endpoint, $sender;
    private $admin_enabled, $default_tpl, $admin_tpl;
    private $telegram_enabled, $telegram_bot_token, $telegram_chat_id, $telegram_tpl;

    public function __construct() {
        $this->api_key          = get_option( $this->prefix . 'api_key', '' );
        $this->endpoint         = rtrim( get_option( $this->prefix . 'url', '' ), '/' );
        $this->sender           = get_option( $this->prefix . 'device', '' );
        $this->admin_recipients = get_option( $this->prefix . 'admin_sms_recipients', '' );
        $this->admin_enabled    = get_option( $this->prefix . 'enable_admin_sms' ) === 'yes';
        $this->default_tpl      = get_option( $this->prefix . 'default_sms_template', 'مرحباً {{first_name}}، طلبك #{{order_id}} أصبح {{order_status}}. شكراً لتسوقك في {{shop_name}}!' );
        $this->admin_tpl        = get_option( $this->prefix . 'admin_sms_template', 'طلب جديد #{{order_id}} من {{first_name}} {{last_name}}. المبلغ: {{order_amount}}.' );
        $this->telegram_enabled   = get_option( $this->prefix . 'enable_telegram_sms' ) === 'yes';
        $this->telegram_bot_token = get_option( $this->prefix . 'telegram_bot_token', '' );
        $this->telegram_chat_id   = get_option( $this->prefix . 'telegram_chat_id', '' );
        $this->telegram_tpl       = get_option( $this->prefix . 'telegram_template', '' );

        add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_change' ], 11, 3 );
        add_filter( 'woocommerce_order_actions', [ $this, 'add_order_action' ] );
        add_action( 'woocommerce_order_action_mpwa_send_status', [ $this, 'send_status_from_order' ] );
        add_action( 'woocommerce_new_customer_note',    [ $this, 'on_customer_note' ] );
        add_action( 'mpwa_retry_send',                  [ $this, 'do_retry' ], 10, 4 );
        add_action( 'woocommerce_thankyou',             [ $this, 'capture_contact' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'schedule_feedback_poll' ], 10, 1 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'send_invoice_receipt' ], 10, 1 );
        add_action( 'mpwa_feedback_poll_cron',          [ $this, 'send_feedback_poll' ], 10, 2 );
    }

    /* ── Hooks ─────────────────────────────────────────────────── */

    public function on_status_change( $order_id, $from, $to ) {
        if ( get_option( $this->prefix . 'send_sms_' . $to, 'yes' ) !== 'yes' ) return;
        // Send immediately so delivery does not depend on WP-Cron being configured.
        $key = 'mpwa_status_queued_' . absint( $order_id ) . '_' . sanitize_key( $to );
        if ( get_transient( $key ) ) return;
        set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
        if ( ! $this->process( absint( $order_id ), sanitize_key( $to ) ) ) delete_transient( $key );
    }

    public function add_order_action( $actions ) {
        $actions['mpwa_send_status'] = 'إرسال إشعار الحالة عبر WhatsApp الآن';
        return $actions;
    }

    public function send_status_from_order( $order ) {
        $order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? $order->get_id() : absint( $order );
        $order_obj = wc_get_order( $order_id );
        if ( ! $order_obj ) return;

        // Manual action intentionally bypasses the automatic duplicate guard.
        $sent = $this->process( $order_id, $order_obj->get_status() );
        $order_obj->add_order_note( $sent ? 'تم إرسال إشعار حالة الطلب عبر WhatsApp يدويًا.' : 'فشل إرسال إشعار حالة الطلب عبر WhatsApp. راجع سجل الإرسال وإعدادات البوابة.' );
    }

    public function schedule_feedback_poll( $order_id ) {
        if ( get_option( $this->prefix . 'enable_feedback_poll', 'no' ) !== 'yes' ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        $phone = $order->get_billing_phone();
        if ( empty($phone) ) return;
        
        // Schedule for 24 hours later
        $timestamp = time() + (24 * 3600);
        
        wp_clear_scheduled_hook( 'mpwa_feedback_poll_cron', [ $order_id, $phone ] );
        wp_schedule_single_event( $timestamp, 'mpwa_feedback_poll_cron', [ $order_id, $phone ] );
    }

    public function send_invoice_receipt( $order_id ) {
        if ( get_option( $this->prefix . 'enable_invoice_receipt', 'no' ) !== 'yes' ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $phone = $order->get_billing_phone();
        if ( empty($phone) ) return;

        $billing_country = $order->get_billing_country();
        $order_cc = get_option( $this->prefix . 'default_country_code', '965' );
        if ( !empty($billing_country) && function_exists('WC') && isset(WC()->countries) ) {
            $cc = WC()->countries->get_country_calling_code( $billing_country );
            if($cc) $order_cc = str_replace('+', '', $cc);
        }
        $formatted_phone = self::format_phone_number( $phone, $order_cc );

        $receipt_url = site_url( '?mpwa_receipt=' . $order->get_order_key() );
        
        $buttons = [
            [ 'type' => 'url', 'displayText' => '🧾 عرض الفاتورة', 'url' => $receipt_url ]
        ];

        $image_url = get_site_icon_url() ? get_site_icon_url() : 'https://via.placeholder.com/300x300.png?text=Invoice';
        $customer_name = $order->get_billing_first_name() ?: 'عميلنا العزيز';
        $message = "مرحباً {$customer_name} 👋\n";
        $message .= "نشكرك على تسوقك معنا. لقد اكتمل طلبك رقم #{$order_id} بنجاح.\n";
        $message .= "يمكنك الاطلاع على فاتورة الطلب عبر الزر أدناه:";

        $ok = $this->send_button( $formatted_phone, $message, $buttons, $image_url, 'نتمنى لك يوماً سعيداً!' );

        if(class_exists('MpwaLogger')) {
            MpwaLogger::write( [
                'recipient'    => $formatted_phone,
                'message'      => $message . "\n[رابط الفاتورة]",
                'order_id'     => $order_id,
                'event_type'   => 'invoice_receipt',
                'status'       => $ok ? 'success' : 'failed',
                'http_code'    => $ok ? 200 : 0,
                'api_response' => $ok ? 'Sent Invoice via Button API' : 'Failed to send invoice',
                'error_msg'    => $ok ? '' : 'فشل إرسال الفاتورة عبر البوابة.'
            ]);
        }
    }

    public function send_feedback_poll( $order_id, $phone ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        $billing_country = $order->get_billing_country();
        $order_cc = get_option( $this->prefix . 'default_country_code', '965' );
        if ( !empty($billing_country) && function_exists('WC') && isset(WC()->countries) ) {
            $cc = WC()->countries->get_country_calling_code( $billing_country );
            if($cc) $order_cc = str_replace('+', '', $cc);
        }
        $formatted_phone = self::format_phone_number( $phone, $order_cc );

        $poll_name = "كيف تقيّم تجربتك معنا في الطلب رقم #{$order_id}؟";
        $options = [
            "⭐️⭐️⭐️⭐️⭐️ ممتاز",
            "⭐️⭐️⭐️⭐️ جيد جداً",
            "⭐️⭐️⭐️ متوسط",
            "⭐️⭐️ سيء",
            "⭐️ غير راضٍ"
        ];
        
        $ok = $this->send_poll( $formatted_phone, $poll_name, $options, 1 );
        
        if(class_exists('MpwaLogger')) {
            MpwaLogger::write( [
                'recipient'    => $formatted_phone,
                'message'      => "استطلاع رأي: " . $poll_name,
                'order_id'     => $order_id,
                'event_type'   => 'feedback_poll',
                'status'       => $ok ? 'success' : 'failed',
                'http_code'    => $ok ? 200 : 0,
                'api_response' => $ok ? 'Sent via Poll API' : 'Failed to send poll',
                'error_msg'    => $ok ? '' : 'فشل إرسال استطلاع الرأي عبر البوابة.'
            ]);
        }
    }

    public function notify_send_admin_sms_for_woo_new_order( $order_id ) {
        if ( $this->admin_enabled ) $this->process( $order_id, 'admin-order' );
        if ( $this->telegram_enabled ) $this->send_telegram( $order_id );
    }

    public function on_customer_note( $data ) {
        if ( get_option( $this->prefix . 'enable_notes_sms' ) !== 'yes' ) return;
        $this->process( $data['order_id'], 'new-note', $data['customer_note'] );
    }

    public function capture_contact( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) MpwaPhoneBook::upsert_from_order( $order );
    }

    /** Cron retry — called after 5 min if first attempt failed */
    public function do_retry( $phone, $message, $order_id, $event ) {
        $this->raw_send( $phone, $message, $order_id, $event, true );
    }

    /* ── Process ────────────────────────────────────────────────── */

    private function process( $order_id, $event, $extra = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return false;

        if ( $event === 'admin-order' ) {
            $message = $this->admin_tpl;
        } elseif ( $event === 'new-note' ) {
            $message = get_option( $this->prefix . 'note_sms_template', '' ) . $extra;
        } else {
            $message = get_option( $this->prefix . $event . '_sms_template', '' );
            if ( empty( $message ) ) $message = $this->default_tpl;
        }

        $message  = self::replace_shortcodes( $message, $order );
        $raw_nums   = ( $event === 'admin-order' ) ? $this->admin_recipients : $order->get_billing_phone();
        $numbers    = array_filter( array_map( 'trim', explode( ',', $raw_nums ) ) );
        $is_admin   = ( $event === 'admin-order' );

        // Get default country code
        $default_cc = get_option( $this->prefix . 'default_country_code', '965' );
        // Try to get country code from order
        $order_cc = $default_cc;
        if ( ! $is_admin && $order && function_exists('WC') ) {
            $billing_country = $order->get_billing_country();
            if ( ! empty( $billing_country ) && isset( WC()->countries ) ) {
                $calling_code = WC()->countries->get_country_calling_code( $billing_country );
                if ( ! empty( $calling_code ) ) {
                    // Remove any + sign if present
                    $order_cc = str_replace('+', '', $calling_code);
                }
            }
        }

        $sent = false;
        foreach ( $numbers as $raw ) {
            // أرقام الأدمن تُرسل كما هي (بدون كود دولة إجباري)
            // أرقام العملاء تُنسَّق بكود الدولة التلقائي
            $phone = $is_admin ? self::clean_number( $raw ) : self::format_phone_number( $raw, $order_cc );
            
            if ( ! $is_admin && $event === 'on-hold' ) {
                $buttons = [
                    ['type' => 'reply', 'displayText' => '✅ تأكيد الطلب'],
                    ['type' => 'reply', 'displayText' => '❌ إلغاء الطلب']
                ];
                $image = get_site_icon_url() ? get_site_icon_url() : 'https://via.placeholder.com/300x300.png?text=Order';
                
                $ok = $this->send_button($phone, $message, $buttons, $image, 'يرجى الرد لتأكيد طلبك');
                
                MpwaLogger::write( [
                    'recipient'    => $phone,
                    'message'      => $message . "\n[أزرار تأكيد الطلب]",
                    'order_id'     => $order_id,
                    'event_type'   => $event,
                    'status'       => $ok ? 'success' : 'failed',
                    'http_code'    => $ok ? 200 : 0,
                    'api_response' => $ok ? 'Sent via Button API' : '',
                    'error_msg'    => $ok ? '' : 'فشل إرسال رسالة الأزرار عبر البوابة.'
                ]);
                $sent = $ok || $sent;
            } else {
                $sent = $this->raw_send( $phone, $message, $order_id, $event, false ) || $sent;
            }
        }
        return $sent;
    }

    /* ── HTTP Send (with retry logic) ──────────────────────────── */

    private function raw_send( $phone, $message, $order_id, $event, $is_retry ) {
        $base = [ 'order_id' => $order_id, 'recipient' => $phone, 'event_type' => $event, 'message' => $message ];

        if ( empty( $this->endpoint ) || empty( $this->api_key ) || empty( $this->sender ) ) {
            MpwaLogger::write( array_merge( $base, [
                'status' => 'error', 'http_code' => 0, 'api_response' => '', 'error_msg' => 'بيانات API غير مكتملة.',
            ]));
            return false;
        }

        $client = new MpwaXGrowthClient();
        $response = $client->send_message( $phone, $message );

        /* ── Connection error ─── */
        if ( is_wp_error( $response ) ) {
            if ( ! $is_retry ) {
                // لا تسجّل — أعد المحاولة بعد 5 دقائق
                wp_schedule_single_event( time() + 300, 'mpwa_retry_send', [ $phone, $message, $order_id, $event ] );
                return false;
            }
            MpwaLogger::write( array_merge( $base, [
                'status' => 'failed', 'http_code' => 0, 'api_response' => '', 'error_msg' => $response->get_error_message(),
            ]));
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $api_body = $client->decode_response( $response );

        /* ── Non-2xx ─── */
        if ( $code < 200 || $code >= 300 || ! is_array( $api_body ) || empty( $api_body['status'] ) ) {
            if ( ! $is_retry ) {
                wp_schedule_single_event( time() + 300, 'mpwa_retry_send', [ $phone, $message, $order_id, $event ] );
                return false;
            }
            MpwaLogger::write( array_merge( $base, [
                'status' => 'failed', 'http_code' => $code,
                'api_response' => wp_remote_retrieve_body( $response ), 'error_msg' => $client->error_message( $response ),
            ]));
            return false;
        }

        /* ── Success ─── */
        MpwaLogger::write( array_merge( $base, [
            'status' => 'success', 'http_code' => $code,
            'api_response' => wp_remote_retrieve_body( $response ), 'error_msg' => '',
        ]));
        return true;
    }

    /* ── Advanced X-Growth APIs ──────────────────────────────── */
    
    /** إرسال رسالة إلى قناة (Channel Text) */
    public function send_channel( $channel_id, $message ) {
        $client = new MpwaXGrowthClient();
        return $client->is_success( $client->send_channel( $channel_id, $message ) );
    }

    /** إرسال كارت منتج (Product Message) */
    public function send_product( $phone, $product_url, $message ) {
        $client = new MpwaXGrowthClient();
        return $client->is_success( $client->send_product( $phone, $product_url, $message ) );
    }

    /** إرسال أزرار (Button Message) */
    public function send_button( $phone, $message, $buttons = [], $media_url = '', $footer = '' ) {
        if ( empty( $this->endpoint ) || empty( $this->api_key ) || empty( $this->sender ) || empty($buttons) ) return false;
        
        // API requires an image for buttons. Provide a fallback if empty.
        if ( empty($media_url) ) {
            $media_url = get_site_icon_url() ? get_site_icon_url() : 'https://via.placeholder.com/600x400.png?text=Message';
        }

        $client = new MpwaXGrowthClient();
        return $client->is_success( $client->send_button( $phone, $message, $buttons, $media_url, $footer ) );
    }

    /** إرسال وسائط (Media Message) */
    public function send_media( $phone, $media_type, $url, $caption = '', $footer = '' ) {
        $client = new MpwaXGrowthClient();
        return $client->is_success( $client->send_media( $phone, $url, $caption, $media_type, $footer ) );
    }

    /** إرسال استطلاع رأي (Poll Message) */
    public function send_poll( $phone, $name, $options = [], $countable = '1' ) {
        $client = new MpwaXGrowthClient();
        return $client->is_success( $client->send_poll( $phone, $name, $options, $countable ) );
    }

    /** إرسال قائمة تفاعلية (List Message) */
    public function send_list( $phone, $name, $title, $message, $buttontext, $sections = [], $image = '', $footer = '' ) {
        $client = new MpwaXGrowthClient();
        return $client->is_success( $client->send_list( $phone, $message, $name, $title, $buttontext, $sections, $image, $footer ) );
    }

    /** التحقق من الرقم (Check Number) */
    public static function check_number( $phone ) {
        $client = new MpwaXGrowthClient();
        $response = $client->check_number( $phone );
        if ( is_wp_error( $response ) ) return true;
        $res_body = $client->decode_response( $response );
        if ( isset($res_body['status']) && $res_body['status'] === true && isset($res_body['msg']['exists']) ) {
            return $res_body['msg']['exists'] === true;
        }
        return true; // Fallback
    }

    /* ── Device Management APIs ──────────────────────────────── */
    
    public static function info_devices() {
        $client = new MpwaXGrowthClient();
        return $client->decode_response( $client->device_info() );
    }

    public static function generate_qr() {
        $client = new MpwaXGrowthClient();
        return $client->decode_response( $client->generate_qr( true ) );
    }

    public static function logout_device() {
        $client = new MpwaXGrowthClient();
        return $client->decode_response( $client->logout_device() );
    }

    /* ── Public: send directly to a phone (for PhoneBook page and Webhook) ─ */
    public function send_direct( $phone, $message ) {
        $phone = self::clean_number( $phone );
        return $this->raw_send( $phone, $message, 0, 'direct', false );
    }

    /** إعادة الإرسال من السجل */
    public function retry_by_log_id( $log_id ) {
        $log = MpwaLogger::get_log_by_id( $log_id );
        if ( ! $log ) return false;
        return $this->raw_send( $log->recipient, $log->message, $log->order_id, $log->event_type, true );
    }

    /** إرسال رسالة اختبار حقيقية للأدمن */
    public function send_test_message() {
        if ( empty( $this->admin_recipients ) ) return false;
        $raw_nums = explode( ',', $this->admin_recipients );
        $first    = trim( $raw_nums[0] );
        $phone    = self::clean_number( $first );
        $msg      = '✅ اختبار بوابة واتساب: هذه رسالة تجريبية حقيقية من متجرك.';
        return $this->raw_send( $phone, $msg, 0, 'test', false );
    }

    /* ── Helpers ────────────────────────────────────────────────── */

    public function send_telegram( $order_id ) {
        if ( empty( $this->telegram_bot_token ) || empty( $this->telegram_chat_id ) ) return;
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        $message = $this->telegram_tpl;
        if ( empty( $message ) ) {
            $message = 'طلب جديد #{{order_id}} بقيمة {{order_amount}}';
        }
        $message = self::replace_shortcodes( $message, $order );
        
        $url = 'https://api.telegram.org/bot' . $this->telegram_bot_token . '/sendMessage';
        wp_remote_post( $url, [
            'body' => [
                'chat_id' => $this->telegram_chat_id,
                'text'    => $message,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 15,
        ]);
    }

    /**
     * تنظيف رقم بدون إضافة كود دولة — للأدمن والأرقام الدولية.
     * يزيل كل شيء ما عدا الأرقام ويزيل الأصفار البادئة.
     */
    public static function clean_number( $value ) {
        $phone = preg_replace( '/\D/', '', trim( $value ) );
        $phone = ltrim( $phone, '0' );
        return $phone;
    }

    public static function format_international_number( $value, $country_code = '' ) {
        $phone = preg_replace( '/\D/', '', trim( $value ) );
        $phone = ltrim( $phone, '0' );
        
        if ( empty( $country_code ) ) {
            $country_code = '965'; // Default to Kuwait if not specified
        }
        
        $country_code = preg_replace( '/\D/', '', $country_code );
        
        if ( strpos( $phone, $country_code ) !== 0 ) {
            $phone = $country_code . $phone;
        }
        
        return $phone;
    }

    /**
     * تنسيق رقم العميل — يضاف كود الدولة الممرر تلقائياً بذكاء دون تكرار.
     */
    public static function format_phone_number( $value, $country_code = '' ) {
        $phone = preg_replace( '/\D/', '', trim( (string) $value ) );
        $phone = ltrim( $phone, '0' );
        
        if ( empty( $country_code ) ) {
            $country_code = get_option( 'mpedia_wagatewaydefault_country_code', '965' );
        }
        $country_code = preg_replace( '/\D/', '', (string) $country_code );
        
        $arab_codes = ['966', '965', '971', '974', '973', '968', '20', '962', '961', '964', '963', '967', '212', '216', '213', '249', '218'];

        if ( ! empty( $country_code ) && strpos( $phone, $country_code ) === 0 ) {
            return $phone;
        }

        if ( strlen( $phone ) >= 11 ) {
            foreach ( $arab_codes as $ac ) {
                if ( strpos( $phone, $ac ) === 0 ) {
                    return $phone;
                }
            }
        }

        if ( ! empty( $country_code ) ) {
            $phone = $country_code . $phone;
        }
        return $phone;
    }

    public static function replace_shortcodes( $msg, $order ) {
        if ( ! is_a( $order, 'WC_Order' ) && function_exists('wc_get_order') ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) return $msg;

        $currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' );
        $formatted_amount = function_exists( 'wc_price' )
            ? wp_strip_all_tags( wc_price( $order->get_total(), [ 'currency' => $currency ] ) )
            : ( $order->get_total() . ' ' . $currency );

        $items_list = [];
        if ( method_exists( $order, 'get_items' ) ) {
            foreach ( $order->get_items() as $item ) {
                $items_list[] = '• ' . $item->get_name() . ' × ' . $item->get_quantity();
            }
        }
        $items_text = implode( "\n", $items_list );

        $status_name = function_exists( 'wc_get_order_status_name' ) 
            ? wc_get_order_status_name( $order->get_status() ) 
            : ucfirst( $order->get_status() );

        $tags = [
            '{{shop_name}}'       => get_bloginfo( 'name' ),
            '{{order_id}}'        => $order->get_order_number(),
            '{{order_amount}}'    => $formatted_amount,
            '{{currency}}'        => $currency,
            '{{order_status}}'    => $status_name,
            '{{first_name}}'      => $order->get_billing_first_name(),
            '{{last_name}}'       => $order->get_billing_last_name(),
            '{{customer_name}}'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            '{{billing_city}}'    => $order->get_billing_city(),
            '{{billing_address}}' => method_exists( $order, 'get_billing_address_1' ) ? $order->get_billing_address_1() : '',
            '{{customer_phone}}'  => $order->get_billing_phone(),
            '{{billing_email}}'   => $order->get_billing_email(),
            '{{payment_method}}'  => method_exists( $order, 'get_payment_method_title' ) ? $order->get_payment_method_title() : '',
            '{{order_items}}'     => $items_text,
            '{{tracking_link}}'   => $order->get_meta( '_tracking_link' ) ?: $order->get_meta( '_wc_shipment_tracking_items' ) ?: '',
        ];
        
        return str_replace( array_keys( $tags ), array_values( $tags ), $msg );
    }
}
