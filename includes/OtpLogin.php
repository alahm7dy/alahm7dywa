<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaOtpLogin {

    public function __construct() {
        // Register Shortcode
        add_shortcode( 'mpwa_otp_login', [ $this, 'render_shortcode' ] );

        // Add to WooCommerce login form automatically
        add_action( 'woocommerce_after_customer_login_form', [ $this, 'render_woo_form' ] );

        // AJAX Handlers
        add_action( 'wp_ajax_mpwa_request_otp', [ $this, 'ajax_request_otp' ] );
        add_action( 'wp_ajax_nopriv_mpwa_request_otp', [ $this, 'ajax_request_otp' ] );

        add_action( 'wp_ajax_mpwa_verify_otp', [ $this, 'ajax_verify_otp' ] );
        add_action( 'wp_ajax_nopriv_mpwa_verify_otp', [ $this, 'ajax_verify_otp' ] );
    }

    public function render_shortcode() {
        if ( is_user_logged_in() ) {
            return '<div class="woocommerce-message" style="direction:rtl; text-align:right;">أنت مسجل الدخول بالفعل. <a href="'.esc_url( wc_get_account_endpoint_url('dashboard') ).'">الانتقال إلى حسابي</a></div>';
        }
        ob_start();
        $this->render_html();
        return ob_get_clean();
    }

    public function render_woo_form() {
        echo '<div class="mpwa-woo-login-divider">';
        echo '<span>أو الدخول السريع برمز التحقق</span>';
        echo '</div>';
        $this->render_html();
    }

    private function render_html() {
        ?>
        <div class="mpwa-otp-wrapper" id="mpwa-otp-wrapper">
            <div class="mpwa-otp-header">
                <div class="mpwa-otp-icon">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12.01 2.01A10 10 0 002.01 12c0 1.76.45 3.44 1.3 4.95L2.01 22l5.22-1.37a9.97 9.97 0 004.78 1.21h.01a10 10 0 0010-10 10 10 0 00-10-9.83zM12.01 20.14h-.01a8.31 8.31 0 01-4.23-1.15l-.3-.18-3.15.83.84-3.07-.2-.32A8.34 8.34 0 013.68 12a8.34 8.34 0 018.33-8.33 8.34 8.34 0 018.33 8.33 8.34 8.34 0 01-8.33 8.14zM16.58 13.9c-.25-.12-1.48-.73-1.7-.81-.23-.08-.4-.12-.57.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-1.1-.49-2.07-1.1-2.92-2.14-.23-.28-.02-.27.22-.52.05-.05.11-.12.16-.18.06-.06.08-.12.12-.18.04-.08.02-.15-.01-.21-.03-.06-.25-.6-.35-.82-.09-.21-.18-.18-.25-.18h-.21c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.02 2.6.12.16 1.78 2.71 4.3 3.8.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.48-.6 1.69-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.16-.48-.28z"/></svg>
                </div>
                <div class="mpwa-otp-title-wrap">
                    <h4>تسجيل الدخول عبر WhatsApp</h4>
                    <p>أدخل رقم هاتفك ليصلك رمز تحقق فوري دون الحاجة لكلمة مرور</p>
                </div>
            </div>

            <!-- Step 1: Phone Input -->
            <form id="mpwa-otp-phone-form" class="mpwa-otp-form">
                <p class="form-row form-row-wide">
                    <label for="mpwa_otp_phone">رقم الهاتف <span class="required">*</span></label>
                    <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="mpwa_otp_phone" id="mpwa_otp_phone" placeholder="965xxxxxxx" required />
                </p>
                <p class="form-row">
                    <button type="submit" class="woocommerce-button button mpwa-btn-whatsapp" name="mpwa_request_otp" value="أرسل الكود">
                        <span>إرسال رمز التحقق عبر واتساب</span>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </p>
                <div class="mpwa-otp-msg" style="display:none;"></div>
            </form>

            <!-- Step 2: OTP Input (Hidden initially) -->
            <form id="mpwa-otp-verify-form" class="mpwa-otp-form" style="display:none;">
                <p class="form-row form-row-wide">
                    <label for="mpwa_otp_code">رمز التحقق المكوّن من 6 أرقام <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text mpwa-code-input" name="mpwa_otp_code" id="mpwa_otp_code" placeholder="------" required maxlength="6" autocomplete="one-time-code" />
                </p>
                <p class="form-row">
                    <button type="submit" class="woocommerce-button button mpwa-btn-verify" name="mpwa_verify_otp" value="دخول">
                        <span>تأكيد وتسجيل الدخول</span>
                    </button>
                </p>
                <div class="mpwa-otp-resend-row">
                    <button type="button" id="mpwa-back-to-phone" class="mpwa-link-btn">تغيير رقم الهاتف</button>
                </div>
                <div class="mpwa-otp-msg" style="display:none;"></div>
            </form>
        </div>
        <?php
    }

    public function ajax_request_otp() {
        check_ajax_referer( 'mpwa_frontend_nonce', 'nonce' );
        $phone = sanitize_text_field( $_POST['phone'] ?? '' );
        
        if ( empty( $phone ) ) {
            wp_send_json_error( 'يرجى إدخال رقم هاتف صحيح.' );
        }

        if ( class_exists( 'SendTrigger' ) ) {
            $phone = SendTrigger::clean_number( $phone );
            
            // التحقق من أن الرقم مسجل في واتساب
            if ( ! SendTrigger::check_number( $phone ) ) {
                wp_send_json_error( 'هذا الرقم غير مسجل في واتساب. يرجى التأكد من الرقم والمحاولة مرة أخرى.' );
            }
        }

        if ( strlen( $phone ) < 8 || strlen( $phone ) > 16 ) {
            wp_send_json_error( 'يرجى إدخال رقم هاتف دولي صحيح.' );
        }

        if ( ! MpwaRateLimiter::allow( 'otp_request_ip', 5, 15 * MINUTE_IN_SECONDS ) ||
             ! MpwaRateLimiter::allow( 'otp_request_phone', 3, 15 * MINUTE_IN_SECONDS, $phone ) ) {
            wp_send_json_error( 'تم تجاوز عدد محاولات طلب الرمز المسموح بها. يرجى الانتظار قليلاً.', 429 );
        }

        // Generate 6-digit OTP
        $otp = (string) wp_rand( 100000, 999999 );
        
        // Save to transient for 5 minutes
        set_transient( 'mpwa_otp_' . md5( $phone ), [
            'hash'     => wp_hash_password( $otp ),
            'attempts' => 0,
        ], 5 * MINUTE_IN_SECONDS );

        // Send OTP via SendTrigger
        if ( class_exists( 'SendTrigger' ) ) {
            $trigger = new SendTrigger();
            $shop_name = get_bloginfo( 'name' );
            $message = "مرحباً بك في *{$shop_name}* 👋\n\nرمز التحقق الخاص بك هو:\n*{$otp}*\n\nالرمز صالح لمدة 5 دقائق، لا تشاركه مع أي شخص.";
            $trigger->send_direct( $phone, $message );
        }

        wp_send_json_success( [
            'message' => 'تم إرسال رمز التحقق إلى حسابك على الواتساب بنجاح.',
            'phone'   => $phone
        ] );
    }

    public function ajax_verify_otp() {
        check_ajax_referer( 'mpwa_frontend_nonce', 'nonce' );
        $phone = SendTrigger::clean_number( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
        $code  = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) );

        if ( empty( $phone ) || empty( $code ) ) {
            wp_send_json_error( 'البيانات المدخلة غير مكتملة.' );
        }

        if ( ! MpwaRateLimiter::allow( 'otp_verify_ip', 20, 15 * MINUTE_IN_SECONDS ) ||
             ! MpwaRateLimiter::allow( 'otp_verify_phone', 5, 15 * MINUTE_IN_SECONDS, $phone ) ) {
            wp_send_json_error( 'تم تجاوز عدد محاولات التحقق الخاطئة. اطلب رمزاً جديداً.', 429 );
        }

        $otp_key   = 'mpwa_otp_' . md5( $phone );
        $saved_otp = get_transient( $otp_key );

        if ( ! is_array( $saved_otp ) || empty( $saved_otp['hash'] ) || ! wp_check_password( $code, $saved_otp['hash'] ) ) {
            if ( is_array( $saved_otp ) ) {
                $saved_otp['attempts'] = absint( $saved_otp['attempts'] ?? 0 ) + 1;
                if ( $saved_otp['attempts'] >= 5 ) {
                    delete_transient( $otp_key );
                } else {
                    set_transient( $otp_key, $saved_otp, 5 * MINUTE_IN_SECONDS );
                }
            }
            wp_send_json_error( 'رمز التحقق غير صحيح أو انتهت صلاحيته.' );
        }

        // OTP is correct
        $user_id = $this->get_user_by_phone( $phone );

        if ( ! $user_id ) {
            // Create new user with valid fallback email
            $password = wp_generate_password( 16, true, true );
            $username = 'wa_' . substr( $phone, -8 ) . '_' . strtolower( wp_generate_password( 4, false, false ) );
            $email    = 'wa_' . $phone . '@whatsapp.user';
            
            // Check if email somehow exists
            if ( email_exists( $email ) ) {
                $email = 'wa_' . $phone . '_' . wp_rand( 100, 999 ) . '@whatsapp.user';
            }

            $user_id = wp_create_user( $username, $password, $email );
            
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( 'حدث خطأ أثناء إنشاء الحساب: ' . $user_id->get_error_message() );
            }
            
            // Save phone meta
            update_user_meta( $user_id, 'billing_phone', $phone );
            update_user_meta( $user_id, 'first_name', 'عميل واتساب' );
        }

        // Cleanup transient
        delete_transient( $otp_key );
        MpwaRateLimiter::clear( 'otp_verify_phone', $phone );

        // Login user session
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true, is_ssl() );
        
        $redirect_url = wc_get_account_endpoint_url( 'dashboard' );
        if ( isset( $_REQUEST['redirect'] ) && ! empty( $_REQUEST['redirect'] ) ) {
            $redirect_url = esc_url_raw( wp_unslash( $_REQUEST['redirect'] ) );
        }

        wp_send_json_success( [
            'redirect' => $redirect_url,
            'message'  => 'تم تسجيل الدخول بنجاح! جارٍ التحويل...'
        ] );
    }

    private function get_user_by_phone( $phone ) {
        // Direct billing_phone exact search
        $users = get_users([
            'meta_key'     => 'billing_phone',
            'meta_value'   => $phone,
            'number'       => 1,
            'count_total'  => false
        ]);

        if ( ! empty( $users ) ) {
            return $users[0]->ID;
        }

        // Try searching with variants (without country code or with leading zero)
        if ( class_exists( 'SendTrigger' ) ) {
            $clean = SendTrigger::clean_number( $phone );
            // Try last 8-9 digits
            $short = substr( $clean, -8 );
            if ( strlen( $short ) >= 8 ) {
                $users = get_users([
                    'meta_key'     => 'billing_phone',
                    'meta_compare' => 'LIKE',
                    'meta_value'   => $short,
                    'number'       => 1,
                    'count_total'  => false
                ]);
                if ( ! empty( $users ) ) {
                    return $users[0]->ID;
                }
            }
        }

        return false;
    }
}

new MpwaOtpLogin();
