<?php
/**
 * WP-CLI commands.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Cli;

use PromoEngine\Demo\Seeder;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Promo Engine promotions.
 */
final class Command {

	/**
	 * Constructor.
	 *
	 * @param Seeder $seeder Demo seeder.
	 */
	public function __construct( private Seeder $seeder ) {
	}

	/**
	 * Create the five demo promotions. Running it again replaces the previous demo set.
	 *
	 * ## OPTIONS
	 *
	 * --cat1=<category>
	 * : Category 1 (ID or slug) for "Category 1 -20%".
	 *
	 * --cat2=<category>
	 * : Category 2 (ID or slug) for "Buy 2 get 1".
	 *
	 * --cat3=<category>
	 * : Category 3 (ID or slug) for "Bundle: 2 for $250".
	 *
	 * [--flash=<ids>]
	 * : Comma separated product IDs for "Flash -30%". Defaults to two products
	 * from categories 1 and 2 and one from category 3.
	 *
	 * ## EXAMPLES
	 *
	 *     wp promo-engine seed --cat1=hoodie --cat2=pants --cat3=tees
	 *     wp promo-engine seed --cat1=19 --cat2=20 --cat3=21 --flash=101,102,103,104,105
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function seed( array $args, array $assoc_args ): void {
		$categories = array();

		foreach ( array( 'cat1', 'cat2', 'cat3' ) as $key ) {
			$categories[] = $this->category_id( (string) ( $assoc_args[ $key ] ?? '' ), $key );
		}

		$flash = isset( $assoc_args['flash'] ) ? array_filter( array_map( 'absint', explode( ',', $assoc_args['flash'] ) ) ) : array();
		$ids   = $this->seeder->seed( $categories[0], $categories[1], $categories[2], $flash );

		WP_CLI::success( sprintf( 'Created promotions: %s.', implode( ', ', $ids ) ) );
	}

	/**
	 * Resolve a category ID or slug.
	 *
	 * @param string $value Category ID or slug.
	 * @param string $field Argument name for the error message.
	 */
	private function category_id( string $value, string $field ): int {
		$term = is_numeric( $value )
			? get_term( (int) $value, 'product_cat' )
			: get_term_by( 'slug', $value, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			WP_CLI::error( sprintf( 'Unknown product category for --%s: %s', $field, $value ) );
		}

		return (int) $term->term_id;
	}
}
