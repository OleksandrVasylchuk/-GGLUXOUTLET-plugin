<?php
/**
 * Promotion storage.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Promotion;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for the promotions table plus a cached list of enabled promotions.
 *
 * The cache holds every active, not yet expired promotion regardless of its
 * start date; schedule checks happen in memory on each request, so the cache
 * only has to be invalidated on writes.
 */
final class Repository {

	private const CACHE_KEY = 'promo_engine_enabled';

	/**
	 * Per-request copy of the cached list.
	 *
	 * @var Promotion[]|null
	 */
	private ?array $enabled = null;

	/**
	 * Promotions table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'promo_engine_promotions';
	}

	/**
	 * Promotions that are running right now.
	 *
	 * @return Promotion[]
	 */
	public function running(): array {
		$now = time();

		return array_values( array_filter( $this->enabled(), static fn( Promotion $p ) => $p->is_running( $now ) ) );
	}

	/**
	 * Find a promotion by ID.
	 *
	 * @param int $id Promotion ID.
	 */
	public function find( int $id ): ?Promotion {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Promotions by IDs, keyed by ID.
	 *
	 * @param int[] $ids Promotion IDs.
	 * @return array<int, Promotion>
	 */
	public function find_many( array $ids ): array {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %d per ID.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE id IN ($placeholders)", self::table(), ...$ids ), ARRAY_A );

		$promotions = array();

		foreach ( $rows as $row ) {
			$promotions[ (int) $row['id'] ] = $this->hydrate( $row );
		}

		return $promotions;
	}

	/**
	 * Paginated list for the admin table.
	 *
	 * @param array{search?: string, status?: string, orderby?: string, order?: string, per_page?: int, page?: int} $args Query args.
	 * @return array{items: Promotion[], total: int}
	 */
	public function paginate( array $args ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array( self::table() );

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$orderby  = in_array( $args['orderby'] ?? '', array( 'id', 'name', 'priority', 'starts_at', 'ends_at', 'status' ), true ) ? $args['orderby'] : 'priority';
		$order    = 'asc' === strtolower( $args['order'] ?? '' ) ? 'ASC' : 'DESC';
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset   = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;
		$where    = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds placeholders, $orderby/$order are whitelisted.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE $where", ...$params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM %i WHERE $where ORDER BY $orderby $order, id ASC LIMIT %d OFFSET %d", ...array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => array_map( array( $this, 'hydrate' ), $rows ),
			'total' => $total,
		);
	}

	/**
	 * Insert or update. Returns the promotion ID.
	 *
	 * @param Promotion $promotion Promotion.
	 */
	public function save( Promotion $promotion ): int {
		global $wpdb;

		$data = array(
			'name'           => $promotion->name,
			'status'         => $promotion->status,
			'priority'       => $promotion->priority,
			'discount_type'  => $promotion->type,
			'discount_value' => $promotion->value,
			'scope'          => $promotion->scope,
			'scope_ids'      => wp_json_encode( array_values( $promotion->scope_ids ) ),
			'combinable'     => (int) $promotion->combinable,
			'starts_at'      => self::to_datetime( $promotion->starts_at ),
			'ends_at'        => self::to_datetime( $promotion->ends_at ),
			'max_discount'   => $promotion->max_discount,
			'usage_limit'    => $promotion->usage_limit,
			'config'         => wp_json_encode( $promotion->config ),
			'updated_at'     => current_time( 'mysql', true ),
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $promotion->id ) {
			$wpdb->update( self::table(), $data, array( 'id' => $promotion->id ) );
		} else {
			$data['created_at'] = $data['updated_at'];
			$wpdb->insert( self::table(), $data );
			$promotion->id = (int) $wpdb->insert_id;
		}
		// phpcs:enable

		$this->flush();

		return $promotion->id;
	}

	/**
	 * Delete promotions.
	 *
	 * @param int[] $ids Promotion IDs.
	 */
	public function delete( array $ids ): void {
		global $wpdb;

		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$this->flush();
	}

	/**
	 * Change status of promotions.
	 *
	 * @param int[]  $ids    Promotion IDs.
	 * @param string $status New status.
	 */
	public function set_status( array $ids, string $status ): void {
		global $wpdb;

		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		}

		$this->flush();
	}

	/**
	 * Atomically bump usage counters.
	 *
	 * @param int[] $ids Promotion IDs.
	 */
	public function increment_usage( array $ids ): void {
		global $wpdb;

		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET usage_count = usage_count + 1 WHERE id = %d', self::table(), $id ) );
		}

		$this->flush();
	}

	/**
	 * Give usage back, e.g. after a refund.
	 *
	 * @param int[] $ids Promotion IDs.
	 */
	public function decrement_usage( array $ids ): void {
		global $wpdb;

		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET usage_count = usage_count - 1 WHERE id = %d AND usage_count > 0', self::table(), $id ) );
		}

		$this->flush();
	}

	/**
	 * Drop cached promotion lists.
	 */
	public function flush(): void {
		$this->enabled = null;
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Active and not expired promotions, cached.
	 *
	 * @return Promotion[]
	 */
	private function enabled(): array {
		if ( null !== $this->enabled ) {
			return $this->enabled;
		}

		$rows = get_transient( self::CACHE_KEY );

		if ( ! is_array( $rows ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND ( ends_at IS NULL OR ends_at > %s ) ORDER BY priority DESC, id ASC',
					self::table(),
					Promotion::STATUS_ACTIVE,
					current_time( 'mysql', true )
				),
				ARRAY_A
			);

			// A failed query (e.g. missing table) must not be cached.
			if ( ! is_array( $rows ) ) {
				return array();
			}

			set_transient( self::CACHE_KEY, $rows, DAY_IN_SECONDS );
		}

		$this->enabled = array_map( array( $this, 'hydrate' ), $rows );

		return $this->enabled;
	}

	/**
	 * Map a DB row to an entity.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	private function hydrate( array $row ): Promotion {
		return Promotion::from_array(
			array(
				'id'           => $row['id'],
				'name'         => $row['name'],
				'status'       => $row['status'],
				'priority'     => $row['priority'],
				'type'         => $row['discount_type'],
				'value'        => $row['discount_value'],
				'scope'        => $row['scope'],
				'scope_ids'    => json_decode( (string) $row['scope_ids'], true ) ?: array(),
				'combinable'   => $row['combinable'],
				'starts_at'    => $row['starts_at'] ? strtotime( $row['starts_at'] . ' UTC' ) : null,
				'ends_at'      => $row['ends_at'] ? strtotime( $row['ends_at'] . ' UTC' ) : null,
				'max_discount' => $row['max_discount'],
				'usage_limit'  => $row['usage_limit'],
				'usage_count'  => $row['usage_count'],
				'config'       => json_decode( (string) $row['config'], true ) ?: array(),
			)
		);
	}

	/**
	 * Unix timestamp to a UTC DATETIME string.
	 *
	 * @param int|null $timestamp Timestamp.
	 */
	private static function to_datetime( ?int $timestamp ): ?string {
		return null === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
