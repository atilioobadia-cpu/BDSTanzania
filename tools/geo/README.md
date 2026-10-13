# Geographic data generation

Regenerates the official region/district data used by the members dashboard.
Outputs:

- `includes/geo.php` — official region + district lists and centroids
- `database/update_2026_10_geo_dashboard.sql` — reference tables + index
- `assets/geo/tz-regions.geojson`, `assets/geo/tz-districts.geojson` — boundaries

## Requirements

- Python 3.8+
- Node.js (mapshaper is run through `npx`, no global install required)

## Step 1 — download the raw inputs

Put these files in the cache directory (`tools/geo/.cache` by default; override
with the `BDST_GEO_CACHE` environment variable):

| File | URL |
|------|-----|
| `tz-region.json` | `https://openadmindata.org/api/v1/tz/region.json` |
| `tz-district.json` | `https://openadmindata.org/api/v1/tz/district.json` |
| `gh-adm1.geojson` | geoBoundaries gbHumanitarian TZA ADM1 simplified |
| `gh-adm2.geojson` | geoBoundaries gbHumanitarian TZA ADM2 simplified |

geoBoundaries files come from the "gbHumanitarian" release (source: Tanzania
NBS / UN OCHA ROSA via HDX), build `9469f09`:

```
https://github.com/wmgeolab/geoBoundaries/raw/9469f09/releaseData/gbHumanitarian/TZA/ADM1/geoBoundaries-TZA-ADM1_simplified.geojson
https://github.com/wmgeolab/geoBoundaries/raw/9469f09/releaseData/gbHumanitarian/TZA/ADM2/geoBoundaries-TZA-ADM2_simplified.geojson
```

> Use the **gbHumanitarian** ADM1, not **gbOpen**. The open ADM1 is from
> OpenStreetMap (2015): it has 30 units and is missing Songwe, with older
> Zanzibar names. gbHumanitarian ADM1 has the correct 31 regions and matches
> the ADM2 district layer.

## Step 2 — generate

```bash
python tools/geo/gen_php.py       # -> includes/geo.php
python tools/geo/gen_sql.py       # -> database/update_2026_10_geo_dashboard.sql
python tools/geo/gen_geojson.py   # -> .cache/mapped-{regions,districts}.geojson
```

`gen_geojson.py` reconciles names to the official lists and attaches the parent
region to every district feature. It aborts loudly if any name fails to match.

## Step 3 — simplify boundaries

```bash
npx -y mapshaper@0.7.84 tools/geo/.cache/mapped-regions.geojson \
    -simplify 25% keep-shapes -o precision=0.001 assets/geo/tz-regions.geojson

npx -y mapshaper@0.7.84 tools/geo/.cache/mapped-districts.geojson \
    -simplify 25% keep-shapes -o precision=0.001 assets/geo/tz-districts.geojson
```

## Licences (attribution required)

- Boundaries: geoBoundaries / Tanzania NBS / UN OCHA ROSA — **CC BY 3.0 IGO**
- Names, codes, centroids: open-admin-data — **CC BY 4.0**

Keep the map footer attribution and the credits in `assets/geo/README.md`.
