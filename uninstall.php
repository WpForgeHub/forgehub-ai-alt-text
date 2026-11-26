<?php
/**
 * AI Alt Text — Uninstaller
 *
 * Cleans up options and transients when the plugin is deleted.
 *
 * @package WPForgeHub\AIAltText
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete options
delete_option( 'wpfh_aat_settings' );
delete_site_option( 'wpfh_aat_settings' );
delete_option( 'wpfh_aat_review_nudge' );
delete_site_option( 'wpfh_aat_review_nudge' );
delete_option( 'wpfh_aat_review_nudge' );
delete_site_option( 'wpfh_aat_review_nudge' );

// Delete transients
delete_transient( 'wpfh_aat_bulk_queue' );
delete_site_transient( 'wpfh_aat_bulk_queue' );
