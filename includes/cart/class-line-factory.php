<?php
/**
 * Builds calculator lines from WooCommerce products.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Cart;

use PromoEngine\Pricing\Line;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves product terms once per request. Category matching includes
 * ancestors, so a promotion on a parent category covers its subcategories.
 */
final class Line_Factory {

	/**
	 * Term IDs per parent product.
	 *
	 * @var array<int, array{category: int[], tag: int[]}>
	 */
	private array $terms = array();

	/**
	 * Build a line.
	 *
	 * @param string     $key      Cart item key.
	 * @param WC_Product $product  Product or variation.
	 * @param float      $price    Unit price.
	 * @param int        $quantity Quantity.
	 */
	public function make( string $key, WC_Product $product, float $price, int $quantity ): Line {
		$product_id = $product->get_parent_id() ?: $product->get_id();
		$terms      = $this->terms( $product_id );

		return new Line(
			$key,
			$product_id,
			$product->is_type( 'variation' ) ? $product->get_id() : 0,
			$price,
			$quantity,
			$terms['category'],
			$terms['tag']
		);
	}

	/**
	 * Category (with ancestors) and tag IDs of a product.
	 *
	 * @param int $product_id Parent product ID.
	 * @return array{category: int[], tag: int[]}
	 */
	private function terms( int $product_id ): array {
		if ( isset( $this->terms[ $product_id ] ) ) {
			return $this->terms[ $product_id ];
		}

		$categories = wc_get_product_term_ids( $product_id, 'product_cat' );

		foreach ( $categories as $category_id ) {
			$categories = array_merge( $categories, get_ancestors( $category_id, 'product_cat', 'taxonomy' ) );
		}

		$this->terms[ $product_id ] = array(
			'category' => array_values( array_unique( array_map( 'intval', $categories ) ) ),
			'tag'      => array_map( 'intval', wc_get_product_term_ids( $product_id, 'product_tag' ) ),
		);

		return $this->terms[ $product_id ];
	}
}
