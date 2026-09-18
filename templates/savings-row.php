<?php
/**
 * Savings row in the cart totals and checkout order review tables.
 *
 * Override by copying to yourtheme/promo-engine/savings-row.php.
 *
 * @package PromoEngine
 *
 * @var array{applied: array<int, array{name: string, amount: float}>, total: float} $args
 */

use PromoEngine\Front\Template;

defined( 'ABSPATH' ) || exit;
?>

<tr class="pe-savings">
	<th><?php esc_html_e( 'You save', 'promo-engine' ); ?></th>
	<td data-title="<?php esc_attr_e( 'You save', 'promo-engine' ); ?>">
		<strong class="pe-savings__total"><?php echo wp_kses_post( wc_price( $args['total'] ) ); ?></strong>
		<?php Template::render( 'savings-list', $args ); ?>
	</td>
</tr>
