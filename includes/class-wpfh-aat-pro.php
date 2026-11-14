<?php
/**
 * WPForgeHub AI Alt-Text — PRO Modules
 *
 * This file is stripped from the free WordPress.org distribution by the Freemius preprocessor.
 *
 * @package WPForgeHub\AIAltText\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_AAT_Pro {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_pro_menu' ] );
        add_action( 'add_attachment', [ __CLASS__, 'auto_generate_on_upload' ] );
        add_action( 'wp_ajax_wpfh_aat_bulk_process', [ __CLASS__, 'ajax_bulk_process' ] );
    }

    /**
     * Add Bulk Generation page under Media menu
     */
    public static function add_pro_menu() {
        add_media_page(
            __( 'Bulk AI Alt-Text (PRO)', 'wpfh-ai-alt-text' ),
            __( 'Bulk AI Alt-Text', 'wpfh-ai-alt-text' ),
            'manage_options',
            'wpfh-aat-bulk',
            [ __CLASS__, 'render_bulk_page' ]
        );
    }

    /**
     * Hook: Automatically generate alt-text when an image is uploaded.
     */
    public static function auto_generate_on_upload( $attachment_id ) {
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        // Avoid duplicate runs
        if ( get_post_meta( $attachment_id, '_wpfh_aat_processed', true ) ) {
            return;
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return;
        }

        $settings = WPFH_AAT_Admin::get_settings();
        if ( empty( $settings['api_key'] ) && 'custom' !== $settings['provider'] ) {
            return;
        }

        $prompt = sprintf(
            'Generate a concise, SEO-optimized alt text and title for this image. Language: %s. Brand context: %s. Return valid JSON: {"alt_text": "...", "title": "..."}',
            esc_html( $settings['language'] ),
            esc_html( $settings['brand_context'] )
        );

        $result = WPFH_AI_Client::analyze_image( $settings, $file_path, $prompt, $settings['language'] );

        if ( $result['success'] ) {
            $alt_text = sanitize_text_field( $result['data']['alt_text'] ?? '' );
            $title    = sanitize_text_field( $result['data']['title'] ?? '' );

            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
            update_post_meta( $attachment_id, '_wpfh_aat_processed', time() );

            if ( ! empty( $settings['auto_title'] ) && ! empty( $title ) ) {
                wp_update_post( [
                    'ID'         => $attachment_id,
                    'post_title' => $title,
                ] );
            }
        }
    }

    /**
     * AJAX Batch Processor for Bulk Screen.
     */
    public static function ajax_bulk_process() {
        check_ajax_referer( 'wpfh_aat_nonce', 'security' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wpfh-ai-alt-text' ) ] );
        }

        $batch_size = 5;
        $offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

        // Query images missing alt-text
        $query = new WP_Query( [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_wp_attachment_image_alt',
                    'value'   => '',
                    'compare' => '=',
                ],
            ],
        ] );

        $total_remaining = $query->found_posts;
        $processed       = 0;
        $settings        = WPFH_AAT_Admin::get_settings();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $att_id    = get_the_ID();
                $file_path = get_attached_file( $att_id );

                if ( $file_path && file_exists( $file_path ) ) {
                    $prompt = sprintf(
                        'Generate WCAG alt text. Language: %s. Brand: %s. Return valid JSON: {"alt_text": "...", "title": "..."}',
                        esc_html( $settings['language'] ),
                        esc_html( $settings['brand_context'] )
                    );

                    $res = WPFH_AI_Client::analyze_image( $settings, $file_path, $prompt, $settings['language'] );
                    if ( $res['success'] ) {
                        update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( $res['data']['alt_text'] ?? '' ) );
                    }
                }
                $processed++;
            }
            wp_reset_postdata();
        }

        wp_send_json_success( [
            'processed'       => $processed,
            'remaining'       => max( 0, $total_remaining - $processed ),
            'done'            => $processed === 0,
        ] );
    }

    /**
     * Render Bulk Generator Page.
     */
    public static function render_bulk_page() {
        // Count total images without alt-text
        $query = new WP_Query( [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_wp_attachment_image_alt',
                    'value'   => '',
                    'compare' => '=',
                ],
            ],
        ] );

        $missing_count = count( $query->posts );
        ?>
        <div class="wrap wpfh-aat-wrap">
            <h1><?php esc_html_e( 'Bulk AI Alt-Text Generator (PRO)', 'wpfh-ai-alt-text' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Batch process all images in your library with missing alt tags.', 'wpfh-ai-alt-text' ); ?>
            </p>

            <div class="wpfh-aat-bulk-box">
                <h2><?php printf( esc_html__( 'Images needing Alt-Text: %d', 'wpfh-ai-alt-text' ), absint( $missing_count ) ); ?></h2>
                <div class="wpfh-aat-progress-bar" style="display:none;background:#e2e8f0;border-radius:9999px;height:24px;overflow:hidden;margin:20px 0;">
                    <div class="wpfh-aat-progress-fill" style="background:#2563eb;height:100%;width:0%;transition:width 0.3s ease;"></div>
                </div>
                <p>
                    <button type="button" class="button button-primary button-hero" id="wpfh-aat-start-bulk" <?php disabled( $missing_count === 0 ); ?>>
                        <?php esc_html_e( 'Start Bulk Optimization', 'wpfh-ai-alt-text' ); ?>
                    </button>
                </p>
                <div id="wpfh-aat-bulk-log" style="margin-top:20px;font-family:monospace;font-size:12px;color:#475569;"></div>
            </div>
        </div>
        <?php
    }
}
