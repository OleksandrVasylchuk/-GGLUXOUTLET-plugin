<?php
/**
 * Savings in the checkout order review of multibrand-theme, which replaces
 * the WooCommerce totals table with div rows.
 *
 * Override by copying to yourtheme/promo-engine/checkout/savings-multibrand.php.
 *
 * @package PromoEngine
 *
 * @var array{applied: array<int, array{name: string, amount: float}>, total: float} $args
 */

use PromoEngine\Front\Template;

defined( 'ABSPATH' ) || exit;
?>

<div class="cart-discount pe-savings">
	<span class="totals-heading"><?php esc_html_e( 'You save', 'promo-engine' ); ?></span>
	<span class="pe-savings__total"><?php echo wp_kses_post( wc_price( $args['total'] ) ); ?></span>
</div>
<?php
Template::render( 'savings-list', $args );
