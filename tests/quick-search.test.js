/**
 * Tests der Schnellsuche gegen die echten Shopdaten (data/shop-devices.json).
 * Aufruf: node --test tests/
 */
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );

const QS = require( path.join( __dirname, '../eps-produktkonfigurator/assets/js/eps-quick-search.js' ) );
const data = require( path.join( __dirname, '../eps-produktkonfigurator/data/shop-devices.json' ) );

// Gleiche Gültigkeitsregel wie EPS_Device::is_valid() für Shopgeräte (power_rule "max").
function isValid( d ) {
	const u = d.voltage_max;
	const i = d.current_max;
	const p = d.power_max;
	if ( ! ( u > 0 && i > 0 && p > 0 ) ) {
		return true;
	}
	return p <= u * i * Math.max( 1, d.meta.channels ) * 1.1;
}

const devices = data.devices.map( ( d ) => Object.assign( { is_valid: isValid( d ) }, d ) );
const skus = ( r ) => r.map( ( x ) => x.device.sku );

test( 'Shopdaten vollständig geladen', () => {
	assert.ok( data.count >= 700, 'mindestens 700 Geräte erwartet' );
	assert.equal( devices.length, data.count );
	assert.equal( devices.filter( ( d ) => ! d.is_valid ).length, 7 ); // bekannte Datenfehler im Shop
} );

test( 'Anfrage lesen', () => {
	let q = QS.parseQuery( 'Stromquelle 0-30V 1kA 30kW' );
	assert.deepEqual( [ q.voltage, q.current, q.power, q.source, q.deviceType ], [ 30, 1000, 30000, true, '' ] );
	q = QS.parseQuery( 'E-Last 500V 100A' );
	assert.deepEqual( [ q.voltage, q.current, q.load, q.deviceType ], [ 500, 100, true, 'e_load' ] );
	assert.equal( QS.powerNeeded( q ), 50000 );
	q = QS.parseQuery( 'DC/DC 48VDC 20,5A 1,5 kW' );
	assert.deepEqual( [ q.voltage, q.current, q.power, q.deviceType, q.output ], [ 48, 20.5, 1500, 'dc_dc', 'DC' ] );
	assert.equal( QS.parseQuery( 'Wechselrichter 400V 6kVA' ).deviceType, 'dc_ac' );
	assert.equal( QS.parseQuery( 'Netzgerät 30 kVA' ).power, 30000 );
	assert.ok( QS.search( devices, 'irgendwas ohne Werte' ).length === 0 );
} );

test( 'Obergrenze wie Konfigurator (+20 %, 1-2-5 / 250er)', () => {
	assert.equal( QS.upperLimit( 30 ), 50 );
	assert.equal( QS.upperLimit( 4 ), 5 );
	assert.equal( QS.upperLimit( 1000 ), 1250 );
	assert.equal( QS.upperLimit( 30000 ), 36000 );
} );

test( 'Beispiel Stromquelle 0-30V 1kA 30kW', () => {
	const r = QS.search( devices, 'Stromquelle 0-30V 1kA 30kW' );
	assert.deepEqual( skus( r.slice( 0, 2 ) ), [ '202307', '200961' ] ); // HC 40030-1000-WC, HC 40030-1200-M
	assert.ok( r.slice( 0, 2 ).every( ( x ) => x.category === 'exact' ) );
	assert.equal( r[ 2 ].category, 'larger' );
	assert.ok( ! r.some( ( x ) => x.device.device_type === 'e_load' ), 'Quelle → keine reinen Lasten' );
	const par = r.find( ( x ) => x.device.sku === '200252' ); // E/PSI 10060-510 3U
	assert.equal( par.category, 'parallel' );
	assert.equal( par.units, 2 );
	assert.ok( ! r.some( ( x ) => x.device.sku === '200962' ), 'Datenfehler (240000 W) wird nicht vorgeschlagen' );
} );

test( 'Last zeigt E-Lasten und bidirektionale Geräte', () => {
	const r = QS.search( devices, 'E-Last 500V 100A' );
	assert.ok( r.length > 0 );
	assert.ok( r.every( ( x ) => x.device.device_type === 'e_load' || x.device.attributes.bidirectional === true ) );
	assert.ok( r.some( ( x ) => x.device.device_type === 'e_load' ) );
	assert.ok( r.some( ( x ) => x.device.attributes.bidirectional === true && x.device.device_type !== 'e_load' ) );
} );

test( 'AC-Festspannung muss passen', () => {
	const r = QS.search( devices, '230VAC 3kVA' );
	assert.ok( r.length > 0 );
	assert.ok( r.every( ( x ) => [ 'ac_ac', 'dc_ac' ].includes( x.device.device_type ) || x.device.meta.ac ) );
	assert.ok( r.some( ( x ) => x.device.sku === '400284' && x.category === 'exact' ) ); // FC 3000-UE 230 VAC
	assert.ok( ! r.some( ( x ) => x.device.sku === '400282' ), '115-V-Gerät passt nicht zu 230 V' );
	const inv = QS.search( devices, 'Wechselrichter 400V 6kVA' );
	assert.ok( inv.length > 0 && inv.every( ( x ) => x.device.device_type === 'dc_ac' ) );
	assert.ok( ! inv.some( ( x ) => x.device.sku.startsWith( '6000' ) ), 'CSI 230 V passt nicht zu 400 V' );
} );

test( 'Kundenmodus: keine internen Kategorien', () => {
	const r = QS.search( devices, 'Stromquelle 0-30V 1kA 30kW', { internal: false } );
	assert.ok( r.every( ( x ) => [ 'exact', 'larger', 'system' ].includes( x.category ) ) );
	assert.ok( r.every( ( x ) => x.notes.length === 0 ) );
	assert.ok( r.some( ( x ) => x.category === 'system' ) );
} );

test( 'Max. Geräte parallel begrenzt Vorschläge', () => {
	const r = QS.search( devices, 'Stromquelle 60V 2000A', { maxUnits: 2 } );
	assert.ok( r.filter( ( x ) => x.category === 'parallel' ).every( ( x ) => x.units <= 2 ) );
} );

test( 'Filterwerte für die Regler', () => {
	assert.deepEqual( QS.toFilterValues( 'Stromquelle 0-30V 1kA 30kW' ), {
		output_voltage: { from: 30, to: 30 },
		output_current: { from: 1000, to: 1250 },
		output_power: { from: 30000, to: 36000 }
	} );
	assert.equal( QS.toFilterValues( 'E-Last 500V 100A' ).device_type, 'e_load' );
} );
