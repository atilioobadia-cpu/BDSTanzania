#!/usr/bin/env python3
"""Generate includes/geo.php from the open-admin-data Tanzania dataset."""
import json, io, datetime, os, re

def norm_name(s):
    return re.sub(r"\s+", " ", s).strip()

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
GEO = os.environ.get("BDST_GEO_CACHE", os.path.join(os.path.dirname(__file__), ".cache"))
OUT = os.path.join(REPO, "includes", "geo.php")

with io.open(os.path.join(GEO, "tz-region.json"), encoding="utf-8") as f:
    regions_raw = json.load(f)["entities"]
with io.open(os.path.join(GEO, "tz-district.json"), encoding="utf-8") as f:
    districts_raw = json.load(f)["entities"]

# Canonical display-name fixes (dataset spelling -> official/common English)
REGION_NAME_FIX = {"Dar-es-salaam": "Dar es Salaam"}
DISTRICT_NAME_FIX = {}

def fix_region(n):
    return REGION_NAME_FIX.get(norm_name(n), norm_name(n))

regions = {}
for r in regions_raw:
    name = fix_region(r["name_en"])
    regions[name] = {"id": r["id"], "slug": r["slug"], "lat": r["lat"], "lon": r["lon"]}

regions = dict(sorted(regions.items(), key=lambda kv: kv[0].lower()))

districts = {name: [] for name in regions}
unmatched = []
collisions = []
for d in districts_raw:
    parent = fix_region(d["parent_name_en"])
    dname = norm_name(DISTRICT_NAME_FIX.get(d["name_en"], d["name_en"]))
    if parent in districts:
        districts[parent].append(dname)
    else:
        unmatched.append((d["name_en"], d["parent_name_en"]))
for k in districts:
    before = len(districts[k])
    districts[k] = sorted(set(districts[k]), key=lambda s: s.lower())
    if len(districts[k]) != before:
        collisions.append((k, before, len(districts[k])))

def pstr(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"

lines = []
w = lines.append
w("<?php")
w("declare(strict_types=1);")
w("")
w("/**")
w(" * BDS Tanzania - official geographic reference data (regions + districts).")
w(" *")
w(" * AUTO-GENERATED FILE - do not edit by hand.")
w(" * Regenerate from the source dataset if the administrative divisions change.")
w(" *")
w(" * Source:   open-admin-data/tanzania-administrative-divisions")
w(" *           https://github.com/open-admin-data/tanzania-administrative-divisions")
w(" *           Derived from OCHA COD-AB (HDX): https://data.humdata.org/dataset/cod-ab-tza")
w(" * License:  CC-BY-4.0")
w(" * Generated: " + datetime.date.today().isoformat())
w(" *")
w(" * Reference points (lat/lon) are administrative centroids, used to place")
w(" * AREA markers on the map. They are NOT residential addresses and must")
w(" * never be treated as a member's precise location.")
w(" */")
w("")
w("/**")
w(" * Official regions (31). Map of official name => id/slug/centroid.")
w(" *")
w(" * @return array<string, array{id: string, slug: string, lat: float, lon: float}>")
w(" */")
w("function bdst_official_regions(): array")
w("{")
w("    return [")
for name, m in regions.items():
    w("        %s => ['id' => %s, 'slug' => %s, 'lat' => %s, 'lon' => %s]," % (
        pstr(name), pstr(m["id"]), pstr(m["slug"]), m["lat"], m["lon"]))
w("    ];")
w("}")
w("")
w("/**")
w(" * Official districts grouped by official region name.")
w(" *")
w(" * @return array<string, list<string>>")
w(" */")
w("function bdst_official_districts(): array")
w("{")
w("    return [")
for region, ds in districts.items():
    inner = ", ".join(pstr(d) for d in ds)
    w("        %s => [%s]," % (pstr(region), inner))
w("    ];")
w("}")
w("")
w("/**")
w(" * All official region names (sorted).")
w(" *")
w(" * @return list<string>")
w(" */")
w("function bdst_region_names(): array")
w("{")
w("    return array_keys(bdst_official_regions());")
w("}")
w("")
w("/**")
w(" * Districts belonging to a region (empty array if the region is unknown).")
w(" *")
w(" * @return list<string>")
w(" */")
w("function bdst_districts_for_region(string $region): array")
w("{")
w("    $all = bdst_official_districts();")
w("    return $all[$region] ?? [];")
w("}")
w("")
w("/**")
w(" * Normalise a loose region value to the official name, or null if unknown.")
w(" * Matching ignores case and surrounding whitespace.")
w(" */")
w("function bdst_normalize_region(string $value): ?string")
w("{")
w("    static $index = null;")
w("    if ($index === null) {")
w("        $index = [];")
w("        foreach (bdst_official_regions() as $name => $_) {")
w("            $index[mb_strtolower(trim($name))] = $name;")
w("        }")
w("    }")
w("    $key = mb_strtolower(trim($value));")
w("    return $index[$key] ?? null;")
w("}")
w("")
w("/**")
w(" * Normalise a loose district value to the official name, or null if unknown.")
w(" * When $region is given, the district must belong to that region.")
w(" */")
w("function bdst_normalize_district(string $value, ?string $region = null): ?string")
w("{")
w("    $key = mb_strtolower(trim($value));")
w("    $pools = $region !== null ? [bdst_districts_for_region($region)] : bdst_official_districts();")
w("    foreach ($pools as $pool) {")
w("        foreach ($pool as $district) {")
w("            if (mb_strtolower(trim($district)) === $key) {")
w("                return $district;")
w("            }")
w("        }")
w("    }")
w("    return null;")
w("}")
w("")

with io.open(OUT, "w", encoding="utf-8", newline="\n") as f:
    f.write("\n".join(lines))

print("regions:", len(regions))
print("districts total:", sum(len(v) for v in districts.values()))
print("unmatched districts:", unmatched)
print("dedupe collisions:", collisions)
print("wrote:", OUT)
