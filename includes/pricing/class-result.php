<?php
/**
 * Calculation result.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of a cart calculation. Amounts are stored in minor units and
 * converted back to currency by the accessors.
 */
final class Result {

	/**
	 * Lines keyed by cart item key.
	 *
	 * @var array<string, Line_Result>
	 */
	private array $lines = array();

	/**
	 * Closest cart threshold not reached yet.
	 *
	 * @var array{promotion_id: int, subtotal: int, threshold: int, percent: float}|null
	 */
	private ?array $next_tier = null;

	/**
	 * Constructor.
	 *
	 * @param int $factor Minor units per currency unit (100 for two decimals).
	 */
	public function __construct( private int $factor ) {
	}

	/**
	 * Register a line.
	 *
	 * @param Line_Result $line Line.
	 */
	public function add_line( Line_Result $line ): void {
		$this->lines[ $line->key ] = $line;
	}

	/**
	 * Whether the result has a line for the cart item key.
	 *
	 * @param string $key Cart item key.
	 */
	public function has_line( string $key ): bool {
		return isset( $this->lines[ $key ] );
	}

	/**
	 * Line by cart item key.
	 *
	 * @param string $key Cart item key.
	 */
	public function line( string $key ): Line_Result {
		return $this->lines[ $key ];
	}

	/**
	 * All lines.
	 *
	 * @return array<string, Line_Result>
	 */
	public function lines(): array {
		return $this->lines;
	}

	/**
	 * Final unit price to set on the cart product.
	 *
	 * @param string $key Cart item key.
	 */
	public function unit_price( string $key ): float {
		$line = $this->lines[ $key ];

		return $line->quantity > 0 ? $line->total / $line->quantity / $this->factor : 0.0;
	}

	/**
	 * Original unit price.
	 *
	 * @param string $key Cart item key.
	 */
	public function original_unit_price( string $key ): float {
		$line = $this->lines[ $key ];

		return $line->quantity > 0 ? $line->original_total / $line->quantity / $this->factor : 0.0;
	}

	/**
	 * Line total after promotions.
	 *
	 * @param string $key Cart item key.
	 */
	public function line_total( string $key ): float {
		return $this->to_amount( $this->lines[ $key ]->total );
	}

	/**
	 * Line total before promotions.
	 *
	 * @param string $key Cart item key.
	 */
	public function original_line_total( string $key ): float {
		return $this->to_amount( $this->lines[ $key ]->original_total );
	}

	/**
	 * Cart subtotal after promotions.
	 */
	public function subtotal(): float {
		return $this->to_amount( array_sum( array_map( static fn( Line_Result $line ) => $line->total, $this->lines ) ) );
	}

	/**
	 * Total discount across all promotions.
	 */
	public function total_discount(): float {
		return array_sum( $this->discounts() );
	}

	/**
	 * Discount per promotion, highest first.
	 *
	 * @return array<int, float>
	 */
	public function discounts(): array {
		$totals = array();

		foreach ( $this->lines as $line ) {
			foreach ( $line->discounts as $promotion_id => $amount ) {
				$totals[ $promotion_id ] = ( $totals[ $promotion_id ] ?? 0 ) + $amount;
			}
		}

		arsort( $totals );

		return array_map( array( $this, 'to_amount' ), $totals );
	}

	/**
	 * Discount per promotion for one line.
	 *
	 * @param string $key Cart item key.
	 * @return array<int, float>
	 */
	public function line_discounts( string $key ): array {
		return array_map( array( $this, 'to_amount' ), $this->lines[ $key ]->discounts );
	}

	/**
	 * Set the closest unreached cart threshold.
	 *
	 * @param int   $promotion_id Promotion ID.
	 * @param int   $subtotal     Eligible subtotal, minor units.
	 * @param int   $threshold    Threshold, minor units.
	 * @param float $percent      Discount at that threshold.
	 */
	public function set_next_tier( int $promotion_id, int $subtotal, int $threshold, float $percent ): void {
		$this->next_tier = array(
			'promotion_id' => $promotion_id,
			'subtotal'     => $subtotal,
			'threshold'    => $threshold,
			'percent'      => $percent,
		);
	}

	/**
	 * Closest unreached cart threshold.
	 *
	 * @return array{promotion_id: int, remaining: float, threshold: float, percent: float, progress: float}|null
	 */
	public function next_tier(): ?array {
		if ( null === $this->next_tier ) {
			return null;
		}

		return array(
			'promotion_id' => $this->next_tier['promotion_id'],
			'remaining'    => $this->to_amount( $this->next_tier['threshold'] - $this->next_tier['subtotal'] ),
			'threshold'    => $this->to_amount( $this->next_tier['threshold'] ),
			'percent'      => $this->next_tier['percent'],
			'progress'     => round( 100 * $this->next_tier['subtotal'] / $this->next_tier['threshold'], 1 ),
		);
	}

	/**
	 * Minor units to currency.
	 *
	 * @param int $minor Minor units.
	 */
	public function to_amount( int $minor ): float {
		return $minor / $this->factor;
	}
}
