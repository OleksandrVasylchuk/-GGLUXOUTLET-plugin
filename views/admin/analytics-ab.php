<?php
/**
 * Popup A/B test results.
 *
 * @package PromoEngine
 *
 * @var array<string, array<string, float|int>> $variants Metrics keyed by variant.
 */

use PromoEngine\Analytics\Ab_Test;

defined( 'ABSPATH' ) || exit;

$a = $variants['a'];
$b = $variants['b'];

$comparisons = array(
	array( __( 'CTR (clicks / impressions)', 'promo-engine' ), Ab_Test::compare( $a['impressions'], $a['clicks'], $b['impressions'], $b['clicks'] ) ),
	array( __( 'Conversion (orders / add to cart)', 'promo-engine' ), Ab_Test::compare( $a['add_to_cart'], $a['orders'], $b['add_to_cart'], $b['orders'] ) ),
);

$columns = array(
	'impressions' => __( 'Impressions', 'promo-engine' ),
	'clicks'      => __( 'Clicks', 'promo-engine' ),
	'ctr'         => __( 'CTR', 'promo-engine' ),
	'add_to_cart' => __( 'Add to cart', 'promo-engine' ),
	'orders'      => __( 'Orders', 'promo-engine' ),
	'conversion'  => __( 'Conversion', 'promo-engine' ),
	'revenue'     => __( 'Revenue', 'promo-engine' ),
);

$format = static function ( string $key, $value ): string {
	return match ( $key ) {
		'ctr', 'conversion' => number_format_i18n( $value, 1 ) . '%',
		'revenue'           => wc_price( $value ),
		default             => number_format_i18n( $value ),
	};
};
?>

<div class="pe-panel">
	<h2><?php esc_html_e( 'Popup A/B test', 'promo-engine' ); ?></h2>

	<table class="widefat striped pe-report">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Variant', 'promo-engine' ); ?></th>
				<?php foreach ( $columns as $label ) : ?>
					<th scope="col" class="num"><?php echo esc_html( $label ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $variants as $variant => $metrics ) : ?>
				<tr>
					<th scope="row"><strong><?php echo esc_html( strtoupper( $variant ) ); ?></strong></th>
					<?php foreach ( array_keys( $columns ) as $key ) : ?>
						<td class="num"><?php echo wp_kses_post( $format( $key, $metrics[ $key ] ) ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<ul class="pe-ab-verdicts">
		<?php foreach ( $comparisons as [ $label, $result ] ) : ?>
			<li>
				<strong><?php echo esc_html( $label ); ?>:</strong>
				<?php
				if ( ! $result['enough_data'] ) {
					echo esc_html(
						sprintf(
							/* translators: %d: minimum number of trials per variant. */
							__( 'not enough data yet (at least %d per variant needed).', 'promo-engine' ),
							Ab_Test::MIN_TRIALS
						)
					);
				} else {
					$uplift = null === $result['uplift']
						? '&mdash;'
						: ( $result['uplift'] >= 0 ? '+' : '' ) . number_format_i18n( $result['uplift'], 1 ) . '%';

					echo wp_kses_post(
						sprintf(
							/* translators: 1: relative change of B vs A, 2: z-score. */
							__( 'B vs A %1$s, z = %2$s', 'promo-engine' ),
							$uplift,
							number_format_i18n( $result['z'], 2 )
						)
					);
					echo ' &mdash; ';
					echo $result['significant']
						? '<span class="pe-status pe-status--running">' . esc_html__( 'significant at 95%', 'promo-engine' ) . '</span>'
						: '<span class="pe-status">' . esc_html__( 'not significant yet', 'promo-engine' ) . '</span>';
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="description"><?php esc_html_e( 'Two-proportion z-test. Visitors are split 50/50 and keep their variant for 30 days; add to cart and orders are attributed through the same assignment.', 'promo-engine' ); ?></p>
</div>
