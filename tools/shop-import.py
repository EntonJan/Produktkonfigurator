#!/usr/bin/env python3
"""Shopdaten der EPS-Produktsuche → Gerätedaten des Produktkonfigurators.

Eingabe:  shopdaten.json der EPS-Produktsuche (liest www.eps-germany.de/shop aus, liegt neben der EXE in
          „EPS-Produktsuche-Daten“).
Ausgabe:  eps-produktkonfigurator/data/shop-devices.json (wird vom Plugin über eps_get_shop_devices() geladen).

Aufruf:   python tools/shop-import.py <pfad-zu-shopdaten.json>

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


def umwandeln(g: dict) -> dict:
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
    attr["mains_regeneration"] = bool(re.search(r"netzrückspeisung|rückspeisend|regenerativ", g["name"] + " " + g["serie"], re.I))
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
            "texts": {"voltage": g["spannung"], "current": g["strom"], "power": g["leistung"], "form": g["bauart"]},
            "pdf_url": g["pdf_url"],
            "shop_url": g["shop_url"],
        },
    }


def main(quelle: str) -> None:
    d = json.loads(Path(quelle).read_text(encoding="utf-8"))
    geraete = [umwandeln(g) for g in d["geraete"] if g["geraetetyp"] in GERAETETYP]
    ZIEL.parent.mkdir(parents=True, exist_ok=True)
    kopf = json.dumps({"source": d.get("quelle", ""), "stand": d.get("stand", ""), "count": len(geraete)}, ensure_ascii=False)
    zeilen = ",\n".join(json.dumps(g, ensure_ascii=False, separators=(",", ":")) for g in geraete)
    ZIEL.write_text(kopf[:-1] + ',\n"devices":[\n' + zeilen + "\n]}\n", encoding="utf-8")   # ein Gerät pro Zeile (lesbare Diffs)
    print(f"{len(geraete)} Geräte → {ZIEL}")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(sys.argv[1])
