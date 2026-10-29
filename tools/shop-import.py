#!/usr/bin/env python3
"""Shopdaten der EPS-Produktsuche → Gerätedaten des Produktkonfigurators.

Eingabe:  shopdaten.json der EPS-Produktsuche (liest www.eps-germany.de/shop aus, liegt neben der EXE in
          „EPS-Produktsuche-Daten“).
Ausgabe:  eps-produktkonfigurator/data/shop-devices.json (wird vom Plugin über eps_get_shop_devices() geladen).

Aufruf:   python tools/shop-import.py <pfad-zu-shopdaten.json>
          python tools/shop-import.py --json <datei>   (nur umwandeln und als JSON ausgeben – für die Node-Tests)

Später ersetzt die Weclapp-Anbindung diese Quelle – das Ausgabeformat (EPS_Device-Felder + attributes + meta) bleibt.
"""
import json
import re
import sys
from pathlib import Path

GERAETETYP = {"AC/DC": "ac_dc", "AC/AC": "ac_ac", "DC/AC": "dc_ac", "DC/DC": "dc_dc",
              "Elektronische Last": "e_load", "Ladegeräte": "charger"}
BAUFORM = {"19 Zoll": "rack_19", "Schrank/Cabinet": "cabinet", "Einbau/Built-in": "built_in", "Desktop": "desktop",
           "Tower": "tower", "DIN Rail": "din_rail"}
ZIEL = Path(__file__).resolve().parents[1] / "eps-produktkonfigurator" / "data" / "shop-devices.json"


STANDARD = {"serie": "", "geraetetyp": "", "bidirektional": False, "spannung": "", "strom": "", "leistung": "", "bauart": "",
            "umin": None, "umax": None, "imax": None, "pmax": None, "ac": False, "u_fest": [], "u_bereiche": [],
            "aus_name": "", "parallel": False, "parallel_max": None, "reihe": False, "shop_url": "", "pdf_url": "",
            "f_fest": [], "f_bereiche": [], "f_option": [], "wasser": "", "rueckspeisung": False}
WASSER = re.compile(r"-WC\b|\bWC\b|wassergekühlt|wasserkühlung|water.?cooled", re.I)


def kanaele(g: dict) -> int:
    """Wie viele Ausgänge/Phasen teilen sich die Leistung (für die Plausibilitätsprüfung P ≤ U × I × Kanäle)."""
    m = re.search(r"(\d)\s*x\b", g["strom"]) or re.search(r"(\d)x\d", g["name"])
    if m:
        return int(m.group(1))
    if re.search(r"triple|3-fach", g["name"] + " " + g["serie"], re.I):
        return 3
    if g["ac"] and re.search(r"3\s*ph|3x|3~|L-L|\+N", g["spannung"], re.I):
        return 3
    return 1


def umwandeln(g: dict, datenformat: int = 2) -> dict:
    g = {**STANDARD, **g}
    if datenformat < 2 and not g["wasser"] and WASSER.search(g["name"] + " " + g["serie"]):
        g["wasser"] = "ja"                         # alte Shopdaten: Wasserkühlung nur aus dem Namen
    attr = {}
    if g["umax"] is not None:
        attr["output_voltage"] = [g["umin"] if g["umin"] is not None else g["umax"], g["umax"]]
    if g["imax"] is not None:
        attr["output_current"] = g["imax"]
    if g["pmax"] is not None:
        attr["output_power"] = g["pmax"]
    if g["bauart"] in BAUFORM:
        attr["form_factor"] = BAUFORM[g["bauart"]]
    attr["bidirectional"] = bool(g["bidirektional"])
    attr["mains_regeneration"] = bool(g["rueckspeisung"]) or bool(
        re.search(r"netzrückspeisung|rückspeis|regenerativ", g["name"] + " " + g["serie"], re.I))
    return {
        "sku": g["bestellnummer"],
        "name_de": g["name"],
        "name_en": g["name"],                      # Shop ist nur deutsch – EN folgt mit Weclapp
        "category": g["serie"],
        "device_type": GERAETETYP.get(g["geraetetyp"], ""),
        "voltage_min": g["umin"] or 0.0,
        "voltage_max": g["umax"] or 0.0,
        "current_max": g["imax"] or 0.0,
        "power_max": g["pmax"] or 0.0,
        "attributes": attr,
        "meta": {
            "source": "shop",
            "power_rule": "max",                   # Validierung: P ≤ U × I × Kanäle (Autoranging/Derating erlaubt)
            "channels": kanaele(g),
            "ac": bool(g["ac"]),
            "u_fixed": g["u_fest"],
            "u_ranges": g["u_bereiche"],
            "parallel": bool(g["parallel"]),
            "parallel_max": g["parallel_max"],
            "series": bool(g["reihe"]),
            "derived_from_name": g["aus_name"],
            "f_fixed": g["f_fest"],                # Ausgangsfrequenz Hz (Standard), z. B. [50, 60]
            "f_ranges": g["f_bereiche"],           # einstellbar, z. B. [[15, 1000]]
            "f_option": g["f_option"],             # als Option lieferbar
            "water": g["wasser"],                  # "ja" | "option" | ""
            "texts": {"voltage": g["spannung"], "current": g["strom"], "power": g["leistung"], "form": g["bauart"]},
            "pdf_url": g["pdf_url"],
            "shop_url": g["shop_url"],
        },
    }


def umwandeln_datei(d: dict) -> list[dict]:
    fmt = d.get("format", 1)
    return [umwandeln(g, fmt) for g in d["geraete"] if g.get("geraetetyp") in GERAETETYP]


def main(quelle: str) -> None:
    d = json.loads(Path(quelle).read_text(encoding="utf-8"))
    geraete = umwandeln_datei(d)
    ZIEL.parent.mkdir(parents=True, exist_ok=True)
    kopf = json.dumps({"source": d.get("quelle", ""), "stand": d.get("stand", ""), "count": len(geraete)}, ensure_ascii=False)
    zeilen = ",\n".join(json.dumps(g, ensure_ascii=False, separators=(",", ":")) for g in geraete)
    ZIEL.write_text(kopf[:-1] + ',\n"devices":[\n' + zeilen + "\n]}\n", encoding="utf-8")   # ein Gerät pro Zeile (lesbare Diffs)
    print(f"{len(geraete)} Geräte → {ZIEL}")


if __name__ == "__main__":
    if len(sys.argv) == 3 and sys.argv[1] == "--json":
        sys.stdout.reconfigure(encoding="utf-8")
        print(json.dumps(umwandeln_datei(json.loads(Path(sys.argv[2]).read_text(encoding="utf-8"))), ensure_ascii=False))
        sys.exit(0)
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(sys.argv[1])
