<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Repräsentiert ein einzelnes Stromversorgungsgerät (Netzteil, Ladegerät, ...).
 */
class EPS_Device {

	/** @var string Eindeutige Artikelnummer/Webcode. */
	public $sku;

	/** @var string Bezeichnung auf Deutsch. */
	public $name_de;

	/** @var string Bezeichnung auf Englisch. */
	public $name_en;

	/** @var string Kategorie, z. B. "Netzteil", "Ladegerät". */
	public $category;

	/** @var float Minimale Ausgangsspannung in Volt. */
	public $voltage_min;

	/** @var float Maximale Ausgangsspannung in Volt. */
	public $voltage_max;

	/** @var float Maximaler Ausgangsstrom in Ampere. */
	public $current_max;

	/** @var float Maximale Ausgangsleistung in Watt. */
	public $power_max;

	/** @var bool Ob das Gerät die TEK-Kennzeichnung trägt. */
	public $tek_flag;

	/** @var string[] Anwendungsbereiche, z. B. ["Industrie", "Labor"]. */
	public $application_tags;

	/**
	 * @var string Gerätetyp-Key aus der Filter-Registry (siehe filters-config.php),
	 *             z. B. "ac_dc", "e_load". Steuert, welche Registry-Filter für
	 *             dieses Gerät überhaupt zutreffen können (applies_to).
	 */
	public $device_type;

	/**
	 * @var array Zusätzliche Attribute nach der Filter-Registry, key => Wert.
	 *            Bereichswerte (match "cover") werden als [min, max] abgelegt,
	 *            Einzelwerte (match "within") als einfacher Skalar.
	 */
	public $attributes;

	/**
	 * @var array Zusatzinfos, die keine Registry-Filter sind (z. B. aus dem
	 *            Onlineshop): power_rule, channels, ac, u_fixed, u_ranges,
	 *            parallel, parallel_max, series, texts, pdf_url, shop_url.
	 *            Wird von der Schnellsuche (assets/js/eps-quick-search.js) und
	 *            der Validierung genutzt.
	 */
	public $meta;

	/**
	 * Toleranz für den Strom-Leistungs-Konflikt-Check (10 %).
	 */
	const POWER_TOLERANCE = 0.10;

	/**
	 * @param array $args {
	 *     @type string   $sku
	 *     @type string   $name_de
	 *     @type string   $name_en
	 *     @type string   $category
	 *     @type float    $voltage_min
	 *     @type float    $voltage_max
	 *     @type float    $current_max
	 *     @type float    $power_max
	 *     @type bool     $tek_flag
	 *     @type string[] $application_tags
	 *     @type string   $device_type
	 *     @type array    $attributes
	 * }
	 */
	public function __construct( array $args = array() ) {
		$defaults = array(
			'sku'              => '',
			'name_de'          => '',
			'name_en'          => '',
			'category'         => '',
			'voltage_min'      => 0.0,
			'voltage_max'      => 0.0,
			'current_max'      => 0.0,
			'power_max'        => 0.0,
			'tek_flag'         => false,
			'application_tags' => array(),
			'device_type'      => '',
			'attributes'       => array(),
			'meta'             => array(),
		);

		$args = array_merge( $defaults, $args );

		$this->sku              = (string) $args['sku'];
		$this->name_de          = (string) $args['name_de'];
		$this->name_en          = (string) $args['name_en'];
		$this->category         = (string) $args['category'];
		$this->voltage_min      = (float) $args['voltage_min'];
		$this->voltage_max      = (float) $args['voltage_max'];
		$this->current_max      = (float) $args['current_max'];
		$this->power_max        = (float) $args['power_max'];
		$this->tek_flag         = (bool) $args['tek_flag'];
		$this->application_tags = (array) $args['application_tags'];
		$this->device_type      = (string) $args['device_type'];
		$this->attributes       = (array) $args['attributes'];
		$this->meta             = (array) $args['meta'];
	}

