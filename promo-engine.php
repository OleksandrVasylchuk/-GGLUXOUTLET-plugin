<?php
/**
 * Plugin Name:          Promo Engine
 * Description:          Rule-based promotions for WooCommerce: item discounts, buy X get Y, bundles, cart thresholds and promotion analytics.
 * Version:              1.0.0
 * Requires at least:    6.4
 * Requires PHP:         8.0
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.1
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          promo-engine
 * Domain Path:          /languages
 *
 * @package PromoEngine
 */

defined( 'ABSPATH' ) || exit;

define( 'PROMO_ENGINE_VERSION', '1.0.0' );
define( 'PROMO_ENGINE_FILE', __FILE__ );
define( 'PROMO_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROMO_ENGINE_URL', plugin_dir_url( __FILE__ ) );

require_once PROMO_ENGINE_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( PromoEngine\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( PromoEngine\Installer::class, 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		PromoEngine\Plugin::instance()->boot();
	}
);
