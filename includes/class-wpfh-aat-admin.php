<?php
/**
 * WPForgeHub AI Alt-Text — Admin & Media Integration
 *
 * @package WPForgeHub\AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_AAT_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        
        // Media Library Integration
        add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_media_modal_button' ], 10, 2 );
        add_action( 'wp_ajax_wpfh_aat_generate_single', [ __CLASS__, 'ajax_generate_single' ] );
    }

    /**
     * Add settings page under WordPress Settings menu.
     */
    public static function add_settings_menu() {
        add_options_page(
            __( 'AI Alt-Text Settings', 'wpfh-ai-alt-text' ),
            __( 'AI Alt-Text (BYOK)', 'wpfh-ai-alt-text' ),
            'manage_options',
            'wpfh-ai-alt-text',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Register plugin settings.
     */
    public static function register_settings() {
        register_setting( 'wpfh_aat_options_group', 'wpfh_aat_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
            'default'           => self::get_default_settings(),
        ] );
    }

    public static function get_default_settings() {
        return [
            'provider'        => 'gemini',
            'api_key'         => '',
            'model'           => 'gemini-1.5-flash',
            'custom_endpoint' => '',
            'language'        => 'en',
            'auto_title'      => 0,
            'brand_context'   => '',
        ];
    }

    public static function get_settings() {
        return wp_parse_args( get_option( 'wpfh_aat_settings', [] ), self::get_default_settings() );
    }

    public static function sanitize_settings( $input ) {
        $clean = [];
        $clean['provider']        = sanitize_key( $input['provider'] ?? 'gemini' );
        $clean['api_key']         = trim( sanitize_text_field( $input['api_key'] ?? '' ) );
        
        $model = sanitize_text_field( $input['model'] ?? '' );
        if ( 'custom' === $clean['provider'] && ! empty( $input['custom_model'] ) ) {
            $model = sanitize_text_field( $input['custom_model'] );
        }
        $clean['model']           = $model;
        $clean['custom_endpoint'] = esc_url_raw( trim( $input['custom_endpoint'] ?? '' ) );
        $clean['language']        = sanitize_key( $input['language'] ?? 'en' );
        $clean['auto_title']      = ! empty( $input['auto_title'] ) ? 1 : 0;
        $clean['brand_context']   = sanitize_text_field( $input['brand_context'] ?? '' );
        return $clean;
    }

    /**
     * Enqueue CSS and JS in WordPress admin.
     */
    public static function enqueue_assets( $hook ) {
        // Enqueue on media screens and our settings page
        $allowed_screens = [ 'settings_page_wpfh-ai-alt-text', 'upload.php', 'post.php', 'post-new.php' ];
        if ( ! in_array( $hook, $allowed_screens, true ) && ! wp_attachment_is_image() ) {
            return;
        }

        wp_enqueue_style(
            'wpfh-aat-admin-css',
            WPFH_AAT_URL . 'assets/css/admin.css',
            [],
            WPFH_AAT_VERSION
        );

        wp_enqueue_script(
            'wpfh-aat-admin-js',
            WPFH_AAT_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            WPFH_AAT_VERSION,
            true
        );

        $settings = self::get_settings();

        wp_localize_script( 'wpfh-aat-admin-js', 'wpfhAatData', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'wpfh_aat_nonce' ),
            'providers'        => WPFH_AI_Client::get_providers(),
            'current_provider' => $settings['provider'],
            'current_model'    => $settings['model'],
            'i18n'             => [
                'generating' => __( 'Analyzing image...', 'wpfh-ai-alt-text' ),
                'success'    => __( 'Alt-Text generated!', 'wpfh-ai-alt-text' ),
                'error'      => __( 'Failed to generate.', 'wpfh-ai-alt-text' ),
            ],
        ] );
    }

    /**
     * Add "Generate with AI" button into Media Library attachment sidebar.
     */
    public static function add_media_modal_button( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }

        $form_fields['wpfh_aat_button'] = [
            'label' => __( 'AI Alt-Text', 'wpfh-ai-alt-text' ),
            'input' => 'html',
            'html'  => sprintf(
                '<div class="wpfh-aat-media-action">
                    <button type="button" class="button button-secondary wpfh-aat-btn" data-attachment-id="%d">
                        <span class="dashicons dashicons-superhero" style="font-size:16px;line-height:1.6;margin-right:4px;"></span>
                        %s
                    </button>
                    <span class="wpfh-aat-status" style="margin-left:8px;font-size:12px;"></span>
                </div>',
                absint( $post->ID ),
                esc_html__( 'Generate with AI', 'wpfh-ai-alt-text' )
            ),
            'helps' => __( 'Powered by your BYOK AI provider configured in Settings.', 'wpfh-ai-alt-text' ),
        ];

        return $form_fields;
    }

    /**
     * AJAX handler for single image alt-text generation.
     */
    public static function ajax_generate_single() {
        check_ajax_referer( 'wpfh_aat_nonce', 'security' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wpfh-ai-alt-text' ) ] );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid image ID.', 'wpfh-ai-alt-text' ) ] );
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            wp_send_json_error( [ 'message' => __( 'Image file not found on disk.', 'wpfh-ai-alt-text' ) ] );
        }

        $settings = self::get_settings();

        if ( empty( $settings['api_key'] ) && 'custom' !== $settings['provider'] ) {
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'API key missing! Please <a href="%s" target="_blank">configure your key</a> first.', 'wpfh-ai-alt-text' ),
                    esc_url( admin_url( 'options-general.php?page=wpfh-ai-alt-text' ) )
                ),
            ] );
        }

        // Custom prompt incorporating user's brand context and language
        $prompt = sprintf(
            'Analyze this image and generate an accurate, concise, WCAG-compliant alt text (under 125 characters) and a short title. Language: %s. Brand context: %s. Return valid JSON only: {"alt_text": "...", "title": "..."}',
            esc_html( $settings['language'] ),
            esc_html( $settings['brand_context'] )
        );

        $result = WPFH_AI_Client::analyze_image( $settings, $file_path, $prompt, $settings['language'] );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        $alt_text = sanitize_text_field( $result['data']['alt_text'] ?? '' );
        $title    = sanitize_text_field( $result['data']['title'] ?? '' );

        // Save alt text to WordPress attachment meta
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

        // Optionally update post title if setting enabled
        if ( ! empty( $settings['auto_title'] ) && ! empty( $title ) ) {
            wp_update_post( [
                'ID'         => $attachment_id,
                'post_title' => $title,
            ] );
        }

        wp_send_json_success( [
            'alt_text' => $alt_text,
            'title'    => $title,
            'message'  => __( 'Success!', 'wpfh-ai-alt-text' ),
        ] );
    }

    /**
     * Render Settings Page.
     */
    public static function render_settings_page() {
        $settings  = self::get_settings();
        $providers = WPFH_AI_Client::get_providers();
        ?>
        <div class="wrap wpfh-aat-wrap">
            <h1><?php esc_html_e( 'WPForgeHub AI Alt-Text & Image SEO', 'wpfh-ai-alt-text' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Zero server bloat, 100% private BYOK (Bring Your Own Key) architecture. Your images are sent directly to your chosen AI provider without intermediary servers.', 'wpfh-ai-alt-text' ); ?>
            </p>

            <div class="wpfh-aat-settings-grid">
                <!-- Left Column: Settings Form -->
                <div class="wpfh-aat-main-col">
                    <form method="post" action="options.php" class="wpfh-aat-form">
                        <?php
                        settings_fields( 'wpfh_aat_options_group' );
                        ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="wpfh_provider"><?php esc_html_e( 'AI Provider', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <select name="wpfh_aat_settings[provider]" id="wpfh_provider" class="regular-text">
                                        <?php foreach ( $providers as $key => $prov ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['provider'], $key ); ?>>
                                                <?php echo esc_html( $prov['label'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wpfh_api_key"><?php esc_html_e( 'API Key', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <input name="wpfh_aat_settings[api_key]" type="password" id="wpfh_api_key" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" placeholder="sk-... or AIzaSy..." />
                                    <p class="description"><?php esc_html_e( 'Your private API key. Stored securely in your WordPress database.', 'wpfh-ai-alt-text' ); ?></p>
                                </td>
                            </tr>

                            <tr id="wpfh_custom_endpoint_row" style="<?php echo 'custom' === $settings['provider'] ? '' : 'display:none;'; ?>">
                                <th scope="row"><label for="wpfh_custom_endpoint"><?php esc_html_e( 'Custom Base URL', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <input name="wpfh_aat_settings[custom_endpoint]" type="url" id="wpfh_custom_endpoint" value="<?php echo esc_attr( $settings['custom_endpoint'] ); ?>" class="regular-text" placeholder="http://localhost:11434/v1" />
                                    <p class="description"><?php esc_html_e( 'OpenAI-compatible endpoint URL for local Ollama, Groq, vLLM, etc.', 'wpfh-ai-alt-text' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wpfh_model"><?php esc_html_e( 'Model Selection', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <?php
                                    $current_provider = $settings['provider'] ?? 'gemini';
                                    $current_models   = $providers[ $current_provider ]['models'] ?? [];
                                    $is_custom        = 'custom' === $current_provider;
                                    ?>
                                    <select name="wpfh_aat_settings[model]" id="wpfh_model" class="regular-text" style="<?php echo $is_custom ? 'display:none;' : ''; ?>">
                                        <?php if ( ! empty( $current_models ) ) : ?>
                                            <?php foreach ( $current_models as $m_key => $m_label ) : ?>
                                                <option value="<?php echo esc_attr( $m_key ); ?>" <?php selected( $settings['model'], $m_key ); ?>>
                                                    <?php echo esc_html( $m_label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <option value="<?php echo esc_attr( $settings['model'] ); ?>" selected="selected">
                                                <?php echo esc_html( $settings['model'] ?: 'Default Model' ); ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                    <input name="wpfh_aat_settings[custom_model]" type="text" id="wpfh_custom_model" value="<?php echo esc_attr( $settings['model'] ); ?>" class="regular-text" style="<?php echo $is_custom ? '' : 'display:none;'; ?>" placeholder="e.g. llama-3.3-70b-versatile or custom-model" />
                                    <p class="description" id="wpfh_model_help">
                                        <?php esc_html_e( 'Flash models are ultra-fast and free/cheap; Pro/Flagship models provide maximum deep vision reasoning.', 'wpfh-ai-alt-text' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wpfh_language"><?php esc_html_e( 'Output Language', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <select name="wpfh_aat_settings[language]" id="wpfh_language">
                                        <option value="en" <?php selected( $settings['language'], 'en' ); ?>>English</option>
                                        <option value="tr" <?php selected( $settings['language'], 'tr' ); ?>>Türkçe</option>
                                        <option value="de" <?php selected( $settings['language'], 'de' ); ?>>Deutsch</option>
                                        <option value="es" <?php selected( $settings['language'], 'es' ); ?>>Español</option>
                                        <option value="fr" <?php selected( $settings['language'], 'fr' ); ?>>Français</option>
                                        <option value="it" <?php selected( $settings['language'], 'it' ); ?>>Italiano</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wpfh_brand_context"><?php esc_html_e( 'Brand / Niche Context', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <input name="wpfh_aat_settings[brand_context]" type="text" id="wpfh_brand_context" value="<?php echo esc_attr( $settings['brand_context'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Handmade leather goods store, tech blog, medical clinic...', 'wpfh-ai-alt-text' ); ?>" />
                                    <p class="description"><?php esc_html_e( 'Guides the AI to use relevant domain keywords and tone.', 'wpfh-ai-alt-text' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Auto Update Title', 'wpfh-ai-alt-text' ); ?></th>
                                <td>
                                    <label for="wpfh_auto_title">
                                        <input name="wpfh_aat_settings[auto_title]" type="checkbox" id="wpfh_auto_title" value="1" <?php checked( $settings['auto_title'], 1 ); ?> />
                                        <?php esc_html_e( 'Also update media title alongside the alt-text.', 'wpfh-ai-alt-text' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(); ?>
                    </form>
                </div>

                <!-- Right Column: Quick Start Help Box -->
                <div class="wpfh-aat-sidebar-col">
                    <div class="wpfh-aat-help-box">
                        <h3>📖 <?php esc_html_e( 'How to Use This Plugin', 'wpfh-ai-alt-text' ); ?></h3>
                        <ol class="wpfh-aat-steps">
                            <li>
                                <strong>1. <?php esc_html_e( 'Save Your API Key:', 'wpfh-ai-alt-text' ); ?></strong>
                                <span><?php esc_html_e( 'Select your AI provider on the left, paste your API key, choose your preferred model, and click "Save Changes".', 'wpfh-ai-alt-text' ); ?></span>
                            </li>
                            <li>
                                <strong>2. <?php esc_html_e( 'Go to Media Library:', 'wpfh-ai-alt-text' ); ?></strong>
                                <span><?php printf( esc_html__( 'Open %s in your admin sidebar.', 'wpfh-ai-alt-text' ), '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '"><strong>Media &rarr; Library</strong></a>' ); ?></span>
                            </li>
                            <li>
                                <strong>3. <?php esc_html_e( 'Click Any Image:', 'wpfh-ai-alt-text' ); ?></strong>
                                <span><?php esc_html_e( 'Click on any photo. In the attachment details panel on the right, you will see the new button:', 'wpfh-ai-alt-text' ); ?><br><code style="display:inline-block;margin-top:4px;padding:2px 8px;background:#e2e8f0;color:#0f172a;border-radius:4px;font-size:12px;">⚡ Generate with AI</code></span>
                            </li>
                            <li>
                                <strong>4. <?php esc_html_e( 'Auto-Saves Instantly:', 'wpfh-ai-alt-text' ); ?></strong>
                                <span><?php esc_html_e( 'The AI inspects your image, drafts WCAG-compliant descriptive alt text and SEO title, and saves it to WordPress metadata automatically.', 'wpfh-ai-alt-text' ); ?></span>
                            </li>
                        </ol>

                        <div class="wpfh-aat-help-links">
                            <h4>🔑 <?php esc_html_e( 'Get Free / Direct API Keys:', 'wpfh-ai-alt-text' ); ?></h4>
                            <ul>
                                <li><a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio &rarr;</a> (Gemini 1.5 Flash has a generous free tier)</li>
                                <li><a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform &rarr;</a> (GPT-4o Mini)</li>
                                <li><a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener">DeepSeek Platform &rarr;</a> (DeepSeek-V3)</li>
                                <li><a href="https://console.anthropic.com/" target="_blank" rel="noopener">Anthropic Console &rarr;</a> (Claude 3.5 Sonnet)</li>
                            </ul>
                        </div>

                        <div class="wpfh-aat-help-tip">
                            💡 <strong><?php esc_html_e( 'Pro Tip:', 'wpfh-ai-alt-text' ); ?></strong> <?php esc_html_e( 'Enter your "Brand / Niche Context" on the left (e.g. "Handmade leather bags store") so the AI weaves your domain keywords into every alt description naturally.', 'wpfh-ai-alt-text' ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <hr style="margin-top:40px;border:0;border-top:1px solid #ccd0d4;" />

            <!-- PRO UPGRADE BANNER -->
            <div class="wpfh-aat-pro-card">
                <h2>🚀 <?php esc_html_e( 'Need Bulk Generation & Live Auto-Scan?', 'wpfh-ai-alt-text' ); ?></h2>
                <p>
                    <?php esc_html_e( 'With WPForgeHub AI Alt-Text PRO, you never have to click images one-by-one again:', 'wpfh-ai-alt-text' ); ?>
                </p>
                <ul class="wpfh-aat-feature-list">
                    <li>⚡ <strong><?php esc_html_e( 'Background Bulk Scanner:', 'wpfh-ai-alt-text' ); ?></strong> <?php esc_html_e( 'Scan your entire 10,000+ media library via WP-Cron with zero timeouts.', 'wpfh-ai-alt-text' ); ?></li>
                    <li>📷 <strong><?php esc_html_e( 'Instant Upload Hook:', 'wpfh-ai-alt-text' ); ?></strong> <?php esc_html_e( 'Automatically generates alt-text the exact second you upload a new photo.', 'wpfh-ai-alt-text' ); ?></li>
                    <li>🛍️ <strong><?php esc_html_e( 'WooCommerce Context:', 'wpfh-ai-alt-text' ); ?></strong> <?php esc_html_e( 'Includes product title, SKU, and categories in product image alt tags.', 'wpfh-ai-alt-text' ); ?></li>
                </ul>
                <p>
                    <a href="<?php echo esc_url( wpfh_aat_fs()->get_upgrade_url() ); ?>" class="button button-primary button-hero" target="_blank">
                        <?php esc_html_e( 'Upgrade to Pro — Just $5/mo ($39/yr)', 'wpfh-ai-alt-text' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}
