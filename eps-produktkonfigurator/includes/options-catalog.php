<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkter Zugriff nicht erlaubt.
}

/**
 * Katalog aller Geräte-Optionen (Zusatzausstattung) von EPS.
 *
 * Quelle: Optionskatalog-800000er_ab-2018.xlsx, vom Chef am 12.11.2026 freigegeben, Stand nach dem Umbau vom
 * 16.11.2026: 68 Optionen, durchgehend nummeriert von 1 bis 68 (Integer, lückenlos), Zubehör ist darin aufgegangen.
 * Die englischen Bezeichnungen sind Übersetzungsvorschläge und in Weclapp noch zu prüfen.
 *
 * Artikelnummern: 80 + Kategorie-Ziffer + Optionsnummer (3-stellig), z. B. Option 1 Wasserkühlung = 802001 (AC-DC).
 * Ziffern: 2 AC-DC, 3 E-Lasten, 4 AC-AC, 5 DC-DC, 6 DC-AC, 7 Lade/USV. 'articles' führt je Gerätetyp die Nummer
 * nur dort, wo die Option bei Geräten dieser Kategorie vorkommt.
 *
 * Jedes Gerät führt in $attributes['option_numbers'] nur die für dieses Produkt tatsächlich verfügbaren Nummern als
 * Teilmenge dieses Katalogs (siehe mock-devices.php). Die Options-Suche (Textfeld, siehe
 * EPS_Admin_Test_Page::render_options_search_field()) vergleicht eingegebene Nummern exakt gegen diesen Katalog
 * bzw. gegen die Nummern eines Geräts.
 *
 * @return array<int, array{label_de: string, label_en: string, articles: array<string, int[]>}>
 */
