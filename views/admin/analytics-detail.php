<?php
/**
 * Analytics for one promotion.
 *
 * @package PromoEngine
 *
 * @var array{promotion: int, from: string, to: string}                                        $filters
 * @var PromoEngine\Promotion\Promotion                                                         $promotion
 * @var array<string, float|int>                                                                $metrics
 * @var array<int, array{product_id: int, added: int, sold: int, revenue: float, discount: float}> $products
 * @var array<string, array<string, float|int>>                                                 $variants
 * @var bool                                                                                    $ab_test
 */

use PromoEngine\Admin\Admin;

defined( 'ABSPATH' ) || exit;

$cards = array(
	array( __( 'Impressions', 'promo-engine' ), number_format_i18n( $metrics['impressions'] ) ),
	array( __( 'Clicks', 'promo-engine' ), number_format_i18n( $metrics['clicks'] ) ),
	array( __( 'CTR', 'promo-engine' ), number_format_i18n( $metrics['ctr'], 1 ) . '%' ),
	array( __( 'Add to cart', 'promo-engine' ), number_format_i18n( $metrics['add_to_cart'] ) ),
	array( __( 'Orders', 'promo-engine' ), number_format_i18n( $metrics['orders'] ) ),
	array( __( 'Conversion', 'promo-engine' ), number_format_i18n( $metrics['conversion'], 1 ) . '%' ),
	array( __( 'Revenue', 'promo-engine' ), wc_price( $metrics['revenue'] ) ),
	array( __( 'Discount given', 'promo-engine' ), wc_price( $metrics['discount'] ) ),
);

$overview_url = Admin::url(
	Admin::PAGE_ANALYTICS,
	array(
		'from' => $filters['from'],
		'to'   => $filters['to'],
	)
);
?>

<div class="wrap pe-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $promotion->name ); ?></h1>
	<a href="<?php echo esc_url( Admin::url( Admin::PAGE_EDIT, array( 'id' => $promotion->id ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit promotion', 'promo-engine' ); ?></a>
	<a href="<?php echo esc_url( $overview_url ); ?>" class="page-title-action"><?php esc_html_e( 'All promotions', 'promo-engine' ); ?></a>
	<hr class="wp-header-end">

	<?php require __DIR__ . '/analytics-filters.php'; ?>

	<ul class="pe-cards">
		<?php foreach ( $cards as [ $label, $value ] ) : ?>
			<li class="pe-card">
				<span class="pe-card__label"><?php echo esc_html( $label ); ?></span>
				<strong class="pe-card__value"><?php echo wp_kses_post( $value ); ?></strong>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php
	if ( $ab_test ) {
		require __DIR__ . '/analytics-ab.php';
	}
	?>

	<div class="pe-panel">
		<h2><?php esc_html_e( 'Activity', 'promo-engine' ); ?></h2>
		<div class="pe-chart" data-pe-chart="impressions,clicks,add_to_cart,orders" role="img" aria-label="<?php esc_attr_e( 'Daily impressions, clicks, add to cart events and orders', 'promo-engine' ); ?>"></div>
	</div>

	<div class="pe-panel">
		<h2><?php esc_html_e( 'Revenue', 'promo-engine' ); ?></h2>
		<div class="pe-chart" data-pe-chart="revenue" data-pe-money role="img" aria-label="<?php esc_attr_e( 'Daily revenue', 'promo-engine' ); ?>"></div>
	</div>

	<div class="pe-panel">
		<h2><?php esc_html_e( 'Top products', 'promo-engine' ); ?></h2>
		<table class="widefat striped pe-report">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Product', 'promo-engine' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Added to cart', 'promo-engine' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Units sold', 'promo-engine' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Revenue', 'promo-engine' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Discount given', 'promo-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $products ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No data for this period.', 'promo-engine' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $products as $row ) : ?>
					<?php $product = wc_get_product( $row['product_id'] ); ?>
					<tr>
						<td>
							<?php if ( $product ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $row['product_id'] ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
							<?php else : ?>
								<?php
								/* translators: %d: product ID. */
								echo esc_html( sprintf( __( 'Deleted product #%d', 'promo-engine' ), $row['product_id'] ) );
								?>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row['added'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row['sold'] ) ); ?></td>
						<td class="num"><?php echo wp_kses_post( wc_price( $row['revenue'] ) ); ?></td>
						<td class="num"><?php echo wp_kses_post( wc_price( $row['discount'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
