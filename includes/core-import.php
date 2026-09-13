<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require __DIR__ . '/Logger.php';
require __DIR__ . '/RateLimiter.php';
require __DIR__ . '/PhoneBook.php';
require __DIR__ . '/XGrowthClient.php';
require __DIR__ . '/SendTrigger.php';
require __DIR__ . '/MpwaWooCommerce.php';

$p = 'mpedia_wagateway';

if ( get_option($p.'enable_feature_abandoned_cart', 'yes') === 'yes' ) {
    require __DIR__ . '/AbandonedCart.php';
}

if ( get_option($p.'enable_feature_otp_login', 'yes') === 'yes' ) {
    require __DIR__ . '/OtpLogin.php';
}

if ( get_option($p.'enable_feature_webhook', 'yes') === 'yes' ) {
    require __DIR__ . '/WebhookHandler.php';
}

if ( get_option($p.'enable_admin_2fa', 'no') === 'yes' ) {
    require __DIR__ . '/Admin2FA.php';
}

require __DIR__ . '/GeminiHandler.php';
require __DIR__ . '/PdfInvoice.php';
require __DIR__ . '/GitHubUpdater.php';

if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
    new MpwaGitHubUpdater( MPWA_PLUGIN_FILE, MPWA_VERSION );
}
