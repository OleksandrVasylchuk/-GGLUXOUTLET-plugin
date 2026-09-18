<?php
/**
 * Installation and schema upgrades.
 *
 * @package PromoEngine
 */

namespace PromoEngine;

use PromoEngine\Analytics\Event_Logger;
use PromoEngine\Front\Deals_Page;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Creates tables and keeps the schema in sync with the plugin version.
 */
final class Installer {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'promo_engine_schema_version';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::create_tables();
		Deals_Page::add_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		delete_transient( 'promo_engine_enabled' );
		flush_rewrite_rules();
	}

	/**
	 * Run dbDelta when the stored schema version is outdated.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Create or update plugin tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$promotions = Repository::table();
		$events     = Event_Logger::table();

		dbDelta(
			"CREATE TABLE {$promotions} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(190) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'paused',
				priority int(11) NOT NULL DEFAULT 10,
				discount_type varchar(20) NOT NULL,
				discount_value decimal(19,4) NOT NULL DEFAULT 0,
				scope varchar(20) NOT NULL DEFAULT 'all',
				scope_ids longtext NULL,
				combinable tinyint(1) NOT NULL DEFAULT 1,
				starts_at datetime NULL DEFAULT NULL,
				ends_at datetime NULL DEFAULT NULL,
				max_discount decimal(5,2) NULL DEFAULT NULL,
				usage_limit int(10) unsigned NOT NULL DEFAULT 0,
				usage_count int(10) unsigned NOT NULL DEFAULT 0,
				config longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_ends (status,ends_at)
			) {$charset};

			CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				promotion_id bigint(20) unsigned NOT NULL,
				event_type varchar(20) NOT NULL,
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				quantity int(10) unsigned NOT NULL DEFAULT 0,
				revenue decimal(19,4) NOT NULL DEFAULT 0,
				discount decimal(19,4) NOT NULL DEFAULT 0,
				variant char(1) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY promotion_event_date (promotion_id,event_type,created_at),
				KEY created_at (created_at),
				KEY order_id (order_id)
			) {$charset};"
		);

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}
}
