<?php
/**
 * Mini-cart promotions block. Part of the mini-cart fragment.
 *
 * Override by copying to yourtheme/promo-engine/cart/mini-cart-summary.php.
 *
 * @package PromoEngine
 *
 * @var array{applied: array<int, array{name: string, amount: float}>, total: float, next: array<string, mixed>|null} $args
 */

use PromoEngine\Front\Template;

defined( 'ABSPATH' ) || exit;

$next = $args['next'];
?>

<div class="pe-cart-summary">
	<?php if ( $args['applied'] ) : ?>
		<?php Template::render( 'savings-list', $args ); ?>

		<p class="pe-cart-summary__total">
			<span><?php esc_html_e( 'You save', 'promo-engine' ); ?></span>
			<strong><?php echo wp_kses_post( wc_price( $args['total'] ) ); ?></strong>
		</p>
	<?php endif; ?>

	<?php if ( $next ) : ?>
		<div class="pe-progress">
			<p class="pe-progress__text">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: amount left to spend, 2: discount percent. */
						__( 'Add %1$s more to get %2$s', 'promo-engine' ),
						'<strong>' . wc_price( $next['remaining'] ) . '</strong>',
						'<strong>&minus;' . esc_html( wc_format_localized_decimal( $next['percent'] ) ) . '%</strong>'
					)
				);
				?>
			</p>
			<div
				class="pe-progress__bar"
				role="progressbar"
				aria-label="<?php esc_attr_e( 'Progress to the next discount', 'promo-engine' ); ?>"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow="<?php echo esc_attr( (int) $next['progress'] ); ?>"
			>
				<span class="pe-progress__fill" style="width: <?php echo esc_attr( $next['progress'] ); ?>%"></span>
			</div>
		</div>
	<?php endif; ?>
</div>
