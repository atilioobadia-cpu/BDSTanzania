-- ============================================================================
-- BDS Tanzania - geographic dashboard reference data + supporting index
--
-- AUTO-GENERATED FILE - do not edit by hand.
-- Generated: 2026-10-13
--
-- Source:  open-admin-data/tanzania-administrative-divisions (CC-BY-4.0)
--          Derived from OCHA COD-AB (HDX) cod-ab-tza
--
-- Safe to run once on production. All statements are guarded/idempotent.
-- Take a full backup before running. No existing data is modified or dropped.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Reference tables (official regions + districts with centroids)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS geo_regions (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code           VARCHAR(8)   NOT NULL,
  name_official  VARCHAR(60)  NOT NULL,
  slug           VARCHAR(60)  NOT NULL,
  centroid_lat   DECIMAL(9,6) NOT NULL,
  centroid_lng   DECIMAL(9,6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_geo_region_name (name_official),
  UNIQUE KEY uq_geo_region_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS geo_districts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id      INT UNSIGNED NOT NULL,
  name_official  VARCHAR(80)  NOT NULL,
  centroid_lat   DECIMAL(9,6) NULL,
  centroid_lng   DECIMAL(9,6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_geo_district (region_id, name_official),
  KEY idx_geo_district_region (region_id),
  CONSTRAINT fk_geo_district_region FOREIGN KEY (region_id) REFERENCES geo_regions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Supporting index for fast geographic aggregation on the members table
--    (guarded so re-running is harmless on MariaDB/MySQL)
-- ----------------------------------------------------------------------------
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bds_registrations'
     AND INDEX_NAME = 'idx_bds_reg_geo');
SET @ddl := IF(@idx_exists = 0,
   'ALTER TABLE bds_registrations ADD KEY idx_bds_reg_geo (region, district)',
   'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 3. Seed regions (31)
-- ----------------------------------------------------------------------------
INSERT INTO geo_regions (code, name_official, slug, centroid_lat, centroid_lng) VALUES
  ('TZ02', 'Arusha', 'arusha', -2.918, 36.089),
  ('TZ07', 'Dar es Salaam', 'dar-es-salaam', -6.872, 39.28),
  ('TZ01', 'Dodoma', 'dodoma', -5.845, 36.008),
  ('TZ25', 'Geita', 'geita', -3.353, 31.971),
  ('TZ11', 'Iringa', 'iringa', -7.964, 35.519),
  ('TZ18', 'Kagera', 'kagera', -2.172, 31.514),
  ('TZ54', 'Kaskazini Pemba', 'kaskazini-pemba', -5.036, 39.753),
  ('TZ51', 'Kaskazini Unguja', 'kaskazini-unguja', -5.881, 39.291),
  ('TZ23', 'Katavi', 'katavi', -6.373, 31.331),
  ('TZ16', 'Kigoma', 'kigoma', -4.699, 30.541),
  ('TZ03', 'Kilimanjaro', 'kilimanjaro', -3.732, 37.647),
  ('TZ55', 'Kusini Pemba', 'kusini-pemba', -5.323, 39.709),
  ('TZ52', 'Kusini Unguja', 'kusini-unguja', -6.25, 39.425),
  ('TZ08', 'Lindi', 'lindi', -9.334, 38.424),
  ('TZ21', 'Manyara', 'manyara', -4.7, 36.432),
  ('TZ20', 'Mara', 'mara', -1.762, 34.234),
  ('TZ12', 'Mbeya', 'mbeya', -8.292, 33.931),
  ('TZ53', 'Mjini Magharibi', 'mjini-magharibi', -6.174, 39.248),
  ('TZ05', 'Morogoro', 'morogoro', -7.861, 36.92),
  ('TZ09', 'Mtwara', 'mtwara', -10.771, 39.222),
  ('TZ19', 'Mwanza', 'mwanza', -2.551, 32.861),
  ('TZ22', 'Njombe', 'njombe', -9.601, 34.667),
  ('TZ06', 'Pwani', 'pwani', -7.177, 38.851),
  ('TZ15', 'Rukwa', 'rukwa', -7.979, 31.477),
  ('TZ10', 'Ruvuma', 'ruvuma', -10.505, 36.341),
  ('TZ17', 'Shinyanga', 'shinyanga', -3.76, 32.958),
  ('TZ24', 'Simiyu', 'simiyu', -3.132, 34.323),
  ('TZ13', 'Singida', 'singida', -5.668, 34.421),
  ('TZ26', 'Songwe', 'songwe', -8.275, 32.855),
  ('TZ14', 'Tabora', 'tabora', -5.427, 32.64),
  ('TZ04', 'Tanga', 'tanga', -5.064, 38.12)
ON DUPLICATE KEY UPDATE
  code = VALUES(code), slug = VALUES(slug),
  centroid_lat = VALUES(centroid_lat), centroid_lng = VALUES(centroid_lng);

-- ----------------------------------------------------------------------------
-- 4. Seed districts (170)
-- ----------------------------------------------------------------------------
INSERT INTO geo_districts (region_id, name_official, centroid_lat, centroid_lng)
SELECT gr.id, x.name_official, x.centroid_lat, x.centroid_lng
FROM (
  SELECT 'TZ02' AS region_code, 'Arusha' AS name_official, -3.359 AS centroid_lat, 36.679 AS centroid_lng
  UNION ALL
  SELECT 'TZ02' AS region_code, 'Arusha Urban' AS name_official, -3.448 AS centroid_lat, 36.675 AS centroid_lng
  UNION ALL
  SELECT 'TZ02' AS region_code, 'Karatu' AS name_official, -3.487 AS centroid_lat, 35.362 AS centroid_lng
  UNION ALL
  SELECT 'TZ02' AS region_code, 'Longido' AS name_official, -2.642 AS centroid_lat, 36.658 AS centroid_lng
  UNION ALL
  SELECT 'TZ02' AS region_code, 'Meru' AS name_official, -3.323 AS centroid_lat, 36.906 AS centroid_lng
  UNION ALL
  SELECT 'TZ02' AS region_code, 'Monduli' AS name_official, -3.452 AS centroid_lat, 36.235 AS centroid_lng
  UNION ALL
  SELECT 'TZ02' AS region_code, 'Ngorongoro' AS name_official, -2.654 AS centroid_lat, 35.505 AS centroid_lng
  UNION ALL
  SELECT 'TZ07' AS region_code, 'Ilala' AS name_official, -6.941 AS centroid_lat, 39.161 AS centroid_lng
  UNION ALL
  SELECT 'TZ07' AS region_code, 'Kinondoni' AS name_official, -6.719 AS centroid_lat, 39.148 AS centroid_lng
  UNION ALL
  SELECT 'TZ07' AS region_code, 'Temeke' AS name_official, -6.996 AS centroid_lat, 39.372 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Bahi' AS name_official, -6.167 AS centroid_lat, 35.567 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Chamwino' AS name_official, -6.232 AS centroid_lat, 35.678 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Chemba' AS name_official, -5.27 AS centroid_lat, 35.695 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Dodoma Urban' AS name_official, -6.146 AS centroid_lat, 35.807 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Kondoa' AS name_official, -4.78 AS centroid_lat, 35.887 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Kongwa' AS name_official, -5.974 AS centroid_lat, 36.607 AS centroid_lng
  UNION ALL
  SELECT 'TZ01' AS region_code, 'Mpwapwa' AS name_official, -6.763 AS centroid_lat, 36.262 AS centroid_lng
  UNION ALL
  SELECT 'TZ25' AS region_code, 'Bukombe' AS name_official, -3.788 AS centroid_lat, 31.654 AS centroid_lng
  UNION ALL
  SELECT 'TZ25' AS region_code, 'Chato' AS name_official, -2.845 AS centroid_lat, 31.667 AS centroid_lng
  UNION ALL
  SELECT 'TZ25' AS region_code, 'Geita' AS name_official, -2.782 AS centroid_lat, 32.179 AS centroid_lng
  UNION ALL
  SELECT 'TZ25' AS region_code, 'Mbogwe' AS name_official, -3.47 AS centroid_lat, 32.127 AS centroid_lng
  UNION ALL
  SELECT 'TZ25' AS region_code, 'Nyang''hwale' AS name_official, -3.178 AS centroid_lat, 32.588 AS centroid_lng
  UNION ALL
  SELECT 'TZ11' AS region_code, 'Iringa' AS name_official, -7.564 AS centroid_lat, 35.197 AS centroid_lng
  UNION ALL
  SELECT 'TZ11' AS region_code, 'Iringa Urban' AS name_official, -7.748 AS centroid_lat, 35.702 AS centroid_lng
  UNION ALL
  SELECT 'TZ11' AS region_code, 'Kilolo' AS name_official, -7.785 AS centroid_lat, 36.216 AS centroid_lng
  UNION ALL
  SELECT 'TZ11' AS region_code, 'Mafinga Township Authority' AS name_official, -8.362 AS centroid_lat, 35.273 AS centroid_lng
  UNION ALL
  SELECT 'TZ11' AS region_code, 'Mufindi' AS name_official, -8.57 AS centroid_lat, 35.333 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Biharamulo' AS name_official, -2.772 AS centroid_lat, 31.26 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Bukoba' AS name_official, -1.353 AS centroid_lat, 31.604 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Bukoba Urban' AS name_official, -1.321 AS centroid_lat, 31.817 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Karagwe' AS name_official, -1.721 AS centroid_lat, 31.084 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Kyerwa' AS name_official, -1.299 AS centroid_lat, 30.78 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Missenyi' AS name_official, -1.204 AS centroid_lat, 31.305 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Muleba' AS name_official, -1.923 AS centroid_lat, 31.903 AS centroid_lng
  UNION ALL
  SELECT 'TZ18' AS region_code, 'Ngara' AS name_official, -2.626 AS centroid_lat, 30.738 AS centroid_lng
  UNION ALL
  SELECT 'TZ54' AS region_code, 'Micheweni' AS name_official, -4.957 AS centroid_lat, 39.775 AS centroid_lng
  UNION ALL
  SELECT 'TZ54' AS region_code, 'Wete' AS name_official, -5.092 AS centroid_lat, 39.752 AS centroid_lng
  UNION ALL
  SELECT 'TZ51' AS region_code, 'Kaskazini A' AS name_official, -5.856 AS centroid_lat, 39.299 AS centroid_lng
  UNION ALL
  SELECT 'TZ51' AS region_code, 'Kaskazini B' AS name_official, -5.966 AS centroid_lat, 39.291 AS centroid_lng
  UNION ALL
  SELECT 'TZ23' AS region_code, 'Mlele' AS name_official, -6.61 AS centroid_lat, 31.843 AS centroid_lng
  UNION ALL
  SELECT 'TZ23' AS region_code, 'Mpanda' AS name_official, -6.035 AS centroid_lat, 30.545 AS centroid_lng
  UNION ALL
  SELECT 'TZ23' AS region_code, 'Mpanda Urban' AS name_official, -6.409 AS centroid_lat, 30.989 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Buhigwe' AS name_official, -4.477 AS centroid_lat, 29.986 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Kakonko' AS name_official, -3.21 AS centroid_lat, 30.849 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Kasulu' AS name_official, -4.434 AS centroid_lat, 30.41 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Kasulu Township Authority' AS name_official, -4.582 AS centroid_lat, 30.09 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Kibondo' AS name_official, -4.184 AS centroid_lat, 30.94 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Kigoma' AS name_official, -4.701 AS centroid_lat, 29.794 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Kigoma Urban' AS name_official, -4.894 AS centroid_lat, 29.66 AS centroid_lng
  UNION ALL
  SELECT 'TZ16' AS region_code, 'Uvinza' AS name_official, -5.704 AS centroid_lat, 30.48 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Hai' AS name_official, -3.24 AS centroid_lat, 37.187 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Moshi' AS name_official, -3.37 AS centroid_lat, 37.432 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Moshi Urban' AS name_official, -3.346 AS centroid_lat, 37.343 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Mwanga' AS name_official, -3.684 AS centroid_lat, 37.681 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Rombo' AS name_official, -3.126 AS centroid_lat, 37.48 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Same' AS name_official, -4.203 AS centroid_lat, 37.932 AS centroid_lng
  UNION ALL
  SELECT 'TZ03' AS region_code, 'Siha' AS name_official, -3.108 AS centroid_lat, 37.077 AS centroid_lng
  UNION ALL
  SELECT 'TZ55' AS region_code, 'Chake Chake' AS name_official, -5.247 AS centroid_lat, 39.752 AS centroid_lng
  UNION ALL
  SELECT 'TZ55' AS region_code, 'Mkoani' AS name_official, -5.384 AS centroid_lat, 39.686 AS centroid_lng
  UNION ALL
  SELECT 'TZ52' AS region_code, 'Kati' AS name_official, -6.207 AS centroid_lat, 39.399 AS centroid_lng
  UNION ALL
  SELECT 'TZ52' AS region_code, 'Kusini' AS name_official, -6.327 AS centroid_lat, 39.484 AS centroid_lng
  UNION ALL
  SELECT 'TZ08' AS region_code, 'Kilwa' AS name_official, -9.089 AS centroid_lat, 39.05 AS centroid_lng
  UNION ALL
  SELECT 'TZ08' AS region_code, 'Lindi' AS name_official, -10.037 AS centroid_lat, 39.499 AS centroid_lng
  UNION ALL
  SELECT 'TZ08' AS region_code, 'Lindi Urban' AS name_official, -9.961 AS centroid_lat, 39.598 AS centroid_lng
  UNION ALL
  SELECT 'TZ08' AS region_code, 'Liwale' AS name_official, -9.186 AS centroid_lat, 37.793 AS centroid_lng
  UNION ALL
  SELECT 'TZ08' AS region_code, 'Nachingwea' AS name_official, -10.327 AS centroid_lat, 38.219 AS centroid_lng
  UNION ALL
  SELECT 'TZ08' AS region_code, 'Ruangwa' AS name_official, -10.09 AS centroid_lat, 38.954 AS centroid_lng
  UNION ALL
  SELECT 'TZ21' AS region_code, 'Babati' AS name_official, -4.024 AS centroid_lat, 35.75 AS centroid_lng
  UNION ALL
  SELECT 'TZ21' AS region_code, 'Babati Urban' AS name_official, -4.214 AS centroid_lat, 35.727 AS centroid_lng
  UNION ALL
  SELECT 'TZ21' AS region_code, 'Hanang' AS name_official, -4.56 AS centroid_lat, 35.265 AS centroid_lng
  UNION ALL
  SELECT 'TZ21' AS region_code, 'Kiteto' AS name_official, -5.194 AS centroid_lat, 36.817 AS centroid_lng
  UNION ALL
  SELECT 'TZ21' AS region_code, 'Mbulu' AS name_official, -3.94 AS centroid_lat, 35.295 AS centroid_lng
  UNION ALL
  SELECT 'TZ21' AS region_code, 'Simanjiro' AS name_official, -4.321 AS centroid_lat, 37.145 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Bunda' AS name_official, -2.036 AS centroid_lat, 33.763 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Butiam' AS name_official, -1.67 AS centroid_lat, 33.96 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Musoma' AS name_official, -1.825 AS centroid_lat, 33.532 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Musoma Urban' AS name_official, -1.521 AS centroid_lat, 33.796 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Rorya' AS name_official, -1.299 AS centroid_lat, 34.07 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Serengeti' AS name_official, -1.957 AS centroid_lat, 34.741 AS centroid_lng
  UNION ALL
  SELECT 'TZ20' AS region_code, 'Tarime' AS name_official, -1.396 AS centroid_lat, 34.474 AS centroid_lng
  UNION ALL
  SELECT 'TZ12' AS region_code, 'Chunya' AS name_official, -7.806 AS centroid_lat, 33.582 AS centroid_lng
  UNION ALL
  SELECT 'TZ12' AS region_code, 'Kyela' AS name_official, -9.544 AS centroid_lat, 33.879 AS centroid_lng
  UNION ALL
  SELECT 'TZ12' AS region_code, 'Mbarali' AS name_official, -8.278 AS centroid_lat, 34.259 AS centroid_lng
  UNION ALL
  SELECT 'TZ12' AS region_code, 'Mbeya' AS name_official, -8.971 AS centroid_lat, 33.419 AS centroid_lng
  UNION ALL
  SELECT 'TZ12' AS region_code, 'Mbeya Urban' AS name_official, -8.905 AS centroid_lat, 33.475 AS centroid_lng
  UNION ALL
  SELECT 'TZ12' AS region_code, 'Rungwe' AS name_official, -9.236 AS centroid_lat, 33.706 AS centroid_lng
  UNION ALL
  SELECT 'TZ53' AS region_code, 'Magharibi' AS name_official, -6.174 AS centroid_lat, 39.255 AS centroid_lng
  UNION ALL
  SELECT 'TZ53' AS region_code, 'Mjini' AS name_official, -6.167 AS centroid_lat, 39.206 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Gairo' AS name_official, -6.228 AS centroid_lat, 37.055 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Kilombero' AS name_official, -8.562 AS centroid_lat, 36.562 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Kilosa' AS name_official, -6.957 AS centroid_lat, 36.988 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Morogoro' AS name_official, -7.052 AS centroid_lat, 37.826 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Morogoro Urban' AS name_official, -6.786 AS centroid_lat, 37.667 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Mvomero' AS name_official, -6.599 AS centroid_lat, 37.634 AS centroid_lng
  UNION ALL
  SELECT 'TZ05' AS region_code, 'Ulanga' AS name_official, -9.043 AS centroid_lat, 36.555 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Masasi' AS name_official, -10.762 AS centroid_lat, 38.821 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Masasi Township Authority' AS name_official, -10.751 AS centroid_lat, 38.879 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Mtwara' AS name_official, -10.477 AS centroid_lat, 40.045 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Mtwara Urban' AS name_official, -10.316 AS centroid_lat, 40.148 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Nanyumbu' AS name_official, -11.051 AS centroid_lat, 38.443 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Newala' AS name_official, -10.7 AS centroid_lat, 39.261 AS centroid_lng
  UNION ALL
  SELECT 'TZ09' AS region_code, 'Tandahimba' AS name_official, -10.663 AS centroid_lat, 39.619 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Ilemela' AS name_official, -2.41 AS centroid_lat, 33.015 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Kwimba' AS name_official, -3.038 AS centroid_lat, 33.267 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Magu' AS name_official, -2.609 AS centroid_lat, 33.401 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Misungwi' AS name_official, -2.954 AS centroid_lat, 33.029 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Nyamagana' AS name_official, -2.576 AS centroid_lat, 32.944 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Sengerema' AS name_official, -2.582 AS centroid_lat, 32.405 AS centroid_lng
  UNION ALL
  SELECT 'TZ19' AS region_code, 'Ukerewe' AS name_official, -1.921 AS centroid_lat, 33.104 AS centroid_lng
  UNION ALL
  SELECT 'TZ22' AS region_code, 'Ludewa' AS name_official, -10.023 AS centroid_lat, 34.646 AS centroid_lng
  UNION ALL
  SELECT 'TZ22' AS region_code, 'Makambako Township Authority' AS name_official, -8.902 AS centroid_lat, 34.916 AS centroid_lng
  UNION ALL
  SELECT 'TZ22' AS region_code, 'Makete' AS name_official, -9.274 AS centroid_lat, 34.147 AS centroid_lng
  UNION ALL
  SELECT 'TZ22' AS region_code, 'Njombe' AS name_official, -9.284 AS centroid_lat, 35.138 AS centroid_lng
  UNION ALL
  SELECT 'TZ22' AS region_code, 'Njombe Urban' AS name_official, -9.468 AS centroid_lat, 34.849 AS centroid_lng
  UNION ALL
  SELECT 'TZ22' AS region_code, 'Wanging''ombe' AS name_official, -9.053 AS centroid_lat, 34.584 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Bagamoyo' AS name_official, -6.309 AS centroid_lat, 38.46 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Kibaha' AS name_official, -6.806 AS centroid_lat, 38.61 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Kibaha Urban' AS name_official, -6.691 AS centroid_lat, 38.913 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Kisarawe' AS name_official, -7.182 AS centroid_lat, 38.692 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Mafia' AS name_official, -7.86 AS centroid_lat, 39.706 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Mkuranga' AS name_official, -7.246 AS centroid_lat, 39.136 AS centroid_lng
  UNION ALL
  SELECT 'TZ06' AS region_code, 'Rufiji' AS name_official, -7.991 AS centroid_lat, 38.62 AS centroid_lng
  UNION ALL
  SELECT 'TZ15' AS region_code, 'Kalambo' AS name_official, -8.538 AS centroid_lat, 31.581 AS centroid_lng
  UNION ALL
  SELECT 'TZ15' AS region_code, 'Nkasi' AS name_official, -7.587 AS centroid_lat, 31.011 AS centroid_lng
  UNION ALL
  SELECT 'TZ15' AS region_code, 'Sumbawanga' AS name_official, -8.085 AS centroid_lat, 31.915 AS centroid_lng
  UNION ALL
  SELECT 'TZ15' AS region_code, 'Sumbawanga Urban' AS name_official, -7.975 AS centroid_lat, 31.629 AS centroid_lng
  UNION ALL
  SELECT 'TZ10' AS region_code, 'Mbinga' AS name_official, -10.703 AS centroid_lat, 35.018 AS centroid_lng
  UNION ALL
  SELECT 'TZ10' AS region_code, 'Namtumbo' AS name_official, -10.673 AS centroid_lat, 36.393 AS centroid_lng
  UNION ALL
  SELECT 'TZ10' AS region_code, 'Nyasa' AS name_official, -11.007 AS centroid_lat, 35 AS centroid_lng
  UNION ALL
  SELECT 'TZ10' AS region_code, 'Songea' AS name_official, -10.415 AS centroid_lat, 35.526 AS centroid_lng
  UNION ALL
  SELECT 'TZ10' AS region_code, 'Songea Urban' AS name_official, -10.637 AS centroid_lat, 35.583 AS centroid_lng
  UNION ALL
  SELECT 'TZ10' AS region_code, 'Tunduru' AS name_official, -10.895 AS centroid_lat, 37.322 AS centroid_lng
  UNION ALL
  SELECT 'TZ17' AS region_code, 'Kahama' AS name_official, -3.76 AS centroid_lat, 32.341 AS centroid_lng
  UNION ALL
  SELECT 'TZ17' AS region_code, 'Kahama Township Authority' AS name_official, -3.838 AS centroid_lat, 32.609 AS centroid_lng
  UNION ALL
  SELECT 'TZ17' AS region_code, 'Kishapu' AS name_official, -3.68 AS centroid_lat, 33.693 AS centroid_lng
  UNION ALL
  SELECT 'TZ17' AS region_code, 'Shinyanga' AS name_official, -3.598 AS centroid_lat, 33.107 AS centroid_lng
  UNION ALL
  SELECT 'TZ17' AS region_code, 'Shinyanga Urban' AS name_official, -3.624 AS centroid_lat, 33.449 AS centroid_lng
  UNION ALL
  SELECT 'TZ24' AS region_code, 'Bariadi' AS name_official, -2.601 AS centroid_lat, 34.423 AS centroid_lng
  UNION ALL
  SELECT 'TZ24' AS region_code, 'Busega' AS name_official, -2.377 AS centroid_lat, 33.761 AS centroid_lng
  UNION ALL
  SELECT 'TZ24' AS region_code, 'Itilima' AS name_official, -2.888 AS centroid_lat, 34.423 AS centroid_lng
  UNION ALL
  SELECT 'TZ24' AS region_code, 'Maswa' AS name_official, -3.202 AS centroid_lat, 33.786 AS centroid_lng
  UNION ALL
  SELECT 'TZ24' AS region_code, 'Meatu' AS name_official, -3.507 AS centroid_lat, 34.468 AS centroid_lng
  UNION ALL
  SELECT 'TZ13' AS region_code, 'Ikungi' AS name_official, -5.124 AS centroid_lat, 34.55 AS centroid_lng
  UNION ALL
  SELECT 'TZ13' AS region_code, 'Iramba' AS name_official, -4.386 AS centroid_lat, 34.243 AS centroid_lng
  UNION ALL
  SELECT 'TZ13' AS region_code, 'Manyoni' AS name_official, -6.406 AS centroid_lat, 34.421 AS centroid_lng
  UNION ALL
  SELECT 'TZ13' AS region_code, 'Mkalama' AS name_official, -4.212 AS centroid_lat, 34.747 AS centroid_lng
  UNION ALL
  SELECT 'TZ13' AS region_code, 'Singida' AS name_official, -4.722 AS centroid_lat, 34.97 AS centroid_lng
  UNION ALL
  SELECT 'TZ13' AS region_code, 'Singida Urban' AS name_official, -4.814 AS centroid_lat, 34.718 AS centroid_lng
  UNION ALL
  SELECT 'TZ26' AS region_code, 'Ileje' AS name_official, -9.39 AS centroid_lat, 33.282 AS centroid_lng
  UNION ALL
  SELECT 'TZ26' AS region_code, 'Mbozi' AS name_official, -8.972 AS centroid_lat, 32.884 AS centroid_lng
  UNION ALL
  SELECT 'TZ26' AS region_code, 'Momba' AS name_official, -8.789 AS centroid_lat, 32.466 AS centroid_lng
  UNION ALL
  SELECT 'TZ26' AS region_code, 'Songwe' AS name_official, -7.825 AS centroid_lat, 32.637 AS centroid_lng
  UNION ALL
  SELECT 'TZ26' AS region_code, 'Tunduma' AS name_official, -9.303 AS centroid_lat, 32.802 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Igunga' AS name_official, -4.378 AS centroid_lat, 33.758 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Kaliua' AS name_official, -4.945 AS centroid_lat, 31.766 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Nzega' AS name_official, -4.4 AS centroid_lat, 32.977 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Sikonge' AS name_official, -6.086 AS centroid_lat, 33.069 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Tabora Urban' AS name_official, -4.941 AS centroid_lat, 32.813 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Urambo' AS name_official, -5.233 AS centroid_lat, 32.167 AS centroid_lng
  UNION ALL
  SELECT 'TZ14' AS region_code, 'Uyui' AS name_official, -4.94 AS centroid_lat, 33.219 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Handeni' AS name_official, -5.494 AS centroid_lat, 38.213 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Handeni Mji' AS name_official, -5.441 AS centroid_lat, 37.924 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Kilindi' AS name_official, -5.503 AS centroid_lat, 37.498 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Korogwe' AS name_official, -4.931 AS centroid_lat, 38.335 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Korogwe Township Authority' AS name_official, -5.145 AS centroid_lat, 38.476 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Lushoto' AS name_official, -4.546 AS centroid_lat, 38.438 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Mkinga' AS name_official, -4.697 AS centroid_lat, 38.941 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Muheza' AS name_official, -5.179 AS centroid_lat, 38.824 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Pangani' AS name_official, -5.615 AS centroid_lat, 38.843 AS centroid_lng
  UNION ALL
  SELECT 'TZ04' AS region_code, 'Tanga Urban' AS name_official, -5.126 AS centroid_lat, 39.017 AS centroid_lng
) AS x
JOIN geo_regions gr ON gr.code = x.region_code
ON DUPLICATE KEY UPDATE
  centroid_lat = VALUES(centroid_lat), centroid_lng = VALUES(centroid_lng);
