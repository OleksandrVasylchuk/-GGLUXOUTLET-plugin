<?php
/**
 * A/B significance tests.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PromoEngine\Analytics\Ab_Test;

final class AbTestTest extends TestCase {

	public function test_clear_winner_is_significant(): void {
		$result = Ab_Test::compare( 1000, 50, 1000, 90 );

		$this->assertTrue( $result['significant'] );
		$this->assertEqualsWithDelta( 80.0, $result['uplift'], 0.001 );
		$this->assertGreaterThan( 1.96, $result['z'] );
	}

	public function test_small_difference_is_not_significant(): void {
		$result = Ab_Test::compare( 40, 4, 40, 6 );

		$this->assertTrue( $result['enough_data'] );
		$this->assertFalse( $result['significant'] );
	}

	public function test_no_verdict_below_minimum_sample(): void {
		$result = Ab_Test::compare( 10, 1, 100, 30 );

		$this->assertFalse( $result['enough_data'] );
		$this->assertNull( $result['z'] );
		$this->assertFalse( $result['significant'] );
	}

	public function test_uplift_is_undefined_when_a_has_no_successes(): void {
		$result = Ab_Test::compare( 100, 0, 100, 10 );

		$this->assertNull( $result['uplift'] );
		$this->assertTrue( $result['significant'] );
	}

	public function test_identical_rates_give_zero(): void {
		$result = Ab_Test::compare( 200, 20, 400, 40 );

		$this->assertSame( 0.0, $result['z'] );
		$this->assertSame( 0.0, $result['uplift'] );
	}
}
