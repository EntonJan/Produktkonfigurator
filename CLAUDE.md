# EPS Produktkonfigurator

## Ziel
WooCommerce-Plugin `eps-produktkonfigurator` für einen Produktkonfigurator.
Aktuell mit Mock-Daten, später Anbindung an die Weclapp-API. Lokale
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
  bleiben es.
- Regel bei fehlendem Attribut: Trifft ein aktiver, für den Gerätetyp
  zutreffender Filter zu, das Gerät hat aber keinen Wert für dieses
  Attribut, fällt das Gerät aus dem Ergebnis.
- Die Webcode/SKU-Suche ist ein eigener, exklusiver Modus: Sie ignoriert
  Gerätetyp, Regler, Dropdowns und Anwendungsbereich und durchsucht alle
  gültigen Geräte.
- Bereits gewählte Filterwerte werden nie stillschweigend geändert (auch
  wenn sie durch andere Filter ungültig werden) – stattdessen erscheint der
  Hinweis „Keine Geräte für diese Kombination“.

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
und das Ergebnis berichten. Mock-Daten bleiben klar als Testdaten
erkennbar (z. B. Testoptionen, offensichtlich ungültige Geräte).
