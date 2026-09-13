<?php
/**
 * GitHub Auto-Updater for Alahm7dy WooCommerce WhatsApp
 * Automatically checks GitHub Releases for plugin updates and integrates with WordPress core updater.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaGitHubUpdater {

    private $file;
    private $plugin_slug;
    private $version;
    private $github_repo;
    private $github_token;
    private $transient_key;

    public function __construct( $file, $version ) {
        $this->file          = $file;
        $this->plugin_slug   = plugin_basename( $file );
        $this->version       = $version;
        $repo = get_option( 'mpedia_wagatewaygithub_repo', '' );
        if ( empty( $repo ) || $repo === 'alahm7dy/mpwa-woocommerce' ) {
            $repo = 'alahm7dy/alahm7dywa';
        }
        $this->github_repo   = $repo;
        $this->github_token  = get_option( 'mpedia_wagatewaygithub_token', '' );
        $this->transient_key = 'mpwa_gh_update_' . md5( $this->github_repo );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api',                            [ $this, 'plugin_popup' ], 20, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'post_install' ], 10, 3 );
        add_filter( 'plugin_row_meta',                       [ $this, 'plugin_row_meta' ], 10, 2 );

        // AJAX live check handler
        add_action( 'wp_ajax_mpwa_check_github_update',      [ $this, 'ajax_check_update' ] );
    }

    /**
     * Get the latest release from GitHub API (with transient caching)
     */
    public function get_latest_release( $force = false ) {
        if ( empty( $this->github_repo ) ) {
            return false;
        }

        if ( ! $force ) {
            $cached = get_transient( $this->transient_key );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $url = 'https://api.github.com/repos/' . trim( $this->github_repo, '/' ) . '/releases/latest';

        $args = [
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ],
        ];

        if ( ! empty( $this->github_token ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . trim( $this->github_token );
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // Cache failure for 10 minutes to prevent spamming failed requests
            set_transient( $this->transient_key, false, 10 * MINUTE_IN_SECONDS );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data ) || ! is_array( $data ) ) {
            return false;
        }

        // Cache valid release for 6 hours
        set_transient( $this->transient_key, $data, 6 * HOUR_IN_SECONDS );
        return $data;
    }

    /**
     * Hook into pre_set_site_transient_update_plugins
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $force  = ( $screen && $screen->id === 'update-core' ) || isset( $_GET['force-check'] );

        $release = $this->get_latest_release( $force );
        if ( ! $release || empty( $release['tag_name'] ) ) {
            return $transient;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        if ( version_compare( $remote_version, $this->version, '>' ) ) {
            $package_url = $this->get_download_package( $release );

            $item = (object) [
                'id'            => $this->plugin_slug,
                'slug'          => dirname( $this->plugin_slug ),
                'plugin'        => $this->plugin_slug,
                'new_version'   => $remote_version,
                'url'           => $release['html_url'] ?? 'https://github.com/' . $this->github_repo,
                'package'       => $package_url,
                'tested'        => '6.7',
                'requires_php'  => '7.4',
                'icons'         => [
                    'default' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/main/assets/images/icon.png',
                ],
            ];

            $transient->response[ $this->plugin_slug ] = $item;
        }

        return $transient;
    }

    /**
     * Get the download package URL from release assets or zipball
     */
    private function get_download_package( $release ) {
        // Check if a release asset zip file is attached to the release
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && substr( strtolower( $asset['name'] ), -4 ) === '.zip' ) {
                    $url = $asset['browser_download_url'] ?? '';
                    if ( ! empty( $this->github_token ) && ! empty( $asset['url'] ) ) {
                        $url = add_query_arg( 'access_token', $this->github_token, $asset['url'] );
                    }
                    return $url;
                }
            }
        }

        // Fallback to GitHub auto-generated zipball
        $zipball = $release['zipball_url'] ?? '';
        if ( ! empty( $this->github_token ) && ! empty( $zipball ) ) {
            $zipball = add_query_arg( 'access_token', $this->github_token, $zipball );
        }
        return $zipball;
    }

    /**
     * Hook into plugins_api for view details modal
     */
    public function plugin_popup( $res, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $res;
        }

        $slug = dirname( $this->plugin_slug );
        if ( ! isset( $args->slug ) || ( $args->slug !== $slug && $args->slug !== $this->plugin_slug ) ) {
            return $res;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $res;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        $info = (object) [
            'name'          => 'Alahm7dy WooCommerce WhatsApp',
            'slug'          => $slug,
            'version'       => $remote_version,
            'author'        => '<a href="https://alahm7dy.com" target="_blank">علي الاحمدي</a>',
            'homepage'      => 'https://github.com/' . $this->github_repo,
            'requires'      => '5.6',
            'tested'        => '6.7',
            'requires_php'  => '7.4',
            'last_updated'  => $release['published_at'] ?? date( 'Y-m-d' ),
            'download_link' => $this->get_download_package( $release ),
            'sections'      => [
                'description' => 'إضافة إدارة وتنبيهات الواتساب لـ WooCommerce مع دعم الذكاء الاصطناعي والدخول السريع برمز OTP.',
                'changelog'   => nl2br( esc_html( $release['body'] ?? 'تحديثات وتحسينات جديدة في هذا الإصدار.' ) ),
            ],
        ];

        return $info;
    }

    /**
     * Normalize directory name after upgrade so the folder matches the plugin directory
     */
    public function post_install( $true, $hook_extra, $result ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $true;
        }

        $proper_destination = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );
        if ( isset( $result['destination'] ) && $result['destination'] !== $proper_destination ) {
            $wp_filesystem->move( $result['destination'], $proper_destination );
            $result['destination'] = $proper_destination;
        }

        if ( is_plugin_active( $this->plugin_slug ) ) {
            activate_plugin( $this->plugin_slug );
        }

        return $result;
    }

    /**
     * Add "Check for updates" link in Plugins list table
     */
    public function plugin_row_meta( $meta, $file ) {
        if ( $file === $this->plugin_slug ) {
            $meta[] = '<a href="' . esc_url( admin_url( 'admin.php?page=mpwa-settings&tab=updates&check_gh=1' ) ) . '" style="font-weight:bold; color:#128C7E;">فحص التحديثات 🔄</a>';
        }
        return $meta;
    }

    /**
     * AJAX Live Check handler for admin dashboard
     */
    public function ajax_check_update() {
        check_ajax_referer( 'mpwa_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'غير مصرح.' );
        }

        // Force fetch fresh from GitHub and flush WP transient
        delete_transient( $this->transient_key );
        delete_site_transient( 'update_plugins' );
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache();
        }

        $release = $this->get_latest_release( true );

        if ( ! $release || empty( $release['tag_name'] ) ) {
            wp_send_json_error( 'تعذر الاتصال بـ GitHub أو لم يتم العثور على إصدارات (Releases) في المستودع: "' . esc_html( $this->github_repo ) . '". تأكد من كتابة اسم المستودع بشكل صحيح ووجود اتصال بالإنترنت.' );
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );
        $is_new = version_compare( $remote_version, $this->version, '>' );

        $data = [
            'current_version' => $this->version,
            'latest_version'  => $remote_version,
            'is_new'          => $is_new,
            'release_name'    => $release['name'] ?? ( 'إصدار ' . $remote_version ),
            'release_notes'   => ! empty( $release['body'] ) ? $release['body'] : 'لا توجد ملاحظات إضافية لهذا الإصدار.',
            'published_at'    => date_i18n( 'Y-m-d H:i', strtotime( $release['published_at'] ?? 'now' ) ),
            'update_url'      => admin_url( 'update-core.php' ),
            'plugins_url'     => admin_url( 'plugins.php' ),
            'message'         => $is_new 
                ? '🚀 يتوفر إصدار جديد (' . $remote_version . ')! يمكنك الآن الانتقال لصفحة تحديثات ووردبريس أو صفحة الإضافات وتثبيته فوراً.'
                : '✅ الإضافة محدثة بالكامل لأحدث إصدار متوفر (' . $this->version . '). لا توجد تحديثات جديدة حالياً.',
        ];

        wp_send_json_success( $data );
    }
}
