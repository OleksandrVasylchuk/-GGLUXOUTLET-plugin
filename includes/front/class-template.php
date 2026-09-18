<?php
/**
 * Template loader.
 *
 * @package PromoEngine
 */

namespace PromoEngine\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Renders plugin templates. A theme can override any of them by placing a
 * copy in {theme}/promo-engine/{name}.php.
 */
final class Template {

	/**
	 * Absolute path of a template, theme copy first.
	 *
	 * @param string $name Template name without extension.
	 */
	public static function locate( string $name ): string {
		$theme = locate_template( 'promo-engine/' . $name . '.php' );

		return $theme ?: PROMO_ENGINE_DIR . 'templates/' . $name . '.php';
	}

	/**
	 * Print a template.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $args Variables available as $args.
	 */
	public static function render( string $name, array $args = array() ): void {
		load_template( self::locate( $name ), false, $args );
	}
}
