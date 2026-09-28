<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Katalog aller verfügbaren Geräte-Optionen (Zusatzausstattung).
 *
 * Durchgehend nummeriert von 1 bis 12 (Integer, lückenlos). Namen sind
 * bewusst erfundene, klar als Testdaten erkennbare Platzhalter.
 *
 * Jedes Gerät führt in $attributes['option_numbers'] nur die für dieses
 * Produkt tatsächlich verfügbaren Nummern als Teilmenge dieses Katalogs
 * (siehe mock-devices.php). Die Options-Suche (Textfeld, siehe
 * EPS_Admin_Test_Page::render_options_search_field()) vergleicht eingegebene
 * Nummern exakt gegen diesen Katalog bzw. gegen die Nummern eines Geräts.
 *
 * @return array<int, array{label_de: string, label_en: string}>
 */
function eps_get_options_catalog() {
	return array(
		1  => array(
			'label_de' => 'Testoption Alpha',
			'label_en' => 'Test Option Alpha',
		),
		2  => array(
			'label_de' => 'Testoption Beta',
			'label_en' => 'Test Option Beta',
		),
		3  => array(
			'label_de' => 'Testoption Gamma',
			'label_en' => 'Test Option Gamma',
		),
		4  => array(
			'label_de' => 'Testoption Delta',
			'label_en' => 'Test Option Delta',
		),
		5  => array(
			'label_de' => 'Testoption Epsilon',
			'label_en' => 'Test Option Epsilon',
		),
		6  => array(
			'label_de' => 'Testoption Zeta',
			'label_en' => 'Test Option Zeta',
		),
		7  => array(
			'label_de' => 'Testoption Eta',
			'label_en' => 'Test Option Eta',
		),
		8  => array(
			'label_de' => 'Testoption Theta',
			'label_en' => 'Test Option Theta',
		),
		9  => array(
			'label_de' => 'Testoption Iota',
			'label_en' => 'Test Option Iota',
		),
		10 => array(
			'label_de' => 'Testoption Kappa',
			'label_en' => 'Test Option Kappa',
		),
		11 => array(
			'label_de' => 'Testoption Lambda',
			'label_en' => 'Test Option Lambda',
		),
		12 => array(
			'label_de' => 'Testoption My',
			'label_en' => 'Test Option Mu',
		),
	);
}
