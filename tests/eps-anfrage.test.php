<?php
/**
 * EPS-Anfrage Format 1: der Konfigurator muss genau den Textblock erzeugen, den die EPS-Produktsuche erwartet.
 * Aufruf: php tests/eps-anfrage.test.php   (ohne WordPress; gemeinsame Beispiele in tests/eps-anfrage/)
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../eps-produktkonfigurator/includes/class-eps-anfrage.php';

$fehler = 0;
$ok     = function ( $bedingung, $name ) use ( &$fehler ) {
	echo ( $bedingung ? 'ok   ' : 'FEHLER ' ) . $name . "\n";
	if ( ! $bedingung ) {
		$fehler++;
	}
};

$beispiele = array_filter( glob( __DIR__ . '/eps-anfrage/*.json' ), function ( $p ) {
	return basename( $p ) !== 'eps-anfrage.schema.json';
} );
$ok( count( $beispiele ) >= 3, 'Beispiele vorhanden' );
foreach ( $beispiele as $pfad ) {
	$d        = json_decode( file_get_contents( $pfad ), true );
	$erwartet = file_get_contents( substr( $pfad, 0, -5 ) . '.txt' );
	$text     = EPS_Anfrage::als_text( $d ) . "\n";
	$ok( $text === $erwartet, 'Textblock wie vorgegeben: ' . basename( $pfad ) );
	if ( $text !== $erwartet ) {
		$a = explode( "\n", $text );
		$b = explode( "\n", $erwartet );
		foreach ( $a as $i => $z ) {
			if ( ( $b[ $i ] ?? null ) !== $z ) {
				echo "   Zeile $i: '" . $z . "' statt '" . ( $b[ $i ] ?? '' ) . "'\n";
				break;
			}
		}
	}
	$json = json_decode( EPS_Anfrage::als_json( $d ), true );
	$ok( 'eps-anfrage' === $json['format'] && $json['anfrage_nr'] === $d['anfrage_nr'], 'JSON-Anhang: ' . basename( $pfad ) );
}

// Neue Anfrage aus Formulardaten
$neu = EPS_Anfrage::erstellen( array(
	'quelle'      => 'anfrageformular',
	'kunde'       => array( 'email' => 'test@example.com', 'firma' => "Zeile1\nZeile2" ),
	'nachricht'   => "Hallo\r\n--- Ende ---\r\nTschüss",
	'datenschutz' => true,
) );
$ok( (bool) preg_match( '/^EPS-\d{8}-\d{6}-[0-9A-F]{4}$/', $neu['anfrage_nr'] ), 'Anfrage-Nr. erzeugt' );
$ok( 'Zeile1 Zeile2' === $neu['kunde']['firma'], 'Zeilenumbruch im Feld wird Leerzeichen' );
$ok( "Hallo\n– Ende –\nTschüss" === $neu['nachricht'], 'Kundentext beendet den Block nicht' );
$ok( array() === EPS_Anfrage::pruefen( $neu ), 'Prüfung: gültige Anfrage' );
$ok( 3 === count( EPS_Anfrage::pruefen( array( 'kunde' => array( 'email' => 'x' ) ) ) ), 'Prüfung: drei Fehler bei leerer Anfrage' );
$ok( 'Anfrage über die Homepage – Zeile1 Zeile2 (' . $neu['anfrage_nr'] . ')' === EPS_Anfrage::betreff( $neu ), 'Betreff' );

echo $fehler ? "\n$fehler FEHLER\n" : "\nAlle Prüfungen bestanden.\n";
exit( $fehler ? 1 : 0 );
