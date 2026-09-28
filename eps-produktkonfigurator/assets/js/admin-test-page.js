/**
 * Faceted-Search-Verhalten für die temporäre Admin-Testseite
 * (EPS_Admin_Test_Page). Reines Vanilla JS, kein Framework. Wird zusammen
 * mit der Testseite entfernt, sobald die echte UI existiert.
 *
 * Generisch für eine BELIEBIGE Anzahl von Filtern aus der Filter-Registry
 * (window.epsFiltersConfig, per wp_add_inline_script von PHP eingebettet) -
 * keine Feldnamen sind hier hartkodiert. Drei Feldarten werden unterstützt:
 *
 * - Slider (kind "slider"): zwei überlappende <input type="range"> bilden
 *   Von/Bis ab. Live-Neuberechnung der MIN/MAX-Grenzen, siehe applyExtent().
 * - Dropdown (kind "dropdown"): <select> mit "Alle" als Standard. Optionen,
 *   die unter den übrigen aktiven Filtern 0 Geräte ergäben, werden live
 *   deaktiviert (ausgegraut) - außer der GERADE gewählten Option, die bleibt
 *   wählbar, damit ihr Wert beim Absenden nicht verloren geht; stattdessen
 *   erscheint der "Keine Geräte"-Hinweis.
 * - Boolean (kind "boolean"): Checkbox "nur Geräte mit ...". Analog zum
 *   Dropdown wird eine NICHT angehakte Checkbox deaktiviert, wenn sie 0
 *   Geräte ergäbe; eine bereits angehakte Checkbox bleibt anfassbar.
 *
 * Die Werte werden weiterhin nativ über das GET-Formular übertragen - dieses
 * Skript kümmert sich nur um die visuelle Darstellung und die Live-
 * Neuberechnung, sobald ein Feld verändert ODER der Gerätetyp gewechselt wird,
 * sowie um das Ein-/Ausblenden von Feldern, die laut Registry (applies_to)
 * für den gewählten Gerätetyp nicht gelten.
 *
 * Wichtige Regel gegen zirkuläre Effekte: die Grenzen/Verfügbarkeit eines
 * Feldes werden IMMER aus dem Gerätetyp und den jeweils ANDEREN Feldern
 * berechnet, nie aus seiner eigenen aktuellen Einstellung - siehe
 * filterDevicesExcluding().
 */
