<?php
/**
 * The /deals/ page.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Front;

use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Virtual page listing running promotions with their products. Served from a
 * rewrite rule, so no WordPress page has to exist.
 */
final class Deals_Page {

	public const QUERY_VAR = 'promo_engine_deals';

	private const PRODUCTS_PER_PROMOTION = 8;

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 */
	public function __construct( private Repository $promotions ) {
	}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'pre_handle_404', array( $this, 'prevent_404' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'template' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Register the rewrite rule. Also called on activation before flushing.
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Page slug, filterable.
	 */
	public static function slug(): string {
		return (string) apply_filters( 'promo_engine_deals_slug', 'deals' );
	}

	/**
	 * Public URL of the page.
	 */
	public static function url(): string {
		return home_url( user_trailingslashit( self::slug() ) );
	}

	/**
	 * Whether the current request is the deals page.
	 */
	public static function is_current(): bool {
		return (bool) get_query_var( self::QUERY_VAR );
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * The main query finds no posts for the virtual page; keep it a 200.
	 *
	 * @param bool      $preempt Whether to short-circuit.
	 * @param \WP_Query $query   Main query.
	 */
	public function prevent_404( $preempt, $query ) {
		if ( $query->get( self::QUERY_VAR ) ) {
			status_header( 200 );

			return true;
		}

		return $preempt;
	}

	/**
	 * Swap in the deals template.
	 *
	 * @param string $template Template path.
	 */
	public function template( $template ) {
		if ( ! self::is_current() ) {
			return $template;
		}

		add_action( 'wp_enqueue_scripts', array( Assets::class, 'enqueue_script' ) );
		add_action( 'promo_engine_deals_content', array( $this, 'render_sections' ) );

		return Template::locate( 'deals' );
	}

	/**
	 * Print every promotion section.
	 */
	public function render_sections(): void {
		$promotions = $this->promotions->running();

		if ( ! $promotions ) {
			Template::render( 'deals-empty' );
			return;
		}

		foreach ( $promotions as $promotion ) {
			Template::render(
				'deals-section',
				array(
					'promotion' => $promotion,
					'products'  => Promotion::TYPE_CART === $promotion->type ? null : $this->products_query( $promotion ),
				)
			);
		}
	}

	/**
	 * Document title.
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function title( $parts ) {
		if ( self::is_current() ) {
			$parts['title'] = __( 'Deals', 'promo-engine' );
		}

		return $parts;
	}

	/**
	 * Body classes.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( self::is_current() ) {
			$classes[] = 'woocommerce';
			$classes[] = 'promo-engine-deals';
		}

		return $classes;
	}

	/**
	 * Products covered by a promotion.
	 *
	 * @param Promotion $promotion Promotion.
	 */
	private function products_query( Promotion $promotion ): WP_Query {
		$hidden = array( 'exclude-from-catalog' );

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$hidden[] = 'outofstock';
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => self::PRODUCTS_PER_PROMOTION,
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => $hidden,
					'operator' => 'NOT IN',
				),
			),
		);

		switch ( $promotion->scope ) {
			case Promotion::SCOPE_PRODUCTS:
				$args['post__in'] = $this->parent_ids( $promotion->scope_ids ) ?: array( 0 );
				$args['orderby']  = 'post__in';
				break;

			case Promotion::SCOPE_CATEGORY:
			case Promotion::SCOPE_TAG:
				$args['tax_query'][] = array(
					'taxonomy' => Promotion::SCOPE_CATEGORY === $promotion->scope ? 'product_cat' : 'product_tag',
					'terms'    => $promotion->scope_ids ?: array( 0 ),
				);
				break;
		}

		return new WP_Query( $args );
	}

	/**
	 * Map variation IDs to their parents.
	 *
	 * @param int[] $ids Product or variation IDs.
	 * @return int[]
	 */
	private function parent_ids( array $ids ): array {
		$parents = array_map( static fn( int $id ) => wp_get_post_parent_id( $id ) ?: $id, $ids );

		return array_values( array_unique( $parents ) );
	}
}
