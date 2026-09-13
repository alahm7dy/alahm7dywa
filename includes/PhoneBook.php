<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaPhoneBook {

    const TABLE = 'mpwa_contacts';
    private static $table_checked = false;

    private static function ensure_table() {
        if ( self::$table_checked ) return;
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
            self::create_table();
        }
        self::$table_checked = true;
    }

    public static function create_table() {
        global $wpdb;
        $t  = $wpdb->prefix . self::TABLE;
        $cc = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$t} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100) NOT NULL DEFAULT '',
            phone       VARCHAR(30)  NOT NULL DEFAULT '',
            email       VARCHAR(100) NOT NULL DEFAULT '',
            source      VARCHAR(20)  NOT NULL DEFAULT 'manual',
            order_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
            last_order  DATETIME     NOT NULL DEFAULT '0000-00-00 00:00:00',
            created_at  DATETIME     NOT NULL DEFAULT '0000-00-00 00:00:00',
            tags        VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY phone (phone)
        ) {$cc};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /** Auto-save/update contact from a WooCommerce order object */
    public static function upsert_from_order( $order ) {
        self::ensure_table();
        global $wpdb;
        if ( ! $order ) return;
        if ( $order->get_meta( '_mpwa_phonebook_synced', true ) ) return true;
        $billing_country = $order->get_billing_country();
        $country_code = '';
        if ( ! empty( $billing_country ) && function_exists('WC') && isset( WC()->countries ) ) {
            $calling_code = WC()->countries->get_country_calling_code( $billing_country );
            if ( ! empty( $calling_code ) ) {
                $country_code = str_replace('+', '', $calling_code);
            }
        }
        $phone = SendTrigger::format_phone_number( $order->get_billing_phone(), $country_code );
        if ( empty( $phone ) || strlen( $phone ) < 8 ) return;

        $t    = $wpdb->prefix . self::TABLE;
        $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE phone = %s", $phone ) );

        if ( $row ) {
            $result = $wpdb->update( $t, [
                'name'        => $name ?: $row->name,
                'email'       => $order->get_billing_email() ?: $row->email,
                'order_count' => $row->order_count + 1,
                'last_order'  => current_time( 'mysql' ),
            ], [ 'phone' => $phone ] );
        } else {
            $result = $wpdb->insert( $t, [
                'name'        => sanitize_text_field( $name ),
                'phone'       => $phone,
                'email'       => sanitize_email( $order->get_billing_email() ),
                'source'      => 'woocommerce',
                'order_count' => 1,
                'last_order'  => current_time( 'mysql' ),
                'created_at'  => current_time( 'mysql' ),
            ] );
        }
        if ( $result !== false ) {
            $order->update_meta_data( '_mpwa_phonebook_synced', 1 );
            $order->save_meta_data();
            return true;
        }
        return false;
    }

    /** Add contact manually */
    public static function insert_manual( $name, $phone, $email = '', $tags = '' ) {
        self::ensure_table();
        global $wpdb;
        $phone = SendTrigger::format_phone_number( $phone );
        if ( empty( $phone ) ) return false;
        $table = $wpdb->prefix . self::TABLE;
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s", $phone ) );
        if ( $existing ) {
            $updated = $wpdb->update( $table, [
                'name'  => sanitize_text_field( $name ) ?: $existing->name,
                'email' => sanitize_email( $email ) ?: $existing->email,
                'tags'  => sanitize_text_field( $tags ) ?: $existing->tags,
            ], [ 'id' => $existing->id ] );
            return $updated === false ? false : (int) $existing->id;
        }
        $inserted = $wpdb->insert( $table, [
            'name'       => sanitize_text_field( $name ),
            'phone'      => $phone,
            'email'      => sanitize_email( $email ),
            'tags'       => sanitize_text_field( $tags ),
            'source'     => 'manual',
            'created_at' => current_time( 'mysql' ),
            'last_order' => current_time( 'mysql' ),
        ] );
        return $inserted ? (int) $wpdb->insert_id : false;
    }

    public static function subscribe_newsletter( $phone ) {
        self::ensure_table();
        global $wpdb;
        $phone = SendTrigger::format_phone_number( $phone );
        if ( empty( $phone ) || strlen( $phone ) < 8 ) return false;
        $table = $wpdb->prefix . self::TABLE;
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s", $phone ) );
        if ( $existing ) {
            $tags = array_filter( array_map( 'trim', explode( ',', (string) $existing->tags ) ) );
            if ( ! in_array( 'newsletter', $tags, true ) ) $tags[] = 'newsletter';
            $source = $existing->source === 'woocommerce' ? 'woocommerce' : 'newsletter';
            $updated = $wpdb->update( $table, [
                'source' => $source,
                'tags' => implode( ',', $tags ),
            ], [ 'id' => $existing->id ] );
            return $updated === false ? false : (int) $existing->id;
        }
        $inserted = $wpdb->insert( $table, [
            'name' => 'مشترك نشرة',
            'phone' => $phone,
            'source' => 'newsletter',
            'tags' => 'newsletter',
            'created_at' => current_time( 'mysql' ),
            'last_order' => current_time( 'mysql' ),
        ] );
        return $inserted ? (int) $wpdb->insert_id : false;
    }

    public static function get_all( $limit = 100, $offset = 0, $search = '' ) {
        self::ensure_table();
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$t} WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s OR tags LIKE %s ORDER BY last_order DESC LIMIT %d OFFSET %d",
                $like, $like, $like, $like, $limit, $offset
            ) );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} ORDER BY last_order DESC LIMIT %d OFFSET %d", $limit, $offset
        ) );
    }

    public static function count( $search = '' ) {
        self::ensure_table();
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s OR tags LIKE %s",
                $like, $like, $like, $like
            ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
    }

    public static function delete( $id ) {
        self::ensure_table();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . self::TABLE, [ 'id' => absint( $id ) ] );
    }

    public static function count_by_source( $source ) {
        self::ensure_table();
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE source = %s", $source ) );
    }
}
