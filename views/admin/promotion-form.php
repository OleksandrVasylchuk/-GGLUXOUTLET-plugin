<?php
/**
 * Promotion edit form.
 *
 * @package PromoEngine
 *
 * @var PromoEngine\Promotion\Promotion $promotion
 * @var string[]                        $errors
 * @var string                          $nonce
 * @var array<string, string>           $types
 * @var array<string, string>           $scopes
 * @var array<string, string>           $statuses
 */

use PromoEngine\Admin\Admin;
use PromoEngine\Promotion\Promotion;

defined( 'ABSPATH' ) || exit;

$datetime_value = static fn( ?int $timestamp ): string => $timestamp ? wp_date( 'Y-m-d\TH:i', $timestamp ) : '';

$term_options = static function ( string $taxonomy, array $selected ): void {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);

	if ( is_wp_error( $terms ) ) {
		return;
	}

	foreach ( $terms as $term ) {
		printf(
			'<option value="%d"%s>%s (%d)</option>',
			(int) $term->term_id,
			selected( in_array( (int) $term->term_id, $selected, true ), true, false ),
			esc_html( $term->name ),
			(int) $term->count
		);
	}
};

$scope_ids = static fn( string $scope ): array => $promotion->scope === $scope ? $promotion->scope_ids : array();
$popup     = $promotion->popup();
$tiers     = $promotion->tiers() ?: array(
	array(
		'threshold' => '',
		'percent'   => '',
	),
);
?>

