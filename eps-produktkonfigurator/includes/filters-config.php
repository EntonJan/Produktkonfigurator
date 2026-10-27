<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Zentrale Filter-Registry des Produktkonfigurators.
 *
 * Liefert eine Definition pro Filter, damit UI und Filterlogik generisch
 * (ohne Sonderfälle pro Filter) aus einer einzigen Quelle arbeiten können -
 * Grundlage dafür, dass künftig über 20 Filter dazukommen können, ohne dass
 * an mehreren Stellen im Code neue Sonderfälle ergänzt werden müssen.
 *
 * Jede Definition hat:
 * - key:         eindeutiger Schlüssel, entspricht dem Attribut-Key in
 *                EPS_Device::$attributes (außer beim Gerätetyp selbst).
 * - type:        "entry" (Einstiegsauswahl Gerätetyp), "slider" (Von-Bis),
 *                "dropdown" (feste Werteliste, Einfachauswahl), "boolean"
 *                (Checkbox "nur Geräte mit ..."), "multiselect" (mehrere
 *                Werte gleichzeitig) oder "text" (Freitext-/SKU-Suche).
 *                "multiselect" und "text" (außer der bereits bestehenden
 *                SKU-Suche) sind für künftige Filter vorgesehen (siehe
 *                Werte-Kategorisierung).
 * - options:     Nur bei "dropdown" (und "entry"): Liste möglicher Werte als
 *                key => [label_de, label_en]. "Alle" (keine Einschränkung)
 *                ist implizit der leere String und wird nicht als eigene
 *                Option geführt.
 * - label_de/en: Sichtbare Bezeichnung.
 * - unit:        Einheit für Slider, z. B. "V", "A", "°C" (leer bei anderen Typen).
 * - applies_to:  Liste von Gerätetyp-Keys, für die der Filter gilt. Leer/nicht
 *                gesetzt = gilt für ALLE Gerätetypen. Siehe
 *                EPS_Filter::filter_applies_to_device() und
 *                EPS_Admin_Test_Page für die Auswertung.
 * - match:       Nur bei Slidern relevant:
 *                "cover"  - das Gerät hat selbst einen Von-Bis-Bereich
 *                           (z. B. Ausgangsspannung) und muss den GESAMTEN
 *                           gewählten Bereich abdecken.
 *                "within" - das Gerät hat einen einzelnen Maximalwert
 *                           (z. B. Ausgangsstrom); er muss innerhalb des
 *                           gewählten Von-Bis-Bereichs liegen (identische
 *                           Containment-Logik wie bisher bei Strom/Leistung
 *                           in Loop 5b/5c - NICHT nur ">= Bis", das hätte
 *                           genau den in Loop 5c behobenen Bug wieder
 *                           eingeführt).
 *
 * @return array<string, array>
 */
