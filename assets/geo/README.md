# Geographic boundary data (GeoJSON)

These files power the interactive members map on `/dashboard`.

| File | Contents | Features | Size |
|------|----------|----------|------|
| `tz-regions.geojson` | Tanzania regions (Mkoa) | 31 | ~160 KB |
| `tz-districts.geojson` | Tanzania districts (Wilaya) | 170 | ~310 KB |

## Feature properties

`tz-regions.geojson` — each feature has:

- `name` — official region name (matches `includes/geo.php`)
- `code` — official code, e.g. `TZ01`
- `slug` — URL-safe slug, e.g. `dar-es-salaam`

`tz-districts.geojson` — each feature has:

- `name` — official district name (matches `includes/geo.php`)
- `region` — parent region official name
- `region_code` — parent region code, e.g. `TZ01`

The `name` values are guaranteed to match the official names in
`includes/geo.php` and the `geo_regions` / `geo_districts` tables.

## Source & licence

Boundaries:
- **geoBoundaries** — Tanzania ADM1 & ADM2, "gbHumanitarian" release
  (`geoBoundaries-TZA-ADM1_simplified.geojson`,
  `geoBoundaries-TZA-ADM2_simplified.geojson`, build `9469f09`).
- Original source: **Tanzania National Bureau of Statistics / UN OCHA ROSA (HDX)**
  <https://data.humdata.org/dataset/cod-ab-tza>
- Licence: **CC BY 3.0 IGO**. Attribution is shown in the map's footer and
  must be kept.

Official names, codes and area centroids:
- **open-admin-data/tanzania-administrative-divisions**
  <https://github.com/open-admin-data/tanzania-administrative-divisions>
- Licence: **CC BY 4.0**.

## Regenerating

The files are produced by the scripts in `tools/geo/`. See that folder's
README. Do not hand-edit these files — regenerate instead.

The geometries are simplified (mapshaper, 25% with topology repair, coordinate
precision ~0.001°). Reference points are administrative area centroids used to
place **area** markers; they are never residential coordinates.
