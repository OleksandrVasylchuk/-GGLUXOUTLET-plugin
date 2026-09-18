<?php
/**
 * Plugin bootstrap.
 *
 * @package PromoEngine
 */

namespace PromoEngine;

use PromoEngine\Admin\Admin;
use PromoEngine\Analytics\Event_Logger;
use PromoEngine\Analytics\Reports;
use PromoEngine\Analytics\Tracker;
use PromoEngine\Cart\Cart_Discounts;
use PromoEngine\Cart\Line_Factory;
use PromoEngine\Cart\Order_Recorder;
use PromoEngine\Cli\Command;
use PromoEngine\Demo\Seeder;
use PromoEngine\Front\Assets;
use PromoEngine\Front\Cart_Summary;
use PromoEngine\Front\Deals_Page;
use PromoEngine\Front\Popup;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires services to WordPress hooks.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	/**
	 * Shared instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Register everything. Called on plugins_loaded once WooCommerce is available.
	 */
	public function boot(): void {
		Installer::maybe_upgrade();

		$promotions   = new Repository();
		$line_factory = new Line_Factory();
		$events       = new Event_Logger();
		$cart         = new Cart_Discounts( $promotions, $line_factory );

		$cart->register();
		( new Order_Recorder( $cart, $promotions, $events ) )->register();
		( new Tracker( $promotions, $line_factory, $events ) )->register();
		( new Assets() )->register();
		( new Deals_Page( $promotions ) )->register();
		( new Popup( $promotions ) )->register();
		( new Cart_Summary( $cart, $promotions ) )->register();

		if ( is_admin() ) {
			( new Admin( $promotions, new Reports(), new Seeder( $promotions ) ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'promo-engine', new Command( new Seeder( $promotions ) ) );
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'promo-engine', false, dirname( plugin_basename( PROMO_ENGINE_FILE ) ) . '/languages' );
	}
}
