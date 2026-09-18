<?php
/**
 * Promotion popup.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Front;

use PromoEngine\Promotion\Promotion;
use PromoEngine\Promotion\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the popup of the top priority running promotion that has one.
 * Once-per-session and timing logic live in assets/js/front.js.
 */
final class Popup {

	/**
	 * Constructor.
	 *
	 * @param Repository $promotions Promotions.
	 */
	public function __construct( private Repository $promotions ) {
	}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
	}

	/**
	 * Print popup markup.
	 */
	public function render(): void {
		if ( is_checkout() || is_admin() ) {
			return;
		}

		$promotion = $this->current();

		if ( ! $promotion ) {
			return;
		}

		Assets::enqueue_script();

		$popup = $promotion->popup();
		$a     = array(
			'title'     => $popup['title'] ?: $promotion->name,
			'text'      => $popup['text'],
			'cta_label' => $popup['cta_label'] ?: __( 'Shop the deal', 'promo-engine' ),
		);

		Template::render(
			'popup',
			$a + array(
				'promotion' => $promotion,
				'cta_url'   => $popup['cta_url'] ?: Deals_Page::url(),
				'variant_b' => $popup['ab_test'] ? array_merge( $a, array_filter( $popup['b'] ) ) : null,
			)
		);
	}

	/**
	 * Promotion to show, if any.
	 */
	private function current(): ?Promotion {
		foreach ( $this->promotions->running() as $promotion ) {
			if ( $promotion->popup()['enabled'] ) {
				return $promotion;
			}
		}

		return null;
	}
}
