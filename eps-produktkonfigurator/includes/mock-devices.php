<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Liefert die 3 bewusst UNGÜLTIGEN Testgeräte für die Validierung.
 *
 * Die früheren 15 erfundenen gültigen Geräte wurden durch echte Geräte aus dem
 * EPS-Onlineshop ersetzt (siehe shop-devices.php / data/shop-devices.json).
 * Übrig bleiben nur diese 3 klar als Testdaten erkennbaren, absichtlich
 * fehlerhaften Geräte (Kennzeichnung "INVALID"). Sie erscheinen nie in
 * Ergebnissen, nur in der Validierungsübersicht der Testseite.
 *
 * Felder wie bei den Shopgeräten (device_type + attributes nach der
 * Filter-Registry), aber ohne meta - es gilt daher die strenge
 * ±10-%-Regel für P = U * I in EPS_Device::is_valid().
 *
 * @return EPS_Device[]
 */
function eps_get_mock_devices() {
	$devices_data = array(
		// 16 - INVALID: voltage_min > voltage_max (Datenfehler im Spannungsbereich) - ac_dc
		array(
			'sku'              => 'NT-9901',
			'name_de'          => 'Steckernetzteil 24-12V/2A (fehlerhaft)',
			'name_en'          => 'Plug-in Power Supply 24-12V/2A (invalid)',
			'category'         => 'Netzteil',
			'voltage_min'      => 24.0,
			'voltage_max'      => 12.0,
			'current_max'      => 2.0,
			'power_max'        => 24.0, // 12 * 2 = 24, Leistung wäre ok, aber Spannungsbereich ist vertauscht
			'tek_flag'         => false,
			'application_tags' => array( 'Consumer' ),
			'device_type'      => 'ac_dc',
			'attributes'       => array(
				'output_voltage'      => array( 24.0, 12.0 ),
				'output_current'      => 2.0,
				'output_power'        => 24.0,
				'input_voltage'       => array( 90.0, 264.0 ),
				'operating_temp'      => array( 0.0, 40.0 ),
				'display'             => 'none',
				'cooling'             => 'fan',
				'form_factor'         => 'rack_19',
				'parallel_operation'  => 'not_available',
				'function_generator'  => false,
				'mains_regeneration'  => false,
				'bidirectional'       => false,
				'option_numbers'      => array( 2, 6 ),
			),
		),
		// 17 - INVALID: power_max weicht > 10% von current_max * voltage_max ab - ac_dc
		array(
			'sku'              => 'IND-9902',
			'name_de'          => 'Industrienetzteil 0-48V/10A (fehlerhaft)',
			'name_en'          => 'Industrial Power Supply 0-48V/10A (invalid)',
			'category'         => 'Industrienetzteil',
			'voltage_min'      => 0.0,
			'voltage_max'      => 48.0,
			'current_max'      => 10.0,
			'power_max'        => 650.0, // erwartet 48 * 10 = 480, angegeben 650 -> ca. 35% Abweichung
			'tek_flag'         => true,
			'application_tags' => array( 'Industrie' ),
			'device_type'      => 'ac_dc',
			'attributes'       => array(
				'output_voltage'      => array( 0.0, 48.0 ),
				'output_current'      => 10.0,
				'output_power'        => 650.0,
				'input_voltage'       => array( 340.0, 460.0 ),
				'operating_temp'      => array( 0.0, 50.0 ),
				'display'             => 'tft_touch_panel',
				'cooling'             => 'fan',
				'form_factor'         => 'rack_19',
				'parallel_operation'  => 'master_slave',
				'function_generator'  => false,
				'mains_regeneration'  => false,
				'bidirectional'       => false,
				'option_numbers'      => array( 4, 7 ),
			),
		),
		// 18 - INVALID: power_max deutlich zu niedrig für current_max * voltage_max - charger
		array(
			'sku'              => 'LG-9903',
			'name_de'          => 'Ladegerät 12V/5A (fehlerhaft)',
			'name_en'          => 'Charger 12V/5A (invalid)',
			'category'         => 'Ladegerät',
			'voltage_min'      => 12.0,
			'voltage_max'      => 12.0,
			'current_max'      => 5.0,
			'power_max'        => 30.0, // erwartet 12 * 5 = 60, angegeben 30 -> 50% Abweichung
			'tek_flag'         => false,
			'application_tags' => array( 'Automotive', 'Werkstatt' ),
			'device_type'      => 'charger',
			'attributes'       => array(
				'output_voltage'      => array( 12.0, 12.0 ),
				'output_current'      => 5.0,
				'output_power'        => 30.0,
				'input_voltage'       => array( 90.0, 264.0 ),
				'operating_temp'      => array( -10.0, 50.0 ),
				'display'             => 'none',
				'cooling'             => 'fan',
				'form_factor'         => 'rack_19',
				'parallel_operation'  => 'not_available',
				'function_generator'  => false,
				'mains_regeneration'  => false,
				'bidirectional'       => false,
				'option_numbers'      => array(),
			),
		),
	);

	$devices = array();

	foreach ( $devices_data as $device_data ) {
		$devices[] = new EPS_Device( $device_data );
	}

	return $devices;
}
