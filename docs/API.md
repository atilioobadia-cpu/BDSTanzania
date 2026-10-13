# BDS Tanzania - Public Dashboard JSON API

The public member dashboard at [`/dashboard`](https://www.bdst.or.tz/dashboard) is backed by four read-only JSON
endpoints under `/api/dashboard/`. They expose **aggregate counts only**. No
member name, firm, CV, reference number or any other identifier is ever
returned; region/district names are published only where the cohort floor
(`min_cohort`, shipped value `1`) makes that safe.

- Base URL: `https://www.bdst.or.tz`
- Media type: `application/json; charset=utf-8`
- Auth: none - the endpoints are public and read-only.
- Rate limiting: none required; payloads are small aggregates and the page's
  clients cache GeoJSON/tiles, not these endpoints.

## Common behaviour

Every endpoint returns a JSON object. Success carries `"ok": true`; failures
carry `"ok": false` plus a short `"error"` message.

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
X-Robots-Tag: noindex, nofollow
Cache-Control: no-store, max-age=0
Referrer-Policy: no-referrer
```

- `X-Robots-Tag: noindex, nofollow` keeps the raw JSON out of search results
  (the rendered dashboard page itself is indexable).
- `Cache-Control: no-store` keeps counts fresh; the browser client refetches
  on load and on auto-refresh.
- Errors are returned with an appropriate status (`400` invalid input,
  `404` unknown region, `500` unexpected failure) and a generic message.

### `band` values

Choropleth bands used by the map and legend:

| band        | Meaning                                  |
| ----------- | ---------------------------------------- |
| `withheld`  | Region hidden (count never disclosed)    |
| `zero`      | 0 members                                |
| `lt_min`    | Fewer than `min_cohort` members (`count` is `null`) |
| `low`       | `min_cohort .. 9` members (`count` is `null` when at the floor) |
| `mid`       | 10 - 24 members                          |
| `high`      | 25 or more members                       |

At the shipped floor (`min_cohort = 1`, `min_total = 1`) nothing is withheld
and every count is the true number.

---

## `GET /api/dashboard/summary.php`

Top-level totals for the KPI strip.

Response payload:

```jsonc
{
  "ok": true,
  "generated_at": "2026-10-13T09:00:00+00:00",
  "published": true,
  "thresholds": { "min_cohort": 1, "min_total": 1 },
  "totals": {
    "members": 142,
    "regions_covered": 18,
    "districts_covered": 51,
    "unmatched_region": 0,
    "unmatched_district": 7
  },
  "max_count": 24,
  "legend": [
    { "key": "lt_min", "label": "Fewer than 1 members" },
    { "key": "zero",   "label": "No members" },
    { "key": "low",    "label": "1 to 9 members" },
    { "key": "mid",    "label": "10 to 24 members" },
    { "key": "high",   "label": "25 or more members" }
  ]
}
```

| Field | Type | Notes |
| ----- | ---- | ----- |
| `generated_at` | string | ISO-8601 UTC timestamp of the aggregate |
| `published` | bool | `false` until `totals.members >= min_total` |
| `thresholds.min_cohort` | int | Privacy floor per region/district |
| `thresholds.min_total` | int | Privacy floor for the whole set |
| `totals.members` | int | Approved members |
| `totals.regions_covered` | int\|null | Regions with >= 1 member (`null` when unpublished) |
| `totals.districts_covered` | int\|null | Districts with >= 1 member (`null` when unpublished) |
| `totals.unmatched_region` | int\|null | Members whose region did not match the official list |
| `totals.unmatched_district` | int\|null | Members whose district did not match |
| `max_count` | int | Largest region count (used to scale pins) |
| `legend` | array | Band key/label pairs for the map legend |

---

## `GET /api/dashboard/regions.php`

One entry per official region (31), with the privacy-filtered count and band.
Same wrapper fields as `summary.php` plus:

```jsonc
"regions": [
  {
    "name": "Arusha",
    "code": "TZ01",
    "slug": "arusha",
    "lat": -3.3869,
    "lon": 36.6830,
    "count": 12,
    "band": "mid",
    "label": "10 to 24 members"
  }
]
```

| Field | Type | Notes |
| ----- | ---- | ----- |
| `name` | string | Official region name (EN) |
| `code` | string | `TZ` + 2 digits (ISO 3166-2:TZ order) |
| `slug` | string | URL-safe name |
| `lat` / `lon` | number | Administrative area **centroid**, not a residential address |
| `count` | int\|null | Approved members in the region; `null` when suppressed (`lt_min`/withheld) |
| `band` | string | See [band values](#band-values) |
| `label` | string | Human-readable band label |

---

## `GET /api/dashboard/districts.php?region=TZ02`

District breakdown for **one region**. Districts below `min_cohort` are folded
into a single `"Other / not specified"` row rather than disclosed individually.

| Parameter | Type | Required | Notes |
| --------- | ---- | -------- | ----- |
| `region` | string | yes | Region code, e.g. `TZ02`. Accepts lowercase; must match `^TZ\d{2}$` |

Errors: `400` when `region` is missing or malformed, `404` for an unknown code.

Response payload:

```jsonc
{
  "ok": true,
  "generated_at": "2026-10-13T09:00:00+00:00",
  "region": {
    "region": "Dodoma",
    "code": "TZ03",
    "slug": "dodoma",
    "published": true,
    "min_cohort": 1,
    "reason": null,
    "total": 9,
    "items": [
      { "name": "Chamwino", "count": 5, "band": "low", "label": "5" },
      { "name": "Dodoma",   "count": 2, "band": "low", "label": "2" },
      { "name": "Other / not specified", "count": 2, "band": "low", "label": "2" }
    ]
  }
}
```

| Field | Type | Notes |
| ----- | ---- | ----- |
| `region` | string | Official region name |
| `published` | bool | `false` only when a regional cohort floor is in force and unmet |
| `reason` | string\|null | `below_cohort` or `withheld` when unpublished, else `null` |
| `total` | int\|null | Region total (`null` when unpublished) |
| `items[]` | array | `{ name, count, band, label }`; `count` is `null` for `lt_min` rows |

---

## `GET /api/dashboard/trends.php?period=monthly`

Approved-member registrations over time. A bucket is a **number of members** -
no region, district or identity is attached.

| Parameter | Type | Default | Notes |
| --------- | ---- | ------- | ----- |
| `period` | string | `monthly` | One of `daily`, `weekly`, `monthly`, `yearly` |
| `from` | string | - | Start date `Y-m-d` aligned to the period |
| `to` | string | - | End date `Y-m-d` (max range enforced server-side) |

Errors: `400` for an unknown `period` or a `from`/`to` value that does not
align with the chosen period (e.g. a month value in a `daily` range).

Response payload:

```jsonc
{
  "ok": true,
  "period": "monthly",
  "periods": ["daily", "weekly", "monthly", "yearly"],
  "from": "2024-01-01",
  "to": "2026-10-01",
  "published": true,
  "total": 142,
  "peak": 24,
  "buckets": [
    { "key": "2026-08", "label": "Aug 2026", "count": 24, "cumulative": 142 }
  ]
}
```

| Field | Type | Notes |
| ----- | ---- | ----- |
| `period` | string | Effective period (validated/normalised) |
| `periods` | array | The four accepted values - clients use this for the segmented control |
| `from` / `to` | string\|null | Scope of the series |
| `published` | bool | `false` until `total >= min_total` |
| `total` | int\|null | Approved members counted in-scope |
| `peak` | int | Largest single-bucket count |
| `buckets[]` | array | `{ key, label, count, cumulative }`; `key` is `YYYY-MM`, `YYYY-Www`, `YYYY-MM-DD` or `YYYY` |

---

## Privacy model (why the API looks this way)

- The map, list and chart never see a name, firm, CV, reference or address.
- A cell (region/district/month) is shown only as a **count in a band**.
- When a cohort would be 1-2 people, the count is `null` / folded into
  `"Other / not specified"` and the row is labelled "Fewer than N". With the
  shipped threshold of 1, every published number is exact.
- GeoJSON boundary files and tile layers are public map data
  (geoBoundaries TZA ADM1/ADM2, OSM tiles CC BY-SA), never member locations.
  Pin and district reference coordinates are administrative centroids.