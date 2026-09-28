<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Filterlogik für den Produktkonfigurator.
 */
class EPS_Filter {

	/**
	 * Filtert Geräte nach Anwendungsbereich (application_tags).
	 *
	 * Es werden ausschließlich gültige Geräte (is_valid() === true)
	 * berücksichtigt - ungültige Geräte werden nie zurückgegeben.
	 *
	 * @param EPS_Device[] $devices Zu filternde Geräte.
	 * @param string       $tag     Gesuchter Anwendungsbereich, z. B. "Industrie".
	 *
	 * @return EPS_Device[]
	 */
	public static function by_application( array $devices, string $tag ) {
		$matching_devices = array();

		foreach ( $devices as $device ) {
			if ( ! $device->is_valid() ) {
				continue;
			}

			if ( in_array( $tag, $device->application_tags, true ) ) {
				$matching_devices[] = $device;
			}
		}

		return $matching_devices;
	}

	/**
	 * Filtert Geräte per Webcode/SKU-Direktsuche (case-insensitive Teilstring-Suche).
	 *
	 * Es werden ausschließlich gültige Geräte (is_valid() === true)
	 * berücksichtigt. Ein exaktes Match ist nicht nötig - "ind" findet
	 * z. B. sowohl "IND-5001" als auch "IND-5002".
	 *
	 * @param EPS_Device[] $devices Zu filternde Geräte.
	 * @param string       $query   Suchbegriff (Teilstring der SKU).
	 *
	 * @return EPS_Device[]
	 */
	public static function by_sku( array $devices, string $query ) {
		$matching_devices = array();

		foreach ( $devices as $device ) {
			if ( ! $device->is_valid() ) {
				continue;
			}

			if ( '' !== $query && false !== stripos( $device->sku, $query ) ) {
				$matching_devices[] = $device;
			}
		}

		return $matching_devices;
	}

	/**
	 * Prüft, ob ein Registry-Filter für den Gerätetyp eines bestimmten Geräts
	 * überhaupt zutrifft. Ein leeres/fehlendes applies_to bedeutet "gilt für
	 * alle Gerätetypen".
	 *
	 * @param array      $filter_def Filter-Definition aus eps_get_filters_config().
	 * @param EPS_Device $device     Zu prüfendes Gerät.
	 *
	 * @return bool
	 */
	public static function filter_applies_to_device( array $filter_def, EPS_Device $device ) {
		if ( empty( $filter_def['applies_to'] ) ) {
			return true;
		}

		return in_array( $device->device_type, $filter_def['applies_to'], true );
	}

	/**
	 * Prüft, ob der Einzel- oder Bereichswert eines Geräte-Attributs zu einem
	 * gewählten Von-Bis-Bereich passt.
	 *
	 * @param string $match Match-Strategie aus der Filter-Registry: "cover" oder "within".
	 * @param mixed  $value Attributwert des Geräts: [min, max] bei "cover", Skalar bei "within".
	 * @param float  $from  Untere Grenze des gewählten Bereichs.
	 * @param float  $to    Obere Grenze des gewählten Bereichs.
	 *
	 * @return bool
	 */
	public static function slider_value_matches( $match, $value, $from, $to ) {
		if ( 'cover' === $match ) {
			if ( ! is_array( $value ) || 2 !== count( $value ) ) {
				return false;
			}

			list( $device_min, $device_max ) = array_values( $value );

			// Das Gerät muss den GESAMTEN gesuchten Bereich abdecken können,
			// nicht nur eine Überschneidung damit haben (siehe Loop 5c).
			return $device_min <= $from && $device_max >= $to;
		}

		// match "within": der Einzelwert des Geräts muss innerhalb [from, to]
		// liegen, nicht nur "Wert >= to" - eine reine ">= to"-Prüfung würde bei
		// voll aufgezogenem Regler wieder nur ein einzelnes Gerät durchlassen
		// (der in Loop 5b/5c behobene Bug). Siehe Docblock von filters-config.php.
		return $value >= $from && $value <= $to;
	}

	/**
	 * Filtert Geräte generisch anhand der Filter-Registry: Gerätetyp
	 * (Hart-Filter) plus beliebig viele aktive Slider-, Dropdown-,
	 * Boolean- und Text-Filter (Options-Suche).
	 *
	 * Es werden ausschließlich gültige Geräte (is_valid() === true)
	 * berücksichtigt. Ein aktiver Filter, der für den Gerätetyp eines
	 * Geräts gar nicht zutrifft (applies_to), wird für dieses Gerät
	 * ignoriert - er schließt es nicht aus. Trifft ein aktiver Filter zu,
	 * das Gerät hat aber keinen Wert für dessen Attribut, fällt das Gerät
	 * aus dem Ergebnis.
	 *
	 * @param EPS_Device[] $devices        Zu filternde Geräte.
	 * @param array        $filters_config Filter-Registry aus eps_get_filters_config().
	 * @param string       $device_type    Gewählter Gerätetyp-Key, oder '' für "Alle".
	 * @param array        $active_filters Aktive Filter, key => Wert je nach Typ:
	 *                                     Slider:   ['from' => float, 'to' => float]
	 *                                     Dropdown: ['value' => string]
	 *                                     Boolean:  true (reine Präsenz genügt)
	 *                                     Text:     ['numbers' => int[]] (UND-Verknüpfung)
	 *
	 * @return EPS_Device[]
	 */
	public static function by_registry( array $devices, array $filters_config, $device_type, array $active_filters ) {
		$matching_devices = array();

		foreach ( $devices as $device ) {
			if ( ! $device->is_valid() ) {
				continue;
			}

			if ( '' !== $device_type && $device->device_type !== $device_type ) {
				continue;
			}

			if ( ! self::device_matches_registry_filters( $device, $filters_config, $active_filters ) ) {
				continue;
			}

			$matching_devices[] = $device;
		}

		return $matching_devices;
	}

