<?php
/**
 * Analytics screen.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Admin;

use DateTimeImmutable;
use PromoEngine\Analytics\Reports;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Overview of all promotions plus a detail view with a chart and top products.
 */
final class Analytics_Page {

	private const DEFAULT_DAYS = 30;
	private const MAX_DAYS     = 366;

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 * @param Reports    $reports    Reports.
	 */
	public function __construct(
		private Repository $promotions,
		private Reports $reports
	) {
	}

	/**
	 * Chart script and data for the detail view.
	 */
	public function enqueue(): void {
		$filters = $this->filters();

		if ( ! $filters['promotion'] ) {
			return;
		}

		wp_enqueue_script( 'promo-engine-chart', PROMO_ENGINE_URL . 'assets/js/chart.js', array(), PROMO_ENGINE_VERSION, true );
		wp_add_inline_script(
			'promo-engine-chart',
			'window.promoEngineChart = ' . wp_json_encode(
				array(
					'series'   => $this->reports->timeline( $filters['promotion'], $filters['from'], $filters['to'] ),
					'currency' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES ),
					'labels'   => array(
						'impressions' => __( 'Impressions', 'promo-engine' ),
						'clicks'      => __( 'Clicks', 'promo-engine' ),
						'add_to_cart' => __( 'Add to cart', 'promo-engine' ),
						'orders'      => __( 'Orders', 'promo-engine' ),
						'revenue'     => __( 'Revenue', 'promo-engine' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		$filters = $this->filters();

		if ( $filters['promotion'] ) {
			$promotion = $this->promotions->find( $filters['promotion'] );

			if ( ! $promotion ) {
				wp_die( esc_html__( 'Promotion not found.', 'promo-engine' ), 404 );
			}

			$metrics  = $this->reports->summary( $filters['from'], $filters['to'], $promotion->id )[ $promotion->id ] ?? Reports::empty_metrics();
			$products = $this->reports->top_products( $promotion->id, $filters['from'], $filters['to'] );
			$variants = $this->reports->variants( $promotion->id, $filters['from'], $filters['to'] );
			$ab_test  = $promotion->popup()['ab_test'] || $variants['a']['impressions'] || $variants['b']['impressions'];

			include PROMO_ENGINE_DIR . 'views/admin/analytics-detail.php';
			return;
		}

		$rows       = $this->reports->summary( $filters['from'], $filters['to'] );
		$promotions = $this->promotions->paginate( array( 'per_page' => 500 ) )['items'];

		include PROMO_ENGINE_DIR . 'views/admin/analytics-overview.php';
	}

	/**
	 * Validated query filters. Read-only screen, so no nonce.
	 *
	 * @return array{promotion: int, from: string, to: string}
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$to    = $this->parse_date( sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ) ) ?? $today;
		$from  = $this->parse_date( sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ) ) ?? $to->modify( '-' . ( self::DEFAULT_DAYS - 1 ) . ' days' );
		$id    = absint( $_GET['promotion'] ?? 0 );
		// phpcs:enable

		if ( $from > $to ) {
			[ $from, $to ] = array( $to, $from );
		}

		if ( $from->diff( $to )->days >= self::MAX_DAYS ) {
			$from = $to->modify( '-' . ( self::MAX_DAYS - 1 ) . ' days' );
		}

		return array(
			'promotion' => $id,
			'from'      => $from->format( 'Y-m-d' ),
			'to'        => $to->format( 'Y-m-d' ),
		);
	}

	/**
	 * Parse a Y-m-d date in the site timezone.
	 *
	 * @param string $value Date.
	 */
	private function parse_date( string $value ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );

		return $date && $date->format( 'Y-m-d' ) === $value ? $date : null;
	}
}
