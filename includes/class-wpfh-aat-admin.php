<?php
/**
 * AI Alt Text — Admin & Media Integration
 *
 * @package WPForgeHub\AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_AAT_Admin {

    /**
     * Value-moment review nudge instance.
     *
     * @var WPFH_Review_Nudge|null
     */
    private static $review_nudge = null;

    /**
     * Get or initialize the Value-Moment review nudge component.
     *
     * @return WPFH_Review_Nudge|null
     */
    public static function get_review_nudge() {
        if ( null === self::$review_nudge && class_exists( 'WPFH_Review_Nudge' ) ) {
            self::$review_nudge = new WPFH_Review_Nudge( [
                'plugin_slug'   => 'forgehub-ai-alt-text',
                'plugin_name'   => 'ForgeHub AI Alt Text',
                'option_prefix' => 'wpfh_aat',
                'github_url'    => 'https://github.com/WpForgeHub/forgehub-ai-alt-text',
                'min_actions'   => 5,
                'min_days'      => 7,
                'trigger_mode'  => 'or',
                'screens'       => [ 'settings_page_forgehub-ai-alt-text', 'upload' ],
                'strings'       => [
                    'heading'     => __( 'Enjoying ForgeHub AI Alt Text?', 'forgehub-ai-alt-text' ),
                    'message'     => __( 'If ForgeHub AI Alt Text has saved you time writing alt text, a quick 5-star review helps other site owners discover it and keeps development active.', 'forgehub-ai-alt-text' ),
                    'rate_cta'    => __( 'Rate 5 Stars on WordPress.org', 'forgehub-ai-alt-text' ),
                    'github_cta'  => __( 'Star on GitHub', 'forgehub-ai-alt-text' ),
                    'later_cta'   => __( 'Maybe later', 'forgehub-ai-alt-text' ),
                    'dismiss_cta' => __( 'Already did / Dismiss', 'forgehub-ai-alt-text' ),
                ],
            ] );
        }
        return self::$review_nudge;
    }

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Media Library Integration
        add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_media_modal_button' ], 10, 2 );
        add_action( 'wp_ajax_wpfh_aat_generate_single', [ __CLASS__, 'ajax_generate_single' ] );

        // Pro Feature: Instant Auto-Generate on Upload Hook
        add_action( 'add_attachment', [ __CLASS__, 'handle_auto_generate_on_upload' ] );

        // Bulk Scanner AJAX Endpoints (Free up to 20 images/run, Pro unlimited)
        add_action( 'wp_ajax_wpfh_aat_bulk_get_stats', [ __CLASS__, 'ajax_bulk_get_stats' ] );
        add_action( 'wp_ajax_wpfh_aat_bulk_process_batch', [ __CLASS__, 'ajax_bulk_process_batch' ] );

        // Value-Moment Review & GitHub Star Nudge (Day-0 silent, one-click permanent dismiss)
        $nudge = self::get_review_nudge();
        if ( $nudge ) {
            $nudge->register();
        }
    }

    /**
     * Add settings page under WordPress Settings menu.
     */
    public static function add_settings_menu() {
        add_options_page(
            __( 'AI Alt-Text Settings', 'forgehub-ai-alt-text' ),
            __( 'AI Alt-Text (BYOK)', 'forgehub-ai-alt-text' ),
            'manage_options',
            'forgehub-ai-alt-text',
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
            'provider'             => 'gemini',
            'api_key'              => '',
            'model'                => 'gemini-1.5-flash',
            'custom_endpoint'      => '',
            'language'             => 'en',
            'generate_alt'         => 1,
            'generate_caption'     => 0,
            'generate_title'       => 1,
            'generate_desc'        => 0,
            'overwrite_existing'   => 1,
            'brand_context'        => '',
            'auto_generate_upload' => 0, // Pro: Auto-run on media upload
            'enable_woo_context'   => 1, // Pro: Auto-enrich with WooCommerce product context
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
        $clean['model']                = $model;
        $clean['custom_endpoint']      = esc_url_raw( trim( $input['custom_endpoint'] ?? '' ) );
        $clean['language']             = sanitize_key( $input['language'] ?? 'en' );
        $clean['generate_alt']         = ! empty( $input['generate_alt'] ) ? 1 : 0;
        $clean['generate_caption']     = ! empty( $input['generate_caption'] ) ? 1 : 0;
        $clean['generate_title']       = ! empty( $input['generate_title'] ) ? 1 : ( ! empty( $input['auto_title'] ) ? 1 : 0 );
        $clean['generate_desc']        = ! empty( $input['generate_desc'] ) ? 1 : 0;
        $clean['overwrite_existing']   = ! empty( $input['overwrite_existing'] ) ? 1 : 0;
        $clean['brand_context']        = sanitize_text_field( $input['brand_context'] ?? '' );
        $clean['auto_generate_upload'] = ! empty( $input['auto_generate_upload'] ) ? 1 : 0;
        $clean['enable_woo_context']   = ! empty( $input['enable_woo_context'] ) ? 1 : 0;
        return $clean;
    }

    /**
     * Enqueue CSS and JS in WordPress admin.
     */
    public static function enqueue_assets( $hook ) {
        $allowed_screens = [ 'settings_page_forgehub-ai-alt-text', 'upload.php', 'post.php', 'post-new.php', 'page.php', 'page-new.php' ];
        if ( ! in_array( $hook, $allowed_screens, true ) && ! wp_attachment_is_image() ) {
            return;
        }

        $css_ver = file_exists( WPFH_AAT_PATH . 'assets/css/admin.css' ) ? filemtime( WPFH_AAT_PATH . 'assets/css/admin.css' ) : WPFH_AAT_VERSION;
        $js_ver  = file_exists( WPFH_AAT_PATH . 'assets/js/admin.js' ) ? filemtime( WPFH_AAT_PATH . 'assets/js/admin.js' ) : WPFH_AAT_VERSION;

        wp_enqueue_style(
            'wpfh-aat-admin-css',
            WPFH_AAT_URL . 'assets/css/admin.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'wpfh-aat-admin-js',
            WPFH_AAT_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            $js_ver,
            true
        );

        $settings = self::get_settings();

        wp_localize_script( 'wpfh-aat-admin-js', 'wpfhAatData', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'wpfh_aat_nonce' ),
            'providers'        => WPFH_AI_Client::get_providers(),
            'current_provider' => $settings['provider'],
            'current_model'    => $settings['model'],
            'is_pro'           => wpfh_aat_fs()->can_use_premium_code(),
            'i18n'             => [
                'generating'   => __( 'Analyzing image...', 'forgehub-ai-alt-text' ),
                'success'      => __( 'Alt-Text generated!', 'forgehub-ai-alt-text' ),
                'error'        => __( 'Failed to generate.', 'forgehub-ai-alt-text' ),
                'scanning'     => __( 'Scanning media library...', 'forgehub-ai-alt-text' ),
                'bulk_done'    => __( 'Bulk generation complete!', 'forgehub-ai-alt-text' ),
            ],
        ] );
    }

    /**
     * Add "Generate with AI" button and per-image field checkboxes into Media Library sidebar.
     */
    public static function add_media_modal_button( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }

        $settings = self::get_settings();

        $alt_checked   = ! empty( $settings['generate_alt'] ) ? 'checked="checked"' : '';
        $title_checked = ! empty( $settings['generate_title'] ) ? 'checked="checked"' : '';
        $capt_checked  = ! empty( $settings['generate_caption'] ) ? 'checked="checked"' : '';
        $desc_checked  = ! empty( $settings['generate_desc'] ) ? 'checked="checked"' : '';
        $over_checked  = ! empty( $settings['overwrite_existing'] ) ? 'checked="checked"' : '';

        $form_fields['wpfh_aat_button'] = [
            'label' => __( 'AI Metadata', 'forgehub-ai-alt-text' ),
            'input' => 'html',
            'html'  => sprintf(
                '<div class="wpfh-aat-media-box" data-attachment-id="%1$d" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:12px;margin:4px 0 10px 0;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#475569;letter-spacing:0.04em;margin-bottom:8px;">
                        %2$s
                    </div>
                    <div class="wpfh-aat-media-toggles" style="display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px;margin-bottom:10px;">
                        <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:#0f172a;font-weight:600;">
                            <input type="checkbox" class="wpfh-field-alt" value="1" %3$s /> Alt
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:#0f172a;font-weight:600;">
                            <input type="checkbox" class="wpfh-field-title" value="1" %4$s /> Title
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:#0f172a;font-weight:600;">
                            <input type="checkbox" class="wpfh-field-caption" value="1" %5$s /> Caption
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:#0f172a;font-weight:600;">
                            <input type="checkbox" class="wpfh-field-desc" value="1" %6$s /> Desc
                        </label>
                    </div>
                    <div style="margin-bottom:10px;font-size:11px;color:#64748b;border-top:1px dashed #cbd5e1;padding-top:6px;">
                        <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
                            <input type="checkbox" class="wpfh-field-overwrite" value="1" %7$s /> %8$s
                        </label>
                    </div>
                    <div class="wpfh-aat-media-action" style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
                        <button type="button" class="button button-primary wpfh-aat-btn" data-attachment-id="%1$d" style="font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                            <span class="dashicons dashicons-superhero" style="font-size:16px;line-height:1.4;margin:0;"></span>
                            %9$s
                        </button>
                        <span class="wpfh-aat-status" style="font-size:12px;font-weight:600;"></span>
                    </div>
                </div>',
                $post->ID,
                esc_html__( 'Select Fields to Generate', 'forgehub-ai-alt-text' ),
                $alt_checked,
                $title_checked,
                $capt_checked,
                $desc_checked,
                $over_checked,
                esc_html__( 'Overwrite existing values', 'forgehub-ai-alt-text' ),
                esc_html__( 'Generate with AI', 'forgehub-ai-alt-text' )
            ),
        ];

        return $form_fields;
    }

    /**
     * Core AI Processing Engine for any Attachment.
     * Reusable across Single AJAX, Auto-Upload Hook, and Background Bulk Scanner.
     *
     * @param int   $attachment_id
     * @param array $enabled_fields
     * @param bool  $overwrite
     * @return array
     */
    public static function process_attachment_ai( $attachment_id, $enabled_fields = [], $overwrite = true ) {
        if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
            return [ 'success' => false, 'error' => __( 'Invalid image ID.', 'forgehub-ai-alt-text' ) ];
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return [ 'success' => false, 'error' => __( 'Image file not found on disk.', 'forgehub-ai-alt-text' ) ];
        }

        $settings = self::get_settings();
        if ( empty( $settings['api_key'] ) && 'custom' !== $settings['provider'] ) {
            return [ 'success' => false, 'error' => __( 'API key missing in settings.', 'forgehub-ai-alt-text' ) ];
        }

        if ( empty( $enabled_fields ) ) {
            $enabled_fields = [ 'alt_text' ];
        }

        // Pro Feature: WooCommerce Context Detection
        $woo_context = '';
        if ( wpfh_aat_fs()->can_use_premium_code() && ! empty( $settings['enable_woo_context'] ) && class_exists( 'WooCommerce' ) ) {
            $parent_id = wp_get_post_parent_id( $attachment_id );
            if ( ! $parent_id ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off lookup of the product that uses this attachment.
                $parent_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
                    $attachment_id
                ) );
            }

            if ( $parent_id && 'product' === get_post_type( $parent_id ) ) {
                $product = wc_get_product( $parent_id );
                if ( $product ) {
                    $sku = $product->get_sku();
                    $cats = wc_get_product_category_list( $parent_id, ', ' );
                    $woo_context = sprintf(
                        ' [WooCommerce Product: "%s" (SKU: %s, Category: %s). Weave these commercial product keywords naturally into the alt-text.]',
                        $product->get_name(),
                        $sku ? $sku : 'N/A',
                        $cats ? wp_strip_all_tags( $cats ) : 'General'
                    );
                }
            }
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
            'Analyze this image and generate: %s. Output language: %s. Brand context: %s.%s Return valid JSON only in this format: {%s}',
            implode( ', ', $instructions ),
            esc_html( $settings['language'] ),
            esc_html( $settings['brand_context'] ),
            $woo_context,
            implode( ', ', $json_keys )
        );

        $result = WPFH_AI_Client::analyze_image( $settings, $file_path, $prompt, $settings['language'] );
        if ( ! $result['success'] ) {
            $nudge = self::get_review_nudge();
            if ( $nudge ) {
                $nudge->record_error();
            }
            return $result;
        }

        $data         = $result['data'] ?? [];
        $current_post = get_post( $attachment_id );
        $current_alt  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

        $post_updates  = [];
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

        if ( ! empty( $post_updates ) ) {
            $post_updates['ID'] = $attachment_id;
            wp_update_post( $post_updates );
        }

        if ( ! empty( $response_data['updated'] ) ) {
            $nudge = self::get_review_nudge();
            if ( $nudge ) {
                $nudge->record_success();
            }
        }

        return [ 'success' => true, 'data' => $response_data ];
    }

    /**
     * AJAX handler for single image generation in Media Modal.
     */
    public static function ajax_generate_single() {
        if ( ! check_ajax_referer( 'wpfh_aat_nonce', 'security', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security token expired. Please refresh the page.', 'forgehub-ai-alt-text' ) ] );
        }

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'forgehub-ai-alt-text' ) ] );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied for this attachment.', 'forgehub-ai-alt-text' ) ] );
        }
        $settings      = self::get_settings();

        if ( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ) {
            $allowed = [ 'alt_text', 'title', 'caption', 'description' ];
            $enabled_fields = array_values( array_intersect( $allowed, array_map( 'sanitize_key', $_POST['fields'] ) ) );
        } else {
            $enabled_fields = [];
            if ( ! empty( $settings['generate_alt'] ) )     $enabled_fields[] = 'alt_text';
            if ( ! empty( $settings['generate_title'] ) )   $enabled_fields[] = 'title';
            if ( ! empty( $settings['generate_caption'] ) ) $enabled_fields[] = 'caption';
            if ( ! empty( $settings['generate_desc'] ) )    $enabled_fields[] = 'description';
        }

        $raw_over  = isset( $_POST['overwrite'] ) ? sanitize_text_field( wp_unslash( $_POST['overwrite'] ) ) : null;
        $overwrite = false;
        if ( null !== $raw_over ) {
            $overwrite = ( '1' === $raw_over || 1 === $raw_over || true === $raw_over || 'true' === $raw_over );
        } else {
            $overwrite = ! empty( $settings['overwrite_existing'] );
        }

        $result = self::process_attachment_ai( $attachment_id, $enabled_fields, $overwrite );
        if ( $result['success'] ) {
            if ( class_exists( 'WPFH_Review_Nudge' ) ) {
                WPFH_Review_Nudge::track_success( 'wpfh_aat', 1 );
            }
            wp_send_json_success( $result['data'] );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }
    }

    /**
     * Pro Feature: Automatic Generation on Media Upload Hook.
     */
    public static function handle_auto_generate_on_upload( $attachment_id ) {
        $settings = self::get_settings();

        // Gated: Pro Plan only
        if ( ! wpfh_aat_fs()->can_use_premium_code() || empty( $settings['auto_generate_upload'] ) ) {
            return;
        }

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        $enabled_fields = [];
        if ( ! empty( $settings['generate_alt'] ) )     $enabled_fields[] = 'alt_text';
        if ( ! empty( $settings['generate_title'] ) )   $enabled_fields[] = 'title';
        if ( ! empty( $settings['generate_caption'] ) ) $enabled_fields[] = 'caption';
        if ( ! empty( $settings['generate_desc'] ) )    $enabled_fields[] = 'description';

        self::process_attachment_ai( $attachment_id, $enabled_fields, (bool) $settings['overwrite_existing'] );
    }

    /**
     * Pro Feature: Bulk Scanner Stats Endpoint.
     */
    public static function ajax_bulk_get_stats() {
        check_ajax_referer( 'wpfh_aat_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        global $wpdb;
        // Count only vision-supported raster image formats (exclude SVG, GIF, ICO, etc.).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live library statistics.
        $total_images = (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg', 'image/jpg', 'image/png', 'image/webp')"
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live library statistics.
        $missing_alt  = (int) $wpdb->get_var(
            "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg', 'image/jpg', 'image/png', 'image/webp')
            AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        wp_send_json_success( [
            'total_images' => $total_images,
            'missing_alt'  => $missing_alt,
            'completed'    => $total_images - $missing_alt,
            'is_pro'       => wpfh_aat_fs()->can_use_premium_code(),
        ] );
    }

    /**
     * Pro Feature: Bulk Scanner Batch Processing Endpoint.
     */
    public static function ajax_bulk_process_batch() {
        check_ajax_referer( 'wpfh_aat_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ] );
        }

        // Free allows up to 20 images per bulk run; Pro runs unlimited background queues.
        $is_pro            = wpfh_aat_fs()->can_use_premium_code();
        $session_processed = isset( $_POST['session_processed'] ) ? absint( $_POST['session_processed'] ) : 0;
        $free_run_limit    = 20;

        global $wpdb;
        $batch_size = 5;
        if ( ! $is_pro ) {
            $remaining_free = max( 0, $free_run_limit - $session_processed );
            if ( 0 === $remaining_free ) {
                wp_send_json_success( [
                    'batch_processed' => 0,
                    'batch_failed'    => 0,
                    'last_id'         => isset( $_POST['last_id'] ) ? absint( $_POST['last_id'] ) : 0,
                    'remaining'       => 0,
                    'free_cap_hit'    => true,
                    'is_finished'     => true,
                ] );
            }
            $batch_size = min( $batch_size, $remaining_free );
        }
        $last_id    = isset( $_POST['last_id'] ) ? absint( $_POST['last_id'] ) : 0;
        $settings   = self::get_settings();

        // Honor the admin's configured field toggles (always include alt_text as baseline for bulk).
        $enabled_fields = [ 'alt_text' ];
        if ( ! empty( $settings['generate_title'] ) ) {
            $enabled_fields[] = 'title';
        }
        if ( ! empty( $settings['generate_caption'] ) ) {
            $enabled_fields[] = 'caption';
        }
        if ( ! empty( $settings['generate_desc'] ) ) {
            $enabled_fields[] = 'description';
        }

        // Cursor-based pagination over vision-supported images missing alt text so failed items never loop infinitely.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- batch queue of images without alt text.
        $image_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type IN ('image/jpeg', 'image/jpg', 'image/png', 'image/webp')
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            AND p.ID > %d
            ORDER BY p.ID ASC
            LIMIT %d
        ", $last_id, $batch_size ) );

        $processed  = 0;
        $failed     = 0;
        $new_last   = $last_id;
        foreach ( $image_ids as $id ) {
            $int_id   = (int) $id;
            $new_last = max( $new_last, $int_id );
            $res      = self::process_attachment_ai( $int_id, $enabled_fields, false );
            if ( $res['success'] ) {
                $processed++;
            } else {
                $failed++;
            }
        }

        // Count remaining images strictly after the cursor.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live progress counter.
        $remaining = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type IN ('image/jpeg', 'image/jpg', 'image/png', 'image/webp')
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            AND p.ID > %d
        ", $new_last ) );

        if ( $processed > 0 && class_exists( 'WPFH_Review_Nudge' ) ) {
            WPFH_Review_Nudge::track_success( 'wpfh_aat', $processed );
        }

        $reached_free_cap = ( ! $is_pro && ( $session_processed + $processed + $failed ) >= $free_run_limit );

        wp_send_json_success( [
            'batch_processed' => $processed,
            'batch_failed'    => $failed,
            'last_id'         => $new_last,
            'remaining'       => $remaining,
            'is_finished'     => ( empty( $image_ids ) || 0 === $remaining || $reached_free_cap ),
            'free_cap_hit'    => ( $reached_free_cap && $remaining > 0 ),
        ] );
    }

    /**
     * Render Settings Page.
     */
    public static function render_settings_page() {
        $settings  = self::get_settings();
        $providers = WPFH_AI_Client::get_providers();
        $is_pro    = wpfh_aat_fs()->can_use_premium_code();
        ?>
        <div class="wrap wpfh-aat-wrap">
            <h1><?php esc_html_e( 'AI Alt Text & Image SEO', 'forgehub-ai-alt-text' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Zero server bloat, 100% private BYOK (Bring Your Own Key) architecture. Your images are sent directly to your chosen AI provider without intermediary servers.', 'forgehub-ai-alt-text' ); ?>
            </p>

            <div class="wpfh-aat-settings-grid">
                <!-- Left Column: Settings Form -->
                <div class="wpfh-aat-main-col">
                    <form method="post" action="options.php" class="wpfh-aat-form">
                        <?php settings_fields( 'wpfh_aat_options_group' ); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="wpfh_provider"><?php esc_html_e( 'AI Provider', 'forgehub-ai-alt-text' ); ?></label></th>
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
                                <th scope="row"><label for="wpfh_api_key"><?php esc_html_e( 'API Key', 'forgehub-ai-alt-text' ); ?></label></th>
                                <td>
                                    <input name="wpfh_aat_settings[api_key]" type="password" id="wpfh_api_key" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" placeholder="sk-... or AIzaSy..." />
                                    <p class="description"><?php esc_html_e( 'Your private API key. Stored securely in your WordPress database.', 'forgehub-ai-alt-text' ); ?></p>
                                </td>
                            </tr>

                            <tr id="wpfh_custom_endpoint_row" style="<?php echo 'custom' === $settings['provider'] ? '' : 'display:none;'; ?>">
                                <th scope="row"><label for="wpfh_custom_endpoint"><?php esc_html_e( 'Custom Base URL', 'forgehub-ai-alt-text' ); ?></label></th>
                                <td>
                                    <input name="wpfh_aat_settings[custom_endpoint]" type="url" id="wpfh_custom_endpoint" value="<?php echo esc_attr( $settings['custom_endpoint'] ); ?>" class="regular-text" placeholder="http://localhost:11434/v1" />
                                    <p class="description"><?php esc_html_e( 'OpenAI-compatible endpoint URL for local Ollama, Groq, vLLM, etc.', 'forgehub-ai-alt-text' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wpfh_model_select"><?php esc_html_e( 'Model Selection', 'forgehub-ai-alt-text' ); ?></label></th>
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
                                                    <?php esc_html_e( '✏️ Custom Model (Type manually...)', 'forgehub-ai-alt-text' ); ?>
                                                </option>
                                            </select>

                                            <button type="button" id="wpfh_toggle_manual_model" class="button button-secondary" style="<?php echo $is_custom_prov ? 'display:none;' : ''; ?>">
                                                <?php esc_html_e( 'Type Manually', 'forgehub-ai-alt-text' ); ?>
                                            </button>
                                        </div>

                                        <div id="wpfh_manual_model_wrap" style="margin-top:8px;<?php echo $show_manual ? '' : 'display:none;'; ?>">
                                            <input name="wpfh_aat_settings[model]" type="text" id="wpfh_model_input" value="<?php echo esc_attr( $saved_model ); ?>" class="regular-text" placeholder="e.g. gpt-4o, gemini-2.5-flash, claude-sonnet-4-20250514" />
                                        </div>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e( 'Flash models are ultra-fast and cost-effective; Pro models provide maximum reasoning depth.', 'forgehub-ai-alt-text' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wpfh_language"><?php esc_html_e( 'Target Language', 'forgehub-ai-alt-text' ); ?></label></th>
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
                                <th scope="row"><label for="wpfh_brand_context"><?php esc_html_e( 'Brand / Niche Context', 'forgehub-ai-alt-text' ); ?></label></th>
                                <td>
                                    <input name="wpfh_aat_settings[brand_context]" type="text" id="wpfh_brand_context" value="<?php echo esc_attr( $settings['brand_context'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. WordPress plugin store, tech blog, WooCommerce shop...', 'forgehub-ai-alt-text' ); ?>" />
                                    <p class="description"><?php esc_html_e( 'Guides the AI to use relevant domain keywords and tone.', 'forgehub-ai-alt-text' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Fields to Generate', 'forgehub-ai-alt-text' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="wpfh_generate_alt" style="display:block;margin-bottom:8px;">
                                            <input name="wpfh_aat_settings[generate_alt]" type="checkbox" id="wpfh_generate_alt" value="1" <?php checked( $settings['generate_alt'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Alternative Text (Alt Text)', 'forgehub-ai-alt-text' ); ?></strong>
                                        </label>

                                        <label for="wpfh_generate_title" style="display:block;margin-bottom:8px;">
                                            <input name="wpfh_aat_settings[generate_title]" type="checkbox" id="wpfh_generate_title" value="1" <?php checked( $settings['generate_title'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Image Title', 'forgehub-ai-alt-text' ); ?></strong>
                                        </label>

                                        <label for="wpfh_generate_caption" style="display:block;margin-bottom:8px;">
                                            <input name="wpfh_aat_settings[generate_caption]" type="checkbox" id="wpfh_generate_caption" value="1" <?php checked( $settings['generate_caption'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Caption', 'forgehub-ai-alt-text' ); ?></strong>
                                        </label>

                                        <label for="wpfh_generate_desc" style="display:block;">
                                            <input name="wpfh_aat_settings[generate_desc]" type="checkbox" id="wpfh_generate_desc" value="1" <?php checked( $settings['generate_desc'], 1 ); ?> />
                                            <strong><?php esc_html_e( 'Description', 'forgehub-ai-alt-text' ); ?></strong>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e( 'Overwrite Policy', 'forgehub-ai-alt-text' ); ?></th>
                                <td>
                                    <label for="wpfh_overwrite_existing">
                                        <input name="wpfh_aat_settings[overwrite_existing]" type="checkbox" id="wpfh_overwrite_existing" value="1" <?php checked( $settings['overwrite_existing'], 1 ); ?> />
                                        <strong><?php esc_html_e( 'Overwrite existing values', 'forgehub-ai-alt-text' ); ?></strong>
                                    </label>
                                </td>
                            </tr>

                            <?php if ( $is_pro ) : ?>
                            <!-- PRO AUTOMATION SECTION -->
                            <tr style="border-top:1px solid #e2e8f0;">
                                <th scope="row">
                                    <?php esc_html_e( 'Automation & WooCommerce', 'forgehub-ai-alt-text' ); ?>
                                </th>
                                <td>
                                    <label for="wpfh_auto_upload" style="display:block;margin-bottom:10px;">
                                        <input name="wpfh_aat_settings[auto_generate_upload]" type="checkbox" id="wpfh_auto_upload" value="1" <?php checked( $settings['auto_generate_upload'], 1 ); ?> />
                                        <strong>📷 <?php esc_html_e( 'Instant Auto-Generate on Upload', 'forgehub-ai-alt-text' ); ?></strong>
                                        <span class="description" style="display:block;margin-left:24px;color:#50575e;">
                                            <?php esc_html_e( 'Automatically analyzes and saves Alt Text the exact second an image is uploaded to WordPress.', 'forgehub-ai-alt-text' ); ?>
                                        </span>
                                    </label>

                                    <label for="wpfh_enable_woo" style="display:block;">
                                        <input name="wpfh_aat_settings[enable_woo_context]" type="checkbox" id="wpfh_enable_woo" value="1" <?php checked( $settings['enable_woo_context'], 1 ); ?> />
                                        <strong>🛍️ <?php esc_html_e( 'WooCommerce Product Context Injection', 'forgehub-ai-alt-text' ); ?></strong>
                                        <span class="description" style="display:block;margin-left:24px;color:#50575e;">
                                            <?php esc_html_e( 'Enriches alt text with WooCommerce product title, SKU code, and category keywords.', 'forgehub-ai-alt-text' ); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>

                        <?php submit_button(); ?>
                    </form>

                    <!-- BULK SCANNER DASHBOARD -->
                    <div class="wpfh-aat-bulk-panel" style="background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;padding:20px;margin-top:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <h3 style="margin:0;font-size:16px;color:#0f172a;">⚡ <?php esc_html_e( 'Bulk Alt-Text Scanner', 'forgehub-ai-alt-text' ); ?></h3>
                            </div>
                            <span id="wpfh-bulk-stats-label" style="font-size:13px;color:#475569;"><?php esc_html_e( 'Calculating media stats...', 'forgehub-ai-alt-text' ); ?></span>
                        </div>
                        <p style="font-size:13px;color:#475569;margin-bottom:16px;">
                            <?php
                            if ( $is_pro ) {
                                esc_html_e( 'Scans your entire media library in asynchronous chunks with zero timeouts and automatically fills missing Alt-Text.', 'forgehub-ai-alt-text' );
                            } else {
                                esc_html_e( 'Scans images missing Alt-Text in batches of up to 20 images per run with zero timeouts.', 'forgehub-ai-alt-text' );
                            }
                            ?>
                        </p>

                        <div id="wpfh-bulk-progress-wrap" style="display:none;background:#e2e8f0;border-radius:8px;overflow:hidden;height:12px;margin-bottom:14px;">
                            <div id="wpfh-bulk-progress-bar" style="width:0%;height:100%;background:#10b981;transition:width 0.3s ease;"></div>
                        </div>

                        <div style="display:flex;align-items:center;gap:12px;">
                            <button type="button" id="wpfh-start-bulk-btn" class="button button-primary">
                                🚀 <?php esc_html_e( 'Start Bulk AI Scan', 'forgehub-ai-alt-text' ); ?>
                            </button>
                            <span id="wpfh-bulk-status-msg" style="font-size:13px;font-weight:600;color:#0f172a;"></span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Quick Start Help Box -->
                <div class="wpfh-aat-sidebar-col">
                    <div class="wpfh-aat-help-box">
                        <h3>📖 <?php esc_html_e( 'How to Use This Plugin', 'forgehub-ai-alt-text' ); ?></h3>
                        <ol class="wpfh-aat-steps">
                            <li>
                                <strong><?php esc_html_e( 'Choose Provider & Model:', 'forgehub-ai-alt-text' ); ?></strong>
                                <span><?php esc_html_e( 'Select Google Gemini, DeepSeek, OpenAI, Claude, or OpenRouter and paste your API key.', 'forgehub-ai-alt-text' ); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e( 'Single Image in Media Library:', 'forgehub-ai-alt-text' ); ?></strong>
                                <span><?php esc_html_e( 'Open any image in Media Library, choose fields (Alt, Title, Caption, Desc), and click "Generate with AI".', 'forgehub-ai-alt-text' ); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e( 'Bulk Alt-Text Scanner:', 'forgehub-ai-alt-text' ); ?></strong>
                                <span><?php esc_html_e( 'Run the Bulk Alt-Text Scanner below to fill missing Alt-Text across your media library in batches.', 'forgehub-ai-alt-text' ); ?></span>
                            </li>
                        </ol>

                        <div class="wpfh-aat-sidebar-links">
                            <h4>🔗 <?php esc_html_e( 'Get Your API Keys', 'forgehub-ai-alt-text' ); ?></h4>
                            <ul>
                                <li><a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio &rarr;</a> (Gemini Flash)</li>
                                <li><a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener">DeepSeek Platform &rarr;</a> (DeepSeek Flash & V4 Pro)</li>
                                <li><a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform &rarr;</a> (GPT-4o)</li>
                                <li><a href="https://console.anthropic.com/" target="_blank" rel="noopener">Anthropic Console &rarr;</a> (Claude)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ( ! $is_pro ) : ?>
            <p class="description" style="margin-top:24px;padding-top:16px;border-top:1px solid #dcdcde;color:#50575e;">
                <?php esc_html_e( 'Need instant auto-generation on upload, WooCommerce product context, unlimited background bulk scanning, or priority support?', 'forgehub-ai-alt-text' ); ?>
                <a href="<?php echo esc_url( wpfh_aat_fs()->get_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Priority Support & Pro', 'forgehub-ai-alt-text' ); ?> &rarr;
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
