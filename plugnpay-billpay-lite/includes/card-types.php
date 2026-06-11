<?php
/**
 * PlugnPay Smart Screens v2 card type catalog and parsing helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supported card types for pb_cards_allowed (canonical SSv2 values).
 *
 * @return array<int, array{name: string, image: string|null, aliases: string[]}>
 */
function pnp_get_card_type_catalog() {
	return array(
		array(
			'name'    => 'Visa',
			'image'   => 'visa',
			'aliases' => array( 'visa' ),
		),
		array(
			'name'    => 'Mastercard',
			'image'   => 'mastercard',
			'aliases' => array( 'mastercard', 'master card' ),
		),
		array(
			'name'    => 'Amex',
			'image'   => 'amex',
			'aliases' => array( 'amex', 'american express', 'americanexpress' ),
		),
		array(
			'name'    => 'Discover',
			'image'   => 'discover',
			'aliases' => array( 'discover' ),
		),
		array(
			'name'    => 'Diners',
			'image'   => 'diners',
			'aliases' => array( 'diners', 'diners club', 'dinersclub' ),
		),
		array(
			'name'    => 'JCB',
			'image'   => 'jcb',
			'aliases' => array( 'jcb' ),
		),
		array(
			'name'    => 'EasyLink',
			'image'   => null,
			'aliases' => array( 'easylink', 'easy link' ),
		),
		array(
			'name'    => 'Bermuda',
			'image'   => null,
			'aliases' => array( 'bermuda' ),
		),
		array(
			'name'    => 'IslandCard',
			'image'   => null,
			'aliases' => array( 'islandcard', 'island card' ),
		),
		array(
			'name'    => 'Butterfield',
			'image'   => null,
			'aliases' => array( 'butterfield' ),
		),
		array(
			'name'    => 'KeyCard',
			'image'   => null,
			'aliases' => array( 'keycard', 'key card' ),
		),
		array(
			'name'    => 'MilStar',
			'image'   => null,
			'aliases' => array( 'milstar', 'mil star' ),
		),
		array(
			'name'    => 'Solo',
			'image'   => 'solo',
			'aliases' => array( 'solo' ),
		),
		array(
			'name'    => 'Switch',
			'image'   => 'switch',
			'aliases' => array( 'switch' ),
		),
	);
}

/**
 * Lookup table: normalized alias => catalog entry name.
 *
 * @return array<string, string>
 */
function pnp_get_card_type_alias_map() {
	$map     = array();
	$catalog = pnp_get_card_type_catalog();

	foreach ( $catalog as $entry ) {
		$map[ strtolower( $entry['name'] ) ] = $entry['name'];
		foreach ( $entry['aliases'] as $alias ) {
			$map[ strtolower( $alias ) ] = $entry['name'];
		}
	}

	return $map;
}

/**
 * Parse a comma-separated card types string into canonical names (deduped, catalog order).
 *
 * @param string $raw Raw setting value.
 * @return string[] Canonical card type names.
 */
function pnp_parse_cards_allowed( $raw ) {
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return array();
	}

	$alias_map = pnp_get_card_type_alias_map();
	$selected  = array();
	$seen      = array();

	foreach ( explode( ',', $raw ) as $part ) {
		$key = strtolower( trim( $part ) );
		if ( '' === $key || ! isset( $alias_map[ $key ] ) ) {
			continue;
		}

		$name = $alias_map[ $key ];
		if ( isset( $seen[ $name ] ) ) {
			continue;
		}

		$seen[ $name ] = true;
		$selected[]    = $name;
	}

	return $selected;
}

/**
 * Format canonical card type names for pb_cards_allowed storage / SSv2.
 *
 * @param string[] $names Canonical names.
 * @return string
 */
function pnp_format_cards_allowed( $names ) {
	$allowed = pnp_parse_cards_allowed( implode( ',', $names ) );
	return implode( ',', $allowed );
}

/**
 * Sanitize Card Types Allowed setting.
 *
 * @param mixed $value Raw submitted value.
 * @return string
 */
function pnp_sanitize_cards_allowed( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( ',', $value );
	}

	$parsed = pnp_parse_cards_allowed( (string) $value );
	if ( empty( $parsed ) ) {
		return 'Visa,Mastercard';
	}

	return implode( ',', $parsed );
}

/**
 * Catalog entries selected in admin (preserves catalog display order).
 *
 * @return array<int, array{name: string, image: string|null, aliases: string[]}>
 */
function pnp_get_selected_card_type_entries() {
	$selected_names = pnp_parse_cards_allowed( get_option( 'pnp_pb_cards_allowed', 'Visa,Mastercard' ) );
	if ( empty( $selected_names ) ) {
		return array();
	}

	$lookup  = array_flip( $selected_names );
	$entries = array();

	foreach ( pnp_get_card_type_catalog() as $entry ) {
		if ( isset( $lookup[ $entry['name'] ] ) ) {
			$entries[] = $entry;
		}
	}

	return $entries;
}
