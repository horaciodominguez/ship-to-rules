<?php
/**
 * PHPUnit bootstrap (minimal stubs for pure rule logic tests).
 *
 * @package ShipToRules
 */

define( 'ABSPATH', __DIR__ . '/../' );

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub translation.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Stub strip tags.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ) {
		return strip_tags( $text );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-str-countries.php';
require_once dirname( __DIR__ ) . '/includes/class-str-rules.php';
