<?php
/**
 * Deals page.
 *
 * Override by copying to yourtheme/promo-engine/deals.php.
 *
 * @package PromoEngine
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main pe-deals" role="main">
	<div class="container">
		<header class="pe-deals__header">
			<h1 class="shop-page__title pe-deals__title"><?php esc_html_e( 'Deals', 'promo-engine' ); ?></h1>
			<p class="pe-deals__lead"><?php esc_html_e( 'Current offers. Discounts are applied automatically in your cart.', 'promo-engine' ); ?></p>
		</header>

		<?php do_action( 'promo_engine_deals_content' ); ?>
	</div>
</main>

<?php
get_footer();
