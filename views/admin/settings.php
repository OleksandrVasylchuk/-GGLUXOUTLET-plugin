<?php
/**
 * Settings screen.
 *
 * @package PromoEngine
 *
 * @var array<string, int|float> $settings
 */

use PromoEngine\Settings;

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap pe-admin">
	<h1><?php esc_html_e( 'Promotion settings', 'promo-engine' ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'promo_engine' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pe-global-cap"><?php esc_html_e( 'Maximum item discount, %', 'promo-engine' ); ?></label></th>
				<td>
					<input type="number" id="pe-global-cap" name="<?php echo esc_attr( Settings::OPTION ); ?>[global_cap]" value="<?php echo esc_attr( $settings['global_cap'] ); ?>" min="0" max="100" step="0.01" class="small-text">
					<p class="description"><?php esc_html_e( 'Combined percentage and fixed discounts on one item never exceed this share of its price. Deals and cart discounts are not affected.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-popup-delay"><?php esc_html_e( 'Popup delay, seconds', 'promo-engine' ); ?></label></th>
				<td><input type="number" id="pe-popup-delay" name="<?php echo esc_attr( Settings::OPTION ); ?>[popup_delay]" value="<?php echo esc_attr( $settings['popup_delay'] ); ?>" min="0" max="120" class="small-text"></td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
