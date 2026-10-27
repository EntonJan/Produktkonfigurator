/**
 * Oberfläche der Schnellsuche auf der Testseite. Die Logik steckt in
 * eps-quick-search.js (window.EPSQuickSearch), die Geräte in window.epsDevices.
 */
( function () {
	'use strict';

	var MAX_ROWS = 50;
	var LABELS = {
		exact: 'passt genau',
		larger: 'passt (größer)',
		parallel: '× parallel',
		series: '× in Reihe',
		system: 'Systemlösung auf Anfrage',
		slightly_below: 'knapp darunter'
	};
	var VALUE_LABELS = { U: 'Spannung', I: 'Strom', P: 'Leistung' };

	function fmt( value, unit ) {
		if ( value === null || value === undefined ) {
			return '–';
		}
		if ( ( unit === 'W' || unit === 'VA' ) && value >= 1000 ) {
			return String( +( value / 1000 ).toFixed( 3 ) ).replace( '.', ',' ) + ' k' + unit;
		}
		return String( +value.toFixed( 3 ) ).replace( '.', ',' ) + ' ' + unit;
	}

	function categoryLabel( r ) {
		if ( r.category === 'parallel' || r.category === 'series' ) {
			return r.units + LABELS[ r.category ];
		}
		return LABELS[ r.category ] || r.category;
	}

	function reserveText( r ) {
		return Object.keys( r.reserve ).map( function ( k ) {
			var f = r.reserve[ k ];
			var pct = Math.round( Math.abs( f - 1 ) * 100 );
			return k + ' ' + ( f >= 1 ? '+' : '−' ) + pct + ' %';
		} ).join( ' · ' );
	}

	function noteText( r ) {
		return r.notes.map( function ( n ) {
			var parts = n.split( ':' );
			var keys = ( parts[ 1 ] || '' ).split( ',' ).map( function ( k ) {
				return VALUE_LABELS[ k ] || k;
			} );
			switch ( parts[ 0 ] ) {
				case 'oversized':
					return 'stark überdimensioniert: ' + keys.join( ', ' );
				case 'derived':
					return keys.join( ', ' ) + ' aus Typbezeichnung';
				case 'below':
					return keys.join( ', ' ) + ' etwas zu klein';
				case 'parallel_max':
					return 'Parallelschaltung bis ' + parts[ 1 ] + ' Geräte';
				default:
					return n;
			}
		} ).join( '; ' );
	}

	function recognizedText( q ) {
		var parts = [];
		if ( q.voltage ) {
			parts.push( 'U = ' + fmt( q.voltage, 'V' ) );
		}
		if ( q.current ) {
			parts.push( 'I = ' + fmt( q.current, 'A' ) );
		}
		if ( q.power ) {
			parts.push( 'P = ' + fmt( q.power, 'W' ) );
		} else if ( q.voltage && q.current ) {
			parts.push( 'P = U × I = ' + fmt( q.voltage * q.current, 'W' ) );
		}
		if ( q.deviceType ) {
			var label = ( window.epsDeviceTypeLabels || {} )[ q.deviceType ] || q.deviceType;
			parts.push( label + ( q.load ? ' (inkl. bidirektionaler Geräte)' : '' ) );
		} else if ( q.source ) {
			parts.push( 'Quelle (ohne reine Lasten)' );
		}
		if ( q.output && ! q.deviceType ) {
			parts.push( q.output + '-Ausgang' );
		}
		return parts.length ? 'Erkannt: ' + parts.join( ' · ' ) : '';
	}

	function cell( text ) {
		var td = document.createElement( 'td' );
		td.textContent = text;
		return td;
	}

	function filterUrl( values ) {
		var params = new URLSearchParams();
		params.set( 'page', 'eps-konfigurator-test' );
		if ( values.device_type ) {
			params.set( 'eps_device_type', values.device_type );
		}
		[ 'output_voltage', 'output_current', 'output_power' ].forEach( function ( key ) {
			if ( values[ key ] ) {
				params.set( 'eps_' + key + '_from', values[ key ].from );
				params.set( 'eps_' + key + '_to', values[ key ].to );
				params.set( 'eps_' + key + '_active', '1' );
			}
		} );
		return window.location.pathname + '?' + params.toString();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var QS = window.EPSQuickSearch;
		var input = document.getElementById( 'eps-qs-input' );
		if ( ! QS || ! input ) {
			return;
		}
		var internal = document.getElementById( 'eps-qs-internal' );
		var apply = document.getElementById( 'eps-qs-apply' );
		var recognized = document.getElementById( 'eps-qs-recognized' );
		var table = document.getElementById( 'eps-qs-results' );
		var tbody = table.querySelector( 'tbody' );
		var more = document.getElementById( 'eps-qs-more' );
		var typeLabels = window.epsDeviceTypeLabels || {};

		function run() {
			var q = QS.parseQuery( input.value );
			var hasValues = !! ( q.voltage || q.current || q.power );
			tbody.innerHTML = '';
			more.textContent = '';
			apply.disabled = ! hasValues;
			if ( ! input.value.trim() ) {
				recognized.textContent = '';
				table.hidden = true;
				return;
			}
			if ( ! hasValues ) {
				recognized.textContent = 'Keine Werte erkannt – bitte Einheit mit angeben (V, A, W, k für kilo). Für Artikelnummern die Webcode/SKU-Suche unten nutzen.';
				table.hidden = true;
				return;
			}
			var results = QS.search( window.epsDevices || [], q, { internal: internal.checked } );
			var counts = {};
			results.forEach( function ( r ) {
				counts[ r.category ] = ( counts[ r.category ] || 0 ) + 1;
			} );
			recognized.textContent = recognizedText( q ) + ' → ' + results.length + ' Treffer' +
				( counts.exact ? ', ' + counts.exact + ' passen genau' : '' );
			table.hidden = results.length === 0;
			results.slice( 0, MAX_ROWS ).forEach( function ( r ) {
				var d = r.device;
				var m = d.meta || {};
				var texts = m.texts || {};
				var a = d.attributes || {};
				var tr = document.createElement( 'tr' );
				tr.className = 'eps-qs-' + r.category;
				tr.appendChild( cell( categoryLabel( r ) ) );
				tr.appendChild( cell( d.sku ) );
				tr.appendChild( cell( d.name_de ) );
				tr.appendChild( cell( typeLabels[ d.device_type ] || d.device_type ) );
				tr.appendChild( cell( texts.voltage || ( a.output_voltage ? a.output_voltage.join( '–' ) + ' V' : '–' ) ) );
				tr.appendChild( cell( texts.current || fmt( a.output_current, 'A' ) ) );
				tr.appendChild( cell( texts.power || fmt( a.output_power, 'W' ) ) );
				tr.appendChild( cell( reserveText( r ) ) );
				tr.appendChild( cell( noteText( r ) ) );
				var td = document.createElement( 'td' );
				if ( m.pdf_url ) {
					var link = document.createElement( 'a' );
					link.href = m.pdf_url;
					link.target = '_blank';
					link.rel = 'noopener';
					link.textContent = 'PDF';
					td.appendChild( link );
				}
				tr.appendChild( td );
				tbody.appendChild( tr );
			} );
			if ( results.length > MAX_ROWS ) {
				more.textContent = ( results.length - MAX_ROWS ) + ' weitere Treffer – Anfrage genauer fassen (z. B. Gerätetyp ergänzen).';
			} else if ( ! results.length ) {
				more.textContent = 'Keine passenden Geräte – im Kundenfall: „Mit Beratung fortfahren“.';
			}
		}

		input.addEventListener( 'input', run );
		internal.addEventListener( 'change', run );
		apply.addEventListener( 'click', function () {
			window.location.href = filterUrl( QS.toFilterValues( input.value ) );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! apply.disabled ) {
				e.preventDefault();
				apply.click();
			}
		} );
	} );
}() );
