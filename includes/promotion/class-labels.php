<?php
/**
 * Human readable promotion labels.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Promotion;

defined( 'ABSPATH' ) || exit;

/**
 * Translatable names for types, scopes and discount badges.
 */
final class Labels {

	/**
	 * Discount types.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			Promotion::TYPE_PERCENT => __( 'Percentage discount', 'promo-engine' ),
			Promotion::TYPE_FIXED   => __( 'Fixed amount discount', 'promo-engine' ),
			Promotion::TYPE_BXGY    => __( 'Buy X get Y', 'promo-engine' ),
			Promotion::TYPE_BUNDLE  => __( 'Bundle for a fixed price', 'promo-engine' ),
			Promotion::TYPE_CART    => __( 'Cart discount by subtotal', 'promo-engine' ),
		);
	}

	/**
	 * Scopes.
	 *
	 * @return array<string, string>
	 */
	public static function scopes(): array {
		return array(
			Promotion::SCOPE_PRODUCTS => __( 'Selected products', 'promo-engine' ),
			Promotion::SCOPE_CATEGORY => __( 'Product categories', 'promo-engine' ),
			Promotion::SCOPE_TAG      => __( 'Product tags', 'promo-engine' ),
			Promotion::SCOPE_ALL      => __( 'Entire catalog', 'promo-engine' ),
		);
	}

	/**
	 * Statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			Promotion::STATUS_ACTIVE => __( 'Active', 'promo-engine' ),
			Promotion::STATUS_PAUSED => __( 'Paused', 'promo-engine' ),
		);
	}

	/**
	 * Short badge, e.g. "-20%", "Buy 2 get 1", "2 for $250". May contain price HTML.
	 *
	 * @param Promotion $promotion Promotion.
	 */
	public static function badge( Promotion $promotion ): string {
		switch ( $promotion->type ) {
			case Promotion::TYPE_PERCENT:
				return '&minus;' . wc_format_localized_decimal( $promotion->value ) . '%';

			case Promotion::TYPE_FIXED:
				return '&minus;' . wc_price( $promotion->value );

			case Promotion::TYPE_BXGY:
				return 100.0 === $promotion->get_discount()
					/* translators: 1: units to buy, 2: free units. */
					? sprintf( __( 'Buy %1$d get %2$d free', 'promo-engine' ), $promotion->buy_qty(), $promotion->get_qty() )
					/* translators: 1: units to buy, 2: discounted units, 3: discount percent. */
					: sprintf( __( 'Buy %1$d get %2$d at &minus;%3$s%%', 'promo-engine' ), $promotion->buy_qty(), $promotion->get_qty(), wc_format_localized_decimal( $promotion->get_discount() ) );

			case Promotion::TYPE_BUNDLE:
				/* translators: 1: units in bundle, 2: bundle price. */
				return sprintf( __( '%1$d for %2$s', 'promo-engine' ), $promotion->bundle_qty(), wc_price( $promotion->bundle_price() ) );

			case Promotion::TYPE_CART:
				$tiers = $promotion->tiers();

				return $tiers
					/* translators: %s: maximum discount percent. */
					? sprintf( __( 'Up to &minus;%s%% on your order', 'promo-engine' ), wc_format_localized_decimal( end( $tiers )['percent'] ) )
					: '';
		}

		return '';
	}
}
