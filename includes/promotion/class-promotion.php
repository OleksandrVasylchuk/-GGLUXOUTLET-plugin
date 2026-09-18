<?php
/**
 * Promotion entity.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Promotion;

use PromoEngine\Pricing\Line;

defined( 'ABSPATH' ) || exit;

/**
 * A single promotion rule. Type-specific options live in $config.
 */
final class Promotion {

	public const STATUS_ACTIVE = 'active';
	public const STATUS_PAUSED = 'paused';

	public const TYPE_PERCENT = 'percent';
	public const TYPE_FIXED   = 'fixed';
	public const TYPE_BXGY    = 'bxgy';
	public const TYPE_BUNDLE  = 'bundle';
	public const TYPE_CART    = 'cart';

	public const SCOPE_PRODUCTS = 'products';
	public const SCOPE_CATEGORY = 'category';
	public const SCOPE_TAG      = 'tag';
	public const SCOPE_ALL      = 'all';

	public const STAGE_ITEM = 1;
	public const STAGE_DEAL = 2;
	public const STAGE_CART = 3;

	public int $id          = 0;
	public string $name     = '';
	public string $status   = self::STATUS_PAUSED;
	public int $priority    = 10;
	public string $type     = self::TYPE_PERCENT;
	public float $value     = 0.0;
	public string $scope    = self::SCOPE_ALL;
	public bool $combinable = true;
	public ?int $starts_at  = null;
	public ?int $ends_at    = null;

	/**
	 * Upper bound for this promotion's own discount, percent. Null means no limit.
	 *
	 * @var float|null
	 */
	public ?float $max_discount = null;

	public int $usage_limit = 0;
	public int $usage_count = 0;

	/**
	 * Product, category or tag IDs depending on $scope.
	 *
	 * @var int[]
	 */
	public array $scope_ids = array();

	/**
	 * Type-specific options: buy_qty, get_qty, get_discount, bundle_qty, bundle_price, tiers, popup.
	 *
	 * @var array<string, mixed>
	 */
	public array $config = array();

	/**
	 * Build from a plain array (DB row, cache entry or fixture).
	 *
	 * @param array<string, mixed> $data Promotion data.
	 */
	public static function from_array( array $data ): self {
		$promotion = new self();

		$promotion->id           = (int) ( $data['id'] ?? 0 );
		$promotion->name         = (string) ( $data['name'] ?? '' );
		$promotion->status       = (string) ( $data['status'] ?? self::STATUS_PAUSED );
		$promotion->priority     = (int) ( $data['priority'] ?? 10 );
		$promotion->type         = (string) ( $data['type'] ?? self::TYPE_PERCENT );
		$promotion->value        = (float) ( $data['value'] ?? 0 );
		$promotion->scope        = (string) ( $data['scope'] ?? self::SCOPE_ALL );
		$promotion->scope_ids    = array_values( array_map( 'intval', (array) ( $data['scope_ids'] ?? array() ) ) );
		$promotion->combinable   = (bool) ( $data['combinable'] ?? true );
		$promotion->starts_at    = isset( $data['starts_at'] ) ? (int) $data['starts_at'] : null;
		$promotion->ends_at      = isset( $data['ends_at'] ) ? (int) $data['ends_at'] : null;
		$promotion->max_discount = isset( $data['max_discount'] ) && '' !== $data['max_discount'] ? (float) $data['max_discount'] : null;
		$promotion->usage_limit  = (int) ( $data['usage_limit'] ?? 0 );
		$promotion->usage_count  = (int) ( $data['usage_count'] ?? 0 );
		$promotion->config       = (array) ( $data['config'] ?? array() );

		return $promotion;
	}

	/**
	 * Pipeline stage the promotion belongs to.
	 */
	public function stage(): int {
		return match ( $this->type ) {
			self::TYPE_BXGY, self::TYPE_BUNDLE => self::STAGE_DEAL,
			self::TYPE_CART                    => self::STAGE_CART,
			default                            => self::STAGE_ITEM,
		};
	}

