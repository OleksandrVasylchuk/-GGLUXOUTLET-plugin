<?php
/**
 * Discount calculator.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Pricing;

use PromoEngine\Promotion\Promotion;

defined( 'ABSPATH' ) || exit;

/**
 * Applies promotions to cart lines in three stages:
 *
 * 1. Item discounts (percent / fixed) per line.
 * 2. Deals (buy X get Y, bundles) on stage 1 prices.
 * 3. Cart threshold discounts on the stage 2 subtotal.
 *
 * Has no WordPress or WooCommerce dependencies. All money is handled in
 * minor units to keep rounding deterministic.
 */
final class Calculator {

	private float $cap;
	private int $factor;

	/**
	 * Constructor.
	 *
	 * @param Promotion[] $promotions Candidate promotions; not running ones are ignored.
	 * @param float       $cap        Maximum total item-level discount, percent.
	 * @param int         $decimals   Price decimals.
	 */
	public function __construct(
		private array $promotions,
		float $cap = 70.0,
		int $decimals = 2
	) {
		$this->cap    = min( 100.0, max( 0.0, $cap ) );
		$this->factor = 10 ** $decimals;
	}

	/**
	 * Calculate discounts for the given lines.
	 *
	 * @param Line[] $lines Cart lines.
	 * @param int    $now   Unix timestamp used for schedule checks.
	 */
	public function calculate( array $lines, int $now ): Result {
		$stages = array(
			Promotion::STAGE_ITEM => array(),
			Promotion::STAGE_DEAL => array(),
			Promotion::STAGE_CART => array(),
		);

		foreach ( $this->promotions as $promotion ) {
			if ( $promotion->is_running( $now ) ) {
				$stages[ $promotion->stage() ][] = $promotion;
			}
		}

		foreach ( $stages as &$promotions ) {
			usort( $promotions, array( self::class, 'compare' ) );
		}
		unset( $promotions );

		$result = new Result( $this->factor );

		foreach ( $lines as $line ) {
			$result->add_line( new Line_Result( $line->key, $line->quantity, $this->to_minor( $line->price ) ) );
		}

		$this->apply_item_discounts( $lines, $stages[ Promotion::STAGE_ITEM ], $result );
		$this->apply_deals( $lines, $stages[ Promotion::STAGE_DEAL ], $result );
		$this->apply_cart_discounts( $lines, $stages[ Promotion::STAGE_CART ], $result );

		return $result;
	}

	/**
	 * Stage 1: percent and fixed discounts, capped per line.
	 *
	 * @param Line[]      $lines      Cart lines.
	 * @param Promotion[] $promotions Sorted item promotions.
	 * @param Result      $result     Result being built.
	 */
	private function apply_item_discounts( array $lines, array $promotions, Result $result ): void {
		foreach ( $lines as $line ) {
			$applied = self::resolve( array_filter( $promotions, static fn( Promotion $p ) => $p->applies_to( $line ) ) );

			if ( ! $applied ) {
				continue;
			}

			$row    = $result->line( $line->key );
			$base   = $row->unit_price;
			$price  = (float) $base;
			$shares = array();

			foreach ( $applied as $promotion ) {
				$before = $price;

				if ( Promotion::TYPE_PERCENT === $promotion->type ) {
					$price *= 1 - $promotion->value / 100;
				} else {
					$price -= $promotion->value * $this->factor;
				}

				$price = max( 0.0, $price );

				if ( null !== $promotion->max_discount ) {
					$price = max( $price, $before * ( 1 - $promotion->max_discount / 100 ) );
				}

				$shares[ $promotion->id ] = $before - $price;
			}

			$discount = (int) round( min( $base - $price, $base * $this->cap / 100 ) );

			if ( $discount <= 0 ) {
				continue;
			}

			$row->unit_price = $base - $discount;
			$row->total      = $row->unit_price * $row->quantity;

			foreach ( self::allocate( $discount, $shares ) as $promotion_id => $amount ) {
				$row->add_discount( $promotion_id, $amount * $row->quantity );
			}
		}
	}

