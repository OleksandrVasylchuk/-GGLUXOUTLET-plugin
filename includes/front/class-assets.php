<?php
/**
 * Storefront assets.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Front;

use PromoEngine\Analytics\Tracker;
use PromoEngine\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The stylesheet is small and needed wherever the mini-cart is, so it loads
 * site-wide. The script is only enqueued by components that need it.
 */
final class Assets {

	public const HANDLE = 'promo-engine';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Enqueue the stylesheet.
	 */
	public function enqueue_style(): void {
		wp_enqueue_style( self::HANDLE, PROMO_ENGINE_URL . 'assets/css/front.css', array(), PROMO_ENGINE_VERSION );
	}

	/**
	 * Enqueue the storefront script with its runtime config. Safe to call from
	 * any hook up to wp_footer.
	 */
	public static function enqueue_script(): void {
		if ( wp_script_is( self::HANDLE ) ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			PROMO_ENGINE_URL . 'assets/js/front.js',
			array(),
			PROMO_ENGINE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.promoEngine = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => Tracker::ACTION,
					'nonce'   => wp_create_nonce( Tracker::ACTION ),
					'delay'   => Settings::popup_delay(),
				)
			) . ';',
			'before'
		);

		wp_enqueue_script( self::HANDLE );
	}
}
