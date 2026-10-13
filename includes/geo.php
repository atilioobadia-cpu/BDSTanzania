<?php
declare(strict_types=1);

/**
 * BDS Tanzania - official geographic reference data (regions + districts).
 *
 * AUTO-GENERATED FILE - do not edit by hand.
 * Regenerate from the source dataset if the administrative divisions change.
 *
 * Source:   open-admin-data/tanzania-administrative-divisions
 *           https://github.com/open-admin-data/tanzania-administrative-divisions
 *           Derived from OCHA COD-AB (HDX): https://data.humdata.org/dataset/cod-ab-tza
 * License:  CC-BY-4.0
 * Generated: 2026-10-13
 *
 * Reference points (lat/lon) are administrative centroids, used to place
 * AREA markers on the map. They are NOT residential addresses and must
 * never be treated as a member's precise location.
 */

/**
 * Official regions (31). Map of official name => id/slug/centroid.
 *
 * @return array<string, array{id: string, slug: string, lat: float, lon: float}>
 */
function bdst_official_regions(): array
{
    return [
        'Arusha' => ['id' => 'TZ02', 'slug' => 'arusha', 'lat' => -2.918, 'lon' => 36.089],
        'Dar es Salaam' => ['id' => 'TZ07', 'slug' => 'dar-es-salaam', 'lat' => -6.872, 'lon' => 39.28],
        'Dodoma' => ['id' => 'TZ01', 'slug' => 'dodoma', 'lat' => -5.845, 'lon' => 36.008],
        'Geita' => ['id' => 'TZ25', 'slug' => 'geita', 'lat' => -3.353, 'lon' => 31.971],
        'Iringa' => ['id' => 'TZ11', 'slug' => 'iringa', 'lat' => -7.964, 'lon' => 35.519],
        'Kagera' => ['id' => 'TZ18', 'slug' => 'kagera', 'lat' => -2.172, 'lon' => 31.514],
        'Kaskazini Pemba' => ['id' => 'TZ54', 'slug' => 'kaskazini-pemba', 'lat' => -5.036, 'lon' => 39.753],
        'Kaskazini Unguja' => ['id' => 'TZ51', 'slug' => 'kaskazini-unguja', 'lat' => -5.881, 'lon' => 39.291],
        'Katavi' => ['id' => 'TZ23', 'slug' => 'katavi', 'lat' => -6.373, 'lon' => 31.331],
        'Kigoma' => ['id' => 'TZ16', 'slug' => 'kigoma', 'lat' => -4.699, 'lon' => 30.541],
        'Kilimanjaro' => ['id' => 'TZ03', 'slug' => 'kilimanjaro', 'lat' => -3.732, 'lon' => 37.647],
        'Kusini Pemba' => ['id' => 'TZ55', 'slug' => 'kusini-pemba', 'lat' => -5.323, 'lon' => 39.709],
        'Kusini Unguja' => ['id' => 'TZ52', 'slug' => 'kusini-unguja', 'lat' => -6.25, 'lon' => 39.425],
        'Lindi' => ['id' => 'TZ08', 'slug' => 'lindi', 'lat' => -9.334, 'lon' => 38.424],
        'Manyara' => ['id' => 'TZ21', 'slug' => 'manyara', 'lat' => -4.7, 'lon' => 36.432],
        'Mara' => ['id' => 'TZ20', 'slug' => 'mara', 'lat' => -1.762, 'lon' => 34.234],
        'Mbeya' => ['id' => 'TZ12', 'slug' => 'mbeya', 'lat' => -8.292, 'lon' => 33.931],
        'Mjini Magharibi' => ['id' => 'TZ53', 'slug' => 'mjini-magharibi', 'lat' => -6.174, 'lon' => 39.248],
        'Morogoro' => ['id' => 'TZ05', 'slug' => 'morogoro', 'lat' => -7.861, 'lon' => 36.92],
        'Mtwara' => ['id' => 'TZ09', 'slug' => 'mtwara', 'lat' => -10.771, 'lon' => 39.222],
        'Mwanza' => ['id' => 'TZ19', 'slug' => 'mwanza', 'lat' => -2.551, 'lon' => 32.861],
        'Njombe' => ['id' => 'TZ22', 'slug' => 'njombe', 'lat' => -9.601, 'lon' => 34.667],
        'Pwani' => ['id' => 'TZ06', 'slug' => 'pwani', 'lat' => -7.177, 'lon' => 38.851],
        'Rukwa' => ['id' => 'TZ15', 'slug' => 'rukwa', 'lat' => -7.979, 'lon' => 31.477],
        'Ruvuma' => ['id' => 'TZ10', 'slug' => 'ruvuma', 'lat' => -10.505, 'lon' => 36.341],
        'Shinyanga' => ['id' => 'TZ17', 'slug' => 'shinyanga', 'lat' => -3.76, 'lon' => 32.958],
        'Simiyu' => ['id' => 'TZ24', 'slug' => 'simiyu', 'lat' => -3.132, 'lon' => 34.323],
        'Singida' => ['id' => 'TZ13', 'slug' => 'singida', 'lat' => -5.668, 'lon' => 34.421],
        'Songwe' => ['id' => 'TZ26', 'slug' => 'songwe', 'lat' => -8.275, 'lon' => 32.855],
        'Tabora' => ['id' => 'TZ14', 'slug' => 'tabora', 'lat' => -5.427, 'lon' => 32.64],
        'Tanga' => ['id' => 'TZ04', 'slug' => 'tanga', 'lat' => -5.064, 'lon' => 38.12],
    ];
}

/**
 * Official districts grouped by official region name.
 *
 * @return array<string, list<string>>
 */
