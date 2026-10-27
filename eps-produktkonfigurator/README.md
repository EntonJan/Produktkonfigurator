# EPS Produktkonfigurator

Ein WordPress-Plugin, das einen Produktkonfigurator für WooCommerce bereitstellt.

## Status

Aktuell nur Grundgerüst: Plugin-Header, Aktivierungs-/Deaktivierungs-Hooks sowie eine
Prüfung, ob WooCommerce installiert und aktiv ist. Filterlogik, Datenanbindung und
Benutzeroberfläche folgen in späteren Ausbaustufen.

## Voraussetzungen

- WordPress ab 6.0
- PHP ab 7.4
- WooCommerce (muss installiert und aktiviert sein)

## Daten

Die Geräte kommen aus dem EPS-Onlineshop (`data/shop-devices.json`, 748 Geräte, Stand 27.10.2026).
Aktualisieren: EPS-Produktsuche starten und „Shop neu einlesen“, danach
`python tools/shop-import.py <…/EPS-Produktsuche-Daten/shopdaten.json>` im Repo-Hauptordner.
Der Shop ist nur deutsch (name_en = name_de), Optionen, Anzeige, Kühlung, Temperatur und Eingangsspannung
stehen nicht in der Shopliste – diese Filter greifen erst mit den Weclapp-Daten.

## Schnellsuche

Testseite „Konfigurator Test“ → oberster Kasten: Kundenanfrage als Freitext (z. B. „Stromquelle 0-30V 1kA 30kW“),
Ergebnis sofort beim Tippen, „In Filter übernehmen“ setzt die Regler. Logik: `assets/js/eps-quick-search.js`,
Tests: `node --test tests/*.test.js` (Repo-Hauptordner).

## Struktur

- `eps-produktkonfigurator.php` – Haupt-Plugin-Datei
- `includes/` – Registry, Geräteklasse, Filter, Shop-Gerätelader, Testseite
- `assets/js/eps-quick-search.js` – Schnellsuche (Logik), `admin-quick-search.js` – Oberfläche der Testseite
- `data/shop-devices.json` – Gerätedaten aus dem Onlineshop
