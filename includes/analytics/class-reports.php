<?php
/**
 * Analytics queries.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Analytics;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * All aggregation happens in SQL; PHP only reshapes the grouped rows.
 *
 * Dates are site-local days. Stored timestamps are UTC, so ranges are
 * converted to UTC bounds and daily buckets are shifted by the site offset.
 */
final class Reports {

	/**
	 * Event types counted as plain event totals, mapped to metric keys.
	 */
	private const COUNTERS = array(
		Event_Logger::IMPRESSION  => 'impressions',
		Event_Logger::CLICK       => 'clicks',
		Event_Logger::ADD_TO_CART => 'add_to_cart',
	);

	/**
	 * Metrics per promotion.
	 *
	 * @param string   $from         First day, Y-m-d.
	 * @param string   $to           Last day, Y-m-d.
	 * @param int|null $promotion_id Limit to one promotion.
	 * @return array<int, array<string, float|int>>
	 */
	public function summary( string $from, string $to, ?int $promotion_id = null ): array {
		global $wpdb;

		[ $start, $end ] = $this->bounds( $from, $to );

		$sql    = 'SELECT promotion_id, event_type, COUNT(*) AS events, COUNT(DISTINCT NULLIF(order_id, 0)) AS orders,
				SUM(revenue) AS revenue, SUM(discount) AS discount
			FROM %i WHERE created_at BETWEEN %s AND %s';
		$params = array( Event_Logger::table(), $start, $end );

		if ( $promotion_id ) {
			$sql     .= ' AND promotion_id = %d';
			$params[] = $promotion_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are in $sql.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' GROUP BY promotion_id, event_type', ...$params ), ARRAY_A );