	/**
	 * Prüft, ob voltage_min <= voltage_max ist.
	 *
	 * @return bool
	 */
	public function has_valid_voltage_range() {
		return $this->voltage_min <= $this->voltage_max;
	}

	/**
	 * Strom-Leistungs-Konflikt-Check: power_max muss ungefähr
	 * current_max * voltage_max entsprechen (10 % Toleranz).
	 *
	 * @return bool
	 */
	public function has_valid_power_current_relation() {
		$expected_power = $this->current_max * $this->voltage_max;

		// Echte Geräte (Onlineshop): Autoranging-/Derating-Geräte liefern
		// absichtlich WENIGER als U_max * I_max (z. B. 60 V / 1000 A / 30 kW).
		// Geprüft wird deshalb nur die Obergrenze: P darf U * I * Kanäle
		// (mehrkanalige Geräte, 3 Phasen) um höchstens 10 % übersteigen.
		// Fehlt einer der Werte, ist keine Prüfung möglich -> gültig; die
		// Filter behandeln fehlende Attribute ohnehin selbst.
		if ( isset( $this->meta['power_rule'] ) && 'max' === $this->meta['power_rule'] ) {
			if ( $this->voltage_max <= 0.0 || $this->current_max <= 0.0 || $this->power_max <= 0.0 ) {
				return true;
			}
			$channels = max( 1, (int) ( isset( $this->meta['channels'] ) ? $this->meta['channels'] : 1 ) );
			return $this->power_max <= $expected_power * $channels * ( 1 + self::POWER_TOLERANCE );
		}

		if ( 0.0 === $expected_power ) {
			return 0.0 === $this->power_max;
		}

		$deviation = abs( $this->power_max - $expected_power ) / $expected_power;

		return $deviation <= self::POWER_TOLERANCE;
	}

	/**
	 * Ein Gerät ist gültig, wenn Spannungsbereich und Strom-Leistungs-Beziehung stimmen.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return $this->has_valid_voltage_range() && $this->has_valid_power_current_relation();
	}

	/**
	 * Liefert eine Liste lesbarer Validierungsfehler (leer, wenn gültig).
	 *
	 * @return string[]
	 */
	public function get_validation_errors() {
		$errors = array();

		if ( ! $this->has_valid_voltage_range() ) {
			$errors[] = sprintf(
				'voltage_min (%s V) ist größer als voltage_max (%s V).',
				$this->voltage_min,
				$this->voltage_max
			);
		}

		if ( ! $this->has_valid_power_current_relation() ) {
			$expected_power = $this->current_max * $this->voltage_max;
			if ( isset( $this->meta['power_rule'] ) && 'max' === $this->meta['power_rule'] ) {
				$errors[] = sprintf(
					'power_max (%s W) ist größer als voltage_max * current_max * Kanäle (%s W) – Shopdaten prüfen.',
					$this->power_max,
					round( $expected_power * max( 1, (int) $this->meta['channels'] ), 2 )
				);
				return $errors;
			}
			$errors[]       = sprintf(
				'power_max (%s W) weicht zu stark von current_max * voltage_max (%s W) ab.',
				$this->power_max,
				round( $expected_power, 2 )
			);
		}

		return $errors;
	}

	/**
	 * Prüft, ob für dieses Gerät ein Registry-Attribut gesetzt ist.
	 *
	 * @param string $key Attribut-Key aus der Filter-Registry, z. B. "output_voltage".
	 *
	 * @return bool
	 */
	public function has_attribute( $key ) {
		return array_key_exists( $key, $this->attributes );
	}

	/**
	 * Liefert den Wert eines Registry-Attributs. Bereichswerte kommen als
	 * [min, max] zurück, Einzelwerte als Skalar; fehlt das Attribut, wird
	 * null zurückgegeben.
	 *
	 * @param string $key Attribut-Key aus der Filter-Registry.
	 *
	 * @return mixed|null
	 */
	public function get_attribute( $key ) {
		return $this->has_attribute( $key ) ? $this->attributes[ $key ] : null;
	}
}
