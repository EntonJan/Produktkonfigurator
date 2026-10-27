/**
 * EPS Schnellsuche – Kundenanfrage als Freitext („Stromquelle 0-30V 1kA 30kW“)
 * → passende Geräte, sortiert nach Eignung.
 *
 * Reine Logik ohne DOM: läuft im Browser (window.EPSQuickSearch) und in Node
 * (module.exports, für tests/quick-search.test.js). Gleiche Regeln wie die
 * interne EPS-Produktsuche (Windows-Programm im Datenblatt-Converter-Repo):
 *
 *  exact          – Gerät reicht und kein Wert liegt über der Obergrenze
 *                   (Wunsch +20 %, aufgerundet: bis 100 in der 1-2-5-Reihe,
 *                   darüber in 250er-Schritten – wie die Konfigurator-Filter)
 *  larger         – Gerät reicht, ist aber größer
 *  parallel/series – reicht erst mit n Geräten (nur wenn laut Daten möglich)
 *  slightly_below – höchstens 10 % zu klein (nur intern)
 *
 * Im Kundenmodus (internal: false) werden parallel/series zu „system“
 * (Systemlösung auf Anfrage) und „slightly_below“ entfällt.
 *
 * Gerät = { sku, name_de, name_en, device_type, is_valid, attributes, meta }
 * wie von EPS_Device / shop-devices.php geliefert.
 */