function eps_get_filters_config() {
	return array(
		'device_type' => array(
			'key'      => 'device_type',
			'type'     => 'entry',
			'label_de' => 'Gerätetyp',
			'label_en' => 'Device Type',
			'unit'     => '',
			'options'  => array(
				'ac_ac'   => array(
					'label_de' => 'AC/AC',
					'label_en' => 'AC/AC',
				),
				'ac_dc'   => array(
					'label_de' => 'AC/DC',
					'label_en' => 'AC/DC',
				),
				'dc_ac'   => array(
					'label_de' => 'DC/AC',
					'label_en' => 'DC/AC',
				),
				'dc_dc'   => array(
					'label_de' => 'DC/DC',
					'label_en' => 'DC/DC',
				),
				'e_load'  => array(
					'label_de' => 'Elektronische Last',
					'label_en' => 'Electronic Load',
				),
				'charger' => array(
					'label_de' => 'Ladegerät',
					'label_en' => 'Charger',
				),
			),
		),

		'output_voltage'   => array(
			'key'        => 'output_voltage',
			'type'       => 'slider',
			'label_de'   => 'Ausgangsspannung',
			'label_en'   => 'Output Voltage',
			'unit'       => 'V',
			'match'      => 'cover',
			'applies_to' => array(),
		),

		'output_current'   => array(
			'key'        => 'output_current',
			'type'       => 'slider',
			'label_de'   => 'Ausgangsstrom',
			'label_en'   => 'Output Current',
			'unit'       => 'A',
			'match'      => 'within',
			'applies_to' => array(),
		),

		'output_power'     => array(
			'key'        => 'output_power',
			'type'       => 'slider',
			'label_de'   => 'Ausgangsleistung',
			'label_en'   => 'Output Power',
			'unit'       => 'W',
			'match'      => 'within',
			'applies_to' => array(),
		),

		'input_voltage'    => array(
			'key'        => 'input_voltage',
			'type'       => 'slider',
			'label_de'   => 'Eingangsspannung',
			'label_en'   => 'Input Voltage',
			'unit'       => 'V',
			'match'      => 'cover',
			'applies_to' => array(),
		),

		'operating_temp'   => array(
			'key'        => 'operating_temp',
			'type'       => 'slider',
			'label_de'   => 'Betriebstemperatur',
			'label_en'   => 'Operating Temperature',
			'unit'       => '°C',
			'match'      => 'cover',
			'applies_to' => array(),
		),

		'resistance_range'    => array(
			'key'        => 'resistance_range',
			'type'       => 'slider',
			'label_de'   => 'Widerstand Einstellbereich',
			'label_en'   => 'Resistance Range',
			'unit'       => 'Ohm',
			'match'      => 'cover',
			'applies_to' => array( 'e_load' ),
		),

		'display'             => array(
			'key'        => 'display',
			'type'       => 'dropdown',
			'label_de'   => 'Anzeige',
			'label_en'   => 'Display',
			'unit'       => '',
			'applies_to' => array(),
			'options'    => array(
				'none'               => array(
					'label_de' => 'keine',
					'label_en' => 'None',
				),
				'tft'                => array(
					'label_de' => 'TFT',
					'label_en' => 'TFT',
				),
				'tft_touch_panel'    => array(
					'label_de' => 'TFT Touch Panel',
					'label_en' => 'TFT Touch Panel',
				),
				'tft_touch_display'  => array(
					'label_de' => 'TFT Touch Display',
					'label_en' => 'TFT Touch Display',
				),
			),
		),

		'cooling'             => array(
			'key'        => 'cooling',
			'type'       => 'dropdown',
			'label_de'   => 'Kühlung',
			'label_en'   => 'Cooling',
			'unit'       => '',
			'applies_to' => array(),
			'options'    => array(
				'fan'                  => array(
					'label_de' => 'Lüfter',
					'label_en' => 'Fan',
				),
				'water_cooling_option' => array(
					'label_de' => 'Option Wasserkühlung',
					'label_en' => 'Water Cooling Option',
				),
			),
		),

		'form_factor'         => array(
			'key'        => 'form_factor',
			'type'       => 'dropdown',
			'label_de'   => 'Bauform',
			'label_en'   => 'Form Factor',
			'unit'       => '',
			'applies_to' => array(),
			'options'    => array(
				'rack_19' => array(
					'label_de' => '19 Zoll',
					'label_en' => '19 Inch',
				),
				'cabinet' => array(
					'label_de' => 'Schrank/Cabinet',
					'label_en' => 'Cabinet',
				),
				'built_in' => array(
					'label_de' => 'Einbau',
					'label_en' => 'Built-in',
				),
				'desktop'  => array(
					'label_de' => 'Tischgerät',
					'label_en' => 'Desktop',
				),
				'tower'    => array(
					'label_de' => 'Tower',
					'label_en' => 'Tower',
				),
				'din_rail' => array(
					'label_de' => 'Hutschiene',
					'label_en' => 'DIN Rail',
				),
			),
		),

		'output_frequency'    => array(
			'key'        => 'output_frequency',
			'type'       => 'dropdown',
			'label_de'   => 'Ausgangsfrequenz',
			'label_en'   => 'Output Frequency',
			'unit'       => '',
			'applies_to' => array( 'ac_ac' ),
			'options'    => array(
				'hz_50'  => array(
					'label_de' => '50 Hz',
					'label_en' => '50 Hz',
				),
				'hz_60'  => array(
					'label_de' => '60 Hz',
					'label_en' => '60 Hz',
				),
				'hz_400' => array(
					'label_de' => '400 Hz',
					'label_en' => '400 Hz',
				),
			),
		),

		'parallel_operation'  => array(
			'key'        => 'parallel_operation',
			'type'       => 'dropdown',
			'label_de'   => 'Parallelschaltung',
			'label_en'   => 'Parallel Operation',
			'unit'       => '',
			'applies_to' => array(),
			'options'    => array(
				'master_slave'  => array(
					'label_de' => 'Master-Slave',
					'label_en' => 'Master-Slave',
				),
				'option'        => array(
					'label_de' => 'Option',
					'label_en' => 'Option',
				),
				'not_available' => array(
					'label_de' => 'nicht verfügbar',
					'label_en' => 'Not available',
				),
			),
		),

		'function_generator'  => array(
			'key'        => 'function_generator',
			'type'       => 'boolean',
			'label_de'   => 'Funktionsgenerator',
			'label_en'   => 'Function Generator',
			'unit'       => '',
			'applies_to' => array(),
		),

		'mains_regeneration'  => array(
			'key'        => 'mains_regeneration',
			'type'       => 'boolean',
			'label_de'   => 'Netzrückspeisung',
			'label_en'   => 'Mains Regeneration',
			'unit'       => '',
			'applies_to' => array(),
		),

		'bidirectional'       => array(
			'key'        => 'bidirectional',
			'type'       => 'boolean',
			'label_de'   => 'Bidirektional',
			'label_en'   => 'Bidirectional',
			'unit'       => '',
			'applies_to' => array(),
		),

		// Options-Suche: Freitext mit mehreren Nummern (UND-Verknüpfung,
		// exakter Zahlenvergleich gegen $attributes['option_numbers']).
		// Katalog siehe eps_get_options_catalog() in options-catalog.php.
		'option_numbers'      => array(
			'key'        => 'option_numbers',
			'type'       => 'text',
			'label_de'   => 'Optionen',
			'label_en'   => 'Options',
			'unit'       => '',
			'applies_to' => array(),
		),
	);
}
