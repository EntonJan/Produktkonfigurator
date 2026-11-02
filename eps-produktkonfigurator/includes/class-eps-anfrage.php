<?php
/**
 * EPS-Anfrage, Format 1 – die eine Anfragestruktur für Anfragekorb (Produktkonfigurator), Anfragefeld der Homepage
 * und später den Shop.
 *
 * Eine Anfrage geht als Mail an das Anfrage-Postfach: Textblock „--- EPS-Anfrage --- … --- Ende ---“ im Mailtext
 * und dieselben Daten als Anhang „EPS-Anfrage.json“. Die EPS-Produktsuche liest beides (bevorzugt den Anhang).
 *
 * Der Textblock muss Zeichen für Zeichen dem Gegenstück der Produktsuche entsprechen
 * (src/eps_produktsuche/anfrage_format.py). Beide werden gegen tests/eps-anfrage/ geprüft
 * (php tests/eps-anfrage.test.php). Diese Klasse braucht WordPress nur zum Versenden.
 *
 * @package EPS_Produktkonfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPS_Anfrage {

	const FORMAT  = 'eps-anfrage';
	const VERSION = 1;
	const ANHANG  = 'EPS-Anfrage.json';
	const BEGINN  = '--- EPS-Anfrage ---';
	const ENDE    = '--- Ende ---';

	const QUELLEN = array(
		'produktkonfigurator' => 'Produktkonfigurator',
		'anfrageformular'     => 'Anfrageformular',
		'shop'                => 'Shop',
	);

	const KUNDENFELDER = array(
		'firma'      => 'Firma',
		'rechtsform' => 'Rechtsform',
		'anrede'     => 'Anrede',
		'vorname'    => 'Vorname',
		'nachname'   => 'Nachname',
		'email'      => 'E-Mail',
		'telefon'    => 'Telefon',
		'strasse'    => 'Straße',
		'plz'        => 'PLZ',
		'ort'        => 'Ort',
		'land'       => 'Land',
		'ust_id'     => 'USt-IdNr.',
	);

	/**
	 * Neue Anfrage aus Formulardaten: füllt Anfrage-Nr., Zeitpunkt und Standardwerte.
	 *
	 * @param array $eingabe Teilweise gefüllte Struktur (kunde, positionen, suche, nachricht, …).
	 * @return array Vollständige Struktur.
	 */
	public static function erstellen( array $eingabe ) {
		if ( empty( $eingabe['anfrage_nr'] ) ) {
			$eingabe['anfrage_nr'] = 'EPS-' . gmdate( 'Ymd-His' ) . '-' . strtoupper( bin2hex( random_bytes( 2 ) ) );
		}
		if ( empty( $eingabe['erstellt'] ) ) {
			$eingabe['erstellt'] = function_exists( 'wp_date' ) ? wp_date( 'c' ) : gmdate( 'c' );
		}
		return self::normalisieren( $eingabe );
	}

	/**
	 * Beliebige Eingabe → vollständige Struktur (wie anfrage_format.normalisieren).
	 */
	public static function normalisieren( array $d ) {
		$n = array(
			'format'       => self::FORMAT,
			'version'      => isset( $d['version'] ) && (int) $d['version'] ? (int) $d['version'] : self::VERSION,
			'anfrage_nr'   => self::einzeilig( $d['anfrage_nr'] ?? '' ),
			'erstellt'     => self::einzeilig( $d['erstellt'] ?? '' ),
			'quelle'       => self::einzeilig( $d['quelle'] ?? '' ),
			'sprache'      => self::einzeilig( $d['sprache'] ?? '' ) ?: 'de',
			'kunde'        => array(),
			'positionen'   => array(),
			'suche'        => array( 'text' => '', 'geraetetyp' => '', 'filter' => array() ),
			'wunschtermin' => self::einzeilig( $d['wunschtermin'] ?? '' ) ?: 'ASAP',
			'nachricht'    => '',
			'datenschutz'  => ! empty( $d['datenschutz'] ),
		);
		$kunde = is_array( $d['kunde'] ?? null ) ? $d['kunde'] : array();
		foreach ( array_keys( self::KUNDENFELDER ) as $feld ) {
			$n['kunde'][ $feld ] = self::einzeilig( $kunde[ $feld ] ?? '' );
		}
		foreach ( (array) ( $d['positionen'] ?? array() ) as $p ) {
			$menge             = isset( $p['menge'] ) ? (int) $p['menge'] : 1;
			$n['positionen'][] = array(
				'bestellnummer' => self::einzeilig( $p['bestellnummer'] ?? '' ),
				'name'          => self::einzeilig( $p['name'] ?? '' ),
				'menge'         => max( 1, $menge ),
			);
		}
		$s          = is_array( $d['suche'] ?? null ) ? $d['suche'] : array();
		$n['suche'] = array(
			'text'       => self::einzeilig( $s['text'] ?? '' ),
			'geraetetyp' => self::einzeilig( $s['geraetetyp'] ?? '' ),
			'filter'     => is_array( $s['filter'] ?? null ) ? $s['filter'] : array(),
		);
		$nachricht = trim( str_replace( "\r\n", "\n", (string) ( $d['nachricht'] ?? '' ) ), "\n" );
		if ( '' !== $nachricht ) {
			$zeilen = array_map(
				function ( $z ) {
					return self::sicher( rtrim( $z ) );
				},
				explode( "\n", $nachricht )
			);
			$n['nachricht'] = implode( "\n", $zeilen );
		}
		return $n;
	}

	/**
	 * Prüft eine Anfrage vor dem Versand. Leeres Array = in Ordnung.
	 *
	 * @return string[] Fehlermeldungen für das Formular.
	 */
	public static function pruefen( array $d ) {
		$fehler = array();
		$email  = $d['kunde']['email'] ?? '';
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$fehler[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
		}
		if ( empty( $d['positionen'] ) && '' === trim( (string) ( $d['nachricht'] ?? '' ) ) ) {
			$fehler[] = 'Bitte einen Artikel auswählen oder Ihre Anfrage beschreiben.';
		}
		if ( empty( $d['datenschutz'] ) ) {
			$fehler[] = 'Bitte der Datenschutzerklärung zustimmen.';
		}
		return $fehler;
	}

	/**
	 * Textblock für den Mailtext – feste Reihenfolge, „Nachricht“ zuletzt.
	 */
	public static function als_text( array $d ) {
		$d = self::normalisieren( $d );
		$z = array(
			self::BEGINN,
			'Format: EPS-Anfrage ' . $d['version'],
			'Anfrage-Nr.: ' . $d['anfrage_nr'],
			'Erstellt: ' . $d['erstellt'],
			'Quelle: ' . ( self::QUELLEN[ $d['quelle'] ] ?? $d['quelle'] ),
			'Sprache: ' . $d['sprache'],
		);
		foreach ( self::KUNDENFELDER as $feld => $titel ) {
			$z[] = $titel . ': ' . $d['kunde'][ $feld ];
		}
		foreach ( $d['positionen'] as $i => $p ) {
			$z[] = 'Position ' . ( $i + 1 ) . ': ' . $p['bestellnummer'] . ' · ' . $p['name'] . ' · Menge ' . $p['menge'];
		}
		if ( '' !== $d['suche']['text'] ) {
			$z[] = 'Suche: ' . $d['suche']['text'];
		}
		if ( '' !== $d['suche']['geraetetyp'] ) {
			$z[] = 'Gerätetyp: ' . $d['suche']['geraetetyp'];
		}
		if ( ! empty( $d['suche']['filter'] ) ) {
			$filter = $d['suche']['filter'];
			ksort( $filter, SORT_STRING );
			$teile = array();
			foreach ( $filter as $k => $v ) {
				$teile[] = $k . '=' . self::filterwert( $v );
			}
			$z[] = 'Filter: ' . implode( '; ', $teile );
		}
		$z[]       = 'Wunschtermin: ' . $d['wunschtermin'];
		$z[]       = 'Datenschutz: ' . ( $d['datenschutz'] ? 'zugestimmt' : 'nicht zugestimmt' );
		$nachricht = '' !== $d['nachricht'] ? explode( "\n", $d['nachricht'] ) : array( '' );
		$z[]       = 'Nachricht: ' . array_shift( $nachricht );
		foreach ( $nachricht as $zeile ) {
			$z[] = $zeile;
		}
		$z[] = self::ENDE;
		return implode( "\n", $z );
	}

	/**
	 * Daten für den Anhang EPS-Anfrage.json.
	 */
	public static function als_json( array $d ) {
		$d = self::normalisieren( $d );
		if ( empty( $d['suche']['filter'] ) ) {
			$d['suche']['filter'] = new stdClass(); // {} statt [] im JSON
		}
		return json_encode( $d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Betreff der Anfrage-Mail.
	 */
	public static function betreff( array $d ) {
		$quelle = self::QUELLEN[ $d['quelle'] ] ?? 'Homepage';
		$wer    = trim( $d['kunde']['firma'] . ' ' . $d['kunde']['rechtsform'] );
		if ( '' === $wer ) {
			$wer = trim( $d['kunde']['vorname'] . ' ' . $d['kunde']['nachname'] );
		}
		return 'Anfrage über ' . ( 'anfrageformular' === $d['quelle'] ? 'die Homepage' : 'den ' . $quelle )
			. ( '' !== $wer ? ' – ' . $wer : '' ) . ' (' . $d['anfrage_nr'] . ')';
	}

	/**
	 * Versendet die Anfrage per wp_mail an das Anfrage-Postfach (Filter „eps_anfrage_empfaenger“).
	 *
	 * @return bool True, wenn WordPress die Mail angenommen hat.
	 */
	public static function senden( array $d ) {
		$d          = self::normalisieren( $d );
		$empfaenger = apply_filters( 'eps_anfrage_empfaenger', get_option( 'eps_anfrage_empfaenger', get_option( 'admin_email' ) ) );
		$text       = "Neue Anfrage über die Homepage. Bitte mit der EPS-Produktsuche bearbeiten.\n\n" . self::als_text( $d ) . "\n";
		$datei      = trailingslashit( get_temp_dir() ) . self::ANHANG;
		file_put_contents( $datei, self::als_json( $d ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$kopf = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( filter_var( $d['kunde']['email'], FILTER_VALIDATE_EMAIL ) ) {
			$kopf[] = 'Reply-To: ' . $d['kunde']['email'];
		}
		$ok = wp_mail( $empfaenger, self::betreff( $d ), $text, $kopf, array( $datei ) );
		wp_delete_file( $datei );
		return $ok;
	}

	// ------------------------------------------------------------------ Hilfen

	private static function einzeilig( $x ) {
		return trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $x ) );
	}

	private static function sicher( $zeile ) {
		return in_array( trim( $zeile ), array( self::BEGINN, self::ENDE ), true ) ? '– Ende –' : $zeile;
	}

	private static function filterwert( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 'ja' : 'nein';
		}
		if ( is_array( $v ) ) {
			$werte = array_values( $v );
			if ( 2 === count( $werte ) && is_numeric( $werte[0] ) && is_numeric( $werte[1] )
				&& ! is_string( $werte[0] ) && ! is_string( $werte[1] ) ) {
				return self::zahl( $werte[0] ) . '-' . self::zahl( $werte[1] );
			}
			return implode( '|', array_map( array( __CLASS__, 'filterwert' ), $werte ) );
		}
		if ( is_int( $v ) || is_float( $v ) ) {
			return self::zahl( $v );
		}
		return self::einzeilig( $v );
	}

	private static function zahl( $x ) {
		return is_float( $x ) ? rtrim( rtrim( sprintf( '%.6F', $x ), '0' ), '.' ) : (string) $x;
	}
}
