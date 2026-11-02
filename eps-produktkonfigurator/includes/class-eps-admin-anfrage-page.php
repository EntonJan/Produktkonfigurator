<?php
/**
 * TEMPORÄR: Admin-Unterseite „Anfrage-Vorschau“ unter „Konfigurator Test“.
 *
 * Zeigt, was eine Anfrage aus Anfragekorb oder Anfragefeld an das Anfrage-Postfach schicken würde: Betreff,
 * Textblock und JSON-Anhang (EPS-Anfrage, Format 1). Versendet nur auf ausdrücklichen Klick.
 *
 * @package EPS_Produktkonfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPS_Admin_Anfrage_Page {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ), 20 );
	}

	public function register_menu_page() {
		add_submenu_page(
			'eps-konfigurator-test',
			'Anfrage-Vorschau',
			'Anfrage-Vorschau',
			'manage_options',
			'eps-anfrage-vorschau',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Formularwerte → Anfrage. Positionen als Zeilen „Bestellnummer;Menge“ (Name kommt aus den Gerätedaten).
	 */
	private function anfrage_aus_formular( array $p ) {
		$namen = array();
		if ( function_exists( 'eps_get_devices' ) ) {
			foreach ( eps_get_devices() as $g ) {
				$namen[ (string) $g->sku ] = (string) $g->name_de;
			}
		}
		$positionen = array();
		foreach ( preg_split( '/\R/', (string) ( $p['positionen'] ?? '' ) ) as $zeile ) {
			$teile = array_map( 'trim', explode( ';', $zeile ) );
			if ( '' === $teile[0] ) {
				continue;
			}
			$positionen[] = array(
				'bestellnummer' => $teile[0],
				'name'          => $namen[ $teile[0] ] ?? '',
				'menge'         => isset( $teile[1] ) ? (int) $teile[1] : 1,
			);
		}
		$kunde = array();
		foreach ( array_keys( EPS_Anfrage::KUNDENFELDER ) as $feld ) {
			$kunde[ $feld ] = sanitize_text_field( $p[ 'kunde_' . $feld ] ?? '' );
		}
		return EPS_Anfrage::erstellen(
			array(
				'quelle'       => in_array( $p['quelle'] ?? '', array( 'produktkonfigurator', 'anfrageformular' ), true ) ? $p['quelle'] : 'anfrageformular',
				'sprache'      => 'en' === ( $p['sprache'] ?? '' ) ? 'en' : 'de',
				'kunde'        => $kunde,
				'positionen'   => $positionen,
				'suche'        => array( 'text' => sanitize_text_field( $p['suche'] ?? '' ) ),
				'wunschtermin' => sanitize_text_field( $p['wunschtermin'] ?? '' ),
				'nachricht'    => sanitize_textarea_field( $p['nachricht'] ?? '' ),
				'datenschutz'  => ! empty( $p['datenschutz'] ),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$p        = array();
		$anfrage  = null;
		$meldung  = '';
		if ( isset( $_POST['eps_anfrage_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['eps_anfrage_nonce'] ), 'eps_anfrage_vorschau' ) ) {
			$p       = wp_unslash( $_POST );
			$anfrage = $this->anfrage_aus_formular( $p );
			$fehler  = EPS_Anfrage::pruefen( $anfrage );
			if ( $fehler ) {
				$meldung = '<div class="notice notice-warning"><p>' . implode( '<br>', array_map( 'esc_html', $fehler ) ) . '</p></div>';
			} elseif ( isset( $p['senden'] ) ) {
				$meldung = EPS_Anfrage::senden( $anfrage )
					? '<div class="notice notice-success"><p>Anfrage verschickt an ' . esc_html( apply_filters( 'eps_anfrage_empfaenger', get_option( 'eps_anfrage_empfaenger', get_option( 'admin_email' ) ) ) ) . '.</p></div>'
					: '<div class="notice notice-error"><p>WordPress konnte die Mail nicht versenden (kein Mailserver eingerichtet?). Die Vorschau unten ist trotzdem vollständig.</p></div>';
			}
		} else {
			$p = array(
				'quelle'          => 'produktkonfigurator',
				'kunde_firma'     => 'Muster Prüftechnik GmbH',
				'kunde_vorname'   => 'Max',
				'kunde_nachname'  => 'Mustermann',
				'kunde_email'     => 'm.mustermann@example.com',
				'kunde_plz'       => '86150',
				'kunde_ort'       => 'Augsburg',
				'kunde_land'      => 'DE',
				'positionen'      => "202307;2\n200961;1",
				'suche'           => 'Stromquelle 0-30V 1kA 30kW',
				'wunschtermin'    => 'ASAP',
				'nachricht'       => 'Testanfrage – bitte nicht bearbeiten.',
				'datenschutz'     => '1',
			);
		}
		$v = function ( $k ) use ( $p ) {
			return esc_attr( $p[ $k ] ?? '' );
		};
		?>
		<div class="wrap">
			<h1>Anfrage-Vorschau</h1>
			<p>So kommt eine Anfrage aus dem Anfragekorb oder dem Anfragefeld im Anfrage-Postfach an (EPS-Anfrage, Format 1). Die EPS-Produktsuche liest sie automatisch.</p>
			<?php echo $meldung; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<form method="post" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:24px;max-width:1200px">
				<?php wp_nonce_field( 'eps_anfrage_vorschau', 'eps_anfrage_nonce' ); ?>
				<div>
					<h2>Kunde</h2>
					<table class="form-table" role="presentation">
						<?php foreach ( EPS_Anfrage::KUNDENFELDER as $feld => $titel ) : ?>
							<tr><th scope="row"><label for="kunde_<?php echo esc_attr( $feld ); ?>"><?php echo esc_html( $titel ); ?></label></th>
								<td><input class="regular-text" id="kunde_<?php echo esc_attr( $feld ); ?>" name="kunde_<?php echo esc_attr( $feld ); ?>" value="<?php echo $v( 'kunde_' . $feld ); // phpcs:ignore ?>"></td></tr>
						<?php endforeach; ?>
					</table>
				</div>
				<div>
					<h2>Anfrage</h2>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><label for="quelle">Quelle</label></th><td>
							<select id="quelle" name="quelle">
								<option value="produktkonfigurator" <?php selected( $p['quelle'] ?? '', 'produktkonfigurator' ); ?>>Produktkonfigurator (Anfragekorb)</option>
								<option value="anfrageformular" <?php selected( $p['quelle'] ?? '', 'anfrageformular' ); ?>>Anfragefeld der Homepage</option>
							</select></td></tr>
						<tr><th scope="row"><label for="positionen">Artikel</label></th><td>
							<textarea id="positionen" name="positionen" rows="4" class="large-text code" placeholder="Bestellnummer;Menge"><?php echo esc_textarea( $p['positionen'] ?? '' ); ?></textarea>
							<p class="description">Eine Zeile je Artikel: Bestellnummer;Menge. Leer = reine Textanfrage.</p></td></tr>
						<tr><th scope="row"><label for="suche">Suche</label></th><td><input class="regular-text" id="suche" name="suche" value="<?php echo $v( 'suche' ); // phpcs:ignore ?>"></td></tr>
						<tr><th scope="row"><label for="wunschtermin">Wunschtermin</label></th><td><input class="regular-text" id="wunschtermin" name="wunschtermin" value="<?php echo $v( 'wunschtermin' ); // phpcs:ignore ?>"></td></tr>
						<tr><th scope="row"><label for="nachricht">Nachricht</label></th><td><textarea id="nachricht" name="nachricht" rows="5" class="large-text"><?php echo esc_textarea( $p['nachricht'] ?? '' ); ?></textarea></td></tr>
						<tr><th scope="row">Datenschutz</th><td><label><input type="checkbox" name="datenschutz" value="1" <?php checked( ! empty( $p['datenschutz'] ) ); ?>> zugestimmt</label></td></tr>
					</table>
					<p><button class="button button-primary" name="vorschau" value="1">Vorschau</button>
						<button class="button" name="senden" value="1">Testanfrage senden</button></p>
				</div>
			</form>
			<?php if ( $anfrage ) : ?>
				<h2>Betreff</h2>
				<p><code><?php echo esc_html( EPS_Anfrage::betreff( $anfrage ) ); ?></code></p>
				<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:24px;max-width:1200px">
					<div><h2>Textblock im Mailtext</h2><pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;white-space:pre-wrap"><?php echo esc_html( EPS_Anfrage::als_text( $anfrage ) ); ?></pre></div>
					<div><h2>Anhang <?php echo esc_html( EPS_Anfrage::ANHANG ); ?></h2><pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;white-space:pre-wrap"><?php echo esc_html( EPS_Anfrage::als_json( $anfrage ) ); ?></pre></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