		return $this->group_metrics( $rows, 'promotion_id' );
	}

	/**
	 * Metrics of one promotion split by A/B variant.
	 *
	 * @param int    $promotion_id Promotion ID.
	 * @param string $from         First day, Y-m-d.
	 * @param string $to           Last day, Y-m-d.
	 * @return array<string, array<string, float|int>> Keyed by variant.
	 */
	public function variants( int $promotion_id, string $from, string $to ): array {
		global $wpdb;

		[ $start, $end ] = $this->bounds( $from, $to );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT variant, event_type, COUNT(*) AS events, COUNT(DISTINCT NULLIF(order_id, 0)) AS orders,
					SUM(revenue) AS revenue, SUM(discount) AS discount
				FROM %i
				WHERE promotion_id = %d AND variant <> '' AND created_at BETWEEN %s AND %s
				GROUP BY variant, event_type",
				Event_Logger::table(),
				$promotion_id,
				$start,
				$end
			),
			ARRAY_A
		);

		$metrics = $this->group_metrics( $rows, 'variant' );

		foreach ( Ab_Test::VARIANTS as $variant ) {
			$metrics[ $variant ] ??= self::empty_metrics();
		}

		ksort( $metrics );

		return $metrics;
	}

	/**
	 * Daily series for one promotion, zero-filled.
	 *
	 * @param int    $promotion_id Promotion ID.
	 * @param string $from         First day, Y-m-d.
	 * @param string $to           Last day, Y-m-d.
	 * @return array<string, array<string, float|int>>
	 */
	public function timeline( int $promotion_id, string $from, string $to ): array {
		global $wpdb;

		[ $start, $end ] = $this->bounds( $from, $to );
		$offset          = wp_timezone()->getOffset( new DateTimeImmutable() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(DATE_ADD(created_at, INTERVAL %d SECOND)) AS day, event_type,
					COUNT(*) AS events, COUNT(DISTINCT NULLIF(order_id, 0)) AS orders, SUM(revenue) AS revenue, SUM(discount) AS discount
				FROM %i
				WHERE promotion_id = %d AND created_at BETWEEN %s AND %s
				GROUP BY day, event_type
				ORDER BY day',
				$offset,
				Event_Logger::table(),
				$promotion_id,
				$start,
				$end
			),
			ARRAY_A
		);

		$days   = $this->group_metrics( $rows, 'day' );
		$series = array();
		$period = new DatePeriod( new DateTimeImmutable( $from ), new DateInterval( 'P1D' ), ( new DateTimeImmutable( $to ) )->modify( '+1 day' ) );

		foreach ( $period as $day ) {
			$key            = $day->format( 'Y-m-d' );
			$series[ $key ] = $days[ $key ] ?? self::empty_metrics();
		}

		return $series;
	}

	/**
	 * Best performing products of a promotion.
	 *
	 * @param int    $promotion_id Promotion ID.
	 * @param string $from         First day, Y-m-d.
	 * @param string $to           Last day, Y-m-d.
	 * @param int    $limit        Rows.
	 * @return array<int, array{product_id: int, added: int, sold: int, revenue: float, discount: float}>
	 */
	public function top_products( int $promotion_id, string $from, string $to, int $limit = 10 ): array {
		global $wpdb;

		[ $start, $end ] = $this->bounds( $from, $to );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_id,
					SUM(CASE WHEN event_type = %s THEN quantity ELSE 0 END) AS added,
					SUM(CASE WHEN event_type = %s THEN quantity ELSE 0 END) AS sold,
					SUM(CASE WHEN event_type = %s THEN revenue ELSE 0 END) AS revenue,
					SUM(CASE WHEN event_type = %s THEN discount ELSE 0 END) AS discount
				FROM %i
				WHERE promotion_id = %d AND product_id > 0 AND created_at BETWEEN %s AND %s
				GROUP BY product_id
				ORDER BY revenue DESC, sold DESC, added DESC
				LIMIT %d',
				Event_Logger::ADD_TO_CART,
				Event_Logger::ORDER,
				Event_Logger::ORDER,
				Event_Logger::ORDER,
				Event_Logger::table(),
				$promotion_id,
				$start,
				$end,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $row ) => array(
				'product_id' => (int) $row['product_id'],
				'added'      => (int) $row['added'],
				'sold'       => (int) $row['sold'],
				'revenue'    => (float) $row['revenue'],
				'discount'   => (float) $row['discount'],
			),
			$rows
		);
	}

	/**
	 * Zeroed metrics.
	 *
	 * @return array<string, float|int>
	 */
	public static function empty_metrics(): array {
		return self::with_rates(
			array(
				'impressions' => 0,
				'clicks'      => 0,
				'add_to_cart' => 0,
				'orders'      => 0,
				'revenue'     => 0.0,
				'discount'    => 0.0,
			)
		);
	}

	/**
	 * Fold rows grouped by (key, event_type) into metrics per key.
	 *
	 * @param array<int, array<string, string>> $rows Grouped rows.
	 * @param string                            $key  Grouping column.
	 * @return array<int|string, array<string, float|int>>
	 */
	private function group_metrics( array $rows, string $key ): array {
		$metrics = array();

		foreach ( $rows as $row ) {
			$id               = $row[ $key ];
			$metrics[ $id ] ??= self::empty_metrics();

			if ( Event_Logger::ORDER === $row['event_type'] ) {
				$metrics[ $id ]['orders']   = (int) $row['orders'];
				$metrics[ $id ]['revenue']  = (float) $row['revenue'];
				$metrics[ $id ]['discount'] = (float) $row['discount'];
			} elseif ( isset( self::COUNTERS[ $row['event_type'] ] ) ) {
				$metrics[ $id ][ self::COUNTERS[ $row['event_type'] ] ] = (int) $row['events'];
			}
		}

		return array_map( array( self::class, 'with_rates' ), $metrics );
	}

	/**
	 * Add derived rates. CTR = clicks / impressions,
	 * conversion = orders / add to cart events.
	 *
	 * @param array<string, float|int> $metrics Raw metrics.
	 * @return array<string, float|int>
	 */
	private static function with_rates( array $metrics ): array {
		$metrics['ctr']        = $metrics['impressions'] ? 100 * $metrics['clicks'] / $metrics['impressions'] : 0.0;
		$metrics['conversion'] = $metrics['add_to_cart'] ? 100 * $metrics['orders'] / $metrics['add_to_cart'] : 0.0;

		return $metrics;
	}

	/**
	 * Site-local day range to UTC DATETIME bounds.
	 *
	 * @param string $from First day, Y-m-d.
	 * @param string $to   Last day, Y-m-d.
	 * @return array{0: string, 1: string}
	 */
	private function bounds( string $from, string $to ): array {
		$utc = new DateTimeZone( 'UTC' );

		return array(
			( new DateTimeImmutable( $from . ' 00:00:00', wp_timezone() ) )->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			( new DateTimeImmutable( $to . ' 23:59:59', wp_timezone() ) )->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		);
	}
}
