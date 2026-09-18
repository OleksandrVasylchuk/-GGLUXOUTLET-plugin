<?php
/**
 * Promotion popup. Hidden until assets/js/front.js opens it.
 *
 * With an A/B test the markup carries variant A, and variant B sits in a
 * <template>; the script swaps it in for visitors assigned to B. Rendering
 * both keeps the page cacheable.
 *
 * Override by copying to yourtheme/promo-engine/popup.php. Keep the data-pe-*
 * attributes, the script relies on them.
 *
 * @package PromoEngine
 *
 * @var array{promotion: PromoEngine\Promotion\Promotion, title: string, text: string, cta_label: string, cta_url: string, variant_b: array{title: string, text: string, cta_label: string}|null} $args
 */

use PromoEngine\Front\Template;
use PromoEngine\Promotion\Labels;

defined( 'ABSPATH' ) || exit;

$promotion = $args['promotion'];
$variant_b = $args['variant_b'];
?>

<div class="pe-popup" data-pe-popup="<?php echo esc_attr( $promotion->id ); ?>"<?php echo $variant_b ? ' data-pe-ab' : ''; ?> hidden>
	<div class="pe-popup__backdrop" data-pe-close></div>

	<div
		class="pe-popup__dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="pe-popup-title"
		aria-describedby="pe-popup-text"
		tabindex="-1"
	>
		<button type="button" class="pe-popup__close" data-pe-close aria-label="<?php esc_attr_e( 'Close', 'promo-engine' ); ?>">
			<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.5"/></svg>
		</button>

		<p class="pe-popup__eyebrow"><?php echo wp_kses_post( Labels::badge( $promotion ) ); ?></p>

		<div class="pe-popup__copy" data-pe-copy>
			<?php Template::render( 'popup-copy', $args ); ?>
		</div>

		<?php if ( $promotion->ends_at ) : ?>
			<p class="pe-popup__timer">
				<span class="pe-popup__timer-label"><?php esc_html_e( 'Ends in', 'promo-engine' ); ?></span>
				<time class="pe-countdown pe-countdown--large" role="timer" datetime="<?php echo esc_attr( gmdate( 'c', $promotion->ends_at ) ); ?>" data-pe-countdown="<?php echo esc_attr( $promotion->ends_at ); ?>">
					<?php echo esc_html( human_time_diff( time(), $promotion->ends_at ) ); ?>
				</time>
			</p>
		<?php endif; ?>

		<a
			class="pe-button pe-popup__cta"
			href="<?php echo esc_url( $args['cta_url'] ); ?>"
			data-pe-cta
			<?php if ( $variant_b ) : ?>
				data-pe-cta-b="<?php echo esc_attr( $variant_b['cta_label'] ); ?>"
			<?php endif; ?>
		><?php echo esc_html( $args['cta_label'] ); ?></a>

		<?php if ( $variant_b ) : ?>
			<template data-pe-variant-b><?php Template::render( 'popup-copy', $variant_b ); ?></template>
		<?php endif; ?>
	</div>
</div>
