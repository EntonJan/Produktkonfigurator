<?php
/**
 * Plugin Name:       EPS Produktkonfigurator
 * Plugin URI:        https://example.com/eps-produktkonfigurator
 * Description:       Produktkonfigurator für WooCommerce. Grundgerüst zur späteren Erweiterung mit Filterlogik, Datenanbindung und UI.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            EPS
 * Author URI:        https://example.com
 * License:            GPL-2.0-or-later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        eps-produktkonfigurator
 * Domain Path:        /languages
 * Requires Plugins:   woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

define( 'EPS_PRODUKTKONFIGURATOR_VERSION', '0.1.0' );
define( 'EPS_PRODUKTKONFIGURATOR_PLUGIN_FILE', __FILE__ );
define( 'EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPS_PRODUKTKONFIGURATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Prüft, ob WooCommerce installiert und aktiv ist.
 *
 * @return bool
 */
function eps_produktkonfigurator_is_woocommerce_active() {
	return in_array(
		'woocommerce/woocommerce.php',
		apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ),
		true
	) || ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins', array() ) ) );
}

/**
 * Aktivierungs-Hook: Bricht die Aktivierung ab, falls WooCommerce fehlt.
 */
function eps_produktkonfigurator_activate() {
	if ( ! eps_produktkonfigurator_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( EPS_PRODUKTKONFIGURATOR_PLUGIN_FILE ) );
		wp_die(
			esc_html__( 'Der EPS Produktkonfigurator benötigt WooCommerce. Bitte installiere und aktiviere WooCommerce, bevor du dieses Plugin aktivierst.', 'eps-produktkonfigurator' ),
			esc_html__( 'Plugin-Aktivierung fehlgeschlagen', 'eps-produktkonfigurator' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'eps_produktkonfigurator_activate' );

/**
 * Deaktivierungs-Hook: Platzhalter für zukünftiges Aufräumen (z. B. Transients).
 */
function eps_produktkonfigurator_deactivate() {
	// Aktuell kein Aufräumen notwendig.
}
register_deactivation_hook( __FILE__, 'eps_produktkonfigurator_deactivate' );

/**
 * Zeigt eine Admin-Fehlermeldung, falls WooCommerce zur Laufzeit fehlt
 * (z. B. wenn WooCommerce nachträglich deaktiviert wird).
 */
function eps_produktkonfigurator_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			esc_html_e( 'Der EPS Produktkonfigurator ist aktiv, benötigt aber WooCommerce. Bitte aktiviere WooCommerce.', 'eps-produktkonfigurator' );
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialisiert das Plugin, sobald alle Plugins geladen sind.
 */
function eps_produktkonfigurator_init() {
	if ( ! eps_produktkonfigurator_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'eps_produktkonfigurator_woocommerce_missing_notice' );
		return;
	}

	require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/filters-config.php';
	require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/options-catalog.php';
	require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/class-eps-device.php';
	require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/class-eps-filter.php';
	require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/mock-devices.php';
	require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/shop-devices.php';

	// TEMPORÄR: Admin-Testseite zur Überprüfung der Mock-Geräte, wird später entfernt.
	if ( is_admin() ) {
		require_once EPS_PRODUKTKONFIGURATOR_PLUGIN_DIR . 'includes/class-eps-admin-test-page.php';
		new EPS_Admin_Test_Page();
	}
}
add_action( 'plugins_loaded', 'eps_produktkonfigurator_init' );
