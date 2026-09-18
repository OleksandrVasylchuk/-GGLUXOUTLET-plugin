<?php
/**
 * One promotion on the deals page.
 *
 * Override by copying to yourtheme/promo-engine/deals-section.php.
 *
 * @package PromoEngine
 *
 * @var array{promotion: PromoEngine\Promotion\Promotion, products: WP_Query|null} $args
 */

use PromoEngine\Promotion\Labels;
use PromoEngine\Promotion\Promotion;

defined( 'ABSPATH' ) || exit;

$promotion = $args['promotion'];
$products  = $args['products'];
$heading   = 'pe-deal-' . $promotion->id;
?>

<section class="pe-deal" aria-labelledby="<?php echo esc_attr( $heading ); ?>">
	<header class="pe-deal__header">
		<div class="pe-deal__heading">
			<span class="pe-badge"><?php echo wp_kses_post( Labels::badge( $promotion ) ); ?></span>
			<h2 id="<?php echo esc_attr( $heading ); ?>" class="pe-deal__title"><?php echo esc_html( $promotion->name ); ?></h2>
		</div>

		<?php if ( $promotion->ends_at ) : ?>
			<p class="pe-deal__timer">
				<?php esc_html_e( 'Ends in', 'promo-engine' ); ?>
				<time class="pe-countdown" role="timer" datetime="<?php echo esc_attr( gmdate( 'c', $promotion->ends_at ) ); ?>" data-pe-countdown="<?php echo esc_attr( $promotion->ends_at ); ?>">
					<?php echo esc_html( human_time_diff( time(), $promotion->ends_at ) ); ?>
				</time>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( Promotion::TYPE_CART === $promotion->type ) : ?>
		<ul class="pe-tiers">
			<?php foreach ( $promotion->tiers() as $tier ) : ?>
				<li class="pe-tiers__item">
					<span class="pe-tiers__threshold">
						<?php
						/* translators: %s: cart subtotal threshold. */
						echo wp_kses_post( sprintf( __( 'Spend %s', 'promo-engine' ), wc_price( $tier['threshold'] ) ) );
						?>
					</span>
					<strong class="pe-tiers__percent">&minus;<?php echo esc_html( wc_format_localized_decimal( $tier['percent'] ) ); ?>%</strong>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( $products && $products->have_posts() ) : ?>
		<div class="products-grid pe-deal__products">
			<?php
			while ( $products->have_posts() ) {
				$products->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			wp_reset_postdata();
			?>
		</div>
	<?php endif; ?>
</section>