<div class="wrap pe-admin">
	<h1 class="wp-heading-inline">
		<?php echo $promotion->id ? esc_html__( 'Edit promotion', 'promo-engine' ) : esc_html__( 'Add promotion', 'promo-engine' ); ?>
	</h1>
	<?php if ( $promotion->id ) : ?>
		<a href="<?php echo esc_url( Admin::url( Admin::PAGE_EDIT ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'promo-engine' ); ?></a>
		<a href="<?php echo esc_url( Admin::url( Admin::PAGE_ANALYTICS, array( 'promotion' => $promotion->id ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Analytics', 'promo-engine' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( $errors ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $errors as $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( Admin::url( Admin::PAGE_EDIT, $promotion->id ? array( 'id' => $promotion->id ) : array() ) ); ?>" class="pe-form" data-pe-form>
		<?php wp_nonce_field( $nonce ); ?>

		<h2 class="title"><?php esc_html_e( 'General', 'promo-engine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pe-name"><?php esc_html_e( 'Name', 'promo-engine' ); ?></label></th>
				<td><input type="text" id="pe-name" name="promotion[name]" value="<?php echo esc_attr( $promotion->name ); ?>" class="regular-text" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-status"><?php esc_html_e( 'Status', 'promo-engine' ); ?></label></th>
				<td>
					<select id="pe-status" name="promotion[status]">
						<?php foreach ( $statuses as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $promotion->status, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-priority"><?php esc_html_e( 'Priority', 'promo-engine' ); ?></label></th>
				<td>
					<input type="number" id="pe-priority" name="promotion[priority]" value="<?php echo esc_attr( $promotion->priority ); ?>" step="1" class="small-text">
					<p class="description"><?php esc_html_e( 'Higher wins when promotions compete. On a tie the older promotion (lower ID) wins.', 'promo-engine' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Discount', 'promo-engine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pe-type"><?php esc_html_e( 'Type', 'promo-engine' ); ?></label></th>
				<td>
					<select id="pe-type" name="promotion[type]" data-pe-type>
						<?php foreach ( $types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $promotion->type, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr data-pe-types="<?php echo esc_attr( Promotion::TYPE_PERCENT . ' ' . Promotion::TYPE_FIXED ); ?>">
				<th scope="row"><label for="pe-value"><?php esc_html_e( 'Value', 'promo-engine' ); ?></label></th>
				<td>
					<input type="text" inputmode="decimal" id="pe-value" name="promotion[value]" value="<?php echo esc_attr( wc_format_localized_decimal( $promotion->value ) ); ?>" class="small-text">
					<span class="description"><?php esc_html_e( 'Percent or amount in store currency, depending on type.', 'promo-engine' ); ?></span>
				</td>
			</tr>
			<tr data-pe-types="<?php echo esc_attr( Promotion::TYPE_BXGY ); ?>">
				<th scope="row"><?php esc_html_e( 'Deal', 'promo-engine' ); ?></th>
				<td class="pe-inline-fields">
					<label><?php esc_html_e( 'Buy', 'promo-engine' ); ?> <input type="number" min="1" name="promotion[buy_qty]" value="<?php echo esc_attr( $promotion->buy_qty() ); ?>" class="small-text"></label>
					<label><?php esc_html_e( 'get', 'promo-engine' ); ?> <input type="number" min="1" name="promotion[get_qty]" value="<?php echo esc_attr( $promotion->get_qty() ); ?>" class="small-text"></label>
					<label><?php esc_html_e( 'at discount, %', 'promo-engine' ); ?> <input type="number" min="1" max="100" step="0.01" name="promotion[get_discount]" value="<?php echo esc_attr( $promotion->get_discount() ); ?>" class="small-text"></label>
					<p class="description"><?php esc_html_e( 'The cheapest items in each group get the discount. 100% = free.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr data-pe-types="<?php echo esc_attr( Promotion::TYPE_BUNDLE ); ?>">
				<th scope="row"><?php esc_html_e( 'Bundle', 'promo-engine' ); ?></th>
				<td class="pe-inline-fields">
					<label><input type="number" min="2" name="promotion[bundle_qty]" value="<?php echo esc_attr( $promotion->bundle_qty() ); ?>" class="small-text"> <?php esc_html_e( 'items for', 'promo-engine' ); ?></label>
					<label><input type="text" inputmode="decimal" name="promotion[bundle_price]" value="<?php echo esc_attr( wc_format_localized_decimal( $promotion->bundle_price() ) ); ?>" class="small-text"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></label>
				</td>
			</tr>
			<tr data-pe-types="<?php echo esc_attr( Promotion::TYPE_CART ); ?>">
				<th scope="row"><?php esc_html_e( 'Thresholds', 'promo-engine' ); ?></th>
				<td>
					<table class="pe-tiers-table widefat striped" data-pe-tiers>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Subtotal from', 'promo-engine' ); ?></th>
								<th><?php esc_html_e( 'Discount, %', 'promo-engine' ); ?></th>
								<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'promo-engine' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_values( $tiers ) as $index => $tier ) : ?>
								<tr data-pe-tier>
									<td><input type="text" inputmode="decimal" name="promotion[tiers][<?php echo (int) $index; ?>][threshold]" value="<?php echo esc_attr( $tier['threshold'] ); ?>" aria-label="<?php esc_attr_e( 'Subtotal from', 'promo-engine' ); ?>"></td>
									<td><input type="text" inputmode="decimal" name="promotion[tiers][<?php echo (int) $index; ?>][percent]" value="<?php echo esc_attr( $tier['percent'] ); ?>" aria-label="<?php esc_attr_e( 'Discount, %', 'promo-engine' ); ?>"></td>
									<td><button type="button" class="button-link button-link-delete" data-pe-remove-tier><?php esc_html_e( 'Remove', 'promo-engine' ); ?></button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button" data-pe-add-tier><?php esc_html_e( 'Add threshold', 'promo-engine' ); ?></button></p>
					<p class="description"><?php esc_html_e( 'Only the highest reached threshold applies.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-max-discount"><?php esc_html_e( 'Maximum discount, %', 'promo-engine' ); ?></label></th>
				<td>
					<input type="number" id="pe-max-discount" name="promotion[max_discount]" min="0" max="100" step="0.01" value="<?php echo esc_attr( $promotion->max_discount ?? '' ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Leave empty for no cap.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Combination', 'promo-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="promotion[combinable]" value="1" <?php checked( $promotion->combinable ); ?>>
						<?php esc_html_e( 'Combines with other promotions', 'promo-engine' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'If one of the promotions on an item does not combine, only the highest priority one applies.', 'promo-engine' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Applies to', 'promo-engine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pe-scope"><?php esc_html_e( 'Scope', 'promo-engine' ); ?></label></th>
				<td>
					<select id="pe-scope" name="promotion[scope]" data-pe-scope>
						<?php foreach ( $scopes as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $promotion->scope, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr data-pe-scopes="<?php echo esc_attr( Promotion::SCOPE_PRODUCTS ); ?>">
				<th scope="row"><label for="pe-products"><?php esc_html_e( 'Products', 'promo-engine' ); ?></label></th>
				<td>
					<select id="pe-products" name="promotion[products][]" class="wc-product-search pe-select" multiple data-action="woocommerce_json_search_products_and_variations" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'promo-engine' ); ?>">
						<?php foreach ( $scope_ids( Promotion::SCOPE_PRODUCTS ) as $product_id ) : ?>
							<?php $product = wc_get_product( $product_id ); ?>
							<?php if ( $product ) : ?>
								<option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr data-pe-scopes="<?php echo esc_attr( Promotion::SCOPE_CATEGORY ); ?>">
				<th scope="row"><label for="pe-categories"><?php esc_html_e( 'Categories', 'promo-engine' ); ?></label></th>
				<td>
					<select id="pe-categories" name="promotion[categories][]" class="wc-enhanced-select pe-select" multiple data-placeholder="<?php esc_attr_e( 'Choose categories&hellip;', 'promo-engine' ); ?>">
						<?php $term_options( 'product_cat', $scope_ids( Promotion::SCOPE_CATEGORY ) ); ?>
					</select>
					<p class="description"><?php esc_html_e( 'Subcategories are included.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr data-pe-scopes="<?php echo esc_attr( Promotion::SCOPE_TAG ); ?>">
				<th scope="row"><label for="pe-tags"><?php esc_html_e( 'Tags', 'promo-engine' ); ?></label></th>
				<td>
					<select id="pe-tags" name="promotion[tags][]" class="wc-enhanced-select pe-select" multiple data-placeholder="<?php esc_attr_e( 'Choose tags&hellip;', 'promo-engine' ); ?>">
						<?php $term_options( 'product_tag', $scope_ids( Promotion::SCOPE_TAG ) ); ?>
					</select>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Schedule & limits', 'promo-engine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pe-starts"><?php esc_html_e( 'Starts', 'promo-engine' ); ?></label></th>
				<td><input type="datetime-local" id="pe-starts" name="promotion[starts_at]" value="<?php echo esc_attr( $datetime_value( $promotion->starts_at ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-ends"><?php esc_html_e( 'Ends', 'promo-engine' ); ?></label></th>
				<td>
					<input type="datetime-local" id="pe-ends" name="promotion[ends_at]" value="<?php echo esc_attr( $datetime_value( $promotion->ends_at ) ); ?>">
					<p class="description">
						<?php
						/* translators: %s: timezone name. */
						echo esc_html( sprintf( __( 'Site timezone: %s. Leave empty for no limit.', 'promo-engine' ), wp_timezone_string() ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-usage-limit"><?php esc_html_e( 'Usage limit', 'promo-engine' ); ?></label></th>
				<td>
					<input type="number" id="pe-usage-limit" name="promotion[usage_limit]" min="0" value="<?php echo esc_attr( $promotion->usage_limit ); ?>" class="small-text">
					<p class="description">
						<?php
						/* translators: %d: number of orders that used the promotion. */
						echo esc_html( sprintf( __( 'Number of paid orders. 0 means unlimited. Used so far: %d.', 'promo-engine' ), $promotion->usage_count ) );
						?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Popup', 'promo-engine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Show popup', 'promo-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="promotion[popup][enabled]" value="1" <?php checked( $popup['enabled'] ); ?>>
						<?php esc_html_e( 'Announce this promotion in a popup (once per session, never on checkout)', 'promo-engine' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Shows a countdown when the promotion has an end date.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-popup-title"><?php esc_html_e( 'Title', 'promo-engine' ); ?></label></th>
				<td><input type="text" id="pe-popup-title" name="promotion[popup][title]" value="<?php echo esc_attr( $popup['title'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $promotion->name ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-popup-text"><?php esc_html_e( 'Text', 'promo-engine' ); ?></label></th>
				<td><textarea id="pe-popup-text" name="promotion[popup][text]" rows="3" class="large-text"><?php echo esc_textarea( $popup['text'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="pe-popup-cta"><?php esc_html_e( 'Button', 'promo-engine' ); ?></label></th>
				<td class="pe-inline-fields">
					<input type="text" id="pe-popup-cta" name="promotion[popup][cta_label]" value="<?php echo esc_attr( $popup['cta_label'] ); ?>" placeholder="<?php esc_attr_e( 'Shop the deal', 'promo-engine' ); ?>" aria-label="<?php esc_attr_e( 'Button label', 'promo-engine' ); ?>">
					<input type="url" name="promotion[popup][cta_url]" value="<?php echo esc_attr( $popup['cta_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( \PromoEngine\Front\Deals_Page::url() ); ?>" aria-label="<?php esc_attr_e( 'Button link', 'promo-engine' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'A/B test', 'promo-engine' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="promotion[popup][ab_test]" value="1" <?php checked( $popup['ab_test'] ); ?> data-pe-ab-toggle>
						<?php esc_html_e( 'Show variant B to half of the visitors', 'promo-engine' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'The copy above is variant A. Results are in Analytics.', 'promo-engine' ); ?></p>
				</td>
			</tr>
			<tr data-pe-ab-row>
				<th scope="row"><label for="pe-popup-b-title"><?php esc_html_e( 'Variant B title', 'promo-engine' ); ?></label></th>
				<td><input type="text" id="pe-popup-b-title" name="promotion[popup][b][title]" value="<?php echo esc_attr( $popup['b']['title'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Same as variant A', 'promo-engine' ); ?>"></td>
			</tr>
			<tr data-pe-ab-row>
				<th scope="row"><label for="pe-popup-b-text"><?php esc_html_e( 'Variant B text', 'promo-engine' ); ?></label></th>
				<td><textarea id="pe-popup-b-text" name="promotion[popup][b][text]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Same as variant A', 'promo-engine' ); ?>"><?php echo esc_textarea( $popup['b']['text'] ); ?></textarea></td>
			</tr>
			<tr data-pe-ab-row>
				<th scope="row"><label for="pe-popup-b-cta"><?php esc_html_e( 'Variant B button', 'promo-engine' ); ?></label></th>
				<td><input type="text" id="pe-popup-b-cta" name="promotion[popup][b][cta_label]" value="<?php echo esc_attr( $popup['b']['cta_label'] ); ?>" placeholder="<?php esc_attr_e( 'Same as variant A', 'promo-engine' ); ?>"></td>
			</tr>
		</table>

		<?php submit_button( $promotion->id ? __( 'Update promotion', 'promo-engine' ) : __( 'Create promotion', 'promo-engine' ) ); ?>
	</form>

	<template data-pe-tier-template>
		<tr data-pe-tier>
			<td><input type="text" inputmode="decimal" name="promotion[tiers][__i__][threshold]" aria-label="<?php esc_attr_e( 'Subtotal from', 'promo-engine' ); ?>"></td>
			<td><input type="text" inputmode="decimal" name="promotion[tiers][__i__][percent]" aria-label="<?php esc_attr_e( 'Discount, %', 'promo-engine' ); ?>"></td>
			<td><button type="button" class="button-link button-link-delete" data-pe-remove-tier><?php esc_html_e( 'Remove', 'promo-engine' ); ?></button></td>
		</tr>
	</template>
</div>
