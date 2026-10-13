#!/usr/bin/env python3
"""Generate the geo reference-table migration SQL from the Tanzania dataset."""
import json, io, os, re, datetime

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
GEO = os.environ.get("BDST_GEO_CACHE", os.path.join(os.path.dirname(__file__), ".cache"))
OUT = os.path.join(REPO, "database", "update_2026_10_geo_dashboard.sql")

def norm_name(s):
    return re.sub(r"\s+", " ", s).strip()

with io.open(os.path.join(GEO, "tz-region.json"), encoding="utf-8") as f:
    regions_raw = json.load(f)["entities"]
with io.open(os.path.join(GEO, "tz-district.json"), encoding="utf-8") as f:
    districts_raw = json.load(f)["entities"]

REGION_NAME_FIX = {"Dar-es-salaam": "Dar es Salaam"}

def fix_region(n):
    n = norm_name(n)
    return REGION_NAME_FIX.get(n, n)

regions = []
for r in regions_raw:
    regions.append({"code": r["id"], "name": fix_region(r["name_en"]),
                     "slug": r["slug"], "lat": r["lat"], "lon": r["lon"]})
regions.sort(key=lambda r: r["name"].lower())

# region code -> name
code_to_name = {r["code"]: r["name"] for r in regions}

districts = []
for d in districts_raw:
    districts.append({
        "region_code": d["parent_id"],
        "name": norm_name(d["name_en"]),
        "lat": d["lat"],
        "lon": d["lon"],
    })
districts.sort(key=lambda d: (code_to_name.get(d["region_code"], "").lower(), d["name"].lower()))

def q(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"

L = []
w = L.append
w("-- ============================================================================")
w("-- BDS Tanzania - geographic dashboard reference data + supporting index")
w("--")
w("-- AUTO-GENERATED FILE - do not edit by hand.")
w("-- Generated: %s" % datetime.date.today().isoformat())
w("--")
w("-- Source:  open-admin-data/tanzania-administrative-divisions (CC-BY-4.0)")
w("--          Derived from OCHA COD-AB (HDX) cod-ab-tza")
w("--")
w("-- Safe to run once on production. All statements are guarded/idempotent.")
w("-- Take a full backup before running. No existing data is modified or dropped.")
w("-- ============================================================================")
w("")
w("SET NAMES utf8mb4;")
w("")
w("-- ----------------------------------------------------------------------------")
w("-- 1. Reference tables (official regions + districts with centroids)")
w("-- ----------------------------------------------------------------------------")
w("CREATE TABLE IF NOT EXISTS geo_regions (")
w("  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,")
w("  code           VARCHAR(8)   NOT NULL,")
w("  name_official  VARCHAR(60)  NOT NULL,")
w("  slug           VARCHAR(60)  NOT NULL,")
w("  centroid_lat   DECIMAL(9,6) NOT NULL,")
w("  centroid_lng   DECIMAL(9,6) NOT NULL,")
w("  PRIMARY KEY (id),")
w("  UNIQUE KEY uq_geo_region_name (name_official),")
w("  UNIQUE KEY uq_geo_region_code (code)")
w(") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;")
w("")
w("CREATE TABLE IF NOT EXISTS geo_districts (")
w("  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,")
w("  region_id      INT UNSIGNED NOT NULL,")
w("  name_official  VARCHAR(80)  NOT NULL,")
w("  centroid_lat   DECIMAL(9,6) NULL,")
w("  centroid_lng   DECIMAL(9,6) NULL,")
w("  PRIMARY KEY (id),")
w("  UNIQUE KEY uq_geo_district (region_id, name_official),")
w("  KEY idx_geo_district_region (region_id),")
w("  CONSTRAINT fk_geo_district_region FOREIGN KEY (region_id) REFERENCES geo_regions (id)")
w(") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;")
w("")
w("-- ----------------------------------------------------------------------------")
w("-- 2. Supporting index for fast geographic aggregation on the members table")
w("--    (guarded so re-running is harmless on MariaDB/MySQL)")
w("-- ----------------------------------------------------------------------------")
w("SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS")
w("   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bds_registrations'")
w("     AND INDEX_NAME = 'idx_bds_reg_geo');")
w("SET @ddl := IF(@idx_exists = 0,")
w("   'ALTER TABLE bds_registrations ADD KEY idx_bds_reg_geo (region, district)',")
w("   'DO 0');")
w("PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;")
w("")
w("-- ----------------------------------------------------------------------------")
w("-- 3. Seed regions (31)")
w("-- ----------------------------------------------------------------------------")
w("INSERT INTO geo_regions (code, name_official, slug, centroid_lat, centroid_lng) VALUES")
rows = []
for r in regions:
    rows.append("  (%s, %s, %s, %s, %s)" % (q(r["code"]), q(r["name"]), q(r["slug"]), r["lat"], r["lon"]))
w(",\n".join(rows) + "")
w("ON DUPLICATE KEY UPDATE")
w("  code = VALUES(code), slug = VALUES(slug),")
w("  centroid_lat = VALUES(centroid_lat), centroid_lng = VALUES(centroid_lng);")
w("")
w("-- ----------------------------------------------------------------------------")
w("-- 4. Seed districts (170)")
w("-- ----------------------------------------------------------------------------")
w("INSERT INTO geo_districts (region_id, name_official, centroid_lat, centroid_lng)")
w("SELECT gr.id, x.name_official, x.centroid_lat, x.centroid_lng")
w("FROM (")
srows = []
for d in districts:
    srows.append("  SELECT %s AS region_code, %s AS name_official, %s AS centroid_lat, %s AS centroid_lng" % (
        q(d["region_code"]), q(d["name"]), d["lat"], d["lon"]))
w("\n  UNION ALL\n".join(srows))
w(") AS x")
w("JOIN geo_regions gr ON gr.code = x.region_code")
w("ON DUPLICATE KEY UPDATE")
w("  centroid_lat = VALUES(centroid_lat), centroid_lng = VALUES(centroid_lng);")
w("")

with io.open(OUT, "w", encoding="utf-8", newline="\n") as f:
    f.write("\n".join(L))

print("regions:", len(regions), "districts:", len(districts))
print("wrote:", OUT)