	/**
	 * Whether the promotion is enabled, inside its schedule and under its usage limit.
	 *
	 * @param int $now Unix timestamp.
	 */
	public function is_running( int $now ): bool {
		return self::STATUS_ACTIVE === $this->status
			&& ( null === $this->starts_at || $this->starts_at <= $now )
			&& ( null === $this->ends_at || $now < $this->ends_at )
			&& ( 0 === $this->usage_limit || $this->usage_count < $this->usage_limit );
	}

	/**
	 * Whether the promotion targets the given cart line.
	 *
	 * @param Line $line Cart line.
	 */
	public function applies_to( Line $line ): bool {
		return match ( $this->scope ) {
			self::SCOPE_ALL      => true,
			self::SCOPE_PRODUCTS => in_array( $line->product_id, $this->scope_ids, true ) || in_array( $line->variation_id, $this->scope_ids, true ),
			self::SCOPE_CATEGORY => (bool) array_intersect( $this->scope_ids, $line->category_ids ),
			self::SCOPE_TAG      => (bool) array_intersect( $this->scope_ids, $line->tag_ids ),
			default              => false,
		};
	}

	/**
	 * Units the customer pays for in a buy X get Y deal.
	 */
	public function buy_qty(): int {
		return max( 1, (int) ( $this->config['buy_qty'] ?? 2 ) );
	}

	/**
	 * Discounted units in a buy X get Y deal.
	 */
	public function get_qty(): int {
		return max( 1, (int) ( $this->config['get_qty'] ?? 1 ) );
	}

	/**
	 * Discount on the "get" units, percent (100 = free).
	 */
	public function get_discount(): float {
		return min( 100.0, max( 0.0, (float) ( $this->config['get_discount'] ?? 100 ) ) );
	}

	/**
	 * Units in one bundle.
	 */
	public function bundle_qty(): int {
		return max( 2, (int) ( $this->config['bundle_qty'] ?? 2 ) );
	}

	/**
	 * Fixed price for one bundle.
	 */
	public function bundle_price(): float {
		return max( 0.0, (float) ( $this->config['bundle_price'] ?? 0 ) );
	}

	/**
	 * Cart thresholds sorted ascending.
	 *
	 * @return array<int, array{threshold: float, percent: float}>
	 */
	public function tiers(): array {
		return self::normalize_tiers( (array) ( $this->config['tiers'] ?? array() ) );
	}

	/**
	 * Drop incomplete rows, clamp percents and sort by threshold.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw threshold rows.
	 * @return array<int, array{threshold: float, percent: float}>
	 */
	public static function normalize_tiers( array $rows ): array {
		$tiers = array();

		foreach ( $rows as $row ) {
			$threshold = (float) ( $row['threshold'] ?? 0 );
			$percent   = (float) ( $row['percent'] ?? 0 );

			if ( $threshold > 0 && $percent > 0 ) {
				$tiers[] = array(
					'threshold' => $threshold,
					'percent'   => min( 100.0, $percent ),
				);
			}
		}

		usort( $tiers, static fn( array $a, array $b ): int => $a['threshold'] <=> $b['threshold'] );

		return $tiers;
	}

	/**
	 * Popup settings. The top-level copy is variant A; when the A/B test is on,
	 * variant B overrides title, text and button label.
	 *
	 * @return array{enabled: bool, title: string, text: string, cta_label: string, cta_url: string, ab_test: bool, b: array{title: string, text: string, cta_label: string}}
	 */
	public function popup(): array {
		$popup = (array) ( $this->config['popup'] ?? array() );
		$b     = (array) ( $popup['b'] ?? array() );

		return array(
			'enabled'   => ! empty( $popup['enabled'] ),
			'title'     => (string) ( $popup['title'] ?? '' ),
			'text'      => (string) ( $popup['text'] ?? '' ),
			'cta_label' => (string) ( $popup['cta_label'] ?? '' ),
			'cta_url'   => (string) ( $popup['cta_url'] ?? '' ),
			'ab_test'   => ! empty( $popup['ab_test'] ),
			'b'         => array(
				'title'     => (string) ( $b['title'] ?? '' ),
				'text'      => (string) ( $b['text'] ?? '' ),
				'cta_label' => (string) ( $b['cta_label'] ?? '' ),
			),
		);
	}
}
