<?php
/**
 * Remove plugin data.
 *
 * @package PromoEngine
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i, %i', $wpdb->prefix . 'promo_engine_promotions', $wpdb->prefix . 'promo_engine_events' ) );
// phpcs:enable

foreach ( array( 'promo_engine_settings', 'promo_engine_schema_version', 'promo_engine_demo_ids' ) as $promo_engine_option ) {
	delete_option( $promo_engine_option );
}

delete_transient( 'promo_engine_enabled' );
