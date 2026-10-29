/**
 * Gemeinsame Suchfälle mit der EPS-Produktsuche (Python): tests/gemeinsame-suchfaelle.json ist in beiden Repos
 * identisch. Die Testgeräte laufen dabei durch tools/shop-import.py – so wird auch die Umwandlung mitgeprüft.
 * Aufruf: node --test tests/   (braucht Python 3 als „python3“ oder „python“; sonst werden die Fälle übersprungen)
 */
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );

const QS = require( path.join( __dirname, '../eps-produktkonfigurator/assets/js/eps-quick-search.js' ) );
const DATEI = path.join( __dirname, 'gemeinsame-suchfaelle.json' );
const faelle = require( DATEI );

function umwandeln() {
	const skript = path.join( __dirname, '../tools/shop-import.py' );
	for ( const py of [ 'python3', 'python', 'py' ] ) {
		try {
			return JSON.parse( execFileSync( py, [ skript, '--json', DATEI ], { encoding: 'utf8' } ) );
		} catch ( e ) {
			// nächsten Interpreter versuchen
		}
	}
	return null;
}

const devices = umwandeln();
const ohnePython = devices ? false : 'Python nicht gefunden';

function beschreiben( r ) {
	if ( ! r ) {
		return null;
	}
	return [ 'parallel', 'series' ].includes( r.category ) ? r.category + ':' + r.units : r.category;
}

test( 'Testgeräte umgewandelt', { skip: ohnePython }, () => {
	assert.equal( devices.length, faelle.geraete.length );
	assert.ok( devices.every( ( d ) => /^T-\d+$/.test( d.sku ) ), 'nur Testgeräte' );
} );

for ( const fall of faelle.faelle ) {
	test( 'Suchfall: ' + fall.anfrage, { skip: ohnePython }, () => {
		const treffer = QS.search( devices, fall.anfrage );
		const nachSku = Object.fromEntries( treffer.map( ( r ) => [ r.device.sku, r ] ) );
		for ( const [ sku, erwartet ] of Object.entries( fall.ergebnis ) ) {
			assert.equal( beschreiben( nachSku[ sku ] ), erwartet, `${ fall.anfrage }: ${ sku }` );
		}
		if ( fall.erster ) {
			assert.equal( treffer[ 0 ].device.sku, fall.erster );
		}
		for ( const [ sku, art ] of Object.entries( fall.merkmal || {} ) ) {
			const prefix = art === 'option' ? 'feature_option:' : 'feature_unknown:';
			assert.ok( nachSku[ sku ].notes.some( ( n ) => n.startsWith( prefix ) ), `${ sku }: ${ nachSku[ sku ].notes }` );
		}
		if ( fall.kunde ) {
			const kunde = Object.fromEntries( QS.search( devices, fall.anfrage, { internal: false } ).map( ( r ) => [ r.device.sku, r.category ] ) );
			for ( const [ sku, erwartet ] of Object.entries( fall.kunde ) ) {
				assert.equal( kunde[ sku ] || null, erwartet, `Kundenmodus ${ sku }` );
			}
		}
	} );
}
