<?php
/**
 * Promotions list table.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Admin;

use PromoEngine\Promotion\Labels;
use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin list of promotions.
 */
final class Promotions_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 */
	public function __construct( private Repository $promotions ) {
		parent::__construct(
			array(
				'singular' => 'promotion',
				'plural'   => 'promotions',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Load items.
	 */
	public function prepare_items(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = $this->get_items_per_page( 'promo_engine_per_page', 20 );
		$page     = $this->promotions->paginate(
			array(
				'search'   => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
				'status'   => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
				'orderby'  => sanitize_key( wp_unslash( $_GET['orderby'] ?? 'priority' ) ),
				'order'    => sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ),
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable

		$this->items           = $page['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );

		$this->set_pagination_args(
			array(
				'total_items' => $page['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'promo-engine' ),
			'type'       => __( 'Discount', 'promo-engine' ),
			'scope'      => __( 'Applies to', 'promo-engine' ),
			'schedule'   => __( 'Schedule', 'promo-engine' ),
			'priority'   => __( 'Priority', 'promo-engine' ),
			'combinable' => __( 'Combines', 'promo-engine' ),
			'usage'      => __( 'Used', 'promo-engine' ),
			'status'     => __( 'Status', 'promo-engine' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'priority' => array( 'priority', true ),
			'schedule' => array( 'starts_at', false ),
			'status'   => array( 'status', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'activate' => __( 'Activate', 'promo-engine' ),
			'pause'    => __( 'Pause', 'promo-engine' ),
			'delete'   => __( 'Delete', 'promo-engine' ),
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views   = array(
			'' => __( 'All', 'promo-engine' ),
		) + Labels::statuses();

		$links = array();

		foreach ( $views as $status => $label ) {
			$links[ $status ?: 'all' ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( Admin::url( Admin::PAGE_LIST, $status ? array( 'status' => $status ) : array() ) ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}

		return $links;
	}

	/**
	 * Checkbox column.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="promotion[]" value="%d" />', $item->id );
	}

	/**
	 * Name with row actions.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_name( $item ) {
		$edit_url = Admin::url( Admin::PAGE_EDIT, array( 'id' => $item->id ) );
		$toggle   = Promotion::STATUS_ACTIVE === $item->status ? Promotion::STATUS_PAUSED : Promotion::STATUS_ACTIVE;

		$actions = array(
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'promo-engine' ) ),
			'status'    => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin-post.php?action=promo_engine_status&id=' . $item->id . '&status=' . $toggle ),
						'promo_engine_status_' . $item->id
					)
				),
				Promotion::STATUS_ACTIVE === $toggle ? esc_html__( 'Activate', 'promo-engine' ) : esc_html__( 'Pause', 'promo-engine' )
			),
			'analytics' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( Admin::url( Admin::PAGE_ANALYTICS, array( 'promotion' => $item->id ) ) ),
				esc_html__( 'Analytics', 'promo-engine' )
			),
			'delete'    => sprintf(
				'<a href="%s" class="submitdelete" data-pe-confirm="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=promo_engine_delete&id=' . $item->id ), 'promo_engine_delete_' . $item->id ) ),
				esc_attr__( 'Delete this promotion?', 'promo-engine' ),
				esc_html__( 'Delete', 'promo-engine' )
			),
		);

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong> <span class="pe-id">#%d</span>%s',
			esc_url( $edit_url ),
			esc_html( $item->name ),
			$item->id,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Discount column.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_type( $item ) {
		return sprintf(
			'<span class="pe-badge">%s</span><br><span class="description">%s</span>',
			wp_kses_post( Labels::badge( $item ) ),
			esc_html( Labels::types()[ $item->type ] ?? $item->type )
		);
	}

	/**
	 * Scope column.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_scope( $item ) {
		$label = esc_html( Labels::scopes()[ $item->scope ] ?? $item->scope );

		if ( Promotion::SCOPE_ALL === $item->scope ) {
			return $label;
		}

		if ( Promotion::SCOPE_PRODUCTS === $item->scope ) {
			/* translators: %d: number of products. */
			return $label . '<br><span class="description">' . esc_html( sprintf( _n( '%d product', '%d products', count( $item->scope_ids ), 'promo-engine' ), count( $item->scope_ids ) ) ) . '</span>';
		}

		$taxonomy = Promotion::SCOPE_CATEGORY === $item->scope ? 'product_cat' : 'product_tag';
		$names    = array();

		foreach ( $item->scope_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		return $label . '<br><span class="description">' . esc_html( implode( ', ', $names ) ) . '</span>';
	}

	/**
	 * Schedule column, site timezone.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_schedule( $item ) {
		if ( ! $item->starts_at && ! $item->ends_at ) {
			return esc_html__( 'Always', 'promo-engine' );
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return sprintf(
			'%s &ndash;<br>%s',
			esc_html( $item->starts_at ? wp_date( $format, $item->starts_at ) : __( 'Now', 'promo-engine' ) ),
			esc_html( $item->ends_at ? wp_date( $format, $item->ends_at ) : __( 'No end date', 'promo-engine' ) )
		);
	}

	/**
	 * Priority column.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_priority( $item ) {
		return (string) $item->priority;
	}

	/**
	 * Combination column.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_combinable( $item ) {
		return $item->combinable ? esc_html__( 'Yes', 'promo-engine' ) : esc_html__( 'No', 'promo-engine' );
	}

	/**
	 * Usage column.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_usage( $item ) {
		return $item->usage_limit ? sprintf( '%d / %d', $item->usage_count, $item->usage_limit ) : (string) $item->usage_count;
	}

	/**
	 * Status column. Distinguishes scheduled and expired from plain active.
	 *
	 * @param Promotion $item Promotion.
	 */
	protected function column_status( $item ) {
		$now = time();

		if ( Promotion::STATUS_PAUSED === $item->status ) {
			$state = array( 'paused', __( 'Paused', 'promo-engine' ) );
		} elseif ( $item->is_running( $now ) ) {
			$state = array( 'running', __( 'Running', 'promo-engine' ) );
		} elseif ( $item->starts_at && $item->starts_at > $now ) {
			$state = array( 'scheduled', __( 'Scheduled', 'promo-engine' ) );
		} elseif ( $item->usage_limit && $item->usage_count >= $item->usage_limit ) {
			$state = array( 'expired', __( 'Limit reached', 'promo-engine' ) );
		} else {
			$state = array( 'expired', __( 'Ended', 'promo-engine' ) );
		}

		return sprintf( '<span class="pe-status pe-status--%s">%s</span>', esc_attr( $state[0] ), esc_html( $state[1] ) );
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No promotions yet. Create one or load the demo set below.', 'promo-engine' );
	}
}
