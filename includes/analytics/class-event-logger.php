<?php
/**
 * Promotion event storage.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Writes rows to the events table.
 */
final class Event_Logger {

	public const IMPRESSION  = 'impression';
	public const CLICK       = 'click';
	public const ADD_TO_CART = 'add_to_cart';
	public const ORDER       = 'order';

	/**
	 * Events table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'promo_engine_events';
	}

	/**
	 * Store one or more events in a single query.
	 *
	 * @param array<int, array{promotion_id: int, event_type: string, product_id?: int, order_id?: int, quantity?: int, revenue?: float, discount?: float, variant?: string}> $events Events.
	 */
	public function log( array $events ): void {
		global $wpdb;

		if ( ! $events ) {
			return;
		}

		$now    = current_time( 'mysql', true );
		$rows   = array();
		$values = array();

		foreach ( $events as $event ) {
			$rows[] = '(%d, %s, %d, %d, %d, %f, %f, %s, %s)';
			array_push(
				$values,
				$event['promotion_id'],
				$event['event_type'],
				$event['product_id'] ?? 0,
				$event['order_id'] ?? 0,
				$event['quantity'] ?? 0,
				$event['revenue'] ?? 0,
				$event['discount'] ?? 0,
				$event['variant'] ?? '',
				$now
			);
		}

		$sql = 'INSERT INTO %i (promotion_id, event_type, product_id, order_id, quantity, revenue, discount, variant, created_at) VALUES ' . implode( ', ', $rows );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $sql, self::table(), ...$values ) );
	}

	/**
	 * Remove all events of an order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function delete_order( int $order_id ): void {
		global $wpdb;

		$wpdb->delete( self::table(), array( 'order_id' => $order_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