( function ( root, factory ) {
	var api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	} else {
		root.EPSQuickSearch = api;
	}
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	var ORDER = { exact: 0, larger: 1, parallel: 2, series: 3, system: 2, slightly_below: 4 };
	var MAX_UNITS = 10;          // ohne Angabe in den Daten höchstens 10 Geräte parallel/in Reihe
	var SLIGHTLY = 0.9;          // „knapp darunter“: mindestens 90 % des Bedarfs
	var AC_TOLERANCE = 0.1;      // feste AC-Spannung: ±10 %
	var OVERSIZED = 3;           // ab dreifacher Reserve Hinweis „stark überdimensioniert“
	var PREFIX = { k: 1e3, K: 1e3, M: 1e6, m: 1e-3 };
	var AC_OUTPUT = [ 'ac_ac', 'dc_ac' ];

	var TOKEN = /(^|[^\w.,])(\d+(?:[.,]\d+)?)\s*(?:(?:-|–|bis|to|\.\.\.?|…)\s*(\d+(?:[.,]\d+)?)\s*)?([kKMm]?)(VA|V|A|W)(AC|DC|ac|dc|rms|eff)?(?![a-zA-Z])/g;

	function num( s ) {
		return parseFloat( String( s ).replace( ',', '.' ) );
	}

	/** Wunsch + 20 %, aufgerundet (1-2-5-Reihe bis 100, darüber 250er-Schritte). */
	function upperLimit( wish ) {
		if ( ! ( wish > 0 ) ) {
			return 0;
		}
		var z = wish * 1.2;
		if ( z > 100 ) {
			return Math.ceil( Math.round( z * 1e9 ) / 1e9 / 250 ) * 250;
		}
		var exp = Math.floor( Math.log10( z ) );
		var bases = [ 1, 2, 5, 10 ];
		for ( var b = 0; b < bases.length; b++ ) {
			var cand = bases[ b ] * Math.pow( 10, exp );
			if ( cand >= z - 1e-12 ) {
				return Math.round( cand * 1e12 ) / 1e12;
			}
		}
		return Math.pow( 10, exp + 1 );
	}

	/**
	 * Freitext lesen. Liefert Zahlen in Grundeinheiten und erkannte Hinweise.
	 *
	 * @param {string} text
	 * @return {{text:string, voltage:?number, current:?number, power:?number,
	 *           deviceType:string, load:boolean, source:boolean, output:string}}
	 */
	function parseQuery( text ) {
		var q = { text: String( text || '' ), voltage: null, current: null, power: null,
			deviceType: '', load: false, source: false, output: '' };
		var m;
		TOKEN.lastIndex = 0;
		while ( ( m = TOKEN.exec( q.text ) ) !== null ) {
			var value = num( m[ 3 ] || m[ 2 ] ) * ( PREFIX[ m[ 4 ] ] || 1 );
			var unit = m[ 5 ];
			var kind = ( m[ 6 ] || '' ).toUpperCase();
			if ( unit === 'V' ) {
				q.voltage = value;
				if ( kind === 'AC' || kind === 'DC' ) {
					q.output = kind;
				}
			} else if ( unit === 'A' ) {
				q.current = value;
			} else {
				q.power = value;
			}
		}
		var t = q.text.toLowerCase();
		if ( /(^|[^a-zäöü])(e-?last|elektronische last|last|senke|sink|e-?load|electronic load|load)([^a-zäöü]|$)/.test( t ) ) {
			q.deviceType = 'e_load';
			q.load = true;
		} else {
			var types = { 'ac/ac': 'ac_ac', 'ac/dc': 'ac_dc', 'dc/ac': 'dc_ac', 'dc/dc': 'dc_dc' };
			Object.keys( types ).forEach( function ( k ) {
				if ( ! q.deviceType && t.indexOf( k ) !== -1 ) {
					q.deviceType = types[ k ];
				}
			} );
			if ( /lade(gerät|geraet)?([^a-zäöü]|$)|charger/.test( t ) ) {
				q.deviceType = 'charger';
			}
			if ( /wechselrichter|inverter/.test( t ) ) {
				q.deviceType = 'dc_ac';
			}
			if ( /frequenzwandler|frequency converter/.test( t ) ) {
				q.deviceType = 'ac_ac';
			}
		}
		if ( /quelle|netzger|netzteil|stromversorgung|source|power supply/.test( t ) ) {
			q.source = true;
		}
		return q;
	}

	/** Bedarf an Leistung: angegeben oder U × I – der größere Wert. */
	function powerNeeded( q ) {
		var vi = ( q.voltage && q.current ) ? q.voltage * q.current : null;
		if ( q.power && vi ) {
			return Math.max( q.power, vi );
		}
		return q.power || vi || null;
	}

	function isEmpty( q ) {
		return ! ( q.voltage || q.current || q.power );
	}

	// ------------------------------------------------------------------ Gerätewerte

	function attr( d, key ) {
		return d.attributes && Object.prototype.hasOwnProperty.call( d.attributes, key ) ? d.attributes[ key ] : null;
	}

	function meta( d ) {
		return d.meta || {};
	}

	function deviceValues( d ) {
		var u = attr( d, 'output_voltage' );
		return {
			umin: Array.isArray( u ) ? u[ 0 ] : ( u === null ? null : u ),
			umax: Array.isArray( u ) ? u[ 1 ] : ( u === null ? null : u ),
			imax: attr( d, 'output_current' ),
			pmax: attr( d, 'output_power' )
		};
	}

	function isAc( d ) {
		return meta( d ).ac === true || AC_OUTPUT.indexOf( d.device_type ) !== -1;
	}

	function canSink( d ) {
		return d.device_type === 'e_load' || attr( d, 'bidirectional' ) === true;
	}

	function typeMatches( d, q ) {
		if ( q.load ) {
			return canSink( d );
		}
		if ( q.deviceType ) {
			return d.device_type === q.deviceType;
		}
		if ( q.source && d.device_type === 'e_load' ) {
			return false;
		}
		if ( q.output === 'AC' ) {
			return isAc( d );
		}
		if ( q.output === 'DC' ) {
			return ! isAc( d );
		}
		return true;
	}

	function acVoltageMatches( d, u ) {
		var m = meta( d );
		var ranges = m.u_ranges || [];
		for ( var r = 0; r < ranges.length; r++ ) {
			if ( ranges[ r ][ 0 ] - 1e-9 <= u && u <= ranges[ r ][ 1 ] + 1e-9 ) {
				return true;
			}
		}
		var fixed = m.u_fixed || [];
		for ( var f = 0; f < fixed.length; f++ ) {
			if ( Math.abs( fixed[ f ] - u ) <= AC_TOLERANCE * u ) {
				return true;
			}
		}
		return false;
	}

	// ------------------------------------------------------------------ Bewertung

	/**
	 * Bewertet ein Gerät. null = passt nicht.
	 *
	 * @param {Object} d   Gerät
	 * @param {Object} q   Ergebnis von parseQuery()
	 * @param {Object=} opts { maxUnits: number }
	 * @return {?{device:Object, category:string, units:number, reserve:Object, notes:string[]}}
	 */
	function evaluate( d, q, opts ) {
		opts = opts || {};
		if ( d.is_valid === false || isEmpty( q ) || ! typeMatches( d, q ) ) {
			return null;
		}
		var v = deviceValues( d );
		var u = q.voltage;
		var i = q.current;
		var p = powerNeeded( q );
		if ( ( u && v.umax === null ) || ( i && v.imax === null ) || ( p && v.pmax === null ) ) {
			return null;
		}
		var m = meta( d );
		var acFixed = !! ( u && isAc( d ) && ( ( m.u_fixed || [] ).length || ( m.u_ranges || [] ).length ) );
		var fu = null;
		if ( acFixed ) {
			if ( ! acVoltageMatches( d, u ) ) {
				return null;
			}
			fu = 1;
		} else if ( u ) {
			fu = v.umax / u;
		}
		var fi = i ? v.imax / i : null;
		var fp = p ? v.pmax / p : null;

		function result( category, units, note ) {
			var reserve = {};
			if ( fu !== null ) {
				reserve.U = fu * ( category === 'series' ? units : 1 );
			}
			if ( fi !== null ) {
				reserve.I = fi * ( category === 'parallel' ? units : 1 );
			}
			if ( fp !== null ) {
				reserve.P = fp * units;
			}
			var notes = [];
			if ( note ) {
				notes.push( note );
			}
			var big = Object.keys( reserve ).filter( function ( k ) {
				return reserve[ k ] >= OVERSIZED;
			} );
			if ( big.length ) {
				notes.push( 'oversized:' + big.join( ',' ) );
			}
			if ( m.derived_from_name ) {
				notes.push( 'derived:' + m.derived_from_name );
			}
			return { device: d, category: category, units: units, reserve: reserve, notes: notes };
		}

		function ok( f ) {
			return f === null || f >= 1;
		}

		if ( ok( fu ) && ok( fi ) && ok( fp ) ) {
			var exact = ( acFixed || ! u || v.umax <= upperLimit( u ) + 1e-9 ) &&
				( ! i || v.imax <= upperLimit( i ) + 1e-9 ) &&
				( ! p || v.pmax <= upperLimit( p ) + 1e-9 );
			return result( exact ? 'exact' : 'larger', 1 );
		}

		var limit = Math.min( m.parallel_max || opts.maxUnits || MAX_UNITS, opts.maxUnits || 64, 64 );
		var need;
		if ( ok( fu ) && m.parallel ) {
			need = Math.ceil( Math.max( fi !== null ? 1 / fi : 0, fp !== null ? 1 / fp : 0 ) - 1e-9 );
			if ( need <= limit ) {
				return result( 'parallel', need, m.parallel_max ? 'parallel_max:' + m.parallel_max : '' );
			}
		}
		if ( ok( fi ) && m.series && ! acFixed ) {
			need = Math.ceil( Math.max( fu !== null ? 1 / fu : 0, fp !== null ? 1 / fp : 0 ) - 1e-9 );
			if ( need <= Math.min( opts.maxUnits || MAX_UNITS, MAX_UNITS ) ) {
				return result( 'series', need );
			}
		}
		if ( [ fu, fi, fp ].every( function ( f ) {
			return f === null || f >= SLIGHTLY;
		} ) ) {
			var below = [];
			if ( fu !== null && fu < 1 ) {
				below.push( 'U' );
			}
			if ( fi !== null && fi < 1 ) {
				below.push( 'I' );
			}
			if ( fp !== null && fp < 1 ) {
				below.push( 'P' );
			}
			return result( 'slightly_below', 1, 'below:' + below.join( ',' ) );
		}
		return null;
	}

	function rank( r ) {
		var over = 0;
		var under = 0;
		Object.keys( r.reserve ).forEach( function ( k ) {
			var f = r.reserve[ k ];
			if ( f >= 1 ) {
				over += Math.log( f );
			} else if ( f > 0 ) {
				under -= Math.log( f );
			}
		} );
		return [ ORDER[ r.category ], r.units, under, over ];
	}

	function compareRank( a, b ) {
		var ra = rank( a );
		var rb = rank( b );
		for ( var k = 0; k < ra.length; k++ ) {
			if ( ra[ k ] !== rb[ k ] ) {
				return ra[ k ] - rb[ k ];
			}
		}
		return 0;
	}

	/**
	 * Alle passenden Geräte, beste zuerst.
	 *
	 * @param {Object[]} devices
	 * @param {Object|string} query  parseQuery()-Ergebnis oder Freitext
	 * @param {Object=} opts { internal: boolean (Standard true), maxUnits: number }
	 */
	function search( devices, query, opts ) {
		opts = opts || {};
		var internal = opts.internal !== false;
		var q = typeof query === 'string' ? parseQuery( query ) : query;
		var out = [];
		( devices || [] ).forEach( function ( d ) {
			var r = evaluate( d, q, opts );
			if ( ! r ) {
				return;
			}
			if ( ! internal ) {
				if ( r.category === 'slightly_below' ) {
					return;
				}
				if ( r.category === 'parallel' || r.category === 'series' ) {
					r.category = 'system';
				}
				r.notes = [];
			}
			out.push( r );
		} );
		return out.sort( compareRank );
	}

	/**
	 * Filterwerte für die normalen Konfigurator-Regler aus einer Anfrage:
	 * Spannung „cover“ (Gerät muss U abdecken), Strom/Leistung „within“
	 * [Wunsch, Obergrenze] – dieselbe +20-%-Regel wie die Schnellsuche.
	 */
	function toFilterValues( query ) {
		var q = typeof query === 'string' ? parseQuery( query ) : query;
		var f = {};
		if ( q.deviceType ) {
			f.device_type = q.deviceType;
		}
		if ( q.voltage ) {
			f.output_voltage = { from: q.voltage, to: q.voltage };
		}
		if ( q.current ) {
			f.output_current = { from: q.current, to: upperLimit( q.current ) };
		}
		var p = powerNeeded( q );
		if ( p ) {
			f.output_power = { from: p, to: upperLimit( p ) };
		}
		return f;
	}

	return {
		parseQuery: parseQuery,
		powerNeeded: powerNeeded,
		upperLimit: upperLimit,
		evaluate: evaluate,
		search: search,
		toFilterValues: toFilterValues
	};
} ) );