function eps_get_options_catalog() {
	return array(
		1 => array(
			'label_de'  => 'Wasserkühlung',
			'label_en'  => 'Water cooling',
			'articles'  => array( 'ac_dc' => array( 802001 ) ),
		),
		2 => array(
			'label_de'  => '19"-Einbaurahmen',
			'label_en'  => '19" mounting frame',
			'articles'  => array( 'ac_dc' => array( 802002 ), 'ac_ac' => array( 804002 ), 'dc_ac' => array( 806002 ) ),
		),
		3 => array(
			'label_de'  => 'Tropenschutz',
			'label_en'  => 'Tropical protection',
			'articles'  => array( 'ac_dc' => array( 802003 ), 'ac_ac' => array( 804003 ) ),
		),
		4 => array(
			'label_de'  => '19"-Halter / Einbauwinkel',
			'label_en'  => '19" bracket / mounting angle',
			'articles'  => array( 'ac_dc' => array( 802004 ), 'ac_ac' => array( 804004 ) ),
		),
		5 => array(
			'label_de'  => '19"-Frontplatte / Teilfrontplatte',
			'label_en'  => '19" front panel / partial front panel',
			'articles'  => array( 'ac_dc' => array( 802005 ) ),
		),
		6 => array(
			'label_de'  => '19"-Schrank / Rack inkl. Schranklüfter',
			'label_en'  => '19" cabinet / rack incl. cabinet fan',
			'articles'  => array( 'ac_dc' => array( 802006 ), 'e_load' => array( 803006 ), 'dc_dc' => array( 805006 ), 'charger' => array( 807006 ) ),
		),
		7 => array(
			'label_de'  => 'Blindplatte 19"',
			'label_en'  => '19" blanking plate',
			'articles'  => array( 'ac_dc' => array( 802007 ), 'e_load' => array( 803007 ) ),
		),
		8 => array(
			'label_de'  => 'Schnittstelle USB / seriell / Feldbus (USB, RS232, RS485, CAN, CANopen, Profibus, Modbus-TCP)',
			'label_en'  => 'Interface USB / serial / fieldbus (USB, RS232, RS485, CAN, CANopen, Profibus, Modbus TCP)',
			'articles'  => array( 'ac_dc' => array( 802008 ), 'ac_ac' => array( 804008 ) ),
		),
		9 => array(
			'label_de'  => 'LAN-Schnittstelle (Ethernet)',
			'label_en'  => 'LAN interface (Ethernet)',
			'articles'  => array( 'ac_dc' => array( 802009 ), 'dc_ac' => array( 806009 ) ),
		),
		10 => array(
			'label_de'  => 'GPIB-Schnittstelle (IEEE 488)',
			'label_en'  => 'GPIB interface (IEEE 488)',
			'articles'  => array( 'ac_dc' => array( 802010 ) ),
		),
		11 => array(
			'label_de'  => 'Analoge Schnittstelle',
			'label_en'  => 'Analog interface',
			'articles'  => array( 'ac_dc' => array( 802011 ) ),
		),
		12 => array(
			'label_de'  => 'Industrial-Ethernet-Schnittstelle (Profinet, EtherCAT, DeviceNet)',
			'label_en'  => 'Industrial Ethernet interface (Profinet, EtherCAT, DeviceNet)',
			'articles'  => array( 'ac_dc' => array( 802012 ), 'ac_ac' => array( 804012 ) ),
		),
		13 => array(
			'label_de'  => 'Schnittstellenwandler',
			'label_en'  => 'Interface converter',
			'articles'  => array( 'ac_dc' => array( 802013 ) ),
		),
		14 => array(
			'label_de'  => 'Externe Bedieneinheit (LCD / grafisch)',
			'label_en'  => 'External control unit (LCD / graphical)',
			'articles'  => array( 'ac_dc' => array( 802014 ), 'dc_ac' => array( 806014 ) ),
		),
		15 => array(
			'label_de'  => 'Busabschluss',
			'label_en'  => 'Bus terminator',
			'articles'  => array( 'ac_dc' => array( 802015 ) ),
		),
		16 => array(
			'label_de'  => 'Gateway (Modbus, Profinet)',
			'label_en'  => 'Gateway (Modbus, Profinet)',
			'articles'  => array( 'ac_dc' => array( 802016 ) ),
		),
		17 => array(
			'label_de'  => 'Batterie-Kontrolleinheit / Batterieschrank',
			'label_en'  => 'Battery control unit / battery cabinet',
			'articles'  => array( 'charger' => array( 807017 ) ),
		),
		18 => array(
			'label_de'  => 'Switch / I/O-System',
			'label_en'  => 'Switch / I/O system',
			'articles'  => array( 'ac_dc' => array( 802018 ) ),
		),
		19 => array(
			'label_de'  => 'Stromaufteilung (aktiv)',
			'label_en'  => 'Active current sharing',
			'articles'  => array( 'ac_dc' => array( 802019 ), 'dc_ac' => array( 806019 ) ),
		),
		20 => array(
			'label_de'  => 'Motorfilter',
			'label_en'  => 'Motor filter',
			'articles'  => array( 'e_load' => array( 803020 ), 'ac_ac' => array( 804020 ) ),
		),
		21 => array(
			'label_de'  => 'Autoranging',
			'label_en'  => 'Autoranging',
			'articles'  => array( 'ac_dc' => array( 802021 ) ),
		),
		22 => array(
			'label_de'  => 'Kabel / Stecker',
			'label_en'  => 'Cable / connector',
			'articles'  => array( 'ac_dc' => array( 802022 ) ),
		),
		23 => array(
			'label_de'  => '12 Pulse Gleichrichter',
			'label_en'  => '12-pulse rectifier',
			'articles'  => array( 'ac_ac' => array( 804023 ) ),
		),
		24 => array(
			'label_de'  => '2-Quadranten Modul',
			'label_en'  => '2-quadrant module',
			'articles'  => array( 'ac_dc' => array( 802024 ) ),
		),
		25 => array(
			'label_de'  => '3 Phasenbetrieb',
			'label_en'  => '3-phase operation',
			'articles'  => array( 'ac_ac' => array( 804025 ) ),
		),
		26 => array(
			'label_de'  => 'Alarmkontakt Übertemperatur 90°C',
			'label_en'  => 'Alarm contact overtemperature 90 °C',
			'articles'  => array( 'ac_dc' => array( 802026 ) ),
		),
		27 => array(
			'label_de'  => 'Anpassung an Bestands-PDU',
			'label_en'  => 'Adaptation to existing PDU',
			'articles'  => array( 'ac_dc' => array( 802027 ) ),
		),
		28 => array(
			'label_de'  => 'Anzeige',
			'label_en'  => 'Display',
			'articles'  => array( 'ac_dc' => array( 802028 ) ),
		),
		29 => array(
			'label_de'  => 'Arbiträre Funktionen (Stromregelung, Waveplayer)',
			'label_en'  => 'Arbitrary functions (current control, waveplayer)',
			'articles'  => array( 'ac_ac' => array( 804029 ) ),
		),
		30 => array(
			'label_de'  => 'ATE Ausführung',
			'label_en'  => 'ATE version',
			'articles'  => array( 'ac_ac' => array( 804030 ) ),
		),
		31 => array(
			'label_de'  => 'Aufpreis für bidirektionalen Betrieb',
			'label_en'  => 'Surcharge for bidirectional operation',
			'articles'  => array( 'ac_ac' => array( 804031 ) ),
		),
		32 => array(
			'label_de'  => 'Ausgangs-Optionen (Frequenz, Spannungsbereich, Überwachung, Meldung)',
			'label_en'  => 'Output options (frequency, voltage range, monitoring, signalling)',
			'articles'  => array( 'ac_dc' => array( 802032 ), 'ac_ac' => array( 804032 ) ),
		),
		33 => array(
			'label_de'  => 'Eingangsüberwachung (OK-Signal, Kontrolle, Spannung)',
			'label_en'  => 'Input monitoring (OK signal, control, voltage)',
			'articles'  => array( 'ac_dc' => array( 802033 ), 'dc_ac' => array( 806033 ) ),
		),
		34 => array(
			'label_de'  => 'Einschaltstrombegrenzung',
			'label_en'  => 'Inrush current limiting',
			'articles'  => array( 'ac_dc' => array( 802034 ) ),
		),
		35 => array(
			'label_de'  => 'Energie-Monitoring (Janitza)',
			'label_en'  => 'Energy monitoring (Janitza)',
			'articles'  => array( 'ac_dc' => array( 802035 ) ),
		),
		36 => array(
			'label_de'  => 'Entkoppeldiode',
			'label_en'  => 'Decoupling diode',
			'articles'  => array( 'ac_dc' => array( 802036 ) ),
		),
		37 => array(
			'label_de'  => 'Erdschlussüberwachung DC-Ausgang',
			'label_en'  => 'Earth fault monitoring DC output',
			'articles'  => array( 'ac_dc' => array( 802037 ) ),
		),
		38 => array(
			'label_de'  => 'Erweiterter Temperaturbereich',
			'label_en'  => 'Extended temperature range',
			'articles'  => array( 'ac_ac' => array( 804038 ) ),
		),
		39 => array(
			'label_de'  => 'Potentiometer / Trennverstärker',
			'label_en'  => 'Potentiometer / isolation amplifier',
			'articles'  => array( 'ac_dc' => array( 802039 ), 'ac_ac' => array( 804039 ) ),
		),
		40 => array(
			'label_de'  => 'Ext.aktive Ein/Ausschaltung des Gerätes',
			'label_en'  => 'External active on/off switching of the unit',
			'articles'  => array( 'ac_dc' => array( 802040 ) ),
		),
		41 => array(
			'label_de'  => 'Externe Abschaltung',
			'label_en'  => 'External shutdown',
			'articles'  => array( 'ac_dc' => array( 802041 ) ),
		),
		42 => array(
			'label_de'  => 'Externe Programmierung Spannung/Strom (0–10 V / 4–20 mA)',
			'label_en'  => 'External programming voltage/current (0–10 V / 4–20 mA)',
			'articles'  => array( 'ac_dc' => array( 802042 ) ),
		),
		43 => array(
			'label_de'  => 'Externer Oszillatoreingang',
			'label_en'  => 'External oscillator input',
			'articles'  => array( 'ac_ac' => array( 804043 ) ),
		),
		44 => array(
			'label_de'  => 'Fernwartungsmodul',
			'label_en'  => 'Remote maintenance module',
			'articles'  => array( 'ac_dc' => array( 802044 ) ),
		),
		45 => array(
			'label_de'  => 'Führungsspannung',
			'label_en'  => 'Guide voltage',
			'articles'  => array( 'ac_dc' => array( 802045 ) ),
		),
		46 => array(
			'label_de'  => 'Galvanische Trennung',
			'label_en'  => 'Galvanic isolation',
			'articles'  => array( 'ac_dc' => array( 802046 ) ),
		),
		47 => array(
			'label_de'  => 'High Speed',
			'label_en'  => 'High speed',
			'articles'  => array( 'ac_dc' => array( 802047 ) ),
		),
		48 => array(
			'label_de'  => 'Hochstromverbindung',
			'label_en'  => 'High-current connection',
			'articles'  => array( 'ac_dc' => array( 802048 ) ),
		),
		49 => array(
			'label_de'  => 'Innenwiderstandsregelung',
			'label_en'  => 'Internal resistance control',
			'articles'  => array( 'ac_dc' => array( 802049 ) ),
		),
		50 => array(
			'label_de'  => 'Messgenauigkeit 0,05% fs',
			'label_en'  => 'Measurement accuracy 0.05 % fs',
			'articles'  => array( 'ac_dc' => array( 802050 ) ),
		),
		51 => array(
			'label_de'  => 'Monitoring Spannung/Strom (analog)',
			'label_en'  => 'Monitoring voltage/current (analog)',
			'articles'  => array( 'ac_dc' => array( 802051 ) ),
		),
		52 => array(
			'label_de'  => 'Netz- und Anlagenschutz',
			'label_en'  => 'Grid and system protection',
			'articles'  => array( 'ac_dc' => array( 802052 ) ),
		),
		53 => array(
			'label_de'  => 'Netzüberwachungsmodul',
			'label_en'  => 'Grid monitoring module',
			'articles'  => array( 'e_load' => array( 803053 ) ),
		),
		54 => array(
			'label_de'  => 'Ohne manuelle Bedienung',
			'label_en'  => 'Without manual operation',
			'articles'  => array( 'ac_dc' => array( 802054 ) ),
		),
		55 => array(
			'label_de'  => 'Parallelbetrieb (Kit, Batterie, Bestandssysteme)',
			'label_en'  => 'Parallel operation (kit, battery, existing systems)',
			'articles'  => array( 'ac_dc' => array( 802055 ), 'ac_ac' => array( 804055 ) ),
		),
		56 => array(
			'label_de'  => 'Power Distribution / Power Control Software',
			'label_en'  => 'Power distribution / power control software',
			'articles'  => array( 'ac_dc' => array( 802056 ) ),
		),
		57 => array(
			'label_de'  => 'Rüttelfestigkeit',
			'label_en'  => 'Vibration resistance',
			'articles'  => array( 'ac_dc' => array( 802057 ), 'ac_ac' => array( 804057 ) ),
		),
		58 => array(
			'label_de'  => 'Safety Master für Parallelbetrieb',
			'label_en'  => 'Safety master for parallel operation',
			'articles'  => array( 'ac_dc' => array( 802058 ) ),
		),
		59 => array(
			'label_de'  => 'Schutzart IP21',
			'label_en'  => 'Protection class IP21',
			'articles'  => array( 'ac_dc' => array( 802059 ) ),
		),
		60 => array(
			'label_de'  => 'Schutzdiode 1000 V',
			'label_en'  => 'Protection diode 1000 V',
			'articles'  => array( 'ac_dc' => array( 802060 ) ),
		),
		61 => array(
			'label_de'  => 'SD Speicherkartenslot',
			'label_en'  => 'SD memory card slot',
			'articles'  => array( 'ac_dc' => array( 802061 ) ),
		),
		62 => array(
			'label_de'  => 'Sequencer-Funktion',
			'label_en'  => 'Sequencer function',
			'articles'  => array( 'ac_ac' => array( 804062 ) ),
		),
		63 => array(
			'label_de'  => 'Sockelerhöhung 200mm',
			'label_en'  => 'Base raise 200 mm',
			'articles'  => array( 'ac_dc' => array( 802063 ) ),
		),
		64 => array(
			'label_de'  => 'Spannungsreferenz',
			'label_en'  => 'Voltage reference',
			'articles'  => array( 'ac_dc' => array( 802064 ) ),
		),
		65 => array(
			'label_de'  => 'Umpolerfunktion (bipolar)',
			'label_en'  => 'Reverse polarity function (bipolar)',
			'articles'  => array( 'ac_dc' => array( 802065 ) ),
		),
		66 => array(
			'label_de'  => 'Wandlerfehlerüberwachung',
			'label_en'  => 'Converter fault monitoring',
			'articles'  => array( 'ac_dc' => array( 802066 ) ),
		),
		67 => array(
			'label_de'  => 'Wasserrückkühler',
			'label_en'  => 'Water recooler',
			'articles'  => array( 'ac_dc' => array( 802067 ) ),
		),
		68 => array(
			'label_de'  => 'Überlastfähigkeit',
			'label_en'  => 'Overload capability',
			'articles'  => array( 'ac_ac' => array( 804068 ) ),
		),
	);
}
