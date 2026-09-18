<?php
/**
 * Persists applied promotions on orders.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Cart;

use PromoEngine\Analytics\Ab_Test;
use PromoEngine\Analytics\Event_Logger;
use PromoEngine\Promotion\Repository;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * At checkout the discount breakdown is copied to the order. Analytics and
 * usage limits only count paid orders: events are written when the order
 * reaches a paid status and removed again if it is cancelled or refunded.
 *
 * Payment often completes in a gateway callback without the customer's
 * cookies, so A/B variants are captured at checkout.
 */
final class Order_Recorder {

	private const ITEM_META    = '_promo_engine_discounts';
	private const SUMMARY_META = '_promo_engine_summary';
	private const VARIANT_META = '_promo_engine_variants';
	private const TRACKED_META = '_promo_engine_tracked';

	private const REVERSED_STATUSES = array( 'cancelled', 'refunded', 'failed' );

	/**
	 * Constructor.
	 *
	 * @param Cart_Discounts $cart       Cart discounts.
	 * @param Repository     $promotions Promotions.
	 * @param Event_Logger   $events     Event logger.
	 */
	public function __construct(
		private Cart_Discounts $cart,
		private Repository $promotions,
		private Event_Logger $events
	) {
	}

	/**
	 * Hook into WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'annotate_item' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_summary' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_summary' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 10, 4 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'add_savings_row' ), 10, 2 );
	}

	/**
	 * Store per-promotion discounts on the order item.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 */
	public function annotate_item( $item, $cart_item_key ): void {
		$result = $this->cart->result();

		if ( ! $result || ! $result->has_line( $cart_item_key ) || ! $result->line( $cart_item_key )->discounts ) {
			return;
		}

		$item->update_meta_data( self::ITEM_META, $result->line_discounts( $cart_item_key ) );
	}

	/**
	 * Save the savings summary and A/B variants. Runs again when a failed
	 * order is retried with a changed cart, until the order is counted.
	 *
	 * @param WC_Order $order Order.
	 */
	public function save_summary( $order ): void {
		if ( ! $order instanceof WC_Order || $order->get_meta( self::TRACKED_META ) ) {
			return;
		}

		$amounts = $this->amounts( $order );

		if ( ! $amounts ) {
			$order->delete_meta_data( self::SUMMARY_META );
			$order->delete_meta_data( self::VARIANT_META );
			$order->save_meta_data();
			return;
		}

		$summary  = array();
		$variants = array();

		foreach ( $this->promotions->find_many( array_keys( $amounts ) ) as $id => $promotion ) {
			$summary[ $id ] = array(
				'name'   => $promotion->name,
				'amount' => round( $amounts[ $id ], wc_get_price_decimals() ),
			);

			$variants[ $id ] = Ab_Test::variant( $promotion );
		}

		$order->update_meta_data( self::SUMMARY_META, $summary );
		$order->update_meta_data( self::VARIANT_META, array_filter( $variants ) );
		$order->save_meta_data();

		// Gateways that mark the order paid inside checkout fire the status change before this hook.
		if ( $order->is_paid() ) {
			$this->track( $order );
		}
	}

	/**
	 * Count the order when it gets paid, undo it when it is cancelled or refunded.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Previous status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public function status_changed( $order_id, $from, $to, $order ): void {
		if ( in_array( $to, wc_get_is_paid_statuses(), true ) ) {
			$this->track( $order );
		} elseif ( in_array( $to, self::REVERSED_STATUSES, true ) ) {
			$this->untrack( $order );
		}
	}

	/**
	 * Add a "You saved" row to order totals (thank you page, emails, My Account).
	 * Informational: the subtotal above it already includes the discount.
	 *
	 * @param array<string, array{label: string, value: string}> $rows  Total rows.
	 * @param WC_Order                                           $order Order.
	 * @return array<string, array{label: string, value: string}>
	 */
	public function add_savings_row( $rows, $order ) {
		$summary = $order->get_meta( self::SUMMARY_META );

		if ( ! is_array( $summary ) || ! $summary ) {
			return $rows;
		}

		$row = array(
			'label' => __( 'You saved:', 'promo-engine' ),
			'value' => wc_price( array_sum( array_column( $summary, 'amount' ) ), array( 'currency' => $order->get_currency() ) )
				. ' <small>(' . esc_html( implode( ', ', array_column( $summary, 'name' ) ) ) . ')</small>',
		);

		$position = array_search( 'order_total', array_keys( $rows ), true );
		$position = false === $position ? count( $rows ) : $position;

		return array_slice( $rows, 0, $position, true ) + array( 'promo_engine' => $row ) + array_slice( $rows, $position, null, true );
	}

	/**
	 * Write order events and bump usage, once per order.
	 *
	 * @param WC_Order $order Order.
	 */
	private function track( WC_Order $order ): void {
		if ( ! $order->get_meta( self::SUMMARY_META ) || $order->get_meta( self::TRACKED_META ) ) {
			return;
		}

		$variants = (array) $order->get_meta( self::VARIANT_META );
		$events   = array();

		foreach ( $this->discounted_items( $order ) as $item ) {
			foreach ( $item->get_meta( self::ITEM_META ) as $promotion_id => $amount ) {
				$events[] = array(
					'promotion_id' => (int) $promotion_id,
					'event_type'   => Event_Logger::ORDER,
					'product_id'   => $item->get_product_id(),
					'order_id'     => $order->get_id(),
					'quantity'     => $item->get_quantity(),
					'revenue'      => (float) $item->get_total(),
					'discount'     => (float) $amount,
					'variant'      => $variants[ $promotion_id ] ?? '',
				);
			}
		}

		$order->update_meta_data( self::TRACKED_META, 'yes' );
		$order->save_meta_data();

		$this->events->log( $events );
		$this->promotions->increment_usage( array_keys( $this->amounts( $order ) ) );
	}

	/**
	 * Remove order events and give usage back.
	 *
	 * @param WC_Order $order Order.
	 */
	private function untrack( WC_Order $order ): void {
		if ( ! $order->get_meta( self::TRACKED_META ) ) {
			return;
		}

		$order->delete_meta_data( self::TRACKED_META );
		$order->save_meta_data();

		$this->events->delete_order( $order->get_id() );
		$this->promotions->decrement_usage( array_keys( $this->amounts( $order ) ) );
	}

	/**
	 * Total discount per promotion across the order.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, float>
	 */
	private function amounts( WC_Order $order ): array {
		$amounts = array();

		foreach ( $this->discounted_items( $order ) as $item ) {
			foreach ( $item->get_meta( self::ITEM_META ) as $promotion_id => $amount ) {
				$amounts[ $promotion_id ] = ( $amounts[ $promotion_id ] ?? 0 ) + $amount;
			}
		}

		return $amounts;
	}

	/**
	 * Order items that received a promotion.
	 *
	 * @param WC_Order $order Order.
	 * @return WC_Order_Item_Product[]
	 */
	private function discounted_items( WC_Order $order ): array {
		return array_filter(
			$order->get_items(),
			fn( $item ) => $item instanceof WC_Order_Item_Product && is_array( $item->get_meta( self::ITEM_META ) )
		);
	}
}
