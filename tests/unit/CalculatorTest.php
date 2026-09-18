<?php
/**
 * Calculator tests. Cases 1-9 mirror the examples from the spec.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PromoEngine\Pricing\Calculator;
use PromoEngine\Pricing\Line;
use PromoEngine\Promotion\Promotion;

final class CalculatorTest extends TestCase {

	private const NOW = 1767225600;

	private const CAT_1 = 11;
	private const CAT_2 = 12;
	private const CAT_3 = 13;

	public function test_category_percent(): void {
		$result = $this->calculate( array( $this->category_20() ), array( $this->line( 'a', 159, array( self::CAT_1 ) ) ) );

		$this->assertSame( 127.20, $result->line_total( 'a' ) );
	}

	public function test_combinable_percents_multiply(): void {
		$result = $this->calculate(
			array( $this->category_20(), $this->percent( 6, 10, 5, true, Promotion::SCOPE_ALL ) ),
			array( $this->line( 'a', 159, array( self::CAT_1 ) ) )
		);

		$this->assertSame( 114.48, $result->line_total( 'a' ) );
	}

	public function test_non_combinable_wins_by_priority(): void {
		$result = $this->calculate(
			array( $this->category_20(), $this->flash( array( 100 ) ) ),
			array( $this->line( 'a', 108, array( self::CAT_1 ), 100 ) )
		);

		$this->assertSame( 75.60, $result->line_total( 'a' ) );
		$this->assertSame( array( 2 => 32.40 ), $result->discounts() );
	}

	public function test_buy_two_get_cheapest_free(): void {
		$result = $this->calculate( array( $this->bxgy() ), $this->category_2_lines() );

		$this->assertSame( 228.0, $result->subtotal() );
		$this->assertSame( 0.0, $result->line_total( 'b' ) );
	}

	public function test_bxgy_uses_stage_one_prices(): void {
		$result = $this->calculate( array( $this->bxgy(), $this->flash( array( 203 ) ) ), $this->category_2_lines() );

		$this->assertSame( 198.0, $result->subtotal() );
		$this->assertSame( 0.0, $result->line_total( 'c' ) );
		$this->assertSame( 90.0, $result->line_total( 'b' ) );
	}

	public function test_cart_threshold_applies_highest_reached_tier(): void {
		$result = $this->calculate( array( $this->bxgy(), $this->cart_tiers() ), $this->category_2_lines() );

		$this->assertSame( 205.20, $result->subtotal() );
		$this->assertSame( array( 3 => 90.0, 5 => 22.80 ), $result->discounts() );
	}

	public function test_cart_discount_is_spread_over_line_prices(): void {
		$result = $this->calculate( array( $this->bxgy(), $this->cart_tiers() ), $this->category_2_lines() );

		// WooCommerce coupons are calculated from line prices, so the cart
		// discount has to live in them for SAVE10 to apply to 205.20.
		$this->assertSame( 97.20, $result->line_total( 'a' ) );
		$this->assertSame( 108.0, $result->line_total( 'c' ) );
		$this->assertSame( 0.0, $result->line_total( 'b' ) );
	}

	public function test_total_item_discount_is_capped(): void {
		$result = $this->calculate(
			array( $this->percent( 7, 50, 10, true ), $this->percent( 8, 60, 10, true ) ),
			array( $this->line( 'a', 100, array( self::CAT_1 ) ) )
		);

		$this->assertSame( 30.0, $result->line_total( 'a' ) );
		$this->assertEqualsWithDelta( 70.0, $result->total_discount(), 0.001 );
	}

	public function test_discount_is_taken_from_the_given_price(): void {
		// Cart_Discounts passes get_price(), i.e. the sale price: regular 120, sale 100.
		$result = $this->calculate( array( $this->category_20() ), array( $this->line( 'a', 100, array( self::CAT_1 ) ) ) );

		$this->assertSame( 80.0, $result->line_total( 'a' ) );
		$this->assertSame( 100.0, $result->original_line_total( 'a' ) );
	}

	public function test_equal_priority_lower_id_wins(): void {
		$result = $this->calculate(
			array( $this->percent( 9, 15, 10, false ), $this->percent( 4, 25, 10, false ) ),
			array( $this->line( 'a', 100, array( self::CAT_1 ) ) )
		);

		$this->assertSame( 75.0, $result->line_total( 'a' ) );
	}

	public function test_bundle_of_two_for_fixed_price(): void {
		$result = $this->calculate(
			array( $this->bundle() ),
			array(
				$this->line( 'a', 159, array( self::CAT_3 ) ),
				$this->line( 'b', 140, array( self::CAT_3 ) ),
				$this->line( 'c', 99, array( self::CAT_3 ) ),
			)
		);

		$this->assertSame( 250.0, $result->line_total( 'a' ) + $result->line_total( 'b' ) );
		$this->assertSame( 99.0, $result->line_total( 'c' ) );
	}

	public function test_bundle_is_skipped_when_not_cheaper(): void {
		$result = $this->calculate(
			array( $this->bundle() ),
			array( $this->line( 'a', 120, array( self::CAT_3 ) ), $this->line( 'b', 110, array( self::CAT_3 ) ) )
		);

		$this->assertSame( 0.0, $result->total_discount() );
	}

	public function test_bxgy_counts_units_of_one_line(): void {
		$result = $this->calculate( array( $this->bxgy() ), array( $this->line( 'a', 90, array( self::CAT_2 ), 0, 3 ) ) );

		$this->assertSame( 180.0, $result->line_total( 'a' ) );
		$this->assertSame( 60.0, $result->unit_price( 'a' ) );
	}

	public function test_next_tier_progress(): void {
		$result = $this->calculate(
			array( $this->cart_tiers() ),
			array( $this->line( 'a', 159, array( self::CAT_1 ) ), $this->line( 'b', 69, array( self::CAT_1 ) ) )
		);

		$this->assertSame( 22.0, $result->next_tier()['remaining'] );
		$this->assertSame( 15.0, $result->next_tier()['percent'] );
	}

	public function test_scheduled_promotion_is_ignored_outside_its_window(): void {
		$flash          = $this->flash( array( 100 ) );
		$flash->ends_at = self::NOW - 1;

		$result = $this->calculate( array( $flash ), array( $this->line( 'a', 108, array(), 100 ) ) );

		$this->assertSame( 108.0, $result->line_total( 'a' ) );
	}

	public function test_promotion_cap_limits_fixed_discount(): void {
		$fixed = Promotion::from_array(
			array(
				'id'           => 10,
				'status'       => Promotion::STATUS_ACTIVE,
				'type'         => Promotion::TYPE_FIXED,
				'value'        => 50,
				'max_discount' => 25,
			)
		);

		$result = $this->calculate( array( $fixed ), array( $this->line( 'a', 100, array() ) ) );

		$this->assertSame( 75.0, $result->line_total( 'a' ) );
	}

	/**
	 * @param Promotion[] $promotions
	 * @param Line[]      $lines
	 */
	private function calculate( array $promotions, array $lines ) {
		return ( new Calculator( $promotions, 70.0, 2 ) )->calculate( $lines, self::NOW );
	}

	private function line( string $key, float $price, array $categories, int $product_id = 0, int $quantity = 1 ): Line {
		return new Line( $key, $product_id ?: crc32( $key ), 0, $price, $quantity, $categories );
	}

	private function category_2_lines(): array {
		return array(
			$this->line( 'a', 108, array( self::CAT_2 ), 201 ),
			$this->line( 'b', 90, array( self::CAT_2 ), 202 ),
			$this->line( 'c', 120, array( self::CAT_2 ), 203 ),
		);
	}

	private function percent( int $id, float $value, int $priority, bool $combinable, string $scope = Promotion::SCOPE_CATEGORY ): Promotion {
		return Promotion::from_array(
			array(
				'id'         => $id,
				'status'     => Promotion::STATUS_ACTIVE,
				'priority'   => $priority,
				'type'       => Promotion::TYPE_PERCENT,
				'value'      => $value,
				'scope'      => $scope,
				'scope_ids'  => array( self::CAT_1 ),
				'combinable' => $combinable,
			)
		);
	}

	private function category_20(): Promotion {
		return $this->percent( 1, 20, 10, true );
	}

	private function flash( array $product_ids ): Promotion {
		return Promotion::from_array(
			array(
				'id'         => 2,
				'status'     => Promotion::STATUS_ACTIVE,
				'priority'   => 40,
				'type'       => Promotion::TYPE_PERCENT,
				'value'      => 30,
				'scope'      => Promotion::SCOPE_PRODUCTS,
				'scope_ids'  => $product_ids,
				'combinable' => false,
				'ends_at'    => self::NOW + DAY_IN_SECONDS,
			)
		);
	}

	private function bxgy(): Promotion {
		return Promotion::from_array(
			array(
				'id'        => 3,
				'status'    => Promotion::STATUS_ACTIVE,
				'priority'  => 20,
				'type'      => Promotion::TYPE_BXGY,
				'scope'     => Promotion::SCOPE_CATEGORY,
				'scope_ids' => array( self::CAT_2 ),
				'config'    => array(
					'buy_qty' => 2,
					'get_qty' => 1,
				),
			)
		);
	}

	private function bundle(): Promotion {
		return Promotion::from_array(
			array(
				'id'         => 4,
				'status'     => Promotion::STATUS_ACTIVE,
				'priority'   => 30,
				'type'       => Promotion::TYPE_BUNDLE,
				'scope'      => Promotion::SCOPE_CATEGORY,
				'scope_ids'  => array( self::CAT_3 ),
				'combinable' => false,
				'config'     => array(
					'bundle_qty'   => 2,
					'bundle_price' => 250,
				),
			)
		);
	}

	private function cart_tiers(): Promotion {
		return Promotion::from_array(
			array(
				'id'       => 5,
				'status'   => Promotion::STATUS_ACTIVE,
				'priority' => 5,
				'type'     => Promotion::TYPE_CART,
				'scope'    => Promotion::SCOPE_ALL,
				'config'   => array(
					'tiers' => array(
						array(
							'threshold' => 150,
							'percent'   => 10,
						),
						array(
							'threshold' => 250,
							'percent'   => 15,
						),
						array(
							'threshold' => 400,
							'percent'   => 20,
						),
					),
				),
			)
		);
	}
}
