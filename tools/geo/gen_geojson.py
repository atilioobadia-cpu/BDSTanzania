#!/usr/bin/env python3
"""Reconcile geoBoundaries humanitarian TZA ADM1/ADM2 to official names and
attach region information, producing intermediate GeoJSON for simplification."""
import json, io, re, os

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
GEO = os.environ.get("BDST_GEO_CACHE", os.path.join(os.path.dirname(__file__), ".cache"))
OUT = GEO

def norm(s):
    return re.sub(r"\s+", " ", s).strip()

REGION_FIX = {"Dar-es-salaam": "Dar es Salaam"}

def fix_region(n):
    n = norm(n)
    return REGION_FIX.get(n, n)

region_meta = json.load(io.open(os.path.join(GEO, "tz-region.json"), encoding="utf-8"))["entities"]
by_name = {fix_region(r["name_en"]): {"code": r["id"], "slug": r["slug"]} for r in region_meta}

district_meta = json.load(io.open(os.path.join(GEO, "tz-district.json"), encoding="utf-8"))["entities"]
district_to_region = {}
for d in district_meta:
    district_to_region[norm(d["name_en"])] = fix_region(d["parent_name_en"])

adm1 = json.load(io.open(os.path.join(GEO, "gh-adm1.geojson"), encoding="utf-8"))
adm2 = json.load(io.open(os.path.join(GEO, "gh-adm2.geojson"), encoding="utf-8"))

# ---- regions ----------------------------------------------------------------
r_feats = []
missing_regions = []
for f in adm1["features"]:
    name = fix_region(f["properties"]["shapeName"])
    if name not in by_name:
        missing_regions.append(name)
        continue
    r_feats.append({
        "type": "Feature",
        "properties": {"name": name, "code": by_name[name]["code"], "slug": by_name[name]["slug"]},
        "geometry": f["geometry"],
    })

# ---- districts --------------------------------------------------------------
d_feats = []
missing_d = []
for f in adm2["features"]:
    name = norm(f["properties"]["shapeName"])
    region = district_to_region.get(name)
    if region is None:
        missing_d.append(name)
        continue
    d_feats.append({
        "type": "Feature",
        "properties": {"name": name, "region": region, "region_code": by_name[region]["code"]},
        "geometry": f["geometry"],
    })

json.dump({"type": "FeatureCollection", "features": r_feats},
          io.open(os.path.join(OUT, "mapped-regions.geojson"), "w", encoding="utf-8"))
json.dump({"type": "FeatureCollection", "features": d_feats},
          io.open(os.path.join(OUT, "mapped-districts.geojson"), "w", encoding="utf-8"))

print("regions in:", len(adm1["features"]), "-> out:", len(r_feats), "missing:", missing_regions)
print("districts in:", len(adm2["features"]), "-> out:", len(d_feats), "missing:", missing_d)
# region coverage
covered = sorted({f["properties"]["region"] for f in d_feats})
print("distinct regions covered by districts:", len(covered))
