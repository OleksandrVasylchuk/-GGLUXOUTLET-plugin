<?php
/**
 * Promotions list screen.
 *
 * @package PromoEngine
 *
 * @var PromoEngine\Admin\Promotions_Table $table
 */

use PromoEngine\Admin\Admin;
use PromoEngine\Front\Deals_Page;

defined( 'ABSPATH' ) || exit;

$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'name',
	)
);
?>

<div class="wrap pe-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Promotions', 'promo-engine' ); ?></h1>
	<a href="<?php echo esc_url( Admin::url( Admin::PAGE_EDIT ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'promo-engine' ); ?></a>
	<a href="<?php echo esc_url( Deals_Page::url() ); ?>" class="page-title-action" target="_blank"><?php esc_html_e( 'View deals page', 'promo-engine' ); ?></a>
	<hr class="wp-header-end">

	<?php $table->views(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE_LIST ); ?>">
		<?php
		$table->search_box( __( 'Search promotions', 'promo-engine' ), 'promotion' );
		$table->display();
		?>
	</form>

	<div class="pe-demo card">
		<h2 class="title"><?php esc_html_e( 'Demo promotions', 'promo-engine' ); ?></h2>
		<p><?php esc_html_e( 'Creates the five reference promotions. Loading again replaces the previous demo set; your own promotions are not touched.', 'promo-engine' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pe-inline-fields">
			<input type="hidden" name="action" value="promo_engine_seed">
			<?php wp_nonce_field( 'promo_engine_seed' ); ?>

			<?php for ( $i = 0; $i < 3; $i++ ) : ?>
				<label>
					<?php
					/* translators: %d: category number from the spec (1-3). */
					echo esc_html( sprintf( __( 'Category %d', 'promo-engine' ), $i + 1 ) );
					?>
					<select name="categories[]" required>
						<option value=""><?php esc_html_e( '&mdash; Select &mdash;', 'promo-engine' ); ?></option>
						<?php foreach ( is_array( $categories ) ? $categories : array() as $category ) : ?>
							<option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name . ' (' . $category->count . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endfor; ?>

			<?php submit_button( __( 'Load demo', 'promo-engine' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
</div>
