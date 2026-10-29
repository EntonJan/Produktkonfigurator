# EPS Produktkonfigurator

## Ziel
WooCommerce-Plugin `eps-produktkonfigurator` für einen Produktkonfigurator.
Gerätedaten aktuell aus dem EPS-Onlineshop (`data/shop-devices.json`, erzeugt
mit `tools/shop-import.py`), später Anbindung an die Weclapp-API. Lokale
Entwicklung per Docker: `docker compose up -d`, Aufruf über
`http://localhost:8080`.

## Architektur
- Zentrale Filter-Registry: `eps-produktkonfigurator/includes/filters-config.php`
  (`eps_get_filters_config()`). Filter und Oberfläche (Testseite) werden
  generisch daraus erzeugt – **keine hartkodierten Feldnamen** in Logik oder
  Templates.
- Gerätetyp (`device_type`) ist die Einstiegsfrage. Er steuert über
  `applies_to` in der Registry, welche weiteren Filter überhaupt sichtbar
  bzw. relevant sind. Ein Filter, der für den gewählten Gerätetyp nicht
  zutrifft, wird ignoriert (schließt keine Geräte aus), nicht ausgeblendet
  durch Sonderfall-Code.
- Slider-Filter haben eine `match`-Strategie:
  - `cover`: Das Gerät hat selbst einen Von-Bis-Bereich (z. B. Spannung) und
    muss den GESAMTEN gewählten Bereich abdecken.
  - `within`: Das Gerät hat einen Einzelwert (z. B. Strommaximum), der
    innerhalb von [von, bis] liegen muss.
- Nur gültige Geräte (`EPS_Device::is_valid()`) erscheinen in Ergebnissen.
  `NT-9901`, `IND-9902`, `LG-9903` sind bewusst ungültige Testgeräte und
  bleiben es (strenge Regel P ≈ U × I ±10 %). Shopgeräte (`meta.power_rule =
  max`) dürfen weniger Leistung haben (Autoranging/Derating), aber nicht mehr
  als U × I × Kanäle +10 %; Verstöße sind Datenfehler im Shop.
- Geräte kommen über `eps_get_devices()` (Shopgeräte + die 3 Testgeräte).
  Zusatzinfos, die keine Registry-Filter sind (AC-Festspannungen,
  Parallelschaltung, Originaltexte, Datenblatt-Link), liegen in
  `EPS_Device::$meta`.
- Regel bei fehlendem Attribut: Trifft ein aktiver, für den Gerätetyp
  zutreffender Filter zu, das Gerät hat aber keinen Wert für dieses
  Attribut, fällt das Gerät aus dem Ergebnis.
- Die Webcode/SKU-Suche ist ein eigener, exklusiver Modus: Sie ignoriert
  Gerätetyp, Regler, Dropdowns und Anwendungsbereich und durchsucht alle
  gültigen Geräte.
- Bereits gewählte Filterwerte werden nie stillschweigend geändert (auch
  wenn sie durch andere Filter ungültig werden) – stattdessen erscheint der
  Hinweis „Keine Geräte für diese Kombination“.

## Schnellsuche
Freitext wie „Stromquelle 0-30V 1kA 30kW“ → passende Geräte, Logik in
`assets/js/eps-quick-search.js` (DOM-frei, im Browser und in Node lauffähig,
Tests: `node --test tests/*.test.js`). Bewertung: exact (≤ Wunsch +20 %,
gleiche Obergrenze wie die Filter), larger, parallel/series (nur wenn laut
Daten möglich), slightly_below (−10 %, nur intern). Im Kundenmodus
(`internal: false`) werden parallel/series zu „system“ (auf Anfrage),
„slightly_below“ entfällt. Leistungsbedarf = angegebene Leistung, nur ohne Angabe U × I (Autoranging); AC-Festspannungen
müssen ±10 % passen. Zusatzkriterien im Freitext: Bauform (19 Zoll/Rack/HE/U, Desktop, Schrank, Einbau,
DIN Rail, Tower → `form_factor`), bidirektional, Netzrückspeisung (`mains_regeneration`),
wassergekühlt (`meta.water` ja/option), Ausgangsfrequenz in Hz (`meta.f_fixed`,
`f_ranges`, `f_option`). Bekannte Abweichung schließt aus, fehlende Angabe → Hinweis
`feature_unknown:…`, nur als Option → `feature_option:…`. Gemeinsame Testfälle mit der
EPS-Produktsuche: `tests/gemeinsame-suchfaelle.json` (in beiden Repos identisch halten;
Node-Test wandelt die Testgeräte über `tools/shop-import.py --json` um).
„In Filter übernehmen“ setzt Gerätetyp + Regler
(Spannung cover = U, Strom/Leistung within [Wunsch, Obergrenze]) nur auf
ausdrücklichen Klick.

## Optionen-Katalog
Zentraler Katalog mit durchgehenden Nummern 1 bis 12
(`eps_get_options_catalog()`). Jedes Gerät führt nur die für es tatsächlich
verfügbaren Nummern als Teilmenge. Die Options-Suche ist ein Textfeld mit
UND-Verknüpfung mehrerer Nummern, exaktem Zahlenvergleich; eine Nummer
außerhalb des Katalogs ergibt einen Hinweis und 0 Treffer.

## Zweisprachigkeit
Alle Bezeichnungen (Filter, Optionen, Gerätetypen) existieren als
`label_de`/`label_en`-Paar.

## Arbeitsweise
Änderungen immer im laufenden Docker-Setup testen (nicht nur Syntaxcheck)
und das Ergebnis berichten. Testdaten bleiben klar als Testdaten
erkennbar (z. B. Testoptionen, offensichtlich ungültige Geräte). Shopdaten
aktualisieren: EPS-Produktsuche starten („Shop neu einlesen“), dann
`python tools/shop-import.py <…/EPS-Produktsuche-Daten/shopdaten.json>`.
