<?php
/**
 * Demo promotions.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Demo;

use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the five reference promotions from the spec. Idempotent: the IDs of
 * the previous demo set are remembered and replaced.
 */
final class Seeder {

	private const OPTION = 'promo_engine_demo_ids';

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 */
	public function __construct( private Repository $promotions ) {
	}

	/**
	 * Create the demo set.
	 *
	 * @param int   $category_1 Category for the -20% promotion.
	 * @param int   $category_2 Category for buy 2 get 1.
	 * @param int   $category_3 Category for the bundle.
	 * @param int[] $flash_ids  Products for the flash sale; picked automatically when empty.
	 * @return int[] Created promotion IDs.
	 */
	public function seed( int $category_1, int $category_2, int $category_3, array $flash_ids = array() ): array {
		$this->promotions->delete( (array) get_option( self::OPTION, array() ) );

		if ( ! $flash_ids ) {
			$flash_ids = $this->pick_flash_products( $category_1, $category_2, $category_3 );
		}

		$ids = array();

		foreach ( $this->definitions( $category_1, $category_2, $category_3, $flash_ids ) as $definition ) {
			$ids[] = $this->promotions->save( Promotion::from_array( $definition + array( 'status' => Promotion::STATUS_ACTIVE ) ) );
		}

		update_option( self::OPTION, $ids, false );

		return $ids;
	}

	/**
	 * Promotion data.
	 *
	 * @param int   $category_1 Category 1.
	 * @param int   $category_2 Category 2.
	 * @param int   $category_3 Category 3.
	 * @param int[] $flash_ids  Flash sale products.
	 * @return array<int, array<string, mixed>>
	 */
	private function definitions( int $category_1, int $category_2, int $category_3, array $flash_ids ): array {
		return array(
			array(
				'name'       => __( 'Category 1 -20%', 'promo-engine' ),
				'priority'   => 10,
				'type'       => Promotion::TYPE_PERCENT,
				'value'      => 20,
				'scope'      => Promotion::SCOPE_CATEGORY,
				'scope_ids'  => array( $category_1 ),
				'combinable' => true,
			),
			array(
				'name'       => __( 'Flash -30%', 'promo-engine' ),
				'priority'   => 40,
				'type'       => Promotion::TYPE_PERCENT,
				'value'      => 30,
				'scope'      => Promotion::SCOPE_PRODUCTS,
				'scope_ids'  => $flash_ids,
				'combinable' => false,
				'starts_at'  => time(),
				'ends_at'    => time() + 7 * DAY_IN_SECONDS,
				'config'     => array(
					'popup' => array(
						'enabled'   => true,
						'title'     => __( 'Flash sale', 'promo-engine' ),
						'text'      => __( '30% off selected styles. Limited time only.', 'promo-engine' ),
						'cta_label' => __( 'Shop the sale', 'promo-engine' ),
						'ab_test'   => true,
						'b'         => array(
							'title'     => __( '30% off. Today only', 'promo-engine' ),
							'text'      => __( 'Your size may not last. Grab it before the timer runs out.', 'promo-engine' ),
							'cta_label' => __( 'Get 30% off', 'promo-engine' ),
						),
					),
				),
			),
			array(
				'name'       => __( 'Buy 2 get 1', 'promo-engine' ),
				'priority'   => 20,
				'type'       => Promotion::TYPE_BXGY,
				'scope'      => Promotion::SCOPE_CATEGORY,
				'scope_ids'  => array( $category_2 ),
				'combinable' => true,
				'config'     => array(
					'buy_qty'      => 2,
					'get_qty'      => 1,
					'get_discount' => 100,
				),
			),
			array(
				/* translators: %s: bundle price. */
				'name'       => sprintf( __( 'Bundle: 2 for %s', 'promo-engine' ), wp_strip_all_tags( wc_price( 250, array( 'decimals' => 0 ) ) ) ),
				'priority'   => 30,
				'type'       => Promotion::TYPE_BUNDLE,
				'scope'      => Promotion::SCOPE_CATEGORY,
				'scope_ids'  => array( $category_3 ),
				'combinable' => false,
				'config'     => array(
					'bundle_qty'   => 2,
					'bundle_price' => 250,
				),
			),
			array(
				'name'       => __( 'Cart discount by subtotal', 'promo-engine' ),
				'priority'   => 5,
				'type'       => Promotion::TYPE_CART,
				'scope'      => Promotion::SCOPE_ALL,
				'combinable' => true,
				'config'     => array(
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
			),
		);
	}

	/**
	 * Two products from categories 1 and 2 and one from category 3, so the
	 * overlap examples (flash + category, flash + buy 2 get 1) can be tried.
	 *
	 * @param int $category_1 Category 1.
	 * @param int $category_2 Category 2.
	 * @param int $category_3 Category 3.
	 * @return int[]
	 */
	private function pick_flash_products( int $category_1, int $category_2, int $category_3 ): array {
		$picked = array();
		$quota  = array(
			$category_1 => 2,
			$category_2 => 2,
			$category_3 => 1,
		);

		foreach ( $quota as $category_id => $limit ) {
			$term = get_term( $category_id, 'product_cat' );

			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$ids = wc_get_products(
				array(
					'status'   => 'publish',
					'category' => array( $term->slug ),
					'exclude'  => $picked,
					'limit'    => $limit,
					'orderby'  => 'ID',
					'order'    => 'ASC',
					'return'   => 'ids',
				)
			);

			$picked = array_merge( $picked, array_map( 'intval', $ids ) );
		}

		return $picked;
	}
}
