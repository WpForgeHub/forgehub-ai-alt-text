<?php
/**
 * Plugin Name:       WPForgeHub AI Alt-Text & Image SEO Generator (BYOK)
 * Plugin URI:        https://wpforgehub.com/plugins/ai-alt-text/
 * Description:       Generate WCAG-compliant image Alt-Text and SEO titles using your own Google Gemini, OpenAI, Claude, DeepSeek, or OpenRouter API key (BYOK).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WPForgeHub
 * Author URI:        https://wpforgehub.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpfh-ai-alt-text
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Version & Path Constants
define( 'WPFH_AAT_VERSION', '1.0.0' );
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
            // Include Freemius SDK if available in bundle
            $freemius_path = WPFH_AAT_PATH . 'includes/freemius/start.php';
            if ( file_exists( $freemius_path ) ) {
                require_once $freemius_path;

                $wpfh_aat_fs = fs_dynamic_init( [
                    'id'               => '16001', // Configured in Freemius Developer Dashboard
                    'slug'             => 'wpfh-ai-alt-text',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_placeholder',
                    'is_premium'       => false,
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'menu'             => [
                        'slug'    => 'wpfh-ai-alt-text',
                        'support' => false,
                    ],
                ] );
            } else {
                // Standalone fallback object for Free distribution
                $wpfh_aat_fs = new class {
                    public function is__premium_only() { return false; }
                    public function can_use_premium_code() { return false; }
                    public function is_plan( $plan ) { return false; }
                    public function get_upgrade_url() { return 'https://wpforgehub.com/plugins/ai-alt-text/#pro'; }
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
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=wpfh-ai-alt-text' ) ) . '">' . esc_html__( 'Settings', 'wpfh-ai-alt-text' ) . '</a>';
    array_unshift( $links, $settings_link );

    if ( ! wpfh_aat_fs()->can_use_premium_code() ) {
        $links['go_pro'] = '<a href="' . esc_url( wpfh_aat_fs()->get_upgrade_url() ) . '" style="color:#2563eb;font-weight:600;" target="_blank">' . esc_html__( 'Upgrade to Pro', 'wpfh-ai-alt-text' ) . '</a>';
    }

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpfh_aat_action_links' );
