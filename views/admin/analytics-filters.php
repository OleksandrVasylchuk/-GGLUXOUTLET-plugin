<?php
/**
 * Date range filter shared by analytics screens.
 *
 * @package PromoEngine
 *
 * @var array{promotion: int, from: string, to: string} $filters
 */

use PromoEngine\Admin\Admin;

defined( 'ABSPATH' ) || exit;
?>

<form method="get" class="pe-filters pe-inline-fields">
	<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE_ANALYTICS ); ?>">
	<?php if ( $filters['promotion'] ) : ?>
		<input type="hidden" name="promotion" value="<?php echo esc_attr( $filters['promotion'] ); ?>">
	<?php endif; ?>

	<label><?php esc_html_e( 'From', 'promo-engine' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>"></label>
	<label><?php esc_html_e( 'To', 'promo-engine' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>"></label>
	<?php submit_button( __( 'Apply', 'promo-engine' ), 'secondary', '', false ); ?>
</form>
