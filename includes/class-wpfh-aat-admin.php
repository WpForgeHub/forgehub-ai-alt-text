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
            'provider'           => 'gemini',
            'api_key'            => '',
            'model'              => 'gemini-1.5-flash',
            'custom_endpoint'    => '',
            'language'           => 'en',
            'generate_alt'       => 1,
            'generate_caption'   => 0,
            'generate_title'     => 0,
            'generate_desc'      => 0,
            'overwrite_existing' => 1,
            'brand_context'      => '',
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
        $clean['model']              = $model;
        $clean['custom_endpoint']    = esc_url_raw( trim( $input['custom_endpoint'] ?? '' ) );
        $clean['language']           = sanitize_key( $input['language'] ?? 'en' );
        $clean['generate_alt']       = ! empty( $input['generate_alt'] ) ? 1 : 0;
        $clean['generate_caption']   = ! empty( $input['generate_caption'] ) ? 1 : 0;
        $clean['generate_title']     = ! empty( $input['generate_title'] ) ? 1 : ( ! empty( $input['auto_title'] ) ? 1 : 0 );
        $clean['generate_desc']      = ! empty( $input['generate_desc'] ) ? 1 : 0;
        $clean['overwrite_existing'] = ! empty( $input['overwrite_existing'] ) ? 1 : 0;
        $clean['brand_context']      = sanitize_text_field( $input['brand_context'] ?? '' );
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

        // Determine which fields user wants generated
        $enabled_fields = [];
        if ( ! empty( $settings['generate_alt'] ) ) {
            $enabled_fields[] = 'alt_text';
        }
        if ( ! empty( $settings['generate_title'] ) ) {
            $enabled_fields[] = 'title';
        }
        if ( ! empty( $settings['generate_caption'] ) ) {
            $enabled_fields[] = 'caption';
        }
        if ( ! empty( $settings['generate_desc'] ) ) {
            $enabled_fields[] = 'description';
        }

        // Fallback: if user unchecked all, default to alt_text
        if ( empty( $enabled_fields ) ) {
            $enabled_fields = [ 'alt_text' ];
        }

        $instructions = [];
        $json_keys    = [];
        if ( in_array( 'alt_text', $enabled_fields, true ) ) {
            $instructions[] = 'accurate, concise WCAG-compliant alt text (maximum 125 characters)';
            $json_keys[]    = '"alt_text": "..."';
        }
        if ( in_array( 'title', $enabled_fields, true ) ) {
            $instructions[] = 'a clean human-readable image title';
            $json_keys[]    = '"title": "..."';
        }
        if ( in_array( 'caption', $enabled_fields, true ) ) {
            $instructions[] = 'a short editorial caption for readers';
            $json_keys[]    = '"caption": "..."';
        }
        if ( in_array( 'description', $enabled_fields, true ) ) {
            $instructions[] = 'a detailed description explaining visual elements and context';
            $json_keys[]    = '"description": "..."';
        }

        $prompt = sprintf(
            'Analyze this image and generate: %s. Output language: %s. Brand context: %s. Return valid JSON only in this format: {%s}',
            implode( ', ', $instructions ),
            esc_html( $settings['language'] ),
            esc_html( $settings['brand_context'] ),
            implode( ', ', $json_keys )
        );

        $result = WPFH_AI_Client::analyze_image( $settings, $file_path, $prompt, $settings['language'] );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        $data      = $result['data'] ?? [];
        $overwrite = ! empty( $settings['overwrite_existing'] );

        // Current attachment metadata
        $current_post = get_post( $attachment_id );
        $current_alt  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

        $post_updates = [];
        $response_data = [
            'alt_text'    => '',
            'title'       => '',
            'caption'     => '',
            'description' => '',
            'updated'     => [],
        ];

        // 1. Alt Text
        if ( in_array( 'alt_text', $enabled_fields, true ) && ! empty( $data['alt_text'] ) ) {
            $new_alt = sanitize_text_field( $data['alt_text'] );
            if ( $overwrite || empty( trim( $current_alt ) ) ) {
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', $new_alt );
                $response_data['alt_text'] = $new_alt;
                $response_data['updated'][] = 'alt';
            } else {
                $response_data['alt_text'] = $current_alt;
            }
        }

        // 2. Title
        if ( in_array( 'title', $enabled_fields, true ) && ! empty( $data['title'] ) ) {
            $new_title = sanitize_text_field( $data['title'] );
            if ( $overwrite || empty( trim( $current_post->post_title ) ) ) {
                $post_updates['post_title'] = $new_title;
                $response_data['title'] = $new_title;
                $response_data['updated'][] = 'title';
            } else {
                $response_data['title'] = $current_post->post_title;
            }
        }

        // 3. Caption (post_excerpt)
        if ( in_array( 'caption', $enabled_fields, true ) && ! empty( $data['caption'] ) ) {
            $new_caption = sanitize_text_field( $data['caption'] );
            if ( $overwrite || empty( trim( $current_post->post_excerpt ) ) ) {
                $post_updates['post_excerpt'] = $new_caption;
                $response_data['caption'] = $new_caption;
                $response_data['updated'][] = 'caption';
            } else {
                $response_data['caption'] = $current_post->post_excerpt;
            }
        }

        // 4. Description (post_content)
        if ( in_array( 'description', $enabled_fields, true ) && ! empty( $data['description'] ) ) {
            $new_desc = sanitize_textarea_field( $data['description'] );
            if ( $overwrite || empty( trim( $current_post->post_content ) ) ) {
                $post_updates['post_content'] = $new_desc;
                $response_data['description'] = $new_desc;
                $response_data['updated'][] = 'description';
            } else {
                $response_data['description'] = $current_post->post_content;
            }
        }

        // Save post fields if any
        if ( ! empty( $post_updates ) ) {
            $post_updates['ID'] = $attachment_id;
            wp_update_post( $post_updates );
        }

        $response_data['message'] = __( 'Metadata generated and saved successfully!', 'wpfh-ai-alt-text' );
        wp_send_json_success( $response_data );
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
                                <th scope="row"><label for="wpfh_model_select"><?php esc_html_e( 'Model Selection', 'wpfh-ai-alt-text' ); ?></label></th>
                                <td>
                                    <?php
                                    $current_provider = $settings['provider'] ?? 'gemini';
                                    $current_models   = $providers[ $current_provider ]['models'] ?? [];
                                    $saved_model      = $settings['model'] ?? '';
                                    $is_preset        = array_key_exists( $saved_model, $current_models );
                                    $is_custom_prov   = 'custom' === $current_provider;
                                    $show_manual      = $is_custom_prov || ( ! empty( $saved_model ) && ! $is_preset );
                                    ?>
                                    <div class="wpfh-model-field-wrap">
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                            <select id="wpfh_model_select" class="regular-text" style="<?php echo $is_custom_prov ? 'display:none;' : 'max-width:380px;'; ?>">
                                                <?php foreach ( $current_models as $m_key => $m_label ) : ?>
                                                    <option value="<?php echo esc_attr( $m_key ); ?>" <?php selected( $saved_model, $m_key ); ?>>
                                                        <?php echo esc_html( $m_label ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="__custom__" <?php selected( ! $is_preset && ! $is_custom_prov, true ); ?>>
                                                    <?php esc_html_e( '✏️ Custom Model (Type manually...)', 'wpfh-ai-alt-text' ); ?>
                                                </option>
                                            </select>

                                            <button type="button" id="wpfh_toggle_manual_model" class="button button-secondary" style="<?php echo $is_custom_prov ? 'display:none;' : ''; ?>">
                                                <?php echo $show_manual ? esc_html__( '📋 Choose from List', 'wpfh-ai-alt-text' ) : esc_html__( '✏️ Type Manually', 'wpfh-ai-alt-text' ); ?>
                                            </button>
                                        </div>

                                        <div id="wpfh_manual_model_wrap" style="<?php echo $show_manual ? 'margin-top:8px;' : 'display:none;margin-top:8px;'; ?>">
                                            <input name="wpfh_aat_settings[model]" type="text" id="wpfh_model_input" value="<?php echo esc_attr( $saved_model ); ?>" class="regular-text" placeholder="e.g. deepseek-flash, gemini-1.5-flash, llama-3.3-70b" />
                                            <p class="description" style="margin-top:4px;">
                                                <?php esc_html_e( 'Enter any model identifier or fine-tune supported by your chosen provider.', 'wpfh-ai-alt-text' ); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <p class="description" id="wpfh_model_help">
                                        <?php esc_html_e( 'Flash models are ultra-fast and cost-effective; Pro models provide maximum reasoning depth.', 'wpfh-ai-alt-text' ); ?>
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
                                <th scope="row"><?php esc_html_e( 'Fields to Generate', 'wpfh-ai-alt-text' ); ?></th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text"><span><?php esc_html_e( 'Fields to Generate', 'wpfh-ai-alt-text' ); ?></span></legend>
                                        
                                        <label for="wpfh_generate_alt" style="display:block;margin-bottom:8px;">
                                            <input name="wpfh_aat_settings[generate_alt]" type="checkbox" id="wpfh_generate_alt" value="1" <?php checked( $settings['generate_alt'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Alternative Text (Alt Text)', 'wpfh-ai-alt-text' ); ?></strong>
                                            <span class="description" style="display:block;margin-left:24px;color:#64748b;">
                                                <?php esc_html_e( 'Essential for web accessibility (WCAG 2.1) and Google Image Search SEO ranking.', 'wpfh-ai-alt-text' ); ?>
                                            </span>
                                        </label>

                                        <label for="wpfh_generate_title" style="display:block;margin-bottom:8px;">
                                            <input name="wpfh_aat_settings[generate_title]" type="checkbox" id="wpfh_generate_title" value="1" <?php checked( $settings['generate_title'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Image Title', 'wpfh-ai-alt-text' ); ?></strong>
                                            <span class="description" style="display:block;margin-left:24px;color:#64748b;">
                                                <?php esc_html_e( 'Clean, readable image title replacing camera raw names (e.g. IMG_4092.jpg).', 'wpfh-ai-alt-text' ); ?>
                                            </span>
                                        </label>

                                        <label for="wpfh_generate_caption" style="display:block;margin-bottom:8px;">
                                            <input name="wpfh_aat_settings[generate_caption]" type="checkbox" id="wpfh_generate_caption" value="1" <?php checked( $settings['generate_caption'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Caption', 'wpfh-ai-alt-text' ); ?></strong>
                                            <span class="description" style="display:block;margin-left:24px;color:#64748b;">
                                                <?php esc_html_e( 'Short editorial caption displayed below images in blog posts.', 'wpfh-ai-alt-text' ); ?>
                                            </span>
                                        </label>

                                        <label for="wpfh_generate_desc" style="display:block;">
                                            <input name="wpfh_aat_settings[generate_desc]" type="checkbox" id="wpfh_generate_desc" value="1" <?php checked( $settings['generate_desc'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Description', 'wpfh-ai-alt-text' ); ?></strong>
                                            <span class="description" style="display:block;margin-left:24px;color:#64748b;">
                                                <?php esc_html_e( 'Detailed visual explanation saved in attachment content for rich context.', 'wpfh-ai-alt-text' ); ?>
                                            </span>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Overwrite Policy', 'wpfh-ai-alt-text' ); ?></th>
                                <td>
                                    <label for="wpfh_overwrite_existing">
                                        <input name="wpfh_aat_settings[overwrite_existing]" type="checkbox" id="wpfh_overwrite_existing" value="1" <?php checked( $settings['overwrite_existing'], 1 ); ?> />
                                        <strong><?php esc_html_e( 'Overwrite existing values', 'wpfh-ai-alt-text' ); ?></strong>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'When unchecked, any field that already contains text (e.g. a manual caption or custom title) is preserved and never overwritten.', 'wpfh-ai-alt-text' ); ?>
                                    </p>
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
                                <span><?php esc_html_e( 'The AI inspects your image, generates your chosen fields (Alt-Text, Title, Caption, or Description), and saves them to WordPress metadata immediately.', 'wpfh-ai-alt-text' ); ?></span>
                            </li>
                        </ol>

                        <div class="wpfh-aat-help-links">
                            <h4>🔑 <?php esc_html_e( 'Get Free / Direct API Keys:', 'wpfh-ai-alt-text' ); ?></h4>
                            <ul>
                                <li><a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio &rarr;</a> (Gemini 1.5 Flash has a free tier)</li>
                                <li><a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener">DeepSeek Platform &rarr;</a> (DeepSeek Flash Vision & V4 Pro)</li>
                                <li><a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform &rarr;</a> (GPT-4o Mini)</li>
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