	/**
	 * Stage 2: buy X get Y and bundles.
	 *
	 * Works on individual units so a line with quantity 3 can take part in a
	 * deal partially. A unit is consumed by at most one deal; deals are
	 * evaluated by priority, so the higher one claims units first.
	 *
	 * @param Line[]      $lines      Cart lines.
	 * @param Promotion[] $promotions Sorted deal promotions.
	 * @param Result      $result     Result being built.
	 */
	private function apply_deals( array $lines, array $promotions, Result $result ): void {
		if ( ! $promotions ) {
			return;
		}

		$units = array();

		foreach ( $lines as $line ) {
			$price = $result->line( $line->key )->unit_price;

			for ( $i = 0; $i < $line->quantity; $i++ ) {
				$units[] = array(
					'line'    => $line,
					'price'   => $price,
					'claimed' => false,
				);
			}
		}

		foreach ( $promotions as $promotion ) {
			$pool = array_keys(
				array_filter( $units, static fn( array $unit ) => ! $unit['claimed'] && $promotion->applies_to( $unit['line'] ) )
			);

			usort( $pool, static fn( int $a, int $b ): int => array( $units[ $b ]['price'], $a ) <=> array( $units[ $a ]['price'], $b ) );

			$size = Promotion::TYPE_BXGY === $promotion->type
				? $promotion->buy_qty() + $promotion->get_qty()
				: $promotion->bundle_qty();

			foreach ( array_chunk( $pool, $size ) as $group ) {
				if ( count( $group ) < $size ) {
					break;
				}

				$prices    = array_combine( $group, array_map( static fn( int $i ) => $units[ $i ]['price'], $group ) );
				$discounts = Promotion::TYPE_BXGY === $promotion->type
					? $this->bxgy_discounts( $promotion, $prices )
					: $this->bundle_discounts( $promotion, $prices );

				if ( null === $discounts ) {
					break;
				}

				foreach ( $group as $i ) {
					$units[ $i ]['claimed'] = true;
					$units[ $i ]['price']  -= $discounts[ $i ] ?? 0;
					$result->line( $units[ $i ]['line']->key )->add_discount( $promotion->id, $discounts[ $i ] ?? 0 );
				}
			}
		}

		$totals = array();

		foreach ( $units as $unit ) {
			$totals[ $unit['line']->key ] = ( $totals[ $unit['line']->key ] ?? 0 ) + $unit['price'];
		}

		foreach ( $totals as $key => $total ) {
			$result->line( $key )->total = $total;
		}
	}

	/**
	 * Discounts for one buy X get Y group: the cheapest units are the "get" ones.
	 *
	 * @param Promotion       $promotion Promotion.
	 * @param array<int, int> $prices    Unit prices keyed by unit index, most expensive first.
	 * @return array<int, int>
	 */
	private function bxgy_discounts( Promotion $promotion, array $prices ): array {
		$discounts = array();

		foreach ( array_slice( $prices, -$promotion->get_qty(), null, true ) as $i => $price ) {
			$discounts[ $i ] = (int) round( $price * $promotion->get_discount() / 100 );
		}

		return $this->cap_group( $promotion, $prices, $discounts );
	}

	/**
	 * Discounts for one bundle group, spread proportionally to unit prices.
	 * Null when the bundle price is not cheaper than buying separately.
	 *
	 * @param Promotion       $promotion Promotion.
	 * @param array<int, int> $prices    Unit prices keyed by unit index, most expensive first.
	 * @return array<int, int>|null
	 */
	private function bundle_discounts( Promotion $promotion, array $prices ): ?array {
		$saving = array_sum( $prices ) - $this->to_minor( $promotion->bundle_price() );

		if ( $saving <= 0 ) {
			return null;
		}

		return $this->cap_group( $promotion, $prices, self::allocate( $saving, $prices ) );
	}

	/**
	 * Limit a group discount to the promotion's own maximum.
	 *
	 * @param Promotion       $promotion Promotion.
	 * @param array<int, int> $prices    Unit prices.
	 * @param array<int, int> $discounts Unit discounts.
	 * @return array<int, int>
	 */
	private function cap_group( Promotion $promotion, array $prices, array $discounts ): array {
		if ( null === $promotion->max_discount ) {
			return $discounts;
		}

		$limit = (int) floor( array_sum( $prices ) * $promotion->max_discount / 100 );

		return array_sum( $discounts ) > $limit ? self::allocate( $limit, $discounts ) : $discounts;
	}

