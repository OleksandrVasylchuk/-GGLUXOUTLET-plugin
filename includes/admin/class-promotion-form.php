<?php
/**
 * Promotion create / edit screen.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Admin;

use DateTimeImmutable;
use PromoEngine\Promotion\Labels;
use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the form on the load-{page} hook, before any output, so a valid
 * submission can redirect and an invalid one re-renders with errors.
 */
final class Promotion_Form {

	private const NONCE = 'promo_engine_save_promotion';

	private ?Promotion $promotion = null;

	/**
	 * Validation errors.
	 *
	 * @var string[]
	 */
	private array $errors = array();

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 */
	public function __construct( private Repository $promotions ) {
	}

	/**
	 * Load the edited promotion and process a submission.
	 */
	public function handle(): void {
		$id = absint( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->promotion = $id ? $this->promotions->find( $id ) : $this->defaults();

		if ( ! $this->promotion ) {
			wp_die( esc_html__( 'Promotion not found.', 'promo-engine' ), 404 );
		}

		if ( ! isset( $_POST['promotion'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE );

		Admin::check_capability();

		$input        = wp_unslash( (array) ( $_POST['promotion'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in fill().
		$this->errors = $this->fill( $this->promotion, $input );

		if ( $this->errors ) {
			return;
		}

		$id = $this->promotions->save( $this->promotion );

		wp_safe_redirect(
			Admin::url(
				Admin::PAGE_EDIT,
				array(
					'id'        => $id,
					'pe_notice' => 'saved',
				)
			)
		);
		exit;
	}

	/**
	 * Print the form.
	 */
	public function render(): void {
		$promotion = $this->promotion ?? $this->defaults();
		$errors    = $this->errors;
		$nonce     = self::NONCE;
		$types     = Labels::types();
		$scopes    = Labels::scopes();
		$statuses  = Labels::statuses();

		include PROMO_ENGINE_DIR . 'views/admin/promotion-form.php';
	}

	/**
	 * Apply sanitized input to the promotion.
	 *
	 * @param Promotion            $promotion Promotion to fill.
	 * @param array<string, mixed> $input     Unslashed input.
	 * @return string[] Errors.
	 */
	private function fill( Promotion $promotion, array $input ): array {
		$errors = array();

		$promotion->name       = sanitize_text_field( $input['name'] ?? '' );
		$promotion->status     = isset( Labels::statuses()[ $input['status'] ?? '' ] ) ? $input['status'] : Promotion::STATUS_PAUSED;
		$promotion->priority   = (int) ( $input['priority'] ?? 10 );
		$promotion->type       = isset( Labels::types()[ $input['type'] ?? '' ] ) ? $input['type'] : Promotion::TYPE_PERCENT;
		$promotion->value      = max( 0.0, (float) wc_format_decimal( $input['value'] ?? 0 ) );
		$promotion->scope      = isset( Labels::scopes()[ $input['scope'] ?? '' ] ) ? $input['scope'] : Promotion::SCOPE_ALL;
		$promotion->combinable = ! empty( $input['combinable'] );

		$promotion->max_discount = '' === trim( (string) ( $input['max_discount'] ?? '' ) )
			? null
			: min( 100.0, max( 0.0, (float) wc_format_decimal( $input['max_discount'] ) ) );

		$promotion->usage_limit = absint( $input['usage_limit'] ?? 0 );

		$promotion->scope_ids = match ( $promotion->scope ) {
			Promotion::SCOPE_PRODUCTS => array_map( 'absint', (array) ( $input['products'] ?? array() ) ),
			Promotion::SCOPE_CATEGORY => array_map( 'absint', (array) ( $input['categories'] ?? array() ) ),
			Promotion::SCOPE_TAG      => array_map( 'absint', (array) ( $input['tags'] ?? array() ) ),
			default                   => array(),
		};
		$promotion->scope_ids = array_values( array_unique( array_filter( $promotion->scope_ids ) ) );

		$promotion->starts_at = $this->parse_datetime( $input['starts_at'] ?? '' );
		$promotion->ends_at   = $this->parse_datetime( $input['ends_at'] ?? '' );

		$promotion->config = array(
			'buy_qty'      => max( 1, absint( $input['buy_qty'] ?? 2 ) ),
			'get_qty'      => max( 1, absint( $input['get_qty'] ?? 1 ) ),
			'get_discount' => min( 100.0, max( 0.0, (float) wc_format_decimal( $input['get_discount'] ?? 100 ) ) ),
			'bundle_qty'   => max( 2, absint( $input['bundle_qty'] ?? 2 ) ),
			'bundle_price' => max( 0.0, (float) wc_format_decimal( $input['bundle_price'] ?? 0 ) ),
			'tiers'        => $this->parse_tiers( (array) ( $input['tiers'] ?? array() ) ),
			'popup'        => array(
				'enabled'   => ! empty( $input['popup']['enabled'] ),
				'title'     => sanitize_text_field( $input['popup']['title'] ?? '' ),
				'text'      => sanitize_textarea_field( $input['popup']['text'] ?? '' ),
				'cta_label' => sanitize_text_field( $input['popup']['cta_label'] ?? '' ),
				'cta_url'   => esc_url_raw( $input['popup']['cta_url'] ?? '' ),
				'ab_test'   => ! empty( $input['popup']['ab_test'] ),
				'b'         => array(
					'title'     => sanitize_text_field( $input['popup']['b']['title'] ?? '' ),
					'text'      => sanitize_textarea_field( $input['popup']['b']['text'] ?? '' ),
					'cta_label' => sanitize_text_field( $input['popup']['b']['cta_label'] ?? '' ),
				),
			),
		);

		if ( '' === $promotion->name ) {
			$errors[] = __( 'Name is required.', 'promo-engine' );
		}

		if ( in_array( $promotion->type, array( Promotion::TYPE_PERCENT, Promotion::TYPE_FIXED ), true ) && $promotion->value <= 0 ) {
			$errors[] = __( 'Discount value must be greater than zero.', 'promo-engine' );
		}

		if ( Promotion::TYPE_PERCENT === $promotion->type && $promotion->value > 100 ) {
			$errors[] = __( 'Percentage discount cannot exceed 100%.', 'promo-engine' );
		}

		if ( Promotion::TYPE_BUNDLE === $promotion->type && $promotion->bundle_price() <= 0 ) {
			$errors[] = __( 'Bundle price must be greater than zero.', 'promo-engine' );
		}

		if ( Promotion::TYPE_CART === $promotion->type && ! $promotion->tiers() ) {
			$errors[] = __( 'Add at least one cart threshold.', 'promo-engine' );
		}

		if ( Promotion::SCOPE_ALL !== $promotion->scope && ! $promotion->scope_ids ) {
			$errors[] = __( 'Select at least one product, category or tag.', 'promo-engine' );
		}

		if ( $promotion->starts_at && $promotion->ends_at && $promotion->ends_at <= $promotion->starts_at ) {
			$errors[] = __( 'End date must be after the start date.', 'promo-engine' );
		}

		return $errors;
	}

	/**
	 * Cart thresholds from repeater rows.
	 *
	 * @param array<int, array{threshold?: string, percent?: string}> $rows Rows.
	 * @return array<int, array{threshold: float, percent: float}>
	 */
	private function parse_tiers( array $rows ): array {
		$rows = array_map(
			static fn( $row ): array => array(
				'threshold' => wc_format_decimal( $row['threshold'] ?? 0 ),
				'percent'   => wc_format_decimal( $row['percent'] ?? 0 ),
			),
			$rows
		);

		return Promotion::normalize_tiers( $rows );
	}

	/**
	 * A datetime-local value in the site timezone to a timestamp.
	 *
	 * @param string $value Y-m-d\TH:i.
	 */
	private function parse_datetime( string $value ): ?int {
		$value = sanitize_text_field( $value );
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );

		// createFromFormat() silently rolls over invalid dates such as 2026-13-45.
		return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $date->getTimestamp() : null;
	}

	/**
	 * A new, paused promotion.
	 */
	private function defaults(): Promotion {
		return Promotion::from_array(
			array(
				'status'   => Promotion::STATUS_PAUSED,
				'priority' => 10,
				'config'   => array(
					'tiers' => array(),
				),
			)
		);
	}
}
