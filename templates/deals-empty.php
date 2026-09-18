<?php
/**
 * Deals page without running promotions.
 *
 * Override by copying to yourtheme/promo-engine/deals-empty.php.
 *
 * @package PromoEngine
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="pe-deals__empty">
	<p><?php esc_html_e( 'There are no active deals right now. Check back soon.', 'promo-engine' ); ?></p>
	<a class="pe-button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Continue shopping', 'promo-engine' ); ?></a>
</div>
