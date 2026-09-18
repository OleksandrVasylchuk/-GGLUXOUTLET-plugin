<?php
/**
 * Collects storefront events.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Analytics;

use PromoEngine\Cart\Line_Factory;
use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Popup impressions and clicks come from the browser; add to cart events are
 * logged server side for every running promotion that covers the product.
 */
final class Tracker {

	public const ACTION = 'promo_engine_track';

	/**
	 * Constructor.
	 *
	 * @param Repository   $promotions Promotions.
	 * @param Line_Factory $line_factory Line factory.
	 * @param Event_Logger $events     Event logger.
	 */
	public function __construct(
		private Repository $promotions,
		private Line_Factory $line_factory,
		private Event_Logger $events
	) {
	}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 4 );
	}

	/**
	 * Popup impression / click endpoint.
	 */
	public function handle_ajax(): void {
		check_ajax_referer( self::ACTION, 'nonce' );

		$event        = sanitize_key( wp_unslash( $_POST['event'] ?? '' ) );
		$promotion_id = absint( $_POST['promotion'] ?? 0 );

		if ( ! in_array( $event, array( Event_Logger::IMPRESSION, Event_Logger::CLICK ), true ) ) {
			wp_send_json_error( null, 400 );
		}

		$running = array_filter( $this->promotions->running(), static fn( Promotion $p ) => $p->id === $promotion_id );

		if ( ! $running ) {
			wp_send_json_error( null, 404 );
		}

		$this->events->log(
			array(
				array(
					'promotion_id' => $promotion_id,
					'event_type'   => $event,
					'variant'      => Ab_Test::variant( reset( $running ) ),
				),
			)
		);

		wp_send_json_success();
	}

	/**
	 * Log add to cart for each promotion covering the product.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation ID.
	 */
	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id ): void {
		$product = wc_get_product( $variation_id ?: $product_id );

		if ( ! $product ) {
			return;
		}

		$line   = $this->line_factory->make( (string) $cart_item_key, $product, (float) $product->get_price(), (int) $quantity );
		$events = array();

		foreach ( $this->promotions->running() as $promotion ) {
			if ( $promotion->applies_to( $line ) ) {
				$events[] = array(
					'promotion_id' => $promotion->id,
					'event_type'   => Event_Logger::ADD_TO_CART,
					'product_id'   => $line->product_id,
					'quantity'     => $line->quantity,
					'variant'      => Ab_Test::variant( $promotion ),
				);
			}
		}

		$this->events->log( $events );
	}
}
