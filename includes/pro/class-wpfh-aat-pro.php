<?php
/**
 * AI Alt Text — Pro Features (Auto-Upload, WooCommerce Context, Unlimited Bulk)
 *
 * @package WPForgeHub\AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_AAT_Pro {

	/**
	 * Register Pro hooks.
	 */
	public static function init() {
		add_filter( 'wpfh_aat_is_pro', [ __CLASS__, 'is_active' ] );
		add_filter( 'wpfh_aat_upgrade_url', [ __CLASS__, 'get_upgrade_url' ] );
		add_action( 'add_attachment', [ __CLASS__, 'handle_auto_generate_on_upload' ] );
		add_filter( 'wpfh_aat_prompt_context', [ __CLASS__, 'append_woo_context' ], 10, 3 );
		add_action( 'wpfh_aat_settings_pro_rows', [ __CLASS__, 'render_pro_settings_rows' ] );
	}

	/**
	 * Check whether Pro license is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return function_exists( 'wpfh_aat_fs' ) && wpfh_aat_fs()->can_use_premium_code();
	}

	/**
	 * Return canonical Freemius upgrade URL.
	 *
	 * @param string $url Default URL.
	 * @return string
	 */
	public static function get_upgrade_url( $url ) {
		if ( function_exists( 'wpfh_aat_fs' ) ) {
			return wpfh_aat_fs()->get_upgrade_url();
		}
		return $url;
	}

	/**
	 * Pro Feature: Automatic Generation on Media Upload Hook.
	 *
	 * @param int $attachment_id Uploaded attachment ID.
	 */
	public static function handle_auto_generate_on_upload( $attachment_id ) {
		$settings = WPFH_AAT_Admin::get_settings();

		if ( ! self::is_active() || empty( $settings['auto_generate_upload'] ) ) {
			return;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

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

		WPFH_AAT_Admin::process_attachment_ai( $attachment_id, $enabled_fields, (bool) $settings['overwrite_existing'] );
	}

	/**
	 * Pro Feature: Enrich AI prompt with WooCommerce product title, SKU, and category.
	 *
	 * @param string $context       Existing prompt context.
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $settings      Plugin settings.
	 * @return string
	 */
	public static function append_woo_context( $context, $attachment_id, $settings ) {
		if ( ! self::is_active() || empty( $settings['enable_woo_context'] ) || ! class_exists( 'WooCommerce' ) ) {
			return $context;
		}

		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( ! $parent_id ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off lookup of the product that uses this attachment.
			$parent_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
					$attachment_id
				)
			);
		}

		if ( $parent_id && 'product' === get_post_type( $parent_id ) ) {
			$product = wc_get_product( $parent_id );
			if ( $product ) {
				$sku  = $product->get_sku();
				$cats = wc_get_product_category_list( $parent_id, ', ' );
				$context .= sprintf(
					' [WooCommerce Product: "%s" (SKU: %s, Category: %s). Weave these commercial product keywords naturally into the alt-text.]',
					$product->get_name(),
					$sku ? $sku : 'N/A',
					$cats ? wp_strip_all_tags( $cats ) : 'General'
				);
			}
		}

		return $context;
	}

	/**
	 * Render Pro automation & WooCommerce settings rows.
	 *
	 * @param array $settings Plugin settings.
	 */
	public static function render_pro_settings_rows( $settings ) {
		if ( ! self::is_active() ) {
			return;
		}
		?>
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
		<?php
	}
}
