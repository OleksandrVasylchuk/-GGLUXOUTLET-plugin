<?php
/**
 * Savings blocks in mini-cart, cart and checkout.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Front;

use PromoEngine\Cart\Cart_Discounts;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * The mini-cart block is printed inside the widget markup, so WooCommerce's
 * standard cart fragments refresh it without extra requests.
 */
final class Cart_Summary {

	/**
	 * Constructor.
	 *
	 * @param Cart_Discounts $cart       Cart discounts.
	 * @param Repository     $promotions Promotions.
	 */
	public function __construct(
		private Cart_Discounts $cart,
		private Repository $promotions
	) {
	}

	/**
	 * Hook into WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'mini_cart' ), 5 );
		add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'cart_totals' ) );
		add_action( 'woocommerce_review_order_before_order_total', array( $this, 'checkout' ) );
	}

	/**
	 * Mini-cart block: applied promotions and progress to the next threshold.
	 */
	public function mini_cart(): void {
		$data = $this->data();

		if ( $data['applied'] || $data['next'] ) {
			Template::render( 'cart/mini-cart-summary', $data );
		}
	}

	/**
	 * Cart page totals row.
	 */
	public function cart_totals(): void {
		$data = $this->data();

		if ( $data['applied'] ) {
			Template::render( 'savings-row', $data );
		}
	}

	/**
	 * Checkout order review row.
	 */
	public function checkout(): void {
		$data = $this->data();

		if ( $data['applied'] ) {
			// multibrand-theme renders the order review totals as div rows.
			$template = 'multibrand-theme' === get_template() ? 'checkout/savings-multibrand' : 'savings-row';

			Template::render( (string) apply_filters( 'promo_engine_checkout_savings_template', $template ), $data );
		}
	}

	/**
	 * Template data.
	 *
	 * @return array{applied: array<int, array{name: string, amount: float}>, total: float, next: array<string, mixed>|null}
	 */
	private function data(): array {
		$result = $this->cart->result();
		$data   = array(
			'applied' => array(),
			'total'   => 0.0,
			'next'    => null,
		);

		if ( ! $result ) {
			return $data;
		}

		$promotions = array();

		foreach ( $this->promotions->running() as $promotion ) {
			$promotions[ $promotion->id ] = $promotion;
		}

		foreach ( $result->discounts() as $id => $amount ) {
			$data['applied'][ $id ] = array(
				'name'   => isset( $promotions[ $id ] ) ? $promotions[ $id ]->name : '',
				'amount' => $amount,
			);
		}

		$data['total'] = $result->total_discount();
		$next          = $result->next_tier();

		if ( $next && isset( $promotions[ $next['promotion_id'] ] ) ) {
			$data['next'] = $next + array( 'promotion' => $promotions[ $next['promotion_id'] ] );
		}

		return $data;
	}
}
