<?php
/**
 * Calculated cart line.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Per-line amounts in minor units (cents).
 */
final class Line_Result {

	public int $unit_price;
	public int $total;
	public int $original_total;

	/**
	 * Discount per promotion ID, minor units.
	 *
	 * @var array<int, int>
	 */
	public array $discounts = array();

	/**
	 * Constructor.
	 *
	 * @param string $key        Cart item key.
	 * @param int    $quantity   Quantity.
	 * @param int    $unit_price Original unit price, minor units.
	 */
	public function __construct(
		public string $key,
		public int $quantity,
		int $unit_price
	) {
		$this->unit_price     = $unit_price;
		$this->total          = $unit_price * $quantity;
		$this->original_total = $this->total;
	}

	/**
	 * Record a discount for a promotion.
	 *
	 * @param int $promotion_id Promotion ID.
	 * @param int $amount       Minor units.
	 */
	public function add_discount( int $promotion_id, int $amount ): void {
		if ( $amount > 0 ) {
			$this->discounts[ $promotion_id ] = ( $this->discounts[ $promotion_id ] ?? 0 ) + $amount;
		}
	}

	/**
	 * Whether the line got cheaper.
	 */
	public function is_discounted(): bool {
		return $this->total < $this->original_total;
	}
}
