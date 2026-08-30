<?php
/**
 * Destination helpers: list, cookie, ISO flags, cache.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Countries
 */
class STR_Countries {

	const TRANSIENT     = 'str_countries_list_v2';
	const TRANSIENT_ISO = 'str_countries_iso_map_v2';

	/**
	 * Get destinations (cached).
	 *
	 * @param bool $force            Force refresh.
	 * @param bool $include_inactive Include inactive terms (admin).
	 * @return array<int,object>
	 */
	public static function get_all( $force = false, $include_inactive = false ) {
		$cache_key = $include_inactive ? self::TRANSIENT . '_all' : self::TRANSIENT;
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$terms = get_terms(
			array(
				'taxonomy'   => STR_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$out = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$active = get_term_meta( $term->term_id, 'str_active', true );
				if ( '' === $active ) {
					$active = '1';
				}
				if ( ! $include_inactive && '0' === (string) $active ) {
					continue;
				}
				$iso = strtoupper( (string) get_term_meta( $term->term_id, 'str_iso2', true ) );
				$out[] = (object) array(
					'id'     => (int) $term->term_id,
					'name'   => $term->name,
					'slug'   => $term->slug,
					'iso2'   => $iso,
					'flag'   => self::flag_emoji( $iso ),
					'count'  => (int) $term->count,
					'active' => '1' === (string) $active,
				);
			}
		}

		set_transient( $cache_key, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Bust destinations cache.
	 */
	public static function flush_cache() {
		delete_transient( self::TRANSIENT );
		delete_transient( self::TRANSIENT . '_all' );
		delete_transient( self::TRANSIENT_ISO );
	}

	/**
	 * Resolve destination by ISO2 country code.
	 *
	 * @param string $iso2 ISO 3166-1 alpha-2.
	 * @return object|null
	 */
	public static function get_by_iso2( $iso2 ) {
		$iso2 = self::sanitize_iso2( $iso2 );
		if ( 2 !== strlen( $iso2 ) ) {
			return null;
		}

		$map = self::get_iso_map();
		return isset( $map[ $iso2 ] ) ? $map[ $iso2 ] : null;
	}

	/**
	 * ISO2 => destination object map (cached).
	 *
	 * @param bool $force Force refresh.
	 * @return array<string,object>
	 */
	public static function get_iso_map( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_ISO );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$map = array();
		foreach ( self::get_all( $force ) as $d ) {
			if ( ! empty( $d->iso2 ) ) {
				$map[ strtoupper( $d->iso2 ) ] = $d;
			}
		}

		set_transient( self::TRANSIENT_ISO, $map, HOUR_IN_SECONDS );
		return $map;
	}

	/**
	 * Resolve a destination by slug or ID.
	 *
	 * @param string|int $ref Slug or term ID.
	 * @return object|null
	 */
	public static function get( $ref ) {
		if ( '' === $ref || null === $ref ) {
			return null;
		}

		foreach ( self::get_all() as $d ) {
			if ( (string) $d->slug === (string) $ref || (int) $d->id === (int) $ref ) {
				return $d;
			}
		}

		// Inactive / uncached: try direct lookup.
		if ( is_numeric( $ref ) ) {
			$term = get_term( (int) $ref, STR_TAXONOMY );
		} else {
			$term = get_term_by( 'slug', sanitize_title( $ref ), STR_TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$iso = strtoupper( (string) get_term_meta( $term->term_id, 'str_iso2', true ) );
		return (object) array(
			'id'   => (int) $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
			'iso2' => $iso,
			'flag' => self::flag_emoji( $iso ),
			'count'=> (int) $term->count,
		);
	}

	/**
	 * Current visitor destination from GET (priority) or cookie.
	 *
	 * @return object|null
	 */
	public static function current() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ STR_QUERY_VAR ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ref = sanitize_title( wp_unslash( $_GET[ STR_QUERY_VAR ] ) );
			if ( '' === $ref ) {
				return null;
			}
			$dest = self::get( $ref );
			if ( $dest ) {
				return $dest;
			}
		}

		if ( ! empty( $_COOKIE[ STR_COOKIE ] ) ) {
			$ref = sanitize_title( wp_unslash( $_COOKIE[ STR_COOKIE ] ) );
			return self::get( $ref );
		}

		// One-time read of legacy cookie from previous installs (migration copies selection on next set).
		if ( ! empty( $_COOKIE['ds_destination'] ) ) {
			$ref = sanitize_title( wp_unslash( $_COOKIE['ds_destination'] ) );
			$dest = self::get( $ref );
			if ( $dest ) {
				self::set_cookie( $dest->slug );
				return $dest;
			}
		}

		return null;
	}

	/**
	 * Persist destination cookie (slug).
	 *
	 * @param string $slug Destination slug.
	 */
	public static function set_cookie( $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! $slug || headers_sent() ) {
			return;
		}
		$expire = time() + ( 30 * DAY_IN_SECONDS );
		setcookie( STR_COOKIE, $slug, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ STR_COOKIE ] = $slug;
	}

