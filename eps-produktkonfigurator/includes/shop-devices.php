<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Echte Geräte aus dem EPS-Onlineshop (www.eps-germany.de/shop).
 *
 * Quelle: data/shop-devices.json, erzeugt mit tools/shop-import.py aus den
 * Shopdaten der EPS-Produktsuche. Ersetzt die früheren erfundenen
 * Mock-Geräte; später kommt dieselbe Struktur aus der Weclapp-API.
 *
 * @return EPS_Device[]
 */
function eps_get_shop_devices() {
	static $devices = null;

	if ( null !== $devices ) {
		return $devices;
	}

	$devices = array();
	$file    = EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'data/shop-devices.json';

	if ( ! is_readable( $file ) ) {
		return $devices;
	}

	$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( ! is_array( $data ) || empty( $data['devices'] ) ) {
		return $devices;
	}

	foreach ( $data['devices'] as $device_data ) {
		$devices[] = new EPS_Device( $device_data );
	}

	return $devices;
}

/**
 * Stand der Shopdaten (ISO-Datum/Zeit) für Anzeigen, '' wenn unbekannt.
 *
 * @return string
 */
function eps_get_shop_devices_stand() {
	$file = EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'data/shop-devices.json';

	if ( ! is_readable( $file ) ) {
		return '';
	}

	$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	return is_array( $data ) && isset( $data['stand'] ) ? (string) $data['stand'] : '';
}

/**
 * Alle Geräte für Konfigurator und Testseite: echte Shopgeräte plus die drei
 * bewusst ungültigen Testgeräte (NT-9901, IND-9902, LG-9903), damit die
 * Validierung sichtbar überprüfbar bleibt.
 *
 * @return EPS_Device[]
 */
function eps_get_devices() {
	return array_merge( eps_get_shop_devices(), eps_get_mock_devices() );
}