	/**
	 * Prüft ein einzelnes Gerät gegen alle aktiven Registry-Filter.
	 *
	 * @param EPS_Device $device         Zu prüfendes Gerät.
	 * @param array      $filters_config Filter-Registry.
	 * @param array      $active_filters Aktive Filter, siehe by_registry().
	 *
	 * @return bool
	 */
	private static function device_matches_registry_filters( EPS_Device $device, array $filters_config, array $active_filters ) {
		foreach ( $active_filters as $key => $filter_value ) {
			if ( ! isset( $filters_config[ $key ] ) ) {
				continue;
			}

			$filter_def = $filters_config[ $key ];

			if ( ! self::filter_applies_to_device( $filter_def, $device ) ) {
				continue; // Filter gilt für diesen Gerätetyp nicht - ignorieren, nicht ausschließen.
			}

			if ( ! $device->has_attribute( $key ) ) {
				return false; // Aktiver, zutreffender Filter ohne Wert -> Gerät fällt raus.
			}

			$device_value = $device->get_attribute( $key );

			switch ( $filter_def['type'] ) {
				case 'slider':
					if ( ! self::slider_value_matches( $filter_def['match'], $device_value, $filter_value['from'], $filter_value['to'] ) ) {
						return false;
					}
					break;

				case 'dropdown':
					// Einfachauswahl: exakter Match auf den Attributwert.
					if ( $device_value !== $filter_value['value'] ) {
						return false;
					}
					break;

				case 'boolean':
					// Checkbox "nur Geräte mit ..." - das Gerät muss true haben.
					if ( true !== $device_value ) {
						return false;
					}
					break;

				case 'text':
					// Options-Suche: ALLE eingegebenen Nummern müssen (UND-
					// Verknüpfung) exakt unter den Optionsnummern des Geräts
					// sein. Eine katalogfremde Nummer (z. B. 13) hat kein
					// Gerät jemals in seiner Liste -> führt automatisch zu 0
					// Treffern, ohne Sonderbehandlung hier nötig.
					$device_numbers = (array) $device_value;
					foreach ( (array) $filter_value['numbers'] as $requested_number ) {
						if ( ! in_array( $requested_number, $device_numbers, true ) ) {
							return false;
						}
					}
					break;

				default:
					// Künftige Filtertypen (z. B. multiselect) werden hier noch
					// nicht ausgewertet - absichtlich kein Ausschluss.
					break;
			}
		}

		return true;
	}

	/**
	 * Erkennt, ob die vom Nutzer gewählten Strom-, Spannungs- und
	 * Leistungswerte elektrisch nicht zusammenpassen (power soll ca.
	 * current * voltage entsprechen, gleiche Toleranz wie EPS_Device).
	 *
	 * Der Check greift nur, wenn bei allen drei Reglern beide Enden exakt
	 * gleich sind (also ein einzelner konkreter Wert statt eines Bereichs
	 * gewählt wurde) - bei echten Bereichen ist ein Konflikt-Check nicht
	 * sinnvoll und die Methode gibt null zurück.
	 *
	 * @param float|null $current_from Untere Stromgrenze in Ampere.
	 * @param float|null $current_to   Obere Stromgrenze in Ampere.
	 * @param float|null $voltage_from Untere Spannungsgrenze in Volt.
	 * @param float|null $voltage_to   Obere Spannungsgrenze in Volt.
	 * @param float|null $power_from   Untere Leistungsgrenze in Watt.
	 * @param float|null $power_to     Obere Leistungsgrenze in Watt.
	 *
	 * @return string|null Erklärtext bei Konflikt, sonst null (kein Konflikt oder kein Einzelwert).
	 */
	public static function detect_conflict( ?float $current_from, ?float $current_to, ?float $voltage_from, ?float $voltage_to, ?float $power_from, ?float $power_to ) {
		if ( null === $current_from || null === $current_to || null === $voltage_from || null === $voltage_to || null === $power_from || null === $power_to ) {
			return null;
		}

		$is_exact_value = ( $current_from === $current_to ) && ( $voltage_from === $voltage_to ) && ( $power_from === $power_to );

		if ( ! $is_exact_value ) {
			return null;
		}

		$current = $current_from;
		$voltage = $voltage_from;
		$power   = $power_from;

		$expected_power = $current * $voltage;

		if ( 0.0 === $expected_power ) {
			if ( 0.0 === $power ) {
				return null;
			}

			return sprintf(
				'Konflikt: Bei %s V und %s A wird eine Leistung von 0 W erwartet, angegeben sind %s W.',
				$voltage,
				$current,
				$power
			);
		}

		$deviation = abs( $power - $expected_power ) / $expected_power;

		if ( $deviation <= EPS_Device::POWER_TOLERANCE ) {
			return null;
		}

		return sprintf(
			'Konflikt: Bei %s V und %s A werden ca. %s W erwartet, angegeben sind %s W (Abweichung %s%%).',
			$voltage,
			$current,
			round( $expected_power, 2 ),
			$power,
			round( $deviation * 100, 1 )
		);
	}
}
