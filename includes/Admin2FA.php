<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaAdmin2FA {
    private $prefix = 'mpedia_wagateway';

    public function __construct() {
        if ( defined( 'MPWA_DISABLE_2FA' ) && MPWA_DISABLE_2FA ) return;
        if ( get_option( $this->prefix . 'enable_admin_2fa' ) !== 'yes' ) return;
        
        add_action( 'wp_login', [ $this, 'on_admin_login' ], 10, 2 );
        add_action( 'wp_logout', [ $this, 'on_admin_logout' ] );
        add_action( 'admin_init', [ $this, 'check_2fa_status' ] );
        add_action( 'login_form_mpwa_2fa', [ $this, 'render_2fa_page' ] );
        add_action( 'login_form_mpwa_2fa_verify', [ $this, 'verify_2fa_code' ] );
        add_action( 'login_form_mpwa_2fa_resend', [ $this, 'resend_2fa_code' ] );
    }

    public function on_admin_login( $user_login, $user ) {
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            $phone = get_user_meta( $user->ID, 'billing_phone', true );
            if ( ! empty( $phone ) ) {
                $this->send_otp( $user->ID, $phone );
                update_user_meta( $user->ID, 'mpwa_2fa_status', 'pending' );
            }
        }
    }

    public function on_admin_logout( $user_id ) {
        if ( $user_id ) {
            delete_user_meta( $user_id, 'mpwa_2fa_status' );
            delete_transient( 'mpwa_admin_2fa_' . $user_id );
        }
    }

    private function send_otp( $user_id, $phone ) {
        $otp = (string) wp_rand( 100000, 999999 );
        $key = 'mpwa_admin_2fa_' . $user_id;

        set_transient( $key, [
            'hash'     => wp_hash_password( $otp ),
            'attempts' => 0,
            'time'     => time(),
        ], 300 ); // 5 minutes

        if ( class_exists( 'SendTrigger' ) ) {
            $trigger = new SendTrigger();
            $message = "🔐 رمز الدخول الآمن للوحة تحكم " . get_bloginfo( 'name' ) . ":\n*{$otp}*\n\nالرمز صالح لمدة 5 دقائق.";
            
            $order_cc = get_option( $this->prefix . 'default_country_code', '965' );
            $formatted_phone = SendTrigger::format_phone_number( $phone, $order_cc );
            $trigger->send_direct( $formatted_phone, $message );
        }
    }

    public function check_2fa_status() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return;

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $user_id = get_current_user_id();
            $phone   = get_user_meta( $user_id, 'billing_phone', true );
            
            if ( ! empty( $phone ) ) {
                $status = get_user_meta( $user_id, 'mpwa_2fa_status', true );
                if ( $status === 'pending' ) {
                    wp_redirect( site_url( 'wp-login.php?action=mpwa_2fa' ) );
                    exit;
                }
            }
        }
    }

    public function render_2fa_page() {
        if ( ! is_user_logged_in() ) {
            wp_redirect( wp_login_url() );
            exit;
        }

        $user_id = get_current_user_id();
        $status  = get_user_meta( $user_id, 'mpwa_2fa_status', true );
        if ( $status !== 'pending' ) {
            wp_redirect( admin_url() );
            exit;
        }

        $error   = isset( $_GET['error'] ) ? 'الرمز غير صحيح أو منتهي الصلاحية، يرجى المحاولة مرة أخرى.' : '';
        $resent  = isset( $_GET['resent'] ) ? 'تم إرسال رمز جديد إلى رقمك على الواتساب بنجاح.' : '';
        $phone   = get_user_meta( $user_id, 'billing_phone', true );
        $masked  = strlen( $phone ) > 6 ? substr( $phone, 0, 3 ) . '****' . substr( $phone, -3 ) : $phone;

        login_header( 'التحقق بخطوتين عبر واتساب', '<p class="message" style="text-align:center;">يرجى إدخال رمز التحقق المرسل لواتساب الرقم: <strong>' . esc_html( $masked ) . '</strong></p>', ! empty( $error ) ? new WP_Error( 'invalid_otp', $error ) : '' );
        ?>
        <?php if ( $resent ) : ?>
            <p class="message" style="border-right-color:#25d366; text-align:center;"><?php echo esc_html( $resent ); ?></p>
        <?php endif; ?>

        <form name="mpwa_2fa_form" id="mpwa_2fa_form" action="<?php echo esc_url( site_url( 'wp-login.php?action=mpwa_2fa_verify', 'login_post' ) ); ?>" method="post" style="direction:rtl; text-align:right;">
            <?php wp_nonce_field( 'mpwa_admin_2fa_verify', 'mpwa_2fa_nonce' ); ?>
            <p>
                <label for="mpwa_otp" style="font-weight:600;">رمز التحقق (OTP)<br />
                <input type="text" name="mpwa_otp" id="mpwa_otp" class="input" value="" size="20" required="required" autocomplete="one-time-code" maxlength="6" style="text-align:center; font-size:22px; letter-spacing:6px; font-weight:700; direction:ltr;" placeholder="------" /></label>
            </p>
            <p class="submit" style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="تحقق ودخول لوحة التحكم" style="background:#25d366; border-color:#25d366; text-shadow:none; font-weight:700;" />
            </p>
        </form>

        <form action="<?php echo esc_url( site_url( 'wp-login.php?action=mpwa_2fa_resend', 'login_post' ) ); ?>" method="post" style="text-align:center; margin-top:15px;">
            <?php wp_nonce_field( 'mpwa_admin_2fa_resend', 'mpwa_resend_nonce' ); ?>
            <button type="submit" class="button button-secondary" style="font-size:12px;">🔄 إعادة إرسال الرمز</button>
        </form>

        <p id="backtoblog" style="text-align:center; margin-top:15px;">
            <a href="<?php echo esc_url( wp_logout_url() ); ?>">تسجيل الخروج</a>
        </p>
        <?php
        login_footer();
        exit;
    }

    public function resend_2fa_code() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            wp_redirect( site_url( 'wp-login.php?action=mpwa_2fa' ) );
            exit;
        }
        if ( ! is_user_logged_in() || ! check_admin_referer( 'mpwa_admin_2fa_resend', 'mpwa_resend_nonce' ) ) {
            wp_die( 'فشل التحقق الأمني.' );
        }

        $user_id = get_current_user_id();
        $phone   = get_user_meta( $user_id, 'billing_phone', true );

        if ( ! empty( $phone ) ) {
            if ( class_exists( 'MpwaRateLimiter' ) && ! MpwaRateLimiter::allow( 'admin_2fa_resend', 3, 5 * MINUTE_IN_SECONDS, (string) $user_id ) ) {
                wp_redirect( site_url( 'wp-login.php?action=mpwa_2fa&error=rate_limit' ) );
                exit;
            }
            $this->send_otp( $user_id, $phone );
        }

        wp_redirect( site_url( 'wp-login.php?action=mpwa_2fa&resent=1' ) );
        exit;
    }

    public function verify_2fa_code() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            wp_redirect( site_url( 'wp-login.php?action=mpwa_2fa' ) );
            exit;
        }
        if ( ! is_user_logged_in() || ! check_admin_referer( 'mpwa_admin_2fa_verify', 'mpwa_2fa_nonce' ) ) {
            wp_die( 'فشل التحقق الأمني.' );
        }

        $user_id       = get_current_user_id();
        $key           = 'mpwa_admin_2fa_' . $user_id;
        $submitted_otp = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['mpwa_otp'] ?? '' ) ) );
        $saved_otp     = get_transient( $key );

        if ( is_array( $saved_otp ) && ! empty( $saved_otp['hash'] ) && wp_check_password( $submitted_otp, $saved_otp['hash'] ) ) {
            // Verified successfully
            update_user_meta( $user_id, 'mpwa_2fa_status', 'verified' );
            delete_transient( $key );
            wp_redirect( admin_url() );
            exit;
        } else {
            // Failed attempt
            if ( is_array( $saved_otp ) ) {
                $saved_otp['attempts'] = absint( $saved_otp['attempts'] ?? 0 ) + 1;
                if ( $saved_otp['attempts'] >= 5 ) {
                    delete_transient( $key );
                } else {
                    set_transient( $key, $saved_otp, 300 );
                }
            }
            wp_redirect( site_url( 'wp-login.php?action=mpwa_2fa&error=1' ) );
            exit;
        }
    }
}
new MpwaAdmin2FA();
