<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaAbandonedCart {

    public function __construct() {
        // Track when the cart is updated
        add_action( 'woocommerce_cart_updated', [ $this, 'track_abandoned_cart' ] );
        
        // Cron job for sending abandoned cart reminders
        add_action( 'mpwa_abandoned_cart_cron', [ $this, 'send_abandoned_whatsapp' ], 10, 2 );
        
        // Remove scheduled cron if the order is placed
        add_action( 'woocommerce_new_order', [ $this, 'clear_abandoned_cron' ], 10, 1 );
    }

    public function track_abandoned_cart() {
        if ( is_user_logged_in() && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
            $user_id = get_current_user_id();
            $phone   = get_user_meta( $user_id, 'billing_phone', true );
            
            if ( ! empty( $phone ) ) {
                // Schedule reminder after 1 hour
                $timestamp = time() + 3600;
                
                // Clear any existing scheduled event for this user to avoid duplicate reminders
                wp_clear_scheduled_hook( 'mpwa_abandoned_cart_cron', [ $user_id, $phone ] );
                
                // Schedule the new event
                wp_schedule_single_event( $timestamp, 'mpwa_abandoned_cart_cron', [ $user_id, $phone ] );
            }
        }
    }

    public function clear_abandoned_cron( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_user_id() ) {
            $user_id     = $order->get_user_id();
            $phone_meta  = get_user_meta( $user_id, 'billing_phone', true );
            $phone_order = $order->get_billing_phone();

            if ( $phone_meta ) {
                wp_clear_scheduled_hook( 'mpwa_abandoned_cart_cron', [ $user_id, $phone_meta ] );
            }
            if ( $phone_order && $phone_order !== $phone_meta ) {
                wp_clear_scheduled_hook( 'mpwa_abandoned_cart_cron', [ $user_id, $phone_order ] );
            }

            update_user_meta( $user_id, '_mpwa_last_order_time', time() );
        }
    }

    public function send_abandoned_whatsapp( $user_id, $phone ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        // Check if user has completed an order recently
        $last_order_time = (int) get_user_meta( $user_id, '_mpwa_last_order_time', true );
        if ( $last_order_time && ( time() - $last_order_time ) < 3600 ) {
            return;
        }
        
        $cart_meta = get_user_meta( $user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
        if ( empty( $cart_meta['cart'] ) ) return; // Cart is empty
        
        $first_item = reset( $cart_meta['cart'] );
        $product_id = $first_item['product_id'] ?? 0;
        $product    = wc_get_product( $product_id );
        
        if ( ! $product ) return;
        
        $product_name = $product->get_name();
        $image_id     = $product->get_image_id();
        $image_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
        if ( empty( $image_url ) ) {
            $image_url = get_site_icon_url() ?: 'https://via.placeholder.com/300x300.png?text=Cart';
        }

        $customer_name = $user->first_name ?: 'عميلنا العزيز';
        $item_count    = count( $cart_meta['cart'] );
        $items_extra   = $item_count > 1 ? " ومنتجات أخرى (" . $item_count . " منتجات)" : "";

        $message  = "مرحباً {$customer_name} 👋\n\n";
        $message .= "لاحظنا أنك تركت [ *{$product_name}* ]{$items_extra} في سلة مشترياتك 🛒\n\n";
        $message .= "سلتك محفوظة ومتاحة لك الآن. أكمل طلبك واستمتع بأفضل العروض قبل نفاد الكمية!";

        if ( class_exists( 'SendTrigger' ) ) {
            $trigger = new SendTrigger();
            
            $buttons = [
                [ 'type' => 'url', 'displayText' => '💳 إكمال الطلب الآن', 'url' => wc_get_checkout_url() ]
            ];
            
            $billing_country = get_user_meta( $user_id, 'billing_country', true );
            $order_cc        = get_option( 'mpedia_wagatewaydefault_country_code', '965' );
            if ( ! empty( $billing_country ) && function_exists( 'WC' ) && isset( WC()->countries ) ) {
                $cc = WC()->countries->get_country_calling_code( $billing_country );
                if ( $cc ) $order_cc = str_replace( '+', '', $cc );
            }
            
            $formatted_phone = SendTrigger::format_phone_number( $phone, $order_cc );
            $ok = $trigger->send_button( $formatted_phone, $message, $buttons, $image_url, 'تسوق ممتع مع ' . get_bloginfo( 'name' ) );
            
            if ( class_exists( 'MpwaLogger' ) ) {
                MpwaLogger::write( [
                    'recipient'    => $formatted_phone,
                    'message'      => $message . "\n[زر إكمال الطلب]",
                    'order_id'     => 0,
                    'event_type'   => 'abandoned_cart',
                    'status'       => $ok ? 'success' : 'failed',
                    'http_code'    => $ok ? 200 : 0,
                    'api_response' => $ok ? 'Sent via Button API' : 'Failed to send button',
                    'error_msg'    => $ok ? '' : 'فشل إرسال تذكير السلة عبر البوابة.'
                ] );
            }
        }
    }
}

new MpwaAbandonedCart();
