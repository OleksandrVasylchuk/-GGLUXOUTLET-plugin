<?php
/**
 * Calculator input line.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * One cart line reduced to what the calculator needs.
 */
final class Line {

	/**
	 * Constructor.
	 *
	 * @param string $key          Cart item key.
	 * @param int    $product_id   Parent product ID.
	 * @param int    $variation_id Variation ID or 0.
	 * @param float  $price        Current unit price (sale price if on sale).
	 * @param int    $quantity     Quantity.
	 * @param int[]  $category_ids product_cat IDs including ancestors.
	 * @param int[]  $tag_ids      product_tag IDs.
	 */
	public function __construct(
		public string $key,
		public int $product_id,
		public int $variation_id,
		public float $price,
		public int $quantity,
		public array $category_ids = array(),
		public array $tag_ids = array()
	) {
	}
}