( function () {
	'use strict';

	var registry = {};
	var deviceTypeSelect = null;

	function formatValue( value, decimals ) {
		return Number( value ).toFixed( decimals );
	}

	function getFilterDef( fieldKey ) {
		return ( window.epsFiltersConfig || {} )[ fieldKey ] || null;
	}

	// Gilt ein Filter laut Registry (applies_to) für den Gerätetyp eines
	// bestimmten Geräts? Leeres/fehlendes applies_to = gilt für alle Typen.
	function filterAppliesToDevice( fieldKey, device ) {
		var def = getFilterDef( fieldKey );

		if ( ! def || ! def.applies_to || ! def.applies_to.length ) {
			return true;
		}

		return -1 !== def.applies_to.indexOf( device.device_type );
	}

	// Liest den aktuellen (Live-)Zustand eines Feldes aus dem DOM, je nach Art.
	function collectFieldState( fieldKey ) {
		var entry = registry[ fieldKey ];

		if ( 'slider' === entry.kind ) {
			return {
				active: entry.toggle ? entry.toggle.checked : false,
				from: parseFloat( entry.fromInput.value ),
				to: parseFloat( entry.toInput.value )
			};
		}

		if ( 'dropdown' === entry.kind ) {
			var value = entry.select.value;
			return {
				active: '' !== value,
				value: value
			};
		}

		if ( 'boolean' === entry.kind ) {
			return {
				active: entry.checkbox.checked
			};
		}

		return { active: false };
	}

	// Prüft, ob der Attributwert eines Geräts zum aktuellen Zustand eines
	// Feldes passt. "cover"/"within" siehe PHP-Pendant
	// EPS_Filter::slider_value_matches(); Dropdown ist exakter Match,
	// Boolean verlangt true.
	function deviceMatchesFieldState( fieldKey, device, state ) {
		var def = getFilterDef( fieldKey );
		var value = device.attributes ? device.attributes[ fieldKey ] : undefined;

		if ( ! def || null === value || undefined === value ) {
			return false;
		}

		if ( 'slider' === def.type ) {
			if ( 'cover' === def.match ) {
				return value[ 0 ] <= state.from && value[ 1 ] >= state.to;
			}
			return value >= state.from && value <= state.to;
		}

		if ( 'dropdown' === def.type ) {
			return value === state.value;
		}

		if ( 'boolean' === def.type ) {
			return true === value;
		}

		return true;
	}

	// Ermittelt die erreichbare Von-Bis-Spanne eines Slider-Feldes über eine
	// Geräteliste - nur Geräte, die für dieses Feld überhaupt einen Wert
	// haben, fließen ein (z. B. hat nur "e_load" resistance_range).
	function getExtent( fieldKey, devices ) {
		var def = getFilterDef( fieldKey );

		if ( ! def ) {
			return null;
		}

		var min = Infinity;
		var max = -Infinity;
		var found = false;

		devices.forEach( function ( device ) {
			var value = device.attributes ? device.attributes[ fieldKey ] : undefined;

			if ( null === value || undefined === value ) {
				return;
			}

			found = true;

			if ( 'cover' === def.match ) {
				min = Math.min( min, value[ 0 ] );
				max = Math.max( max, value[ 1 ] );
			} else {
				min = Math.min( min, value );
				max = Math.max( max, value );
			}
		} );

		if ( ! found ) {
			return null;
		}

		var entry = registry[ fieldKey ];
		var step = ( entry && entry.fromInput ) ? parseFloat( entry.fromInput.step ) : NaN;

		if ( isNaN( step ) || step <= 0 ) {
			step = 1;
		}

		return {
			min: roundToStep( min, step, 'down' ),
			max: roundToStep( max, step, 'up' )
		};
	}

	// Rundet einen Wert auf ein Vielfaches von "step", ab- oder aufwärts.
	// Grund: ein <input type="range" min="X" step="S"> kann per Drag/Tastatur
	// NUR Werte auf dem Raster X, X+S, X+2S, ... erreichen. Ist das
	// tatsächliche Datenminimum kein glattes Vielfaches von S, liegt der
	// wahre Datenmaximalwert u. U. nicht auf diesem Raster und ist per Drag
	// nie exakt erreichbar (siehe PHP-Pendant round_to_step() für das
	// konkrete Bug-Beispiel: 1439 statt 1440 W). Die Division wird vor dem
	// Runden geglättet, um reines Gleitkomma-Rauschen (z. B. 12/0.1 in
	// Binär-Fließkomma) nicht fälschlich als Fehlausrichtung zu werten.
	function roundToStep( value, step, direction ) {
		if ( ! step || step <= 0 ) {
			return value;
		}

		var steps = Math.round( ( value / step ) * 1e6 ) / 1e6;

		return ( 'down' === direction ? Math.floor( steps ) : Math.ceil( steps ) ) * step;
	}

	function getSelectedDeviceType() {
		return deviceTypeSelect ? deviceTypeSelect.value : '';
	}

	// Filtert die gültigen Mock-Geräte nach dem gewählten Gerätetyp (Hart-
	// Filter) und den AKTIVEN Filtern aller ANDEREN Felder - das
	// ausgeschlossene Feld selbst fließt nie ein, das verhindert zirkuläre
	// Effekte (ein Feld beeinflusst nie seine eigenen Grenzen/Optionen).
	function filterDevicesExcluding( excludeKey ) {
		var selectedType = getSelectedDeviceType();
		var devices = ( window.epsMockDevices || [] ).filter( function ( device ) {
			return device.is_valid;
		} );

		if ( selectedType ) {
			devices = devices.filter( function ( device ) {
				return device.device_type === selectedType;
			} );
		}

		var fieldKeys = Object.keys( registry );

		return devices.filter( function ( device ) {
			return fieldKeys.every( function ( key ) {
				if ( key === excludeKey ) {
					return true;
				}

				var state = collectFieldState( key );

				if ( ! state.active ) {
					return true;
				}

				if ( ! filterAppliesToDevice( key, device ) ) {
					return true; // Filter gilt für diesen Gerätetyp nicht - ignorieren, nicht ausschließen.
				}

				return deviceMatchesFieldState( key, device, state );
			} );
		} );
	}

	function updateSliderVisual( fieldKey ) {
		var entry = registry[ fieldKey ];
		var fromInput = entry.fromInput;
		var toInput = entry.toInput;
		var min = parseFloat( fromInput.min );
		var max = parseFloat( fromInput.max );
		var span = max - min;

		var fromValue = parseFloat( fromInput.value );
		var toValue = parseFloat( toInput.value );

		var fromPercent = span > 0 ? ( ( fromValue - min ) / span ) * 100 : 0;
		var toPercent = span > 0 ? ( ( toValue - min ) / span ) * 100 : 100;

		entry.progress.style.left = fromPercent + '%';
		entry.progress.style.right = ( 100 - toPercent ) + '%';

		if ( entry.fromLabel ) {
			entry.fromLabel.textContent = formatValue( fromValue, entry.decimals );
		}
		if ( entry.toLabel ) {
			entry.toLabel.textContent = formatValue( toValue, entry.decimals );
		}
	}

	// Übernimmt eine neu berechnete Spanne für ein Slider-Feld: setzt min/max
	// der beiden Range-Inputs. Nur wenn der Nutzer DIESEN Regler bereits
	// selbst bewegt hat (entry.touched, siehe bindFieldEvents), bleibt seine
	// gewählte Position erhalten und wird nur geklemmt, falls sie jetzt
	// außerhalb der neuen Spanne liegt (nicht auf 0). In jedem anderen Fall
	// - Regler inaktiv, ODER aktiv aber noch nie manuell angefasst - wird
	// immer die volle neue Spanne gesetzt (Regler bleibt "auf Maximum", nur
	// die Zahlen darunter ändern sich live).
	function applyExtent( fieldKey, extent ) {
		var entry = registry[ fieldKey ];
		var fromInput = entry.fromInput;
		var toInput = entry.toInput;
		var isActive = entry.toggle ? entry.toggle.checked : false;

		fromInput.min = extent.min;
		fromInput.max = extent.max;
		toInput.min = extent.min;
		toInput.max = extent.max;

		var fromValue;
		var toValue;

		if ( isActive && entry.touched ) {
			fromValue = Math.min( Math.max( parseFloat( fromInput.value ), extent.min ), extent.max );
			toValue = Math.min( Math.max( parseFloat( toInput.value ), extent.min ), extent.max );
		} else {
			fromValue = extent.min;
			toValue = extent.max;
		}

		if ( fromValue > toValue ) {
			fromValue = toValue;
		}

		fromInput.value = fromValue;
		toInput.value = toValue;

		updateSliderVisual( fieldKey );
	}

	// Blendet einzelne <option>-Elemente eines Dropdown-Feldes aus, wenn sie
	// unter den übrigen aktiven Filtern 0 Geräte ergäben. Die AKTUELL
	// gewählte Option wird nie deaktiviert (sonst ginge ihr Wert beim
	// Absenden verloren, weil deaktivierte Formularfelder nicht mitgeschickt
	// werden) - wird sie durch andere Filter ungültig, erscheint stattdessen
	// der "Keine Geräte"-Hinweis, ohne die Auswahl stillschweigend zu ändern.
	function recomputeDropdownAvailability( fieldKey, matchingDevices ) {
		var entry = registry[ fieldKey ];
		var select = entry.select;
		var currentValue = select.value;
		var currentValueHasNoMatch = false;

		Array.prototype.forEach.call( select.options, function ( option ) {
			if ( '' === option.value ) {
				option.disabled = false; // "Alle" ist nie deaktiviert.
				return;
			}

			var count = matchingDevices.filter( function ( device ) {
				return device.attributes && device.attributes[ fieldKey ] === option.value;
			} ).length;

			if ( option.value === currentValue ) {
				option.disabled = false;
				currentValueHasNoMatch = ( 0 === count );
			} else {
				option.disabled = ( 0 === count );
			}
		} );

		if ( entry.hint ) {
			entry.hint.hidden = ! ( '' !== currentValue && currentValueHasNoMatch );
		}
	}

	// Deaktiviert eine NICHT angehakte Boolean-Checkbox, wenn ein Ankreuzen
	// unter den übrigen aktiven Filtern 0 Geräte ergäbe. Eine bereits
	// angehakte Checkbox bleibt aus demselben Grund wie beim Dropdown immer
	// bedienbar - stattdessen erscheint der "Keine Geräte"-Hinweis.
	function recomputeBooleanAvailability( fieldKey, matchingDevices ) {
		var entry = registry[ fieldKey ];
		var checkbox = entry.checkbox;

		var count = matchingDevices.filter( function ( device ) {
			return device.attributes && true === device.attributes[ fieldKey ];
		} ).length;

		if ( checkbox.checked ) {
			checkbox.disabled = false;
			if ( entry.hint ) {
				entry.hint.hidden = ( 0 !== count );
			}
		} else {
			checkbox.disabled = ( 0 === count );
			if ( entry.hint ) {
				entry.hint.hidden = true;
			}
		}
	}

	// Berechnet für ALLE registrierten Felder Grenzen/Verfügbarkeit neu
	// (jeweils aus Gerätetyp + den jeweils anderen Feldern). Bleibt die
	// passende Gerätemenge für ein Slider-Feld leer (oder hat kein
	// verbleibendes Gerät überhaupt einen Wert für dieses Attribut), bleiben
	// dessen bisherige Grenzen erhalten und ein Hinweistext wird eingeblendet.
	function recomputeAllExtents() {
		Object.keys( registry ).forEach( function ( fieldKey ) {
			var entry = registry[ fieldKey ];
			var matchingDevices = filterDevicesExcluding( fieldKey );

			if ( 'slider' === entry.kind ) {
				var extent = matchingDevices.length ? getExtent( fieldKey, matchingDevices ) : null;

				if ( ! extent ) {
					if ( entry.hint ) {
						entry.hint.hidden = false;
					}
					return;
				}

				if ( entry.hint ) {
					entry.hint.hidden = true;
				}

				applyExtent( fieldKey, extent );
			} else if ( 'dropdown' === entry.kind ) {
				recomputeDropdownAvailability( fieldKey, matchingDevices );
			} else if ( 'boolean' === entry.kind ) {
				recomputeBooleanAvailability( fieldKey, matchingDevices );
			}
		} );
	}

	// Blendet Felder ein/aus, je nachdem ob ihr Filter laut Registry
	// (applies_to) für den aktuell gewählten Gerätetyp gilt. Bei "Alle"
	// (leerer Wert) bleiben alle Felder sichtbar.
	function updateFieldVisibility() {
		var selectedType = getSelectedDeviceType();

		Object.keys( registry ).forEach( function ( fieldKey ) {
			var entry = registry[ fieldKey ];
			var def = getFilterDef( fieldKey );
			var visible = true;

			if ( def && def.applies_to && def.applies_to.length && selectedType ) {
				visible = -1 !== def.applies_to.indexOf( selectedType );
			}

			if ( entry.field ) {
				entry.field.hidden = ! visible;
			}
		} );
	}

	function initSliderField( fieldKey, slider ) {
		var field = slider.closest( '.eps-range-field' );
		var fromInput = slider.querySelector( '.eps-range-from' );
		var toInput = slider.querySelector( '.eps-range-to' );
		var toggle = field ? field.querySelector( '.eps-range-toggle' ) : null;
		var isActiveAtLoad = toggle ? toggle.checked : false;

		// War das Feld beim Laden aktiv UND weicht seine vom Server gerenderte
		// Position von der vollen Standardspanne ab, kommt das von einem
		// bewusst abgeschickten Filter (GET-Parameter) - das zählt als
		// "berührt" und bleibt erhalten. Ein frisch angehaktes, aber noch nie
		// gezogenes Feld (Standardspanne) gilt dagegen als "unberührt" und
		// reagiert weiterhin live auf volle Breite - siehe applyExtent().
		var isAtDefaultRange = ( parseFloat( fromInput.value ) === parseFloat( fromInput.min ) ) &&
			( parseFloat( toInput.value ) === parseFloat( toInput.max ) );

		registry[ fieldKey ] = {
			kind: 'slider',
			field: field,
			slider: slider,
			fromInput: fromInput,
			toInput: toInput,
			progress: slider.querySelector( '.eps-range-progress' ),
			fromLabel: field ? field.querySelector( '.eps-range-value-from' ) : null,
			toLabel: field ? field.querySelector( '.eps-range-value-to' ) : null,
			toggle: toggle,
			hint: field ? field.querySelector( '.eps-range-no-devices' ) : null,
			decimals: parseInt( slider.getAttribute( 'data-decimals' ) || '0', 10 ),
			touched: isActiveAtLoad && ! isAtDefaultRange
		};
	}

	function initDropdownField( fieldKey, field ) {
		registry[ fieldKey ] = {
			kind: 'dropdown',
			field: field,
			select: field.querySelector( 'select' ),
			hint: field.querySelector( '.eps-range-no-devices' )
		};
	}

	function initBooleanField( fieldKey, field ) {
		registry[ fieldKey ] = {
			kind: 'boolean',
			field: field,
			checkbox: field.querySelector( 'input[type="checkbox"]' ),
			hint: field.querySelector( '.eps-range-no-devices' )
		};
	}

	function bindFieldEvents( fieldKey ) {
		var entry = registry[ fieldKey ];

		if ( 'slider' === entry.kind ) {
			var fromInput = entry.fromInput;
			var toInput = entry.toInput;

			var onFromInput = function () {
				entry.touched = true;
				if ( parseFloat( fromInput.value ) > parseFloat( toInput.value ) ) {
					fromInput.value = toInput.value;
				}
				updateSliderVisual( fieldKey );
				recomputeAllExtents();
			};

			var onToInput = function () {
				entry.touched = true;
				if ( parseFloat( toInput.value ) < parseFloat( fromInput.value ) ) {
					toInput.value = fromInput.value;
				}
				updateSliderVisual( fieldKey );
				recomputeAllExtents();
			};

			fromInput.addEventListener( 'input', onFromInput );
			toInput.addEventListener( 'input', onToInput );

			// Wenn beide Regler exakt übereinanderliegen, den zuletzt berührten
			// Regler nach vorne holen, damit er weiterhin greifbar bleibt.
			[ fromInput, toInput ].forEach( function ( input ) {
				input.addEventListener( 'pointerdown', function () {
					fromInput.style.zIndex = input === fromInput ? '3' : '2';
					toInput.style.zIndex = input === toInput ? '3' : '2';
				} );
			} );

			if ( entry.toggle ) {
				entry.toggle.addEventListener( 'change', function () {
					entry.slider.classList.toggle( 'is-inactive', ! entry.toggle.checked );
					recomputeAllExtents();
				} );
			}
		} else if ( 'dropdown' === entry.kind ) {
			entry.select.addEventListener( 'change', function () {
				recomputeAllExtents();
			} );
		} else if ( 'boolean' === entry.kind ) {
			entry.checkbox.addEventListener( 'change', function () {
				recomputeAllExtents();
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		deviceTypeSelect = document.getElementById( 'eps_device_type' );

		document.querySelectorAll( '.eps-range-slider[data-field]' ).forEach( function ( slider ) {
			initSliderField( slider.getAttribute( 'data-field' ), slider );
		} );

		document.querySelectorAll( '.eps-dropdown-field[data-field]' ).forEach( function ( field ) {
			initDropdownField( field.getAttribute( 'data-field' ), field );
		} );

		document.querySelectorAll( '.eps-boolean-field[data-field]' ).forEach( function ( field ) {
			initBooleanField( field.getAttribute( 'data-field' ), field );
		} );

		Object.keys( registry ).forEach( function ( fieldKey ) {
			if ( 'slider' === registry[ fieldKey ].kind ) {
				updateSliderVisual( fieldKey );
			}
			bindFieldEvents( fieldKey );
		} );

		updateFieldVisibility();

		if ( deviceTypeSelect ) {
			deviceTypeSelect.addEventListener( 'change', function () {
				updateFieldVisibility();
				recomputeAllExtents();
			} );
		}
	} );
} )();
