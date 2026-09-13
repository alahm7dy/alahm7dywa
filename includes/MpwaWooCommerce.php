<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaWooCommerce {

    private $prefix = 'mpedia_wagateway';

    public function __construct( $base_file = null ) {
        $trigger = new SendTrigger();
        add_action( 'woocommerce_new_order', [ $trigger, 'notify_send_admin_sms_for_woo_new_order' ], 10, 1 );
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_mpwa_test_connection', [ $this, 'ajax_test' ] );
        add_action( 'wp_ajax_mpwa_api_tool_send', [ $this, 'ajax_api_tool_send' ] );
        add_action( 'wp_ajax_mpwa_clear_logs',      [ $this, 'ajax_clear_logs' ] );
        add_action( 'wp_ajax_mpwa_delete_contact',   [ $this, 'ajax_delete_contact' ] );
        add_action( 'wp_ajax_mpwa_add_contact',      [ $this, 'ajax_add_contact' ] );
        add_action( 'wp_ajax_mpwa_send_to_contact',  [ $this, 'ajax_send_to_contact' ] );
        add_action( 'wp_ajax_mpwa_bulk_send',        [ $this, 'ajax_bulk_send' ] );
        add_action( 'wp_ajax_mpwa_send_test',       [ $this, 'ajax_send_test' ] );
        add_action( 'wp_ajax_mpwa_retry_log',       [ $this, 'ajax_retry_log' ] );
        add_action( 'wp_ajax_mpwa_export_contacts', [ $this, 'ajax_export_contacts' ] );
        add_action( 'wp_ajax_mpwa_sync_old_orders', [ $this, 'ajax_sync_old_orders' ] );
        add_action( 'wp_ajax_mpwa_test_ai',         [ $this, 'ajax_test_ai' ] );
        add_action( 'wp_ajax_mpwa_get_device_status', [ $this, 'ajax_get_device_status' ] );
        add_action( 'wp_ajax_mpwa_generate_qr',     [ $this, 'ajax_generate_qr' ] );
        add_action( 'wp_ajax_mpwa_logout_device',   [ $this, 'ajax_logout_device' ] );
        add_action( 'wp_ajax_mpwa_delete_device',   [ $this, 'ajax_delete_device' ] );
        
        // Frontend & Shortcode
        add_action( 'wp_enqueue_scripts',           [ $this, 'enqueue_frontend_assets' ] );
        add_shortcode( 'mpwa_newsletter',           [ $this, 'shortcode_newsletter' ] );
        add_action( 'wp_ajax_mpwa_subscribe_newsletter', [ $this, 'ajax_subscribe_newsletter' ] );
        add_action( 'wp_ajax_nopriv_mpwa_subscribe_newsletter', [ $this, 'ajax_subscribe_newsletter' ] );

        // Channel Auto-Post
        add_action( 'transition_post_status', [ $this, 'auto_post_to_channel' ], 10, 3 );

        add_filter( 'plugin_row_meta', [ $this, 'plugin_meta' ], 10, 4 );
    }

    public function register_menus() {
        add_menu_page( 'Alahm7dy واتساب', 'Alahm7dy واتساب', 'manage_options', 'mpwa-settings', [ $this, 'page_settings' ], 'dashicons-whatsapp', 58 );
        // Removed submenus to integrate them into a single page
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'mpwa' ) === false ) return;
        wp_enqueue_style( 'mpwa-admin', MPWA_PLUGIN_URL . 'assets/css/admin.css', [], MPWA_VERSION );
        wp_enqueue_script( 'mpwa-admin', MPWA_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], MPWA_VERSION, true );
        wp_localize_script( 'mpwa-admin', 'mpwaAdmin', [ 'nonce' => wp_create_nonce( 'mpwa_nonce' ) ] );
    }

    public function enqueue_frontend_assets() {
        if ( is_admin() ) return;
        // Enqueue intl-tel-input library for country flags
        wp_enqueue_style( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css', [], '17.0.8' );
        wp_enqueue_script( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js', [], '17.0.8', true );

        wp_enqueue_style( 'mpwa-frontend', MPWA_PLUGIN_URL . 'assets/css/frontend.css', [], MPWA_VERSION );
        wp_enqueue_script( 'mpwa-frontend', MPWA_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery', 'intl-tel-input' ], MPWA_VERSION, true );
        wp_localize_script( 'mpwa-frontend', 'mpwaFrontend', [ 
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mpwa_frontend_nonce' ),
            'utils_url' => 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js'
        ] );
    }

    public function save_settings() {
        if ( ! isset( $_POST['mpwa_save'] ) ) return;
        if ( ! check_admin_referer( 'mpwa_save_settings', 'mpwa_nonce_field' ) ) wp_die( 'فشل التحقق الأمني.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'غير مصرح.' );
        $txt = [ 'device','default_country_code','admin_sms_recipients','telegram_chat_id', 'whatsapp_channel_id', 'webhook_secret', 'newsletter_default_style', 'newsletter_title', 'newsletter_desc', 'newsletter_btn_text', 'newsletter_placeholder', 'newsletter_badge', 'github_repo', 'github_token' ];
        foreach ( $txt as $f ) update_option( $this->prefix . $f, sanitize_text_field( wp_unslash( $_POST[ $this->prefix . $f ] ?? '' ) ) );
        update_option( $this->prefix . 'url', esc_url_raw( wp_unslash( $_POST[ $this->prefix . 'url' ] ?? '' ) ) );
        foreach ( [ 'api_key', 'telegram_bot_token', 'gemini_api_key' ] as $f ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $this->prefix . $f ] ?? '' ) );
            if ( $value !== '' ) update_option( $this->prefix . $f, $value );
        }
        $tpl = [ 'default_sms_template','admin_sms_template','note_sms_template','telegram_template', 'gemini_system_prompt' ];
        foreach ( $tpl as $f ) update_option( $this->prefix . $f, sanitize_textarea_field( $_POST[ $this->prefix . $f ] ?? '' ) );
        $bools = [ 'enable_admin_sms','enable_notes_sms','enable_telegram_sms', 'enable_ai_bot', 'enable_feature_abandoned_cart', 'enable_feature_otp_login', 'enable_feature_webhook', 'enable_admin_2fa', 'enable_invoice_receipt', 'enable_feedback_poll' ];
        foreach ( $bools as $f ) update_option( $this->prefix . $f, isset( $_POST[ $this->prefix . $f ] ) ? 'yes' : 'no' );
        foreach ( wc_get_order_statuses() as $key => $val ) {
            $k = str_replace( 'wc-', '', $key );
            update_option( $this->prefix . 'send_sms_' . $k, isset( $_POST[ $this->prefix . 'send_sms_' . $k ] ) ? 'yes' : 'no' );
            update_option( $this->prefix . $k . '_sms_template', sanitize_textarea_field( $_POST[ $this->prefix . $k . '_sms_template' ] ?? '' ) );
        }
        wp_redirect( add_query_arg( [ 'page'=>'mpwa-settings','saved'=>'1' ], admin_url( 'admin.php' ) ) ); exit;
    }

    /* ── AJAX ──────────────────────────────────────────── */
    private function authorize_admin_ajax() {
        check_ajax_referer( 'mpwa_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'غير مصرح.', 403 );
        }
    }

    public function ajax_test() {
        $this->authorize_admin_ajax();
        $client = new MpwaXGrowthClient();
        $response = $client->device_info();
        if ( $client->is_success( $response ) ) wp_send_json_success( '✅ الاتصال والـ API Key ورقم الجهاز يعملون.' );
        wp_send_json_error( $client->error_message( $response ) );
    }
    public function ajax_api_tool_send() {
        $this->authorize_admin_ajax();
        $phone = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['phone'] ?? '' ) );
        $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
        $type = sanitize_key( $_POST['type'] ?? 'text' );
        $url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        $extra_raw = trim( (string) wp_unslash( $_POST['extra'] ?? '' ) );
        $extra = $extra_raw === '' ? [] : json_decode( $extra_raw, true );
        $allowed = [ 'text', 'media', 'sticker', 'product', 'channel', 'button', 'poll', 'list', 'location', 'vcard', 'check' ];
        if ( ! in_array( $type, $allowed, true ) ) wp_send_json_error( 'نوع الإرسال غير مدعوم.' );
        if ( $type !== 'channel' && strlen( $phone ) < 8 ) wp_send_json_error( 'أدخل رقمًا دوليًا صحيحًا.' );
        if ( in_array( $type, [ 'text', 'media', 'product', 'channel', 'button', 'poll', 'list' ], true ) && $message === '' ) wp_send_json_error( 'اكتب محتوى الرسالة.' );
        if ( in_array( $type, [ 'media', 'sticker', 'product', 'channel', 'button', 'list' ], true ) && ! $url ) wp_send_json_error( 'أدخل الرابط المطلوب لهذا النوع.' );
        if ( $extra_raw !== '' && ! is_array( $extra ) ) wp_send_json_error( 'صيغة البيانات الإضافية JSON غير صحيحة.' );
        if ( $type === 'button' && empty( $extra['buttons'] ) ) wp_send_json_error( 'أضف زرًا واحدًا على الأقل.' );
        if ( $type === 'poll' && empty( $extra['options'] ) ) wp_send_json_error( 'أضف اختيارات الاستطلاع.' );
        if ( $type === 'list' && empty( $extra['sections'] ) ) wp_send_json_error( 'أضف أقسام القائمة.' );
        if ( $type === 'location' && ( ! is_numeric( $extra['latitude'] ?? null ) || ! is_numeric( $extra['longitude'] ?? null ) ) ) wp_send_json_error( 'أدخل خط العرض والطول بصورة صحيحة.' );
        if ( $type === 'vcard' && ( empty( $extra['name'] ) || strlen( preg_replace( '/\D+/', '', (string) ( $extra['phone'] ?? '' ) ) ) < 8 ) ) wp_send_json_error( 'أدخل اسم ورقم جهة الاتصال.' );
        $client = new MpwaXGrowthClient();
        if ( $type === 'media' ) $response = $client->send_media( $phone, $url, $message, sanitize_key( $extra['media_type'] ?? 'image' ) );
        elseif ( $type === 'sticker' ) $response = $client->send_sticker( $phone, $url );
        elseif ( $type === 'product' ) $response = $client->send_product( $phone, $url, $message );
        elseif ( $type === 'channel' ) $response = $client->send_channel( $url, $message, sanitize_text_field( $extra['footer'] ?? '' ) );
        elseif ( $type === 'button' ) $response = $client->send_button( $phone, $message, is_array( $extra['buttons'] ?? null ) ? $extra['buttons'] : [], $url, sanitize_text_field( $extra['footer'] ?? '' ) );
        elseif ( $type === 'poll' ) $response = $client->send_poll( $phone, $message, is_array( $extra['options'] ?? null ) ? $extra['options'] : [], ! empty( $extra['countable'] ) );
        elseif ( $type === 'list' ) $response = $client->send_list( $phone, $message, sanitize_text_field( $extra['name'] ?? 'القائمة' ), sanitize_text_field( $extra['title'] ?? 'اختر' ), sanitize_text_field( $extra['buttontext'] ?? 'عرض القائمة' ), is_array( $extra['sections'] ?? null ) ? $extra['sections'] : [], $url, sanitize_text_field( $extra['footer'] ?? '' ) );
        elseif ( $type === 'location' ) $response = $client->send_location( $phone, $extra['latitude'] ?? '', $extra['longitude'] ?? '' );
        elseif ( $type === 'vcard' ) $response = $client->send_vcard( $phone, $extra['name'] ?? '', $extra['phone'] ?? '' );
        elseif ( $type === 'check' ) $response = $client->check_number( $phone );
        else $response = $client->send_message( $phone, $message );
        if ( $client->is_success( $response ) ) wp_send_json_success( $client->decode_response( $response ) );
        wp_send_json_error( $client->error_message( $response ) );
    }
    public function ajax_clear_logs() {
        $this->authorize_admin_ajax();
        MpwaLogger::clear_logs(); wp_send_json_success( 'تم مسح السجلات.' );
    }
    public function ajax_delete_contact() {
        $this->authorize_admin_ajax();
        MpwaPhoneBook::delete( absint( $_POST['id'] ?? 0 ) ); wp_send_json_success( 'تم الحذف.' );
    }
    public function ajax_add_contact() {
        $this->authorize_admin_ajax();
        $r = MpwaPhoneBook::insert_manual(
            wp_unslash( $_POST['name'] ?? '' ),
            wp_unslash( $_POST['phone'] ?? '' ),
            wp_unslash( $_POST['email'] ?? '' ),
            wp_unslash( $_POST['tags'] ?? '' )
        );
        $r ? wp_send_json_success( 'تمت الإضافة.' ) : wp_send_json_error( 'فشلت الإضافة.' );
    }
    public function ajax_send_to_contact() {
        $this->authorize_admin_ajax();
        $t = new SendTrigger();
        $sent = $t->send_direct( wp_unslash( $_POST['phone'] ?? '' ), sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) );
        $sent ? wp_send_json_success( '📤 تم الإرسال.' ) : wp_send_json_error( 'فشل الإرسال.' );
    }
    public function ajax_bulk_send() {
        $this->authorize_admin_ajax();
        $msg = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
        if ( empty( $msg ) ) { wp_send_json_error( 'الرسالة فارغة.' ); }
        
        $target = sanitize_key( wp_unslash( $_POST['target'] ?? 'all' ) );
        if ( ! in_array( $target, [ 'all', 'newsletter', 'buyers' ], true ) ) $target = 'all';
        $media = esc_url_raw( wp_unslash( $_POST['media'] ?? '' ) );
        $btn_text = sanitize_text_field( wp_unslash( $_POST['btn_text'] ?? '' ) );
        
        $all = MpwaPhoneBook::get_all( 200 );
        $filtered = [];
        foreach ($all as $c) {
            if ( $target === 'newsletter' && $c->source !== 'newsletter' && strpos( ',' . $c->tags . ',', ',newsletter,' ) === false ) continue;
            if ( $target === 'buyers' && $c->order_count <= 0 ) continue;
            $filtered[] = $c;
        }

        $t = new SendTrigger();
        $sent = 0;
        foreach ( $filtered as $c ) {
            if ( !empty($media) && !empty($btn_text) ) {
                $buttons = [
                    [ 'type' => 'url', 'displayText' => $btn_text, 'url' => site_url() ]
                ];
                $ok = $t->send_button( $c->phone, $msg, $buttons, $media, 'عرض خاص' );
            } elseif ( !empty($media) ) {
                $ok = $t->send_media( $c->phone, 'image', $media, $msg );
            } else {
                $ok = $t->send_direct( $c->phone, $msg );
            }
            if ( $ok ) $sent++;
        }
        wp_send_json_success( "تم إرسال {$sent} من أصل " . count($filtered) . ' رسالة. الحد الأقصى للدفعة 200 مستلم.' );
    }
    public function ajax_send_test() {
        $this->authorize_admin_ajax();
        $t = new SendTrigger();
        $t->send_test_message() ? wp_send_json_success( '📤 تم إرسال رسالة الاختبار للأدمن.' ) : wp_send_json_error( 'لم يتم العثور على أرقام أدمن.' );
    }
    public function ajax_retry_log() {
        $this->authorize_admin_ajax();
        $t = new SendTrigger();
        $t->retry_by_log_id( $_POST['id'] ?? 0 ) ? wp_send_json_success( '🔄 تم طلب إعادة الإرسال.' ) : wp_send_json_error( 'السجل غير موجود.' );
    }
    public function ajax_export_contacts() {
        $this->authorize_admin_ajax();
        $contacts = MpwaPhoneBook::get_all( 9999 );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=mpwa_contacts_'.date('Y-m-d').'.csv' );
        $out = fopen( 'php://output', 'w' );
        fputs( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM
        fputcsv( $out, [ 'الاسم', 'الهاتف', 'الإيميل', 'المصدر', 'الطلبات', 'آخر طلب', 'التاغات' ] );
        foreach ( $contacts as $c ) {
            $row = [ $c->name, $c->phone, $c->email, $c->source, $c->order_count, $c->last_order, $c->tags ];
            $row = array_map( function( $value ) {
                $value = (string) $value;
                return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
            }, $row );
            fputcsv( $out, $row );
        }
        fclose( $out );
        exit;
    }

    public function ajax_sync_old_orders() {
        $this->authorize_admin_ajax();
        
        if ( ! function_exists('wc_get_orders') ) wp_send_json_error( 'ووكومرس غير مفعل.' );
        $page = max( 1, absint( $_POST['sync_page'] ?? 1 ) );
        $batch_size = 100;
        $args = [
            'limit'  => $batch_size,
            'page'   => $page,
            'return' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        $order_ids = wc_get_orders( $args );
        if ( empty( $order_ids ) ) wp_send_json_success( [ 'count' => 0, 'done' => true, 'next_page' => $page ] );
        
        $count = 0;
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                MpwaPhoneBook::upsert_from_order( $order );
                $count++;
            }
        }
        wp_send_json_success( [
            'count' => $count,
            'done' => count( $order_ids ) < $batch_size,
            'next_page' => $page + 1,
        ] );
    }

    public function ajax_test_ai() {
        $this->authorize_admin_ajax();
        
        $msg = sanitize_textarea_field( $_POST['message'] ?? '' );
        if ( empty( $msg ) ) wp_send_json_error( 'الرسالة فارغة.' );
        
        if ( ! class_exists( 'MpwaGeminiHandler' ) ) wp_send_json_error( 'معالج الذكاء الاصطناعي غير موجود.' );
        
        $ai = new MpwaGeminiHandler();
        if ( ! $ai->is_configured() ) {
            wp_send_json_error( 'يرجى إدخال مفتاح Google Gemini API أولاً وحفظ الإعدادات.' );
        }

        // Use a dummy phone number for testing isolation
        $reply = $ai->process_message( 'test_bot_000', $msg );
        
        if ( $reply ) {
            wp_send_json_success( $reply );
        } else {
            wp_send_json_error( 'لم يتمكن الذكاء الاصطناعي من الرد (تأكد من صحة الـ API Key ووجود رصيد).' );
        }
    }
    
    public function ajax_subscribe_newsletter() {
        check_ajax_referer( 'mpwa_frontend_nonce', 'nonce' );
        if ( ! MpwaRateLimiter::allow( 'newsletter', 5, HOUR_IN_SECONDS ) ) {
            wp_send_json_error( 'تم تجاوز عدد المحاولات المسموح. يرجى المحاولة لاحقاً.', 429 );
        }
        $phone = sanitize_text_field( $_POST['phone'] ?? '' );
        if ( empty( $phone ) ) {
            wp_send_json_error( 'يرجى إدخال رقم الهاتف.' );
        }
        
        // Clean and format phone number with store default country code
        if ( class_exists( 'SendTrigger' ) ) {
            $default_cc = get_option( $this->prefix . 'default_country_code', '965' );
            $phone = SendTrigger::format_phone_number( $phone, $default_cc );
        } else {
            $phone = preg_replace( '/\D/', '', $phone );
        }

        if ( empty( $phone ) ) {
            wp_send_json_error( 'رقم الهاتف غير صحيح.' );
        }

        $r = MpwaPhoneBook::subscribe_newsletter( $phone );
        if ( $r ) {
            wp_send_json_success( 'تم الاشتراك بنجاح! شكراً لك للانضمام إلينا.' );
        } else {
            wp_send_json_error( 'فشل الاشتراك أو الرقم مسجل مسبقاً في القائمة.' );
        }
    }

    /* ── Shortcodes ────────────────────────────────────── */
    public function shortcode_newsletter( $atts ) {
        $default_style       = get_option( $this->prefix . 'newsletter_default_style', 'simple' );
        $default_title       = get_option( $this->prefix . 'newsletter_title', '' );
        $default_desc        = get_option( $this->prefix . 'newsletter_desc', '' );
        $default_btn_text    = get_option( $this->prefix . 'newsletter_btn_text', 'اشترك الآن' );
        $default_placeholder = get_option( $this->prefix . 'newsletter_placeholder', 'رقم الواتساب (مثال: 05xxxxxxxx)' );
        $default_badge       = get_option( $this->prefix . 'newsletter_badge', '' );

        $atts = shortcode_atts( [
            'style'       => $default_style,
            'title'       => $default_title,
            'desc'        => $default_desc,
            'button'      => $default_btn_text,
            'placeholder' => $default_placeholder,
            'badge'       => $default_badge,
        ], $atts, 'mpwa_newsletter' );

        $style = sanitize_key( $atts['style'] );
        if ( ! in_array( $style, [ 'simple', 'card', 'inline', 'bar', 'dark', 'floating', 'sticky', 'minimal', 'gradient' ], true ) ) {
            $style = 'simple';
        }
        if ( $style === 'bar' ) $style = 'inline';
        if ( $style === 'sticky' ) $style = 'floating';

        static $form_counter = 0;
        $form_counter++;
        $unique_id = 'mpwa-nl-' . $form_counter;

        ob_start();
        ?>
        <?php if ( $style === 'simple' ) : ?>
            <!-- ── Style: Simple Box & Button (مربع بسيط بجواره زر بدون أي أعلام) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-simple" id="<?php echo esc_attr( $unique_id ); ?>">
                <?php if ( ! empty( $atts['title'] ) ) : ?>
                    <div class="mpwa-nl-simple-title"><?php echo esc_html( $atts['title'] ); ?></div>
                <?php endif; ?>
                <form class="mpwa-newsletter-form mpwa-nl-simple-form">
                    <div class="mpwa-nl-simple-row">
                        <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                        <button type="submit" class="mpwa-newsletter-btn">
                            <span><?php echo esc_html( $atts['button'] ); ?></span>
                        </button>
                    </div>
                    <div class="mpwa-newsletter-msg" style="display:none;"></div>
                </form>
            </div>

        <?php elseif ( $style === 'card' ) : ?>
            <!-- ── Style 1: Modern Card (الافتراضي) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-style-card" id="<?php echo esc_attr( $unique_id ); ?>">
                <div class="mpwa-nl-card-box">
                    <?php if ( ! empty( $atts['badge'] ) ) : ?>
                        <div class="mpwa-nl-badge"><?php echo esc_html( $atts['badge'] ); ?></div>
                    <?php endif; ?>
                    <div class="mpwa-nl-icon-badge">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.15-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.77 2.7 4.29 3.78.6.26 1.07.41 1.44.53.6.19 1.15.16 1.59.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.11-.23-.17-.48-.3z"/></svg>
                    </div>
                    <h3 class="mpwa-nl-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                    <?php if ( ! empty( $atts['desc'] ) ) : ?>
                        <p class="mpwa-nl-desc"><?php echo esc_html( $atts['desc'] ); ?></p>
                    <?php endif; ?>
                    <form class="mpwa-newsletter-form">
                        <div class="mpwa-nl-field-wrap">
                            <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                        </div>
                        <button type="submit" class="mpwa-newsletter-btn">
                            <span><?php echo esc_html( $atts['button'] ); ?></span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        </button>
                        <div class="mpwa-newsletter-msg" style="display:none;"></div>
                    </form>
                </div>
            </div>

        <?php elseif ( $style === 'inline' ) : ?>
            <!-- ── Style 2: Minimal Inline Banner (الشريط المدمج) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-style-inline" id="<?php echo esc_attr( $unique_id ); ?>">
                <div class="mpwa-nl-inline-content">
                    <div class="mpwa-nl-inline-info">
                        <div class="mpwa-nl-mini-icon">
                            <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.15-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.77 2.7 4.29 3.78.6.26 1.07.41 1.44.53.6.19 1.15.16 1.59.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.11-.23-.17-.48-.3z"/></svg>
                        </div>
                        <div class="mpwa-nl-inline-headings">
                            <h4 class="mpwa-nl-title"><?php echo esc_html( $atts['title'] ); ?></h4>
                            <?php if ( ! empty( $atts['desc'] ) ) : ?>
                                <p class="mpwa-nl-desc"><?php echo esc_html( $atts['desc'] ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form class="mpwa-newsletter-form mpwa-nl-inline-form">
                        <div class="mpwa-nl-input-group">
                            <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                            <button type="submit" class="mpwa-newsletter-btn">
                                <span><?php echo esc_html( $atts['button'] ); ?></span>
                            </button>
                        </div>
                        <div class="mpwa-newsletter-msg" style="display:none;"></div>
                    </form>
                </div>
            </div>

        <?php elseif ( $style === 'dark' ) : ?>
            <!-- ── Style 3: VIP Dark Glassmorphism (الوضع الداكن الفاخر) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-style-dark" id="<?php echo esc_attr( $unique_id ); ?>">
                <div class="mpwa-nl-card-box">
                    <?php if ( ! empty( $atts['badge'] ) ) : ?>
                        <div class="mpwa-nl-badge mpwa-nl-badge-dark"><?php echo esc_html( $atts['badge'] ); ?></div>
                    <?php endif; ?>
                    <div class="mpwa-nl-icon-badge mpwa-nl-icon-dark">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.15-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.77 2.7 4.29 3.78.6.26 1.07.41 1.44.53.6.19 1.15.16 1.59.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.11-.23-.17-.48-.3z"/></svg>
                    </div>
                    <h3 class="mpwa-nl-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                    <?php if ( ! empty( $atts['desc'] ) ) : ?>
                        <p class="mpwa-nl-desc"><?php echo esc_html( $atts['desc'] ); ?></p>
                    <?php endif; ?>
                    <form class="mpwa-newsletter-form">
                        <div class="mpwa-nl-field-wrap">
                            <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                        </div>
                        <button type="submit" class="mpwa-newsletter-btn mpwa-nl-btn-glow">
                            <span><?php echo esc_html( $atts['button'] ); ?></span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        </button>
                        <div class="mpwa-newsletter-msg" style="display:none;"></div>
                    </form>
                </div>
            </div>

        <?php elseif ( $style === 'floating' ) : ?>
            <!-- ── Style 4: Sticky Bottom Bar (الشريط العائم) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-style-floating" id="<?php echo esc_attr( $unique_id ); ?>">
                <button type="button" class="mpwa-nl-close-btn" aria-label="إغلاق">&times;</button>
                <div class="mpwa-nl-floating-container">
                    <div class="mpwa-nl-floating-info">
                        <span class="mpwa-nl-floating-icon">💬</span>
                        <div class="mpwa-nl-floating-text">
                            <strong><?php echo esc_html( $atts['title'] ); ?></strong>
                            <?php if ( ! empty( $atts['desc'] ) ) : ?>
                                <small><?php echo esc_html( $atts['desc'] ); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form class="mpwa-newsletter-form mpwa-nl-floating-form">
                        <div class="mpwa-nl-input-group">
                            <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                            <button type="submit" class="mpwa-newsletter-btn">
                                <span><?php echo esc_html( $atts['button'] ); ?></span>
                            </button>
                        </div>
                        <div class="mpwa-newsletter-msg" style="display:none;"></div>
                    </form>
                </div>
            </div>

        <?php elseif ( $style === 'minimal' ) : ?>
            <!-- ── Style 5: Clean Minimal (المينيمال البسيط) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-style-minimal" id="<?php echo esc_attr( $unique_id ); ?>">
                <h4 class="mpwa-nl-title"><?php echo esc_html( $atts['title'] ); ?></h4>
                <?php if ( ! empty( $atts['desc'] ) ) : ?>
                    <p class="mpwa-nl-desc"><?php echo esc_html( $atts['desc'] ); ?></p>
                <?php endif; ?>
                <form class="mpwa-newsletter-form mpwa-nl-minimal-form">
                    <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                    <button type="submit" class="mpwa-newsletter-btn">
                        <span><?php echo esc_html( $atts['button'] ); ?></span>
                    </button>
                    <div class="mpwa-newsletter-msg" style="display:none;"></div>
                </form>
            </div>

        <?php elseif ( $style === 'gradient' ) : ?>
            <!-- ── Style 6: Royal Emerald Gradient (التدرج الزمردي الملكي) ── -->
            <div class="mpwa-newsletter-wrapper mpwa-nl-style-gradient" id="<?php echo esc_attr( $unique_id ); ?>">
                <div class="mpwa-nl-gradient-inner">
                    <?php if ( ! empty( $atts['badge'] ) ) : ?>
                        <div class="mpwa-nl-badge mpwa-nl-badge-light"><?php echo esc_html( $atts['badge'] ); ?></div>
                    <?php endif; ?>
                    <h3 class="mpwa-nl-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                    <?php if ( ! empty( $atts['desc'] ) ) : ?>
                        <p class="mpwa-nl-desc"><?php echo esc_html( $atts['desc'] ); ?></p>
                    <?php endif; ?>
                    <form class="mpwa-newsletter-form">
                        <div class="mpwa-nl-input-group">
                            <input type="tel" name="mpwa_phone" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required autocomplete="tel" dir="ltr">
                            <button type="submit" class="mpwa-newsletter-btn mpwa-btn-white">
                                <span><?php echo esc_html( $atts['button'] ); ?></span>
                            </button>
                        </div>
                        <div class="mpwa-newsletter-msg" style="display:none;"></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    public function plugin_meta( $meta, $file, $data, $status ) {
        if ( isset( $data['Name'] ) && strtolower( $data['Name'] ) === 'alahm7dy woocommerce whatsapp' ) {
            $meta[] = '<a href="'.admin_url('admin.php?page=mpwa-settings').'">الإعدادات</a>';
        }
        return $meta;
    }

    /* ── Device Management AJAX ────────────────────────── */
    public function ajax_get_device_status() {
        $this->authorize_admin_ajax();
        $client = new MpwaXGrowthClient();
        $response = $client->device_info();
        if ( $client->is_success( $response ) ) {
            $device_data = $client->decode_response( $response );
            $user_data = null;
            $username = get_option( $this->prefix . 'username', '' );
            if ( ! empty( $username ) ) {
                $user_res = $client->user_info( $username );
                if ( $client->is_success( $user_res ) ) {
                    $user_data = $client->decode_response( $user_res );
                }
            }
            wp_send_json_success( [
                'device' => $device_data,
                'user'   => $user_data
            ] );
        }
        wp_send_json_error( $client->error_message( $response ) );
    }

    public function ajax_generate_qr() {
        $this->authorize_admin_ajax();
        $client = new MpwaXGrowthClient();
        $response = $client->generate_qr( true );
        if ( $client->is_success( $response ) ) wp_send_json_success( $client->decode_response( $response ) );
        wp_send_json_error( $client->error_message( $response ) );
    }

    public function ajax_logout_device() {
        $this->authorize_admin_ajax();
        $client = new MpwaXGrowthClient();
        $response = $client->logout_device();
        if ( $client->is_success( $response ) ) wp_send_json_success( $client->decode_response( $response ) );
        wp_send_json_error( $client->error_message( $response ) );
    }

    public function ajax_delete_device() {
        $this->authorize_admin_ajax();
        $client = new MpwaXGrowthClient();
        $response = $client->delete_device();
        if ( $client->is_success( $response ) ) wp_send_json_success( $client->decode_response( $response ) );
        wp_send_json_error( $client->error_message( $response ) );
    }

    /* ── Channel Auto-Post ─────────────────────────────── */
    public function auto_post_to_channel( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'product' ) return;
        if ( $new_status === 'publish' && $old_status !== 'publish' ) {
            $channel_id = get_option( $this->prefix . 'whatsapp_channel_id', '' );
            if ( empty($channel_id) || !class_exists('SendTrigger') ) return;

            $product = wc_get_product( $post->ID );
            if ( ! $product ) return;

            $title = $product->get_name();
            $price = wc_price( wc_get_price_to_display( $product ) );
            // Strip html tags from price since wc_price returns html
            $price_clean = strip_tags( $price );
            $link = get_permalink( $post->ID );
            
            $message = "🆕 أضفنا منتجاً جديداً للمتجر!\n\n";
            $message .= "🛒 *{$title}*\n";
            $message .= "💰 السعر: {$price_clean}\n\n";
            $message .= "تسوقه الآن:\n{$link}";

            $trigger = new SendTrigger();
            $ok = $trigger->send_channel( $channel_id, $message );
            
            if ( class_exists( 'MpwaLogger' ) ) {
                MpwaLogger::write( [
                    'recipient'    => $channel_id,
                    'message'      => $message . "\n[قناة الواتساب]",
                    'order_id'     => 0,
                    'event_type'   => 'channel_post',
                    'status'       => $ok ? 'success' : 'failed',
                    'http_code'    => $ok ? 200 : 0,
                    'api_response' => $ok ? 'Auto Posted to Channel' : 'Failed to post to channel',
                    'error_msg'    => $ok ? '' : 'فشل النشر في قناة الواتساب عبر البوابة.'
                ] );
            }
        }
    }

    /* ── Pages ─────────────────────────────────────────── */
    public function page_settings() { include MPWA_PLUGIN_DIR . 'views/settings.php'; }
}
