<?php
/**
 * Plugin Name:       ForgeHub AI Alt Text
 * Plugin URI:        https://wpforgehub.com/plugins/ai-alt-text/
 * Description:       Generate WCAG-compliant image Alt-Text and SEO titles using your own Google Gemini, OpenAI, Claude, DeepSeek, or OpenRouter API key (BYOK).
 * Version:           1.1.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WPForgeHub
 * Author URI:        https://wpforgehub.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       forgehub-ai-alt-text
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Version & Path Constants
define( 'WPFH_AAT_VERSION', '1.1.3' );
define( 'WPFH_AAT_FILE', __FILE__ );
define( 'WPFH_AAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFH_AAT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Freemius Integration Wrapper
 */
if ( ! function_exists( 'wpfh_aat_fs' ) ) {
    function wpfh_aat_fs() {
        global $wpfh_aat_fs;

        if ( ! isset( $wpfh_aat_fs ) ) {
            $force_pro     = defined( 'WPFH_AAT_FORCE_PRO' ) && WPFH_AAT_FORCE_PRO;
            $freemius_path = WPFH_AAT_PATH . 'includes/freemius/start.php';

            if ( file_exists( $freemius_path ) && ! $force_pro ) {
                require_once $freemius_path;

                $wpfh_aat_fs = fs_dynamic_init( [
                    'id'               => '44985',
                    'slug'             => 'ai-alt-text-generator',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_862f10dd7623aebdb2cd2e4e4f840',
                    'is_premium'       => false,
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'menu'             => [
                        'slug'    => 'forgehub-ai-alt-text',
                        'support' => false,
                    ],
                ] );
            } else {
                // Standalone fallback object for Free / Sandbox QA testing
                $wpfh_aat_fs = new class( $force_pro ) {
                    private $is_pro;
                    public function __construct( $is_pro = false ) { $this->is_pro = (bool) $is_pro; }
                    public function is__premium_only() { return $this->is_pro; }
                    public function can_use_premium_code() { return $this->is_pro; }
                    public function is_plan( $plan ) { return $this->is_pro; }
                    public function get_upgrade_url() { return 'https://wpforgehub.com/upgrade/ai-alt-text/?utm_source=plugin_admin&utm_medium=upgrade_btn'; }
                };
            }
        }

        return $wpfh_aat_fs;
    }

    // Initialize Freemius
    wpfh_aat_fs();
}

// Load Core Dependencies
require_once WPFH_AAT_PATH . 'includes/class-wpfh-ai-client.php';
require_once WPFH_AAT_PATH . 'includes/class-wpfh-aat-admin.php';

/**
 * Main Plugin Initialization
 */
function wpfh_aat_init() {
    WPFH_AAT_Admin::init();
}
add_action( 'plugins_loaded', 'wpfh_aat_init' );

/**
 * Settings action link in Plugins screen
 */
function wpfh_aat_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=forgehub-ai-alt-text' ) ) . '">' . esc_html__( 'Settings', 'forgehub-ai-alt-text' ) . '</a>';
    array_unshift( $links, $settings_link );

    if ( ! wpfh_aat_fs()->can_use_premium_code() ) {
        $links['go_pro'] = '<a href="' . esc_url( wpfh_aat_fs()->get_upgrade_url() ) . '" style="color:#2563eb;font-weight:600;" target="_blank">' . esc_html__( 'Upgrade to Pro', 'forgehub-ai-alt-text' ) . '</a>';
    }

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpfh_aat_action_links' );
