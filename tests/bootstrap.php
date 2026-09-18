<?php
/**
 * PHPUnit bootstrap. The calculator is framework-free, so no WordPress is loaded.
 *
 * @package PromoEngine
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/includes/autoload.php';
