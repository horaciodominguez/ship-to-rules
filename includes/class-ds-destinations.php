<?php
/**
 * Destination helpers: list, cookie, ISO flags, cache.
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DS_Destinations
 */
class DS_Destinations {

	const TRANSIENT = 'ds_destinations_list_v2';

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
				'taxonomy'   => DS_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$out = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$active = get_term_meta( $term->term_id, 'ds_active', true );
				if ( '' === $active ) {
					$active = '1';
				}
				if ( ! $include_inactive && '0' === (string) $active ) {
					continue;
				}
				$iso = strtoupper( (string) get_term_meta( $term->term_id, 'ds_iso2', true ) );
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
			$term = get_term( (int) $ref, DS_TAXONOMY );
		} else {
			$term = get_term_by( 'slug', sanitize_title( $ref ), DS_TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$iso = strtoupper( (string) get_term_meta( $term->term_id, 'ds_iso2', true ) );
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
		if ( isset( $_GET[ DS_QUERY_VAR ] ) && '' !== $_GET[ DS_QUERY_VAR ] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ref = sanitize_title( wp_unslash( $_GET[ DS_QUERY_VAR ] ) );
			$dest = self::get( $ref );
			if ( $dest ) {
				return $dest;
			}
		}

		// Legacy query param from v1.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['product_country'] ) && '' !== $_GET['product_country'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$legacy_id = absint( $_GET['product_country'] );
			$map       = get_option( 'ds_legacy_country_map', array() );
			if ( $legacy_id && isset( $map[ $legacy_id ] ) ) {
				return self::get( (int) $map[ $legacy_id ] );
			}
			return self::get( $legacy_id );
		}

		if ( ! empty( $_COOKIE[ DS_COOKIE ] ) ) {
			$ref = sanitize_title( wp_unslash( $_COOKIE[ DS_COOKIE ] ) );
			return self::get( $ref );
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
		setcookie( DS_COOKIE, $slug, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ DS_COOKIE ] = $slug;
	}

	/**
	 * Clear destination cookie.
	 */
	public static function clear_cookie() {
		if ( headers_sent() ) {
			return;
		}
		setcookie( DS_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ DS_COOKIE ] );
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
			DS_TAXONOMY,
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
			$meta_ids = get_post_meta( $product_id, '_ds_ship_to_ids', true );
			if ( is_array( $meta_ids ) && ! empty( $meta_ids ) ) {
				$terms = array();
				foreach ( $meta_ids as $tid ) {
					$term = get_term( (int) $tid, DS_TAXONOMY );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[] = $term;
					}
				}
			}
		}

		$out = array();
		foreach ( $terms as $term ) {
			$active = get_term_meta( $term->term_id, 'ds_active', true );
			if ( '' === $active ) {
				$active = '1';
			}
			if ( $only_active && '0' === (string) $active ) {
				continue;
			}
			$iso = strtoupper( (string) get_term_meta( $term->term_id, 'ds_iso2', true ) );
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

		$assigned = self::for_product( $product_id, false );
		if ( empty( $assigned ) ) {
			// No destinations assigned = available everywhere (open catalog default).
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
		$url = DS_Settings::get( 'results_url' );
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
