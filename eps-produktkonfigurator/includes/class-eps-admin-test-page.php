<?php
/**
 * ACHTUNG: TEMPORÄRE TESTSEITE.
 *
 * Diese Klasse dient ausschließlich dazu, die Mock-Geräte und ihre
 * Validierung (EPS_Device::is_valid()) während der Entwicklung im
 * Browser zu überprüfen, ohne Code lesen zu müssen.
 *
 * Sie ist NICHT Teil der finalen Plugin-UI und wird entfernt, sobald
 * die echte Produktkonfigurator-Oberfläche existiert.
 *
 * Seit der Umstellung auf die Filter-Registry (filters-config.php) wird
 * hier NICHTS mehr pro Filter hartkodiert: der Gerätetyp und alle
 * Slider-Filter werden generisch aus eps_get_filters_config() gerendert
 * und gefiltert.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

class EPS_Admin_Test_Page {

	/**
	 * Hook-Suffix der Testseite, gesetzt von register_menu_page().
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Registriert die Hooks für die temporäre Testseite.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Legt den Menüpunkt "Konfigurator Test" im Adminmenü an.
	 */
	public function register_menu_page() {
		$this->page_hook = add_menu_page(
			__( 'Konfigurator Test', 'eps-produktkonfigurator' ),
			__( 'Konfigurator Test', 'eps-produktkonfigurator' ),
			'manage_options',
			'eps-konfigurator-test',
			array( $this, 'render_page' ),
			'dashicons-admin-tools',
			58
		);
	}

	/**
	 * Lädt CSS/JS für die Dual-Range-Slider, aber nur auf dieser Testseite.
	 *
	 * @param string $hook Hook-Suffix der aktuellen Admin-Seite.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		// Für die Cache-Busting-Version filemtime() statt der statischen
		// Plugin-Version verwenden: diese Testseiten-Assets ändern sich
		// während der Entwicklung häufig, ohne dass die Plugin-Version
		// jedes Mal hochgezählt wird - sonst liefert der Browser sonst
		// stillschweigend eine gecachte, veraltete JS-Datei aus.
		$css_path = EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'assets/css/admin-test-page.css';
		$js_path  = EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'assets/js/admin-test-page.js';

		wp_enqueue_style(
			'eps-admin-test-page',
			EPS_PRODUKTKONFIGURATOR_PLUGIN_URL . 'assets/css/admin-test-page.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : EPS_PRODUKTKONFIGURATOR_VERSION
		);

		$qs_path    = EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'assets/js/eps-quick-search.js';
		$qs_ui_path = EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'assets/js/admin-quick-search.js';

		wp_enqueue_script(
			'eps-quick-search',
			EPS_PRODUKTKONFIGURATOR_PLUGIN_URL . 'assets/js/eps-quick-search.js',
			array(),
			file_exists( $qs_path ) ? (string) filemtime( $qs_path ) : EPS_PRODUKTKONFIGURATOR_VERSION,
			true
		);

		wp_enqueue_script(
			'eps-admin-quick-search',
			EPS_PRODUKTKONFIGURATOR_PLUGIN_URL . 'assets/js/admin-quick-search.js',
			array( 'eps-quick-search' ),
			file_exists( $qs_ui_path ) ? (string) filemtime( $qs_ui_path ) : EPS_PRODUKTKONFIGURATOR_VERSION,
			true
		);

		wp_enqueue_script(
			'eps-admin-test-page',
			EPS_PRODUKTKONFIGURATOR_PLUGIN_URL . 'assets/js/admin-test-page.js',
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : EPS_PRODUKTKONFIGURATOR_VERSION,
			true
		);

		// Filter-Registry fürs JS: nur die für die Live-Neuberechnung
		// relevanten Felder (type/match/applies_to), nicht die kompletten
		// Definitionen (Labels werden serverseitig gerendert).
		$filters_config    = function_exists( 'eps_get_filters_config' ) ? eps_get_filters_config() : array();
		$filters_config_js = array();

		foreach ( $filters_config as $key => $filter_def ) {
			$filters_config_js[ $key ] = array(
				'type'       => $filter_def['type'],
				'match'      => isset( $filter_def['match'] ) ? $filter_def['match'] : null,
				'applies_to' => isset( $filter_def['applies_to'] ) ? array_values( $filter_def['applies_to'] ) : array(),
			);
		}

		// Geräte-Rohdaten für die live im Browser berechneten Faceted-Search-
		// Reglergrenzen und die Schnellsuche: Gerätetyp, Registry-Attribute,
		// Name und meta (Shop-Zusatzinfos wie AC-Festspannungen,
		// Parallelschaltung, Originaltexte, Datenblatt-Link).
		$all_devices    = function_exists( 'eps_get_devices' ) ? eps_get_devices() : array();
		$devices_for_js = array();

		foreach ( $all_devices as $device ) {
			$devices_for_js[] = array(
				'sku'         => $device->sku,
				'name_de'     => $device->name_de,
				'name_en'     => $device->name_en,
				'device_type' => $device->device_type,
				'is_valid'    => $device->is_valid(),
				'attributes'  => $device->attributes,
				'meta'        => $device->meta,
			);
		}

		$device_type_labels = array();
		if ( isset( $filters_config['device_type']['options'] ) ) {
			foreach ( $filters_config['device_type']['options'] as $type_key => $type_option ) {
				$device_type_labels[ $type_key ] = $type_option['label_de'];
			}
		}

		// Daten VOR dem ersten Skript der Kette ausgeben, damit sowohl die
		// Schnellsuche als auch die Faceted-Search sie vorfinden.
		wp_add_inline_script(
			'eps-quick-search',
			'window.epsFiltersConfig = ' . wp_json_encode( $filters_config_js ) . ';' .
			'window.epsDevices = ' . wp_json_encode( $devices_for_js ) . ';' .
			'window.epsDeviceTypeLabels = ' . wp_json_encode( $device_type_labels ) . ';',
			'before'
		);
	}

	/**
	 * Liest einen GET-Parameter als Float und begrenzt ihn auf [$min, $max].
	 * Fehlt der Parameter oder ist er nicht numerisch, wird $fallback verwendet.
	 *
	 * @param string $get_key  Name des GET-Parameters.
	 * @param float  $min      Untere Grenze.
	 * @param float  $max      Obere Grenze.
	 * @param float  $fallback Rückgabewert, falls kein gültiger Wert übergeben wurde.
	 *
	 * @return float
	 */
	private function get_range_value( $get_key, $min, $max, $fallback ) {
		if ( ! isset( $_GET[ $get_key ] ) ) {
			return $fallback;
		}

		$raw = sanitize_text_field( wp_unslash( $_GET[ $get_key ] ) );

		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return $fallback;
		}

		return max( $min, min( $max, (float) $raw ) );
	}

	/**
	 * Prüft, ob eine Filter-Checkbox in der GET-Anfrage aktiviert wurde.
	 * Checkboxen werden bei einem HTML-Formular nur mitgeschickt, wenn sie
	 * angehakt sind - fehlt der Parameter, gilt das Feld als nicht aktiv.
	 *
	 * @param string $get_key Name des GET-Parameters.
	 *
	 * @return bool
	 */
	private function is_filter_active( $get_key ) {
		if ( ! isset( $_GET[ $get_key ] ) ) {
			return false;
		}

		return '1' === sanitize_text_field( wp_unslash( $_GET[ $get_key ] ) );
	}

	/**
	 * Zerlegt eine per Komma/Leerzeichen getrennte Zahlenliste (z. B. aus
	 * dem Options-Suchfeld) in eindeutige Integer, in Eingabereihenfolge.
	 * Nicht-numerische Bestandteile werden stillschweigend verworfen.
	 *
	 * @param string $raw Roher Feldinhalt, z. B. "3, 6  7".
	 *
	 * @return int[]
	 */
	private function parse_number_list( $raw ) {
		$parts   = preg_split( '/[\s,;]+/', trim( $raw ) );
		$numbers = array();

		foreach ( $parts as $part ) {
			if ( '' === $part || ! is_numeric( $part ) ) {
				continue;
			}

			$number = (int) $part;

			if ( ! in_array( $number, $numbers, true ) ) {
				$numbers[] = $number;
			}
		}

		return $numbers;
	}

	/**
	 * Liest und validiert den gewählten Gerätetyp aus der GET-Anfrage.
	 * Ein unbekannter oder fehlender Wert ergibt "" (= "Alle").
	 *
	 * @param array $device_type_options Verfügbare Gerätetyp-Keys => Labels.
	 *
	 * @return string
	 */
	private function get_selected_device_type( array $device_type_options ) {
		if ( ! isset( $_GET['eps_device_type'] ) ) {
			return '';
		}

		$raw = sanitize_text_field( wp_unslash( $_GET['eps_device_type'] ) );

		return isset( $device_type_options[ $raw ] ) ? $raw : '';
	}

	/**
	 * Ermittelt Schrittweite und Nachkommastellen für die Live-Anzeige eines
	 * Sliders anhand seiner Einheit - reine Darstellungs-Heuristik, hat
	 * keinen Einfluss auf die Filterlogik selbst.
	 *
	 * @param string $unit Einheit aus der Filter-Registry, z. B. "V", "°C".
	 *
	 * @return array{0: float, 1: int} [Schrittweite, Nachkommastellen]
	 */
	private function get_slider_precision( $unit ) {
		$map = array(
			'V' => array( 0.1, 1 ),
			'A' => array( 0.1, 1 ),
		);

		return isset( $map[ $unit ] ) ? $map[ $unit ] : array( 1, 0 );
	}

	/**
	 * Rundet einen Wert auf ein Vielfaches von $step, abwärts oder aufwärts.
	 *
	 * Wichtig für Regler-Grenzen: ein <input type="range" min="X" step="S">
	 * kann per Drag/Tastatur NUR Werte auf dem Raster X, X+S, X+2S, ... erreichen.
	 * Ist das tatsächliche Datenminimum kein glattes Vielfaches von S (z. B.
	 * 8,4 bei Schrittweite 1), liegt der wahre Datenmaximalwert u. U. NICHT
	 * auf diesem Raster und ist per Drag nie exakt erreichbar - der Regler
	 * "verfehlt" ihn dann leicht (siehe Bug: 1439 statt 1440 W). Deshalb wird
	 * hier abwärts (Minimum) bzw. aufwärts (Maximum) auf die Schrittweite
	 * gerundet, sodass beide Enden garantiert Vielfache von $step sind und
	 * das Maximum immer exakt erreichbar bleibt.
	 *
	 * Die Division wird vor dem Runden auf 6 Nachkommastellen geglättet, um
	 * reines Gleitkomma-Rauschen (z. B. fmod(12, 0.1) !== 0 wegen binärer
	 * Fließkommadarstellung von 0,1) nicht fälschlich als Fehlausrichtung zu
	 * werten.
	 *
	 * @param float  $value     Zu rundender Wert.
	 * @param float  $step      Schrittweite des Reglers.
	 * @param string $direction "down" (abrunden) oder "up" (aufrunden).
	 *
	 * @return float
	 */
	private function round_to_step( $value, $step, $direction ) {
		if ( $step <= 0 ) {
			return $value;
		}

		$steps = round( $value / $step, 6 );

		return ( 'down' === $direction ? floor( $steps ) : ceil( $steps ) ) * $step;
	}

	/**
	 * Berechnet die erreichbare Von-Bis-Spanne eines Slider-Filters über
	 * eine Geräteliste - nur Geräte, die das Attribut überhaupt haben,
	 * fließen ein (z. B. hat nur "e_load" resistance_range). Das Minimum
	 * wird auf die Schrittweite ABgerundet, das Maximum AUFgerundet (siehe
	 * round_to_step()), damit der Regler nie den echten Maximalwert eines
	 * passenden Geräts ausschließt.
	 *
	 * @param EPS_Device[] $devices    Geräte, über die die Spanne ermittelt wird.
	 * @param string       $key        Attribut-Key.
	 * @param array        $filter_def Filter-Definition (für "match").
	 * @param float        $step       Schrittweite des Reglers (siehe get_slider_precision()).
	 *
	 * @return array{min: float, max: float}
	 */
	private function compute_slider_bounds( array $devices, $key, array $filter_def, $step ) {
		$min = null;
		$max = null;

		foreach ( $devices as $device ) {
			if ( ! $device->has_attribute( $key ) ) {
				continue;
			}

			$value = $device->get_attribute( $key );

			if ( 'cover' === $filter_def['match'] ) {
				$value_parts = array_values( (array) $value );
				$device_min  = isset( $value_parts[0] ) ? (float) $value_parts[0] : 0.0;
				$device_max  = isset( $value_parts[1] ) ? (float) $value_parts[1] : $device_min;
			} else {
				$device_min = (float) $value;
				$device_max = (float) $value;
			}

			$min = ( null === $min ) ? $device_min : min( $min, $device_min );
			$max = ( null === $max ) ? $device_max : max( $max, $device_max );
		}

		if ( null === $min || null === $max ) {
			return array(
				'min' => 0.0,
				'max' => 0.0,
			);
		}

		return array(
			'min' => $this->round_to_step( $min, $step, 'down' ),
			'max' => $this->round_to_step( $max, $step, 'up' ),
		);
	}

	/**
	 * Rendert ein Von-Bis-Schieberegler-Feld (zwei überlappende Range-Inputs)
	 * mit einer vorgeschalteten Checkbox. Der Regler ist rein visuell/interaktiv
	 * deaktiviert, solange die Checkbox nicht angehakt ist - erst dann wird
	 * sein Wert für die Filterung berücksichtigt (siehe render_page()).
	 *
	 * @param string $label      Sichtbares Label.
	 * @param string $field_key  Basisname für die GET-Parameter, z. B. "output_voltage" -> eps_output_voltage_from/_to/_active.
	 * @param float  $from_value Aktueller "Von"-Wert.
	 * @param float  $to_value   Aktueller "Bis"-Wert.
	 * @param float  $min        Regler-Minimum.
	 * @param float  $max        Regler-Maximum.
	 * @param float  $step       Schrittweite.
	 * @param int    $decimals   Nachkommastellen für die Live-Anzeige.
	 * @param string $unit       Einheit, z. B. "V", "A", "°C".
	 * @param bool   $is_active  Ob die Checkbox angehakt ist (Filter aktiv).
	 */
	private function render_range_field( $label, $field_key, $from_value, $to_value, $min, $max, $step, $decimals, $unit, $is_active ) {
		?>
		<div class="eps-range-field">
			<label class="eps-range-toggle-label">
				<input
					type="checkbox"
					class="eps-range-toggle"
					name="eps_<?php echo esc_attr( $field_key ); ?>_active"
					value="1"
					<?php checked( $is_active ); ?>
				/>
				<?php
				/* translators: %s: Feldbezeichnung, z. B. "Spannung" */
				printf( esc_html__( '%s filtern', 'eps-produktkonfigurator' ), esc_html( $label ) );
				?>
			</label>
			<div class="eps-range-slider<?php echo $is_active ? '' : ' is-inactive'; ?>" data-field="<?php echo esc_attr( $field_key ); ?>" data-decimals="<?php echo esc_attr( $decimals ); ?>">
				<div class="eps-range-track"></div>
				<div class="eps-range-progress"></div>
				<input
					type="range"
					class="eps-range-input eps-range-from"
					name="eps_<?php echo esc_attr( $field_key ); ?>_from"
					min="<?php echo esc_attr( $min ); ?>"
					max="<?php echo esc_attr( $max ); ?>"
					step="<?php echo esc_attr( $step ); ?>"
					value="<?php echo esc_attr( $from_value ); ?>"
				/>
				<input
					type="range"
					class="eps-range-input eps-range-to"
					name="eps_<?php echo esc_attr( $field_key ); ?>_to"
					min="<?php echo esc_attr( $min ); ?>"
					max="<?php echo esc_attr( $max ); ?>"
					step="<?php echo esc_attr( $step ); ?>"
					value="<?php echo esc_attr( $to_value ); ?>"
				/>
			</div>
			<div class="eps-range-values">
				<span class="eps-range-value-from"><?php echo esc_html( number_format_i18n( $from_value, $decimals ) ); ?></span>
				&nbsp;–&nbsp;
				<span class="eps-range-value-to"><?php echo esc_html( number_format_i18n( $to_value, $decimals ) ); ?></span>
				&nbsp;<?php echo esc_html( $unit ); ?>
			</div>
			<p class="eps-range-no-devices" hidden>
				<?php esc_html_e( 'Keine Geräte für diese Kombination.', 'eps-produktkonfigurator' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Rendert ein Dropdown-Filterfeld (Einfachauswahl mit "Alle" als Standard).
	 * Die Optionen kommen unverändert aus der Filter-Registry - nichts ist
	 * hier hartkodiert. Einzelne <option>-Elemente können clientseitig live
	 * deaktiviert werden, wenn sie unter den übrigen aktiven Filtern 0 Geräte
	 * ergäben (siehe admin-test-page.js).
	 *
	 * @param string $label          Sichtbares Label.
	 * @param string $field_key      GET-Parameter-Name, z. B. "display" -> eps_display.
	 * @param array  $options        key => ['label_de' => ..., 'label_en' => ...].
	 * @param string $selected_value Aktuell gewählter Options-Key, oder '' für "Alle".
	 */
	private function render_dropdown_field( $label, $field_key, array $options, $selected_value ) {
		?>
		<div class="eps-dropdown-field" data-field="<?php echo esc_attr( $field_key ); ?>">
			<label for="eps_<?php echo esc_attr( $field_key ); ?>">
				<?php echo esc_html( $label ); ?>:
			</label>
			<select name="eps_<?php echo esc_attr( $field_key ); ?>" id="eps_<?php echo esc_attr( $field_key ); ?>">
				<option value="" <?php selected( '', $selected_value ); ?>>
					<?php esc_html_e( 'Alle', 'eps-produktkonfigurator' ); ?>
				</option>
				<?php foreach ( $options as $option_key => $option ) : ?>
					<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, $selected_value ); ?>>
						<?php echo esc_html( $option['label_de'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="eps-range-no-devices" hidden>
				<?php esc_html_e( 'Keine Geräte für diese Kombination.', 'eps-produktkonfigurator' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Rendert ein Boolean-Filterfeld ("nur Geräte mit ..."-Checkbox). Nicht
	 * angehakt bedeutet keine Einschränkung, NICHT "nur Geräte ohne".
	 *
	 * @param string $label      Sichtbares Label.
	 * @param string $field_key  GET-Parameter-Name, z. B. "bidirectional" -> eps_bidirectional.
	 * @param bool   $is_checked Ob die Checkbox angehakt ist (Filter aktiv).
	 */
	private function render_boolean_field( $label, $field_key, $is_checked ) {
		?>
		<div class="eps-boolean-field" data-field="<?php echo esc_attr( $field_key ); ?>">
			<label>
				<input
					type="checkbox"
					name="eps_<?php echo esc_attr( $field_key ); ?>"
					value="1"
					<?php checked( $is_checked ); ?>
				/>
				<?php
				/* translators: %s: Feldbezeichnung, z. B. "Funktionsgenerator" */
				printf( esc_html__( 'Nur Geräte mit %s', 'eps-produktkonfigurator' ), esc_html( $label ) );
				?>
			</label>
			<p class="eps-range-no-devices" hidden>
				<?php esc_html_e( 'Keine Geräte für diese Kombination.', 'eps-produktkonfigurator' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Rendert das Options-Suchfeld: Freitext mit mehreren, durch Komma oder
	 * Leerzeichen getrennten Optionsnummern (UND-Verknüpfung, exakter
	 * Zahlenvergleich gegen $attributes['option_numbers'] - siehe
	 * EPS_Filter::device_matches_registry_filters()). Unter dem Feld werden
	 * die zu erkannten Nummern gehörenden Katalognamen angezeigt; für
	 * Nummern außerhalb des Katalogs erscheint stattdessen ein Hinweis.
	 *
	 * @param string $label            Sichtbares Label.
	 * @param string $field_key        GET-Parameter-Name, z. B. "option_numbers".
	 * @param string $raw_value        Roher, unveränderter Feldinhalt (für erneutes Anzeigen).
	 * @param array  $resolved_names   Katalognamen der erkannten Nummern, in Eingabereihenfolge.
	 * @param int[]  $unknown_numbers  Eingegebene Nummern, die nicht im Katalog stehen.
	 */
	private function render_options_search_field( $label, $field_key, $raw_value, array $resolved_names, array $unknown_numbers ) {
		?>
		<div class="eps-text-field" data-field="<?php echo esc_attr( $field_key ); ?>">
			<label for="eps_<?php echo esc_attr( $field_key ); ?>">
				<?php echo esc_html( $label ); ?>:
			</label>
			<input
				type="text"
				name="eps_<?php echo esc_attr( $field_key ); ?>"
				id="eps_<?php echo esc_attr( $field_key ); ?>"
				value="<?php echo esc_attr( $raw_value ); ?>"
				placeholder="<?php esc_attr_e( 'z. B. 3, 6', 'eps-produktkonfigurator' ); ?>"
			/>
			<?php if ( ! empty( $resolved_names ) ) : ?>
				<p class="eps-text-field-names">
					<?php echo esc_html( implode( ', ', $resolved_names ) ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $unknown_numbers ) ) : ?>
				<p class="eps-text-field-unknown">
					<?php
					printf(
						/* translators: %s: Liste unbekannter Nummern, z. B. "13, 20" */
						esc_html__( 'Unbekannte Nummer(n): %s (nicht im Katalog).', 'eps-produktkonfigurator' ),
						esc_html( implode( ', ', $unknown_numbers ) )
					);
					?>
				</p>
			<?php endif; ?>
			<p class="eps-range-no-devices" hidden>
				<?php esc_html_e( 'Keine Geräte für diese Kombination.', 'eps-produktkonfigurator' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Schnellsuche: ein Freitextfeld für Kundenanfragen („Stromquelle
	 * 0-30V 1kA 30kW“). Die Auswertung läuft komplett im Browser
	 * (assets/js/eps-quick-search.js + admin-quick-search.js), ohne
	 * Seitenneuladen und ohne KI. „In Filter übernehmen“ setzt Gerätetyp und
	 * Spannungs-/Strom-/Leistungsregler (+20-%-Regel) und lädt die Seite mit
	 * diesen Filtern – bereits gesetzte Filter werden dabei nur auf
	 * ausdrücklichen Klick ersetzt.
	 */
	private function render_quick_search_section() {
		$stand = function_exists( 'eps_get_shop_devices_stand' ) ? eps_get_shop_devices_stand() : '';
		$count = function_exists( 'eps_get_shop_devices' ) ? count( eps_get_shop_devices() ) : 0;
		?>
		<div class="eps-quick-search" id="eps-quick-search-box">
			<h2><?php esc_html_e( 'Schnellsuche: Kundenanfrage eingeben', 'eps-produktkonfigurator' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: 1: Anzahl Geräte, 2: Datum der Shopdaten */
					esc_html__( '%1$d Geräte aus dem EPS-Onlineshop (Stand %2$s). Spannung, Strom und Leistung mit Einheit eingeben, z. B. „Stromquelle 0-30V 1kA 30kW“, „E-Last 500V 100A“, „230VAC 3kVA“.', 'eps-produktkonfigurator' ),
					(int) $count,
					esc_html( $stand ? date_i18n( 'd.m.Y H:i', strtotime( $stand ) ) : '–' )
				);
				?>
			</p>
			<p>
				<input type="search" id="eps-qs-input" class="regular-text eps-qs-input" autocomplete="off"
					placeholder="<?php esc_attr_e( 'z. B. Stromquelle 0-30V 1kA 30kW', 'eps-produktkonfigurator' ); ?>" />
				<label class="eps-qs-option">
					<input type="checkbox" id="eps-qs-internal" checked="checked" />
					<?php esc_html_e( 'Interne Ansicht (knapp darunter, Hinweise, Parallel-Anzahl)', 'eps-produktkonfigurator' ); ?>
				</label>
				<button type="button" class="button" id="eps-qs-apply" disabled="disabled">
					<?php esc_html_e( 'In Filter übernehmen', 'eps-produktkonfigurator' ); ?>
				</button>
			</p>
			<p id="eps-qs-recognized" class="eps-qs-recognized"></p>
			<table class="widefat striped eps-qs-results" id="eps-qs-results" hidden="hidden">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Bewertung', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Name', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Gerätetyp', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Spannung', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Strom', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Leistung', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Reserve', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Hinweis', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Datenblatt', 'eps-produktkonfigurator' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
			<p id="eps-qs-more" class="description"></p>
		</div>
		<?php
	}

	/**
	 * Rendert die Testseite mit Schnellsuche und einer Tabelle aller Geräte inkl. Validierung.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filters_config      = function_exists( 'eps_get_filters_config' ) ? eps_get_filters_config() : array();
		$device_type_def     = isset( $filters_config['device_type'] ) ? $filters_config['device_type'] : array( 'options' => array() );
		$device_type_options = $device_type_def['options'];

		$slider_defs   = array();
		$dropdown_defs = array();
		$boolean_defs  = array();
		$text_defs     = array();
		foreach ( $filters_config as $key => $filter_def ) {
			if ( 'slider' === $filter_def['type'] ) {
				$slider_defs[ $key ] = $filter_def;
			} elseif ( 'dropdown' === $filter_def['type'] ) {
				$dropdown_defs[ $key ] = $filter_def;
			} elseif ( 'boolean' === $filter_def['type'] ) {
				$boolean_defs[ $key ] = $filter_def;
			} elseif ( 'text' === $filter_def['type'] ) {
				$text_defs[ $key ] = $filter_def;
			}
		}

		$options_catalog = function_exists( 'eps_get_options_catalog' ) ? eps_get_options_catalog() : array();

		$all_devices = function_exists( 'eps_get_devices' ) ? eps_get_devices() : array();

		// Alle vorkommenden application_tags einsammeln (für das Dropdown).
		$all_tags = array();
		foreach ( $all_devices as $device ) {
			foreach ( $device->application_tags as $tag ) {
				$all_tags[ $tag ] = true;
			}
		}
		$all_tags = array_keys( $all_tags );
		sort( $all_tags );

		$selected_tag = isset( $_GET['eps_tag'] ) ? sanitize_text_field( wp_unslash( $_GET['eps_tag'] ) ) : '';

		// Webcode/SKU-Direktsuche: eigener, von Anwendungsbereich + Reglern
		// unabhängiger Such-Modus. Ist ein Suchbegriff gesetzt, hat er Vorrang
		// vor allen anderen Filtern (nur EIN Modus ist gleichzeitig aktiv).
		$sku_query      = isset( $_GET['eps_sku'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['eps_sku'] ) ) ) : '';
		$has_sku_search = ( '' !== $sku_query );

		// Gerätetyp: die "Einstiegsfrage". Gilt unabhängig vom gewählten
		// Modus (SKU-Suche, Regler, Anwendungsbereich) als Hart-Filter -
		// jeder Modus arbeitet nur innerhalb der Geräte des gewählten Typs.
		$selected_device_type = $this->get_selected_device_type( $device_type_options );
		$type_filtered_devices = ( '' !== $selected_device_type )
			? array_values(
				array_filter(
					$all_devices,
					function ( $device ) use ( $selected_device_type ) {
						return $device->device_type === $selected_device_type;
					}
				)
			)
			: $all_devices;

		// Für jeden Slider-Filter: Regler-Grenzen aus den tatsächlichen
		// Werten ALLER Mock-Geräte ableiten (nicht nur des gewählten Typs -
		// die Grenzen sollen weit genug sein, dass ein Typwechsel im Browser
		// live nachjustieren kann), GET-Werte lesen und Aktiv-Status prüfen.
		$slider_state = array();
		foreach ( $slider_defs as $key => $filter_def ) {
			list( $step, $decimals ) = $this->get_slider_precision( $filter_def['unit'] );

			$bounds = $this->compute_slider_bounds( $all_devices, $key, $filter_def, $step );

			$from   = $this->get_range_value( 'eps_' . $key . '_from', $bounds['min'], $bounds['max'], $bounds['min'] );
			$to     = $this->get_range_value( 'eps_' . $key . '_to', $bounds['min'], $bounds['max'], $bounds['max'] );
			$active = $this->is_filter_active( 'eps_' . $key . '_active' );

			$slider_state[ $key ] = array(
				'def'      => $filter_def,
				'bounds'   => $bounds,
				'from'     => $from,
				'to'       => $to,
				'active'   => $active,
				'step'     => $step,
				'decimals' => $decimals,
			);
		}

		// Dropdown-Filter: GET-Wert lesen und gegen die erlaubten Options-Keys
		// validieren - ein unbekannter/fehlender Wert ergibt "" (= "Alle").
		$dropdown_state = array();
		foreach ( $dropdown_defs as $key => $filter_def ) {
			$raw = isset( $_GET[ 'eps_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'eps_' . $key ] ) ) : '';

			$dropdown_state[ $key ] = array(
				'def'   => $filter_def,
				'value' => isset( $filter_def['options'][ $raw ] ) ? $raw : '',
			);
		}

		// Boolean-Filter: wiederverwendet dieselbe Checkbox-Logik wie die
		// Slider-Aktivierung (Checkbox angehakt = Filter aktiv).
		$boolean_state = array();
		foreach ( $boolean_defs as $key => $filter_def ) {
			$boolean_state[ $key ] = array(
				'def'    => $filter_def,
				'active' => $this->is_filter_active( 'eps_' . $key ),
			);
		}

		// Text-Filter (Options-Suche): mehrere Nummern per Komma/Leerzeichen,
		// UND-Verknüpfung, exakter Zahlenvergleich. Bekannte Nummern lösen
		// wir hier bereits zu Katalognamen auf; unbekannte (nicht im Katalog)
		// landen separat für den Hinweistext - beeinflussen die Filterung
		// aber trotzdem (kein Gerät hat je eine katalogfremde Nummer, das
		// Ergebnis wird also ehrlich 0 statt die Nummer stillschweigend zu ignorieren).
		$text_state = array();
		foreach ( $text_defs as $key => $filter_def ) {
			$raw     = isset( $_GET[ 'eps_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'eps_' . $key ] ) ) : '';
			$numbers = $this->parse_number_list( $raw );

			$known_names     = array();
			$unknown_numbers = array();

			foreach ( $numbers as $number ) {
				if ( isset( $options_catalog[ $number ] ) ) {
					$known_names[] = $options_catalog[ $number ]['label_de'];
				} else {
					$unknown_numbers[] = $number;
				}
			}

			$text_state[ $key ] = array(
				'def'             => $filter_def,
				'raw'             => $raw,
				'numbers'         => $numbers,
				'known_names'     => $known_names,
				'unknown_numbers' => $unknown_numbers,
			);
		}

		$has_registry_filter = false;
		$active_filters       = array();

		foreach ( $slider_state as $key => $state ) {
			if ( ! $state['active'] ) {
				continue;
			}
			$has_registry_filter    = true;
			$active_filters[ $key ] = array(
				'from' => $state['from'],
				'to'   => $state['to'],
			);
		}

		foreach ( $dropdown_state as $key => $state ) {
			if ( '' === $state['value'] ) {
				continue;
			}
			$has_registry_filter    = true;
			$active_filters[ $key ] = array(
				'value' => $state['value'],
			);
		}

		foreach ( $boolean_state as $key => $state ) {
			if ( ! $state['active'] ) {
				continue;
			}
			$has_registry_filter    = true;
			$active_filters[ $key ] = true;
		}

		foreach ( $text_state as $key => $state ) {
			if ( empty( $state['numbers'] ) ) {
				continue;
			}
			$has_registry_filter    = true;
			$active_filters[ $key ] = array(
				'numbers' => $state['numbers'],
			);
		}

		// Konfliktprüfung Spannung/Strom/Leistung (aus Loop 5b) bleibt
		// unverändert bestehen - sie ist inhaltlich spezifisch für diese
		// drei physikalischen Größen (U = I * P) und lässt sich nicht generisch
		// auf beliebige Registry-Filter übertragen.
		$conflict_message = null;
		$conflict_hint     = null;

		$has_uip_sliders = isset( $slider_state['output_voltage'], $slider_state['output_current'], $slider_state['output_power'] );

		if ( $has_uip_sliders ) {
			$u = $slider_state['output_voltage'];
			$i = $slider_state['output_current'];
			$p = $slider_state['output_power'];

			if ( $u['active'] && $i['active'] && $p['active'] ) {
				$is_exact_value = ( $u['from'] === $u['to'] ) && ( $i['from'] === $i['to'] ) && ( $p['from'] === $p['to'] );

				if ( $is_exact_value ) {
					$conflict_message = EPS_Filter::detect_conflict( $i['from'], $i['to'], $u['from'], $u['to'], $p['from'], $p['to'] );
				} else {
					$conflict_hint = __( 'Konfliktprüfung nur bei exakten Werten möglich (beide Regler-Enden auf denselben Wert stellen).', 'eps-produktkonfigurator' );
				}
			}
		}

		if ( $has_sku_search ) {
			// Modus 1: Webcode/SKU-Direktsuche - ignoriert Gerätetyp, Anwendungsbereich
			// und Regler und durchsucht ALLE gültigen Geräte.
			$devices = EPS_Filter::by_sku( $all_devices, $sku_query );
		} elseif ( $has_registry_filter ) {
			// Modus 2a: Gefiltert nach den aktivierten Registry-Slidern.
			$devices = EPS_Filter::by_registry( $type_filtered_devices, $filters_config, $selected_device_type, $active_filters );
		} elseif ( '' !== $selected_tag ) {
			// Modus 2b: Gefiltert nach Anwendungsbereich.
			$devices = EPS_Filter::by_application( $type_filtered_devices, $selected_tag );
		} else {
			// Kein Such-Modus aktiv: nur der Gerätetyp-Filter (falls gesetzt) greift.
			$devices = $type_filtered_devices;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Konfigurator Test – Geräte aus dem Onlineshop', 'eps-produktkonfigurator' ); ?></h1>
			<p>
				<em>
					<?php
					esc_html_e( 'Diese Seite ist nur temporär zu Testzwecken und wird entfernt, sobald die echte UI existiert.', 'eps-produktkonfigurator' );
					?>
				</em>
			</p>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=eps-konfigurator-test' ) ); ?>" class="button">
					<?php esc_html_e( 'Filter zurücksetzen', 'eps-produktkonfigurator' ); ?>
				</a>
			</p>

			<?php $this->render_quick_search_section(); ?>

			<h2><?php esc_html_e( 'Modus 1: Webcode/SKU-Direktsuche', 'eps-produktkonfigurator' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="eps-konfigurator-test" />

				<p>
					<label for="eps_sku">
						<?php esc_html_e( 'Webcode/SKU suchen:', 'eps-produktkonfigurator' ); ?>
					</label>
					<input type="text" name="eps_sku" id="eps_sku" value="<?php echo esc_attr( $sku_query ); ?>" placeholder="<?php esc_attr_e( 'z. B. IND oder NT-2010', 'eps-produktkonfigurator' ); ?>" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Suchen', 'eps-produktkonfigurator' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Modus 2: Gerätetyp, Anwendungsbereich & technische Werte', 'eps-produktkonfigurator' ); ?></h2>
			<p>
				<em><?php esc_html_e( 'Wird ignoriert, solange oben ein Webcode/SKU-Suchbegriff gesetzt ist.', 'eps-produktkonfigurator' ); ?></em>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="eps-konfigurator-test" />

				<p>
					<label for="eps_device_type">
						<strong><?php esc_html_e( 'Gerätetyp:', 'eps-produktkonfigurator' ); ?></strong>
					</label>
					<select name="eps_device_type" id="eps_device_type">
						<option value="" <?php selected( '', $selected_device_type ); ?>>
							<?php esc_html_e( 'Alle', 'eps-produktkonfigurator' ); ?>
						</option>
						<?php foreach ( $device_type_options as $type_key => $type_option ) : ?>
							<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $type_key, $selected_device_type ); ?>>
								<?php echo esc_html( $type_option['label_de'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="eps_tag">
						<?php esc_html_e( 'Anwendungsbereich:', 'eps-produktkonfigurator' ); ?>
					</label>
					<select name="eps_tag" id="eps_tag">
						<option value="" <?php selected( '', $selected_tag ); ?>>
							<?php esc_html_e( 'Alle anzeigen', 'eps-produktkonfigurator' ); ?>
						</option>
						<?php foreach ( $all_tags as $tag ) : ?>
							<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $tag, $selected_tag ); ?>>
								<?php echo esc_html( $tag ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<div>
					<?php foreach ( $slider_state as $key => $state ) : ?>
						<?php
						$this->render_range_field(
							$state['def']['label_de'],
							$key,
							$state['from'],
							$state['to'],
							$state['bounds']['min'],
							$state['bounds']['max'],
							$state['step'],
							$state['decimals'],
							$state['def']['unit'],
							$state['active']
						);
						?>
					<?php endforeach; ?>
				</div>

				<div>
					<?php foreach ( $dropdown_state as $key => $state ) : ?>
						<?php
						$this->render_dropdown_field(
							$state['def']['label_de'],
							$key,
							$state['def']['options'],
							$state['value']
						);
						?>
					<?php endforeach; ?>
				</div>

				<div>
					<?php foreach ( $boolean_state as $key => $state ) : ?>
						<?php
						$this->render_boolean_field(
							$state['def']['label_de'],
							$key,
							$state['active']
						);
						?>
					<?php endforeach; ?>
				</div>

				<div>
					<?php foreach ( $text_state as $key => $state ) : ?>
						<?php
						$this->render_options_search_field(
							$state['def']['label_de'],
							$key,
							$state['raw'],
							$state['known_names'],
							$state['unknown_numbers']
						);
						?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtern', 'eps-produktkonfigurator' ); ?></button>
				</p>
			</form>

			<?php if ( $conflict_message ) : ?>
				<div class="notice notice-warning" style="padding: 10px 12px; font-weight: bold;">
					<p><?php echo esc_html( $conflict_message ); ?></p>
				</div>
			<?php elseif ( $conflict_hint ) : ?>
				<div class="notice notice-info" style="padding: 10px 12px;">
					<p><?php echo esc_html( $conflict_hint ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			if ( $has_sku_search ) {
				$active_mode_label = sprintf(
					/* translators: %s: eingegebener Suchbegriff */
					__( 'SKU-Suche über alle Gerätetypen nach "%s" (Gerätetyp, Anwendungsbereich & Regler sind deaktiviert).', 'eps-produktkonfigurator' ),
					$sku_query
				);
			} elseif ( $has_registry_filter ) {
				$active_filter_labels = array();
				foreach ( $slider_state as $key => $state ) {
					if ( $state['active'] ) {
						$active_filter_labels[] = $state['def']['label_de'];
					}
				}
				foreach ( $dropdown_state as $key => $state ) {
					if ( '' !== $state['value'] ) {
						$active_filter_labels[] = sprintf(
							'%1$s (%2$s)',
							$state['def']['label_de'],
							$state['def']['options'][ $state['value'] ]['label_de']
						);
					}
				}
				foreach ( $boolean_state as $key => $state ) {
					if ( $state['active'] ) {
						$active_filter_labels[] = $state['def']['label_de'];
					}
				}
				foreach ( $text_state as $key => $state ) {
					if ( ! empty( $state['numbers'] ) ) {
						$active_filter_labels[] = sprintf(
							'%1$s (%2$s)',
							$state['def']['label_de'],
							implode( ', ', $state['numbers'] )
						);
					}
				}
				$active_mode_label = sprintf(
					/* translators: %s: Liste der aktiven Filter, z. B. "Ausgangsspannung, Ausgangsstrom" */
					__( 'Technische Werte-Filterung nach: %s.', 'eps-produktkonfigurator' ),
					implode( ', ', $active_filter_labels )
				);
			} elseif ( '' !== $selected_tag ) {
				$active_mode_label = sprintf(
					/* translators: %s: gewählter Anwendungsbereich */
					__( 'Anwendungsbereich-Filter: "%s".', 'eps-produktkonfigurator' ),
					$selected_tag
				);
			} else {
				$active_mode_label = __( 'Kein Such-Filter aktiv.', 'eps-produktkonfigurator' );
			}

			// Der Gerätetyp-Hinweis gilt nur außerhalb der SKU-Suche, da diese
			// bewusst über ALLE Gerätetypen hinweg sucht (siehe oben).
			if ( ! $has_sku_search && '' !== $selected_device_type && isset( $device_type_options[ $selected_device_type ] ) ) {
				$active_mode_label = sprintf(
					/* translators: 1: Gerätetyp-Label, 2: restlicher Modus-Text */
					__( 'Gerätetyp "%1$s". %2$s', 'eps-produktkonfigurator' ),
					$device_type_options[ $selected_device_type ]['label_de'],
					$active_mode_label
				);
			}
			?>
			<div class="notice notice-info" style="padding: 10px 12px;">
				<p>
					<strong><?php esc_html_e( 'Aktiver Modus:', 'eps-produktkonfigurator' ); ?></strong>
					<?php echo esc_html( $active_mode_label ); ?>
				</p>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'SKU', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Name', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Gerätetyp', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Kategorie', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Spannungsbereich', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Strom', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Leistung', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Optionen', 'eps-produktkonfigurator' ); ?></th>
						<th><?php esc_html_e( 'Gültig?', 'eps-produktkonfigurator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $devices as $device ) : ?>
						<?php $is_valid = $device->is_valid(); ?>
						<tr>
							<td><?php echo esc_html( $device->sku ); ?></td>
							<td><?php echo esc_html( $device->name_de ); ?></td>
							<td>
								<?php
								echo esc_html(
									isset( $device_type_options[ $device->device_type ] )
										? $device_type_options[ $device->device_type ]['label_de']
										: $device->device_type
								);
								?>
							</td>
							<td><?php echo esc_html( $device->category ); ?></td>
							<td>
								<?php
								echo esc_html( $device->voltage_min . ' – ' . $device->voltage_max . ' V' );
								?>
							</td>
							<td><?php echo esc_html( $device->current_max . ' A' ); ?></td>
							<td><?php echo esc_html( $device->power_max . ' W' ); ?></td>
							<td>
								<?php
								$device_option_numbers = (array) $device->get_attribute( 'option_numbers' );

								if ( empty( $device_option_numbers ) ) {
									echo '–';
								} else {
									$option_labels = array();
									foreach ( $device_option_numbers as $option_number ) {
										$option_labels[] = isset( $options_catalog[ $option_number ] )
											? $option_number . ' – ' . $options_catalog[ $option_number ]['label_de']
											: (string) $option_number;
									}
									echo esc_html( implode( ', ', $option_labels ) );
								}
								?>
							</td>
							<td>
								<?php if ( $is_valid ) : ?>
									<span style="color: #1a7f37; font-weight: bold;">&#10004; <?php esc_html_e( 'gültig', 'eps-produktkonfigurator' ); ?></span>
								<?php else : ?>
									<span style="color: #d63638; font-weight: bold;">&#10008; <?php esc_html_e( 'ungültig', 'eps-produktkonfigurator' ); ?></span>
									<br />
									<span style="color: #d63638;">
										<?php echo esc_html( implode( ' ', $device->get_validation_errors() ) ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
