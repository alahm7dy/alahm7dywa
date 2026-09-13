<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaRateLimiter {
    public static function allow( $action, $limit, $window, $subject = '' ) {
        if ( $subject === '' ) {
            $subject = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        }
        $key = 'mpwa_rl_' . md5( sanitize_key( $action ) . '|' . $subject );
        $data = get_transient( $key );
        if ( ! is_array( $data ) ) $data = [ 'count' => 0 ];
        if ( $data['count'] >= $limit ) return false;
        $data['count']++;
        set_transient( $key, $data, max( 1, absint( $window ) ) );
        return true;
    }

    public static function clear( $action, $subject ) {
        delete_transient( 'mpwa_rl_' . md5( sanitize_key( $action ) . '|' . $subject ) );
    }
}