	/**
	 * Clear destination cookie.
	 */
	public static function clear_cookie() {
		if ( headers_sent() ) {
			return;
		}
		setcookie( STR_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ STR_COOKIE ] );
	}

	/**
	 * URL that clears the visitor destination (GET + cookie).
	 *
	 * @return string
	 */
	public static function clear_url() {
		return add_query_arg( STR_QUERY_VAR, '', remove_query_arg( STR_QUERY_VAR ) );
	}

	/**
	 * Destinations assigned to a product.
	 *
	 * Uses wp_get_object_terms (not get_the_terms) to avoid stale false caches,
	 * and falls back to mirrored post meta if needed.
	 *
	 * @param int  $product_id     Product ID.
	 * @param bool $only_active    Skip inactive destinations.
	 * @return array<int,object>
	 */
	public static function for_product( $product_id, $only_active = true ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return array();
		}

		$terms = wp_get_object_terms(
			$product_id,
			STR_TAXONOMY,
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		// Fallback: mirrored IDs from product meta (written on save).
		if ( empty( $terms ) ) {
			$meta_ids = get_post_meta( $product_id, '_str_ship_to_ids', true );
			if ( is_array( $meta_ids ) && ! empty( $meta_ids ) ) {
				$terms = array();
				foreach ( $meta_ids as $tid ) {
					$term = get_term( (int) $tid, STR_TAXONOMY );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[] = $term;
					}
				}
			}
		}

		$out = array();
		foreach ( $terms as $term ) {
			$active = get_term_meta( $term->term_id, 'str_active', true );
			if ( '' === $active ) {
				$active = '1';
			}
			if ( $only_active && '0' === (string) $active ) {
				continue;
			}
			$iso = strtoupper( (string) get_term_meta( $term->term_id, 'str_iso2', true ) );
			$out[] = (object) array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'iso2' => $iso,
				'flag' => self::flag_emoji( $iso ),
			);
		}

		usort(
			$out,
			function ( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			}
		);

		return $out;
	}

	/**
	 * Whether product ships to destination.
	 *
	 * @param int         $product_id Product ID.
	 * @param object|null $destination Destination object.
	 * @return bool|null Null if no visitor destination selected.
	 */
	public static function product_ships_to( $product_id, $destination = null ) {
		$destination = $destination ? $destination : self::current();
		if ( ! $destination ) {
			return null;
		}

		$country = ! empty( $destination->iso2 ) ? $destination->iso2 : '';
		if ( $country && class_exists( 'STR_Rules' ) ) {
			return STR_Rules::can_ship( $product_id, $country );
		}

		$assigned = self::for_product( $product_id, false );
		if ( empty( $assigned ) ) {
			return true;
		}

		foreach ( $assigned as $d ) {
			if ( (int) $d->id === (int) $destination->id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ISO2 → flag emoji (regional indicator symbols).
	 *
	 * Uses mb_chr when available; avoids deprecated HTML-ENTITIES conversion.
	 *
	 * @param string $iso ISO 3166-1 alpha-2.
	 * @return string
	 */
	public static function flag_emoji( $iso ) {
		$iso = strtoupper( preg_replace( '/[^A-Z]/i', '', (string) $iso ) );
		if ( 2 !== strlen( $iso ) ) {
			return '';
		}

		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$code = 0x1F1E6 + ( ord( $iso[ $i ] ) - ord( 'A' ) );
			if ( function_exists( 'mb_chr' ) ) {
				$char = mb_chr( $code, 'UTF-8' );
				if ( false !== $char && null !== $char ) {
					$out .= $char;
					continue;
				}
			}
			// UTF-8 encode supplementary-plane code point manually.
			$out .= chr( 0xF0 | ( $code >> 18 ) )
				. chr( 0x80 | ( ( $code >> 12 ) & 0x3F ) )
				. chr( 0x80 | ( ( $code >> 6 ) & 0x3F ) )
				. chr( 0x80 | ( $code & 0x3F ) );
		}
		return $out;
	}

	/**
	 * Sanitize ISO2.
	 *
	 * @param string $iso Raw ISO.
	 * @return string
	 */
	public static function sanitize_iso2( $iso ) {
		$iso = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $iso ) );
		return substr( $iso, 0, 2 );
	}

	/**
	 * WooCommerce country list for admin suggestions.
	 *
	 * @return array<string,string> code => name
	 */
	public static function wc_countries() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}
		return WC()->countries->get_countries();
	}

	/**
	 * Shop / results URL from settings.
	 *
	 * @return string
	 */
	public static function results_url() {
		$url = STR_Settings::get( 'results_url' );
		if ( $url ) {
			return $url;
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );
			if ( $shop ) {
				return $shop;
			}
		}
		return home_url( '/' );
	}
}