function bdst_official_districts(): array
{
    return [
        'Arusha' => ['Arusha', 'Arusha Urban', 'Karatu', 'Longido', 'Meru', 'Monduli', 'Ngorongoro'],
        'Dar es Salaam' => ['Ilala', 'Kinondoni', 'Temeke'],
        'Dodoma' => ['Bahi', 'Chamwino', 'Chemba', 'Dodoma Urban', 'Kondoa', 'Kongwa', 'Mpwapwa'],
        'Geita' => ['Bukombe', 'Chato', 'Geita', 'Mbogwe', 'Nyang\'hwale'],
        'Iringa' => ['Iringa', 'Iringa Urban', 'Kilolo', 'Mafinga Township Authority', 'Mufindi'],
        'Kagera' => ['Biharamulo', 'Bukoba', 'Bukoba Urban', 'Karagwe', 'Kyerwa', 'Missenyi', 'Muleba', 'Ngara'],
        'Kaskazini Pemba' => ['Micheweni', 'Wete'],
        'Kaskazini Unguja' => ['Kaskazini A', 'Kaskazini B'],
        'Katavi' => ['Mlele', 'Mpanda', 'Mpanda Urban'],
        'Kigoma' => ['Buhigwe', 'Kakonko', 'Kasulu', 'Kasulu Township Authority', 'Kibondo', 'Kigoma', 'Kigoma Urban', 'Uvinza'],
        'Kilimanjaro' => ['Hai', 'Moshi', 'Moshi Urban', 'Mwanga', 'Rombo', 'Same', 'Siha'],
        'Kusini Pemba' => ['Chake Chake', 'Mkoani'],
        'Kusini Unguja' => ['Kati', 'Kusini'],
        'Lindi' => ['Kilwa', 'Lindi', 'Lindi Urban', 'Liwale', 'Nachingwea', 'Ruangwa'],
        'Manyara' => ['Babati', 'Babati Urban', 'Hanang', 'Kiteto', 'Mbulu', 'Simanjiro'],
        'Mara' => ['Bunda', 'Butiam', 'Musoma', 'Musoma Urban', 'Rorya', 'Serengeti', 'Tarime'],
        'Mbeya' => ['Chunya', 'Kyela', 'Mbarali', 'Mbeya', 'Mbeya Urban', 'Rungwe'],
        'Mjini Magharibi' => ['Magharibi', 'Mjini'],
        'Morogoro' => ['Gairo', 'Kilombero', 'Kilosa', 'Morogoro', 'Morogoro Urban', 'Mvomero', 'Ulanga'],
        'Mtwara' => ['Masasi', 'Masasi Township Authority', 'Mtwara', 'Mtwara Urban', 'Nanyumbu', 'Newala', 'Tandahimba'],
        'Mwanza' => ['Ilemela', 'Kwimba', 'Magu', 'Misungwi', 'Nyamagana', 'Sengerema', 'Ukerewe'],
        'Njombe' => ['Ludewa', 'Makambako Township Authority', 'Makete', 'Njombe', 'Njombe Urban', 'Wanging\'ombe'],
        'Pwani' => ['Bagamoyo', 'Kibaha', 'Kibaha Urban', 'Kisarawe', 'Mafia', 'Mkuranga', 'Rufiji'],
        'Rukwa' => ['Kalambo', 'Nkasi', 'Sumbawanga', 'Sumbawanga Urban'],
        'Ruvuma' => ['Mbinga', 'Namtumbo', 'Nyasa', 'Songea', 'Songea Urban', 'Tunduru'],
        'Shinyanga' => ['Kahama', 'Kahama Township Authority', 'Kishapu', 'Shinyanga', 'Shinyanga Urban'],
        'Simiyu' => ['Bariadi', 'Busega', 'Itilima', 'Maswa', 'Meatu'],
        'Singida' => ['Ikungi', 'Iramba', 'Manyoni', 'Mkalama', 'Singida', 'Singida Urban'],
        'Songwe' => ['Ileje', 'Mbozi', 'Momba', 'Songwe', 'Tunduma'],
        'Tabora' => ['Igunga', 'Kaliua', 'Nzega', 'Sikonge', 'Tabora Urban', 'Urambo', 'Uyui'],
        'Tanga' => ['Handeni', 'Handeni Mji', 'Kilindi', 'Korogwe', 'Korogwe Township Authority', 'Lushoto', 'Mkinga', 'Muheza', 'Pangani', 'Tanga Urban'],
    ];
}

/**
 * All official region names (sorted).
 *
 * @return list<string>
 */
function bdst_region_names(): array
{
    return array_keys(bdst_official_regions());
}

/**
 * Districts belonging to a region (empty array if the region is unknown).
 *
 * @return list<string>
 */
function bdst_districts_for_region(string $region): array
{
    $all = bdst_official_districts();
    return $all[$region] ?? [];
}

/**
 * Normalise a loose region value to the official name, or null if unknown.
 * Matching ignores case and surrounding whitespace.
 */
function bdst_normalize_region(string $value): ?string
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (bdst_official_regions() as $name => $_) {
            $index[mb_strtolower(trim($name))] = $name;
        }
    }
    $key = mb_strtolower(trim($value));
    return $index[$key] ?? null;
}

/**
 * Normalise a loose district value to the official name, or null if unknown.
 * When $region is given, the district must belong to that region.
 */
function bdst_normalize_district(string $value, ?string $region = null): ?string
{
    $key = mb_strtolower(trim($value));
    $pools = $region !== null ? [bdst_districts_for_region($region)] : bdst_official_districts();
    foreach ($pools as $pool) {
        foreach ($pool as $district) {
            if (mb_strtolower(trim($district)) === $key) {
                return $district;
            }
        }
    }
    return null;
}
