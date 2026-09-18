<?php
/**
 * Discount per promotion. Shared by the mini-cart, cart and checkout blocks.
 *
 * Override by copying to yourtheme/promo-engine/savings-list.php.
 *
 * @package PromoEngine
 *
 * @var array{applied: array<int, array{name: string, amount: float}>} $args
 */

defined( 'ABSPATH' ) || exit;
?>

<ul class="pe-savings__list">
	<?php foreach ( $args['applied'] as $applied ) : ?>
		<li class="pe-savings__item">
			<span><?php echo esc_html( $applied['name'] ); ?></span>
			<span class="pe-savings__amount">&minus;<?php echo wp_kses_post( wc_price( $applied['amount'] ) ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>
