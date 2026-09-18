<?php
/**
 * Applies promotions to the WooCommerce cart.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Cart;

use PromoEngine\Pricing\Calculator;
use PromoEngine\Pricing\Result;
use PromoEngine\Promotion\Repository;
use PromoEngine\Settings;
use WC_Cart;
use WC_Product;
use WeakMap;

defined( 'ABSPATH' ) || exit;

/**
 * Sets discounted prices on cart item products in memory. Coupons, taxes and
 * totals are then calculated by WooCommerce on top of these prices.
 */
final class Cart_Discounts {

	/**
	 * Price each product object had before the first discount pass. Keyed by
	 * the object itself, so repeated calculate_totals() calls always start
	 * from the original price and a product reloaded from session is priced
	 * afresh.
	 *
	 * @var WeakMap<WC_Product, float>
	 */
	private WeakMap $base_prices;

	private ?Result $result = null;
	private bool $running   = false;

	/**
	 * Constructor.
	 *
	 * @param Repository   $promotions   Promotions.
	 * @param Line_Factory $line_factory Line factory.
	 */
	public function __construct(
		private Repository $promotions,
		private Line_Factory $line_factory
	) {
		$this->base_prices = new WeakMap();
	}

	/**
	 * Hook into WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply' ), 20 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'apply' ), 20 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'item_price_html' ), 20, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'item_subtotal_html' ), 20, 3 );
	}

	/**
	 * Recalculate discounts and update cart item prices.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function apply( WC_Cart $cart ): void {
		if ( $this->running || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}

		$this->running = true;

		try {
			$lines = array();

			foreach ( $cart->get_cart() as $key => $item ) {
				if ( $item['data'] instanceof WC_Product ) {
					$lines[] = $this->line_factory->make( (string) $key, $item['data'], $this->base_price( $item['data'] ), (int) $item['quantity'] );
				}
			}

			$calculator   = new Calculator( $this->promotions->running(), Settings::global_cap(), wc_get_price_decimals() );
			$this->result = $calculator->calculate( $lines, time() );

			foreach ( $cart->get_cart() as $key => $item ) {
				if ( $this->result->has_line( (string) $key ) ) {
					$item['data']->set_price( $this->result->unit_price( (string) $key ) );
				}
			}
		} finally {
			$this->running = false;
		}
	}

	/**
	 * Result of the last calculation for the current cart.
	 */
	public function result(): ?Result {
		if ( null === $this->result && WC()->cart instanceof WC_Cart ) {
			$this->apply( WC()->cart );
		}

		return $this->result;
	}

	/**
	 * Show the original unit price struck through next to the discounted one.
	 *
	 * @param string              $html Price HTML.
	 * @param array<string,mixed> $item Cart item.
	 * @param string              $key  Cart item key.
	 */
	public function item_price_html( $html, $item, $key ) {
		if ( ! $this->is_discounted( $key ) ) {
			return $html;
		}

		return $this->sale_price_html( $item['data'], $this->result->original_unit_price( $key ), $this->result->unit_price( $key ) );
	}

	/**
	 * Same for the line subtotal on the cart page.
	 *
	 * @param string              $html Subtotal HTML.
	 * @param array<string,mixed> $item Cart item.
	 * @param string              $key  Cart item key.
	 */
	public function item_subtotal_html( $html, $item, $key ) {
		if ( ! $this->is_discounted( $key ) ) {
			return $html;
		}

		return $this->sale_price_html( $item['data'], $this->result->original_line_total( $key ), $this->result->line_total( $key ) );
	}

	/**
	 * Whether a cart line got cheaper in the last calculation.
	 *
	 * @param string $key Cart item key.
	 */
	private function is_discounted( string $key ): bool {
		return $this->result && $this->result->has_line( $key ) && $this->result->line( $key )->is_discounted();
	}

	/**
	 * Struck-through original price followed by the discounted one, with the
	 * cart's tax display applied to both.
	 *
	 * @param WC_Product $product Product.
	 * @param float      $was     Original amount.
	 * @param float      $now     Discounted amount.
	 */
	private function sale_price_html( WC_Product $product, float $was, float $now ): string {
		return wc_format_sale_price( $this->display_price( $product, $was ), $this->display_price( $product, $now ) );
	}

	/**
	 * Original price of the product object.
	 *
	 * @param WC_Product $product Cart item product.
	 */
	private function base_price( WC_Product $product ): float {
		if ( ! isset( $this->base_prices[ $product ] ) ) {
			$this->base_prices[ $product ] = (float) $product->get_price();
		}

		return $this->base_prices[ $product ];
	}

	/**
	 * Apply cart tax display settings to an amount.
	 *
	 * @param WC_Product $product Product.
	 * @param float      $amount  Amount.
	 */
	private function display_price( WC_Product $product, float $amount ): float {
		$args = array( 'price' => $amount );

		return WC()->cart->display_prices_including_tax()
			? wc_get_price_including_tax( $product, $args )
			: wc_get_price_excluding_tax( $product, $args );
	}
}
