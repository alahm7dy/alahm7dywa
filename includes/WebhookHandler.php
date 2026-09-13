<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaWebhookHandler {

    public function __construct() {
        if ( ! get_option( 'mpedia_wagatewaywebhook_secret' ) ) {
            update_option( 'mpedia_wagatewaywebhook_secret', wp_generate_password( 48, false, false ), false );
        }
        add_action( 'rest_api_init', [ $this, 'register_webhook_route' ] );
    }

    public function register_webhook_route() {
        register_rest_route( 'mpwa/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_webhook' ],
            'permission_callback' => [ $this, 'verify_webhook' ]
        ]);
    }

    public function verify_webhook( WP_REST_Request $request ) {
        $secret = (string) get_option( 'mpedia_wagatewaywebhook_secret', '' );
        if ( strlen( $secret ) < 24 ) {
            return new WP_Error( 'mpwa_webhook_not_configured', 'Webhook secret is not configured.', [ 'status' => 503 ] );
        }
        $provided_secret = (string) $request->get_header( 'x-mpwa-webhook-secret' );
        if ( $provided_secret !== '' && hash_equals( $secret, $provided_secret ) ) return true;

        $signature = (string) $request->get_header( 'x-mpwa-signature' );
        $signature = preg_replace( '/^sha256=/i', '', trim( $signature ) );
        $expected = hash_hmac( 'sha256', $request->get_body(), $secret );
        if ( $signature !== '' && hash_equals( $expected, $signature ) ) return true;

        return new WP_Error( 'mpwa_invalid_webhook_signature', 'Invalid webhook signature.', [ 'status' => 401 ] );
    }

    public function handle_webhook( WP_REST_Request $request ) {
        if ( class_exists( 'MpwaRateLimiter' ) && ! MpwaRateLimiter::allow( 'webhook', 120, MINUTE_IN_SECONDS ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Rate limit exceeded' ], 429 );
        }
        // Get the JSON payload
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid JSON payload' ], 400 );
        }

        $event_id = sanitize_text_field( (string) (
            $body['event_id'] ?? $body['id'] ?? $body['data']['key']['id'] ?? ''
        ) );
        if ( $event_id !== '' ) {
            $replay_key = 'mpwa_webhook_' . md5( $event_id );
            if ( get_transient( $replay_key ) ) {
                return new WP_REST_Response( [ 'success' => true, 'action' => 'duplicate_ignored' ], 200 );
            }
            set_transient( $replay_key, 1, DAY_IN_SECONDS );
        }
        
        // Log the incoming webhook to help with debugging
        if(class_exists('MpwaLogger')) {
            MpwaLogger::write([
                'order_id' => 0,
                'recipient' => 'Webhook',
                'event_type' => 'incoming_message',
                'message' => 'Webhook event received' . ( $event_id ? ': ' . $event_id : '' ),
                'status' => 'received',
                'http_code' => 200,
                'api_response' => 'Webhook received',
                'error_msg' => ''
            ]);
        }

        // Ignore our own outgoing messages if the provider sends them
        if ( isset($body['data']['key']['fromMe']) && $body['data']['key']['fromMe'] == true ) {
            return new WP_REST_Response( [ 'success' => true, 'action' => 'ignored_from_me' ], 200 );
        }

        // Try to extract the phone number from common API structures
        $phone = '';
        if ( !empty($body['sender']) ) $phone = $body['sender'];
        elseif ( !empty($body['from']) ) $phone = $body['from'];
        elseif ( !empty($body['number']) ) $phone = $body['number'];
        elseif ( !empty($body['phone']) ) $phone = $body['phone'];

        // Try to extract the message text robustly
        $message = '';
        if ( isset($body['data']['message']['conversation']) ) {
            // X-Growth / Baileys format
            $message = $body['data']['message']['conversation'];
        } elseif ( !empty($body['message']) ) {
            if ( is_string($body['message']) ) {
                $message = $body['message'];
            } elseif ( is_array($body['message']) && isset($body['message']['conversation']) ) {
                $message = $body['message']['conversation'];
            }
        } elseif ( !empty($body['text']) ) {
            if(is_array($body['text']) && !empty($body['text']['body'])) $message = $body['text']['body'];
            elseif(is_string($body['text'])) $message = $body['text'];
        }

        // Extract Location
        $lat = null;
        $lon = null;
        if ( isset($body['data']['message']['locationMessage']) ) {
            $lat = $body['data']['message']['locationMessage']['degreesLatitude'] ?? null;
            $lon = $body['data']['message']['locationMessage']['degreesLongitude'] ?? null;
        } elseif ( isset($body['location']) ) { // Alternative simple format
            $lat = $body['location']['latitude'] ?? null;
            $lon = $body['location']['longitude'] ?? null;
        }

        // Extract Audio (Voice Notes)
        $audio_data = null; // Could be URL or base64
        if ( isset($body['data']['message']['audioMessage']) ) {
            $audio_data = $body['data']['message']['audioMessage']['url'] ?? '';
            // If base64 is provided by gateway
            if (empty($audio_data) && !empty($body['data']['message']['audioMessage']['base64'])) {
                $audio_data = $body['data']['message']['audioMessage']['base64'];
            }
        } elseif ( isset($body['audio']) ) {
            $audio_data = $body['audio'];
        }

        if ( empty($phone) || (empty($message) && empty($lat) && empty($audio_data)) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'No phone or content found in payload' ], 200 );
        }

        // Clean the phone number to match WooCommerce format
        if(class_exists('SendTrigger')){
            $clean_phone = SendTrigger::clean_number($phone);
        } else {
            $clean_phone = preg_replace( '/\D/', '', $phone );
        }

        // Action 1: Order Confirmation via WhatsApp
        $message = sanitize_textarea_field( (string) $message );
        $message_clean = trim($message);
        
        // Handle "✅ تأكيد الطلب" or "1"
        if ( $message_clean === '1' || $message_clean === '١' || strpos($message_clean, 'تأكيد الطلب') !== false ) {
            $order_id = $this->find_latest_order_by_phone( $clean_phone, ['on-hold', 'pending'] );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                $order->update_status( 'processing', 'تم تأكيد الطلب آلياً عبر الواتساب (Webhook).' );
                
                // Send a thank you reply
                if(class_exists('SendTrigger')) {
                    $trigger = new SendTrigger();
                    $trigger->send_direct($clean_phone, "شكراً لك! تم تأكيد طلبك رقم {$order_id} وجاري تجهيزه الآن. ✅");
                }
                
                return new WP_REST_Response( [ 'success' => true, 'action' => 'order_confirmed' ], 200 );
            }
        }

        // Handle "❌ إلغاء الطلب"
        if ( strpos($message_clean, 'إلغاء الطلب') !== false ) {
            $order_id = $this->find_latest_order_by_phone( $clean_phone, ['on-hold', 'pending'] );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                $order->update_status( 'cancelled', 'تم إلغاء الطلب من قبل العميل عبر الواتساب (Webhook).' );
                
                if(class_exists('SendTrigger')) {
                    $trigger = new SendTrigger();
                    $trigger->send_direct($clean_phone, "تم إلغاء الطلب رقم {$order_id} بنجاح. نتمنى رؤيتك قريباً. ❌");
                }
                
                return new WP_REST_Response( [ 'success' => true, 'action' => 'order_cancelled' ], 200 );
            }
        }

        // Action 2: Add incoming message as Order Note to their latest order
        $latest_order_id = $this->find_latest_order_by_phone( $clean_phone );
        if ( $latest_order_id ) {
            $order = wc_get_order( $latest_order_id );
            $note = "رد من العميل عبر الواتساب: \n\n" . $message;
            $order->add_order_note( $note, false, false );
        }

        // Action 1.1: Handle Location Capture
        $lat = is_numeric( $lat ) ? (float) $lat : null;
        $lon = is_numeric( $lon ) ? (float) $lon : null;
        if ( $lat !== null && $lon !== null && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180 ) {
            $order_id = $this->find_latest_order_by_phone( $clean_phone, ['processing', 'pending', 'on-hold'] );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                $map_url = "https://maps.google.com/?q={$lat},{$lon}";
                $note = "📍 تم استلام موقع العميل عبر الواتساب:\n" . $map_url;
                $order->add_order_note( $note, false, false );
                
                if(class_exists('SendTrigger')) {
                    $trigger = new SendTrigger();
                    $trigger->send_direct($clean_phone, "شكراً لك! 📍 تم استلام موقعك بنجاح وتم إرفاقه مع طلبك رقم {$order_id} لتسهيل التوصيل.");
                }
                return new WP_REST_Response( [ 'success' => true, 'action' => 'location_saved' ], 200 );
            }
        }

        // Action 1.5: Handle Feedback Poll Responses
        if ( strpos($message_clean, '⭐️') !== false ) {
            $star_count = mb_substr_count($message_clean, '⭐️');
            if ( $star_count > 0 && class_exists('SendTrigger') ) {
                $trigger = new SendTrigger();
                if ( $star_count >= 4 ) {
                    $reply = "شكراً لتقييمك الرائع! 🌟\nيسعدنا جداً رضاك عن خدماتنا. نرجو منك التكرم بترك تقييم لنا على هذا الرابط:\n" . site_url();
                } else {
                    $reply = "نعتذر بشدة إذا لم تكن تجربتك بالصورة المطلوبة 😔.\nسيتم مراجعة طلبك من قبل فريقنا للتحسين.";
                }
                $trigger->send_direct($clean_phone, $reply);
                return new WP_REST_Response( [ 'success' => true, 'action' => 'feedback_handled' ], 200 );
            }
        }

        // Action 1.6: Interactive Bot Menus (Help Menu)
        $help_keywords = ['مساعدة', 'خدمة العملاء', 'القائمة', 'help', 'menu'];
        if ( in_array(strtolower($message_clean), $help_keywords) && class_exists('SendTrigger') ) {
            $trigger = new SendTrigger();
            $menu_options = [[
                'title' => 'خيارات المساعدة',
                'rows' => [
                    ['title' => '📦 تتبع الطلب', 'rowId' => 'track_order', 'description' => 'معرفة حالة طلبك الأخير'],
                    ['title' => '🛍️ تصفح المتجر', 'rowId' => 'browse_store', 'description' => 'الذهاب إلى الأقسام'],
                    ['title' => '📜 سياسة المتجر', 'rowId' => 'store_policy', 'description' => 'الاسترجاع والاستبدال'],
                    ['title' => '👨‍💻 التحدث لموظف', 'rowId' => 'human_support', 'description' => 'التحويل لخدمة العملاء']
                ]
            ]];
            $trigger->send_list( $clean_phone, 'خدمة العملاء', 'خيارات المساعدة', "مرحباً بك في خدمة العملاء الآلية 🤖\nكيف يمكننا مساعدتك اليوم؟", 'اختر من القائمة', $menu_options, get_site_icon_url() );
            return new WP_REST_Response( [ 'success' => true, 'action' => 'help_menu_sent' ], 200 );
        }
        
        // Action 1.7: Handle List Options
        if ( strpos($message_clean, 'تتبع الطلب') !== false && class_exists('SendTrigger') ) {
            $order_id = $this->find_latest_order_by_phone( $clean_phone );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                $status = wc_get_order_status_name( $order->get_status() );
                $trigger = new SendTrigger();
                $trigger->send_direct($clean_phone, "طلبك الأخير رقم {$order_id} حالته الآن: *{$status}* 📦");
                return new WP_REST_Response( [ 'success' => true, 'action' => 'order_tracked' ], 200 );
            }
        } elseif ( strpos($message_clean, 'تصفح المتجر') !== false && class_exists('SendTrigger') ) {
            $trigger = new SendTrigger();
            $trigger->send_direct($clean_phone, "يمكنك تصفح جميع أقسامنا وعروضنا الحصرية عبر الرابط التالي:\n" . site_url('/shop/'));
            return new WP_REST_Response( [ 'success' => true, 'action' => 'shop_link_sent' ], 200 );
        } elseif ( strpos($message_clean, 'سياسة المتجر') !== false && class_exists('SendTrigger') ) {
            $trigger = new SendTrigger();
            $trigger->send_direct($clean_phone, "نوفر لك سياسة استرجاع واستبدال مرنة لضمان راحتك. لمعرفة المزيد:\n" . site_url('/refund-returns/'));
            return new WP_REST_Response( [ 'success' => true, 'action' => 'policy_link_sent' ], 200 );
        } elseif ( strpos($message_clean, 'التحدث لموظف') !== false && class_exists('SendTrigger') ) {
            $trigger = new SendTrigger();
            $trigger->send_direct($clean_phone, "تم تحويلك لموظف الدعم 👨‍💻.\nيرجى كتابة استفسارك وسيقوم بالرد عليك في أقرب وقت.");
            return new WP_REST_Response( [ 'success' => true, 'action' => 'support_redirect' ], 200 );
        }

        // Action 3: Gemini AI Bot
        if ( get_option('mpedia_wagatewayenable_ai_bot', 'no') === 'yes' ) {
            $gemini = new MpwaGeminiHandler();
            if ( $gemini->is_configured() ) {
                $reply = $gemini->process_message( $clean_phone, $message_clean, $audio_data );
                if ( $reply ) {
                    if(class_exists('SendTrigger')) {
                        $trigger = new SendTrigger();
                        $trigger->send_direct( $clean_phone, $reply );
                    }
                    return new WP_REST_Response( [ 'success' => true, 'action' => 'ai_replied' ], 200 );
                }
            }
        }

        return new WP_REST_Response( [ 'success' => true, 'action' => 'note_added_or_ignored' ], 200 );
    }

    private function find_latest_order_by_phone( $phone, $status = 'any' ) {
        if ( ! function_exists( 'wc_get_orders' ) ) return false;

        $clean = preg_replace( '/\D/', '', (string) $phone );
        if ( empty( $clean ) ) return false;

        $candidates = [ $clean, '+' . $clean, '00' . $clean ];

        // Extract last 9 and 8 digits (common Arab mobile length without country code)
        $short9 = substr( $clean, -9 );
        if ( strlen( $short9 ) === 9 ) {
            $candidates[] = '0' . $short9;
            $candidates[] = $short9;
        }
        $short8 = substr( $clean, -8 );
        if ( strlen( $short8 ) === 8 ) {
            $candidates[] = '0' . $short8;
            $candidates[] = $short8;
        }
        $candidates = array_unique( $candidates );

        // 1. Try standard WooCommerce billing_phone query (HPOS compatible)
        foreach ( $candidates as $candidate ) {
            $orders = wc_get_orders( [
                'limit'         => 1,
                'orderby'       => 'date',
                'order'         => 'DESC',
                'status'        => $status,
                'billing_phone' => $candidate,
            ] );
            if ( ! empty( $orders ) ) {
                return $orders[0]->get_id();
            }
        }

        // 2. Fallback meta query with OR and LIKE for spaced or legacy formats
        $meta_conditions = [];
        foreach ( $candidates as $cand ) {
            $meta_conditions[] = [
                'key'     => '_billing_phone',
                'value'   => $cand,
                'compare' => '='
            ];
        }
        if ( ! empty( $short8 ) ) {
            $meta_conditions[] = [
                'key'     => '_billing_phone',
                'value'   => $short8,
                'compare' => 'LIKE'
            ];
        }

        $orders = wc_get_orders( [
            'limit'      => 1,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'status'     => $status,
            'meta_query' => array_merge( [ 'relation' => 'OR' ], $meta_conditions )
        ] );

        if ( ! empty( $orders ) ) {
            return $orders[0]->get_id();
        }

        return false;
    }
}

new MpwaWebhookHandler();
