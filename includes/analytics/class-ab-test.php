<?php
/**
 * Popup A/B test helpers.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Analytics;

use PromoEngine\Promotion\Promotion;

defined( 'ABSPATH' ) || exit;

/**
 * The browser assigns a visitor to variant A or B once and stores it in a
 * cookie, so the choice survives page caching and is visible to the server
 * when the same visitor later adds to cart or places an order.
 */
final class Ab_Test {

	public const COOKIE_PREFIX = 'promo_engine_ab_';
	public const VARIANTS      = array( 'a', 'b' );

	/**
	 * Below this many trials per variant the normal approximation behind the
	 * z-test is unreliable, so no verdict is given.
	 */
	public const MIN_TRIALS = 30;

	/**
	 * Variant of the current visitor for a promotion running an A/B test,
	 * or an empty string when there is no test or no assignment yet.
	 *
	 * @param Promotion $promotion Promotion.
	 */
	public static function variant( Promotion $promotion ): string {
		if ( ! $promotion->popup()['ab_test'] ) {
			return '';
		}

		$value = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_PREFIX . $promotion->id ] ?? '' ) );

		return in_array( $value, self::VARIANTS, true ) ? $value : '';
	}

	/**
	 * Two-proportion z-test comparing B against A.
	 *
	 * @param int $trials_a    Trials in A (e.g. impressions).
	 * @param int $successes_a Successes in A (e.g. clicks).
	 * @param int $trials_b    Trials in B.
	 * @param int $successes_b Successes in B.
	 * @return array{enough_data: bool, uplift: float|null, z: float|null, significant: bool}
	 */
	public static function compare( int $trials_a, int $successes_a, int $trials_b, int $successes_b ): array {
		if ( min( $trials_a, $trials_b ) < self::MIN_TRIALS ) {
			return array(
				'enough_data' => false,
				'uplift'      => null,
				'z'           => null,
				'significant' => false,
			);
		}

		// Orders can outnumber add to carts logged before the visitor got a variant.
		$rate_a = min( 1, $successes_a / $trials_a );
		$rate_b = min( 1, $successes_b / $trials_b );
		$pooled = ( $rate_a * $trials_a + $rate_b * $trials_b ) / ( $trials_a + $trials_b );
		$error  = sqrt( $pooled * ( 1 - $pooled ) * ( 1 / $trials_a + 1 / $trials_b ) );
		$z      = $error > 0 ? ( $rate_b - $rate_a ) / $error : 0.0;

		return array(
			'enough_data' => true,
			'uplift'      => $rate_a > 0 ? 100 * ( $rate_b - $rate_a ) / $rate_a : null,
			'z'           => $z,
			'significant' => abs( $z ) >= 1.96,
		);
	}
}
