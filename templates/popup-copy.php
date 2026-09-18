<?php
/**
 * Popup title and text for one A/B variant.
 *
 * Override by copying to yourtheme/promo-engine/popup-copy.php.
 *
 * @package PromoEngine
 *
 * @var array{title: string, text: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>

<h2 id="pe-popup-title" class="pe-popup__title"><?php echo esc_html( $args['title'] ); ?></h2>
<span class="pe-popup__divider" aria-hidden="true"></span>
<?php if ( $args['text'] ) : ?>
	<p id="pe-popup-text" class="pe-popup__text"><?php echo esc_html( $args['text'] ); ?></p>
<?php endif; ?>