	/**
	 * Stage 3: cart threshold discounts. Only the highest reached tier of each
	 * promotion counts; the combination rule decides between promotions.
	 *
	 * @param Line[]      $lines      Cart lines.
	 * @param Promotion[] $promotions Sorted cart promotions.
	 * @param Result      $result     Result being built.
	 */
	private function apply_cart_discounts( array $lines, array $promotions, Result $result ): void {
		$reached      = array();
		$percents     = array();
		$closest_left = PHP_INT_MAX;

		foreach ( $promotions as $promotion ) {
			$subtotal = $this->subtotal( $result, $this->scoped_keys( $lines, $promotion ) );

			foreach ( $promotion->tiers() as $tier ) {
				$threshold = $this->to_minor( $tier['threshold'] );

				if ( $subtotal >= $threshold ) {
					$reached[ $promotion->id ]  = $promotion;
					$percents[ $promotion->id ] = $tier['percent'];
					continue;
				}

				if ( $threshold - $subtotal < $closest_left ) {
					$closest_left = $threshold - $subtotal;
					$result->set_next_tier( $promotion->id, $subtotal, $threshold, $tier['percent'] );
				}
				break;
			}
		}

		foreach ( self::resolve( $reached ) as $promotion ) {
			$keys    = $this->scoped_keys( $lines, $promotion );
			$percent = $percents[ $promotion->id ];

			if ( null !== $promotion->max_discount ) {
				$percent = min( $percent, $promotion->max_discount );
			}

			$weights  = array_map( static fn( string $key ) => $result->line( $key )->total, array_combine( $keys, $keys ) );
			$discount = (int) round( array_sum( $weights ) * $percent / 100 );

			foreach ( self::allocate( $discount, $weights ) as $key => $amount ) {
				$row         = $result->line( $key );
				$row->total -= $amount;
				$row->add_discount( $promotion->id, $amount );
			}
		}
	}

	/**
	 * Cart item keys a promotion applies to.
	 *
	 * @param Line[]    $lines     Cart lines.
	 * @param Promotion $promotion Promotion.
	 * @return string[]
	 */
	private function scoped_keys( array $lines, Promotion $promotion ): array {
		$keys = array();

		foreach ( $lines as $line ) {
			if ( $promotion->applies_to( $line ) ) {
				$keys[] = $line->key;
			}
		}

		return $keys;
	}

	/**
	 * Current total of the given lines, minor units.
	 *
	 * @param Result   $result Result being built.
	 * @param string[] $keys   Cart item keys.
	 */
	private function subtotal( Result $result, array $keys ): int {
		return array_sum( array_map( static fn( string $key ) => $result->line( $key )->total, $keys ) );
	}

	/**
	 * Currency to minor units.
	 *
	 * @param float $amount Amount.
	 */
	private function to_minor( float $amount ): int {
		return (int) round( $amount * $this->factor );
	}

	/**
	 * Combination rule. If any candidate is not combinable, only the top one
	 * applies; otherwise all of them apply in priority order.
	 *
	 * @param Promotion[] $candidates Candidates sorted by compare().
	 * @return Promotion[]
	 */
	private static function resolve( array $candidates ): array {
		$candidates = array_values( $candidates );

		foreach ( $candidates as $promotion ) {
			if ( ! $promotion->combinable ) {
				return array( $candidates[0] );
			}
		}

		return $candidates;
	}

	/**
	 * Higher priority first, lower ID wins a tie.
	 *
	 * @param Promotion $a First promotion.
	 * @param Promotion $b Second promotion.
	 */
	private static function compare( Promotion $a, Promotion $b ): int {
		return array( $b->priority, $a->id ) <=> array( $a->priority, $b->id );
	}

	/**
	 * Split an integer amount proportionally to weights so the parts add up
	 * exactly (largest remainder method).
	 *
	 * @param int                      $amount  Amount to split, minor units.
	 * @param array<int|string, float> $weights Weights.
	 * @return array<int|string, int>
	 */
	private static function allocate( int $amount, array $weights ): array {
		$sum   = array_sum( $weights );
		$parts = array_fill_keys( array_keys( $weights ), 0 );

		if ( $amount <= 0 || $sum <= 0 ) {
			return $parts;
		}

		$remainders = array();

		foreach ( $weights as $key => $weight ) {
			$exact              = $amount * $weight / $sum;
			$parts[ $key ]      = (int) floor( $exact );
			$remainders[ $key ] = $exact - $parts[ $key ];
		}

		arsort( $remainders );

		$left = $amount - array_sum( $parts );

		foreach ( array_keys( $remainders ) as $key ) {
			if ( $left-- <= 0 ) {
				break;
			}
			++$parts[ $key ];
		}

		return $parts;
	}
}
