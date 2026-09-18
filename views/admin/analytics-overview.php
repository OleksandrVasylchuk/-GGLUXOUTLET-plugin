<?php
/**
 * Analytics overview: one row per promotion.
 *
 * @package PromoEngine
 *
 * @var array{promotion: int, from: string, to: string}   $filters
 * @var array<int, array<string, float|int>>               $rows
 * @var PromoEngine\Promotion\Promotion[]                  $promotions
 */

use PromoEngine\Admin\Admin;
use PromoEngine\Analytics\Reports;

defined( 'ABSPATH' ) || exit;

$range = array(
	'from' => $filters['from'],
	'to'   => $filters['to'],
);
?>

<div class="wrap pe-admin">
	<h1><?php esc_html_e( 'Promotion analytics', 'promo-engine' ); ?></h1>

	<?php require __DIR__ . '/analytics-filters.php'; ?>

	<table class="widefat striped pe-report">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Promotion', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Impressions', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Clicks', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'CTR', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Add to cart', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Orders', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Conversion', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Revenue', 'promo-engine' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Discount given', 'promo-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $promotions ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'No promotions yet.', 'promo-engine' ); ?></td></tr>
			<?php endif; ?>

			<?php foreach ( $promotions as $promotion ) : ?>
				<?php
				$metrics    = $rows[ $promotion->id ] ?? Reports::empty_metrics();
				$detail_url = Admin::url( Admin::PAGE_ANALYTICS, array( 'promotion' => $promotion->id ) + $range );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $detail_url ); ?>">
							<strong><?php echo esc_html( $promotion->name ); ?></strong>
						</a>
					</td>
					<td class="num"><?php echo esc_html( number_format_i18n( $metrics['impressions'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $metrics['clicks'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $metrics['ctr'], 1 ) ); ?>%</td>
					<td class="num"><?php echo esc_html( number_format_i18n( $metrics['add_to_cart'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $metrics['orders'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $metrics['conversion'], 1 ) ); ?>%</td>
					<td class="num"><?php echo wp_kses_post( wc_price( $metrics['revenue'] ) ); ?></td>
					<td class="num"><?php echo wp_kses_post( wc_price( $metrics['discount'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php esc_html_e( 'CTR = popup clicks / popup impressions. Conversion = orders / add to cart events under the promotion. Revenue is the order line total after all discounts.', 'promo-engine' ); ?>
	</p>
</div>
