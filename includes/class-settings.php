<?php
/**
 * Plugin settings.
 *
 * @package PromoEngine
 */

namespace PromoEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the promo_engine_settings option.
 */
final class Settings {

	public const OPTION = 'promo_engine_settings';

	/**
	 * Defaults for every known setting.
	 *
	 * @return array<string, int|float>
	 */
	public static function defaults(): array {
		return array(
			'global_cap'  => 70.0,
			'popup_delay' => 3,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, int|float>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Maximum total item-level discount, percent.
	 */
	public static function global_cap(): float {
		return (float) self::all()['global_cap'];
	}

	/**
	 * Seconds before the promo popup opens.
	 */
	public static function popup_delay(): int {
		return (int) self::all()['popup_delay'];
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Raw option value.
	 * @return array<string, int|float>
	 */
	public static function sanitize( $input ): array {
		$input = array_merge( self::defaults(), is_array( $input ) ? $input : array() );

		return array(
			'global_cap'  => min( 100.0, max( 0.0, (float) $input['global_cap'] ) ),
			'popup_delay' => min( 120, absint( $input['popup_delay'] ) ),
		);
	}
}
