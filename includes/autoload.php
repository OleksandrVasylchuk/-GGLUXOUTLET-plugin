<?php
/**
 * Class autoloader.
 *
 * Maps PromoEngine\Sub_Namespace\Class_Name to includes/sub-namespace/class-class-name.php.
 *
 * @package PromoEngine
 */

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'PromoEngine\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$class = array_pop( $parts );
		$slug  = static fn( string $name ): string => strtolower( str_replace( '_', '-', $name ) );
		$dir   = $parts ? implode( '/', array_map( $slug, $parts ) ) . '/' : '';
		$file  = __DIR__ . '/' . $dir . 'class-' . $slug( $class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
