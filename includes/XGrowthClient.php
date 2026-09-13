<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Central X-Growth API client used by the plugin's messaging services. */
class MpwaXGrowthClient {
    private $base_url;
    private $api_key;
    private $sender;

    public function __construct() {
        $this->base_url = rtrim( (string) get_option( 'mpedia_wagatewayurl', 'https://app.x-growth.live' ), '/' );
        $this->api_key  = (string) get_option( 'mpedia_wagatewayapi_key', '' );
        $this->sender   = (string) get_option( 'mpedia_wagatewaydevice', '' );
    }

    public function is_configured() {
        return $this->base_url !== '' && $this->api_key !== '' && $this->sender !== '';
    }

    public function send_message( $number, $message, $footer = '' ) {
        return $this->post( '/send-message', [
            'sender' => $this->sender,
            'number' => preg_replace( '/\D/', '', (string) $number ),
            'message' => (string) $message,
            'footer' => (string) $footer,
        ] );
    }

    public function send_product( $number, $url, $message = '' ) {
        return $this->post( '/send-product', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'url' => esc_url_raw( $url ), 'message' => (string) $message ] );
    }

    public function send_media( $number, $url, $caption = '', $media_type = 'image', $footer = '' ) {
        $allowed = [ 'image', 'video', 'audio', 'document' ];
        $media_type = in_array( $media_type, $allowed, true ) ? $media_type : 'image';
        return $this->post( '/send-media', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'media_type' => $media_type, 'url' => esc_url_raw( $url ), 'caption' => (string) $caption, 'footer' => (string) $footer ] );
    }

    public function send_channel( $channel_url, $message, $footer = '' ) {
        return $this->post( '/send-text-channel', [ 'sender' => $this->sender, 'url' => esc_url_raw( $channel_url ), 'message' => (string) $message, 'footer' => (string) $footer ] );
    }

    public function send_sticker( $number, $url ) {
        return $this->post( '/send-sticker', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'url' => esc_url_raw( $url ) ] );
    }

    public function send_poll( $number, $question, $options, $countable = 1 ) {
        return $this->post( '/send-poll', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'name' => (string) $question, 'option' => array_values( (array) $options ), 'countable' => $countable ? '1' : '0' ] );
    }

    public function send_button( $number, $message, $buttons, $image, $footer = '' ) {
        return $this->post( '/send-button', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'message' => (string) $message, 'button' => array_slice( array_values( (array) $buttons ), 0, 5 ), 'image' => esc_url_raw( $image ), 'footer' => (string) $footer ] );
    }

    public function send_list( $number, $message, $name, $title, $button_text, $sections, $image, $footer = '' ) {
        if ( empty( $image ) ) $image = get_site_icon_url() ?: 'https://via.placeholder.com/600x400.png?text=Menu';
        return $this->post( '/send-list', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'name' => (string) $name, 'title' => (string) $title, 'buttontext' => (string) $button_text, 'message' => (string) $message, 'sections' => array_slice( array_values( (array) $sections ), 0, 5 ), 'image' => esc_url_raw( $image ), 'footer' => (string) $footer ] );
    }

    public function send_location( $number, $latitude, $longitude ) {
        return $this->post( '/send-location', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'latitude' => (string) $latitude, 'longitude' => (string) $longitude ] );
    }

    public function send_vcard( $number, $name, $phone ) {
        return $this->post( '/send-vcard', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ), 'name' => sanitize_text_field( $name ), 'phone' => $this->clean_number( $phone ) ] );
    }

    public function device_info() { return $this->post( '/info-devices', [ 'number' => $this->sender ] ); }
    public function user_info( $username ) { return $this->post( '/info-user', [ 'username' => sanitize_user( $username ) ] ); }
    public function generate_qr( $force = false ) { return $this->post( '/generate-qr', [ 'device' => $this->sender, 'force' => (bool) $force ] ); }
    public function logout_device() { return $this->post( '/logout-device', [ 'sender' => $this->sender ] ); }
    public function check_number( $number ) { return $this->post( '/check-number', [ 'sender' => $this->sender, 'number' => $this->clean_number( $number ) ] ); }
    public function create_user( $username, $email, $password, $expire, $limit_device = 1 ) { return $this->post( '/create-user', [ 'username' => sanitize_user( $username ), 'email' => sanitize_email( $email ), 'password' => (string) $password, 'expire' => absint( $expire ), 'limit_device' => absint( $limit_device ) ] ); }
    public function disconnect_device() { return $this->logout_device(); }
    public function delete_device() { return $this->post( '/delete-device', [ 'sender' => $this->sender ] ); }

    private function clean_number( $number ) { return preg_replace( '/\D/', '', (string) $number ); }

    public function decode_response( $response ) {
        if ( is_wp_error( $response ) ) return null;
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $decoded ) ? $decoded : null;
    }

    public function is_success( $response ) {
        if ( is_wp_error( $response ) ) return false;
        $code = wp_remote_retrieve_response_code( $response );
        $body = $this->decode_response( $response );
        return $code >= 200 && $code < 300 && is_array( $body ) && ! empty( $body['status'] );
    }

    public function error_message( $response ) {
        if ( is_wp_error( $response ) ) return $response->get_error_message();
        $body = $this->decode_response( $response );
        if ( isset( $body['msg'] ) && is_string( $body['msg'] ) ) return sanitize_text_field( $body['msg'] );
        if ( isset( $body['message'] ) && is_string( $body['message'] ) ) return sanitize_text_field( $body['message'] );
        return 'فشل طلب البوابة. رمز HTTP: ' . absint( wp_remote_retrieve_response_code( $response ) );
    }

    public function post( $path, $payload ) {
        if ( ! $this->is_configured() ) return new WP_Error( 'mpwa_api_not_configured', 'بيانات X-Growth غير مكتملة.' );
        $payload['api_key'] = $this->api_key;
        return wp_safe_remote_post( $this->base_url . '/' . ltrim( $path, '/' ), [
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body' => wp_json_encode( $payload ),
            'timeout' => 20,
            'redirection' => 2,
        ] );
    }
}
