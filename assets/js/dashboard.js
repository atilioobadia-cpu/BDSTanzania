/* ==========================================================================
   BDS Tanzania - public member map (/dashboard)
   Builds the Leaflet choropleth + green location-pin markers from the JSON
   endpoints and GeoJSON boundaries, drives the ranked region list and the
   district drill-down, and powers the small charts (trend, donut, coverage),
   the auto-refresh and the CSV export. This file contains no figures.

   Markers: proportional SVG teardrop pins (green = membership) instead of
   plain dots, so a region reads as a place on the map. Every pin sits at an
   administrative reference point - never at a member's address.
   ========================================================================== */

(function () {
    'use strict';

    var configEl = document.getElementById('bdst-dashboard-config');
    var mapEl = document.getElementById('bdst-map');
    if (!configEl || !mapEl) {
        return;
    }

    var config;
    try {
        config = JSON.parse(configEl.textContent);
    } catch (err) {
        return;
    }

    var statusEl       = document.getElementById('bdst-map-status');
    var fallbackEl     = document.getElementById('bdst-map-fallback');
    var legendEl       = document.getElementById('bdst-legend');
    var pinKeyEl       = document.getElementById('bdst-pin-key');
    var titleEl        = document.getElementById('bdst-detail-title');
    var noteEl         = document.getElementById('bdst-detail-note');
    var regionViewEl   = document.getElementById('bdst-region-view');
    var districtViewEl = document.getElementById('bdst-district-view');
    var districtListEl = document.getElementById('bdst-detail-list');
    var regionSelect   = document.getElementById('bdst-region-select');
    var filterEl       = document.getElementById('bdst-region-filter');
    var resetBtn       = document.getElementById('bdst-detail-reset');
    var zeroToggle     = document.getElementById('bdst-zero-toggle');
    var zeroBox        = document.getElementById('bdst-rank-zero');
    var rankListEl     = document.getElementById('bdst-rank-list');
    var refreshBtn     = document.getElementById('bdst-refresh');
    var exportBtn      = document.getElementById('bdst-export-csv');
    var updatedEl      = document.getElementById('bdst-updated');
    var tableUpdatedEl = document.getElementById('bdst-table-updated');
    var donutEl        = document.getElementById('bdst-donut');
    var donutLegendEl  = document.getElementById('bdst-donut-legend');
    var donutNoteEl    = document.getElementById('bdst-donut-note');
    var trendChartEl   = document.getElementById('bdst-trend-chart');
    var trendBarsEl    = document.getElementById('bdst-trend-bars');
    var trendEmptyEl   = document.getElementById('bdst-trend-empty');
    var trendDataEl    = document.getElementById('bdst-trend-data');
    var trendRowsEl    = document.getElementById('bdst-trend-rows');
    var trendRangeEl   = document.getElementById('bdst-trend-range');
    var coverageEl     = document.querySelector('.bdst-coverage');

    // Default copy is read from the server-rendered markup so the two never
    // drift apart.
    var TITLE_REGIONS = titleEl ? titleEl.textContent : 'Regions by membership';
    var NOTE_REGIONS  = noteEl ? noteEl.textContent : '';

    var zeroCount = zeroBox ? zeroBox.querySelectorAll('.bdst-row').length : 0;

    // Cached payloads so auto-refresh can skip redundant re-renders and the
    // CSV export never needs a fresh fetch.
    var lastRegionsPayload = null;
    var lastSummary        = null;
    var lastRegionsJson    = '';
    var lastSummaryJson    = '';
    var refreshing         = false;
    var refreshTimer       = null;
    var trendPeriod        = 'monthly';
    var satelliteOn        = false;

    function setStatus(message) {
        if (statusEl) {
            statusEl.textContent = message || '';
        }
    }

    function setText(el, html) {
        if (el) {
            el.innerHTML = html;
        }
    }

    function cssVar(name, fallback) {
        var value = window.getComputedStyle(document.documentElement).getPropertyValue(name);
        value = (value || '').trim();
        return value || fallback;
    }

    var BAND_COLOURS = {
        withheld: cssVar('--bdst-band-withheld', '#dfe6ea'),
        zero: cssVar('--bdst-band-zero', '#f3f8fa'),
        lt_min: cssVar('--bdst-band-lt-min', '#d7ebf5'),
        low: cssVar('--bdst-band-low', '#cfe7f2'),
        mid: cssVar('--bdst-band-mid', '#8ec6de'),
        high: cssVar('--bdst-band-high', '#4a9fc4')
    };

    var BUBBLE = cssVar('--bdst-bubble', '#16a34a');

    // Palette for the donut chart - a green family that stays on brand.
    var DONUT_COLOURS = ['#0f7b3d', '#16a34a', '#3db96d', '#6fce94', '#a5dfba', '#d3eedd'];

    function colourFor(band) {
        return BAND_COLOURS[band] || BAND_COLOURS.withheld;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmt(value) {
        return Number(value).toLocaleString('en-US');
    }

    function memberLabel(count) {
        var n = Number(count);
        return n === 1 ? '1 member' : fmt(n) + ' members';
    }

    function getJson(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        });
    }

    function showFallback(message) {
        if (mapEl) {
            mapEl.hidden = true;
        }
        if (fallbackEl) {
            fallbackEl.hidden = false;
        }
        if (legendEl) {
            legendEl.hidden = true;
        }
        if (pinKeyEl) {
            pinKeyEl.hidden = true;
        }
        setStatus(message);
    }

    if (typeof window.L === 'undefined') {
        showFallback('The interactive map could not start. The ranked list and the table below carry the same figures.');
        return;
    }

    /* ----------------------------------------------------------------------
       Map shell + base layers (Street / Satellite)
       ---------------------------------------------------------------------- */

    // Reveal the map box (see the .is-ready rule in dashboard.css) before
    // Leaflet measures it, so the collapsed no-JS state does not leave
    // Leaflet with a zero-sized container.
    mapEl.classList.add('is-ready');

    var map = window.L.map(mapEl, {
        center: [-6.4, 34.9],
        zoom: 6,
        minZoom: 4,
        maxZoom: 13,
        scrollWheelZoom: false,
        attributionControl: true
    });

    // Pins live in their own pane above the choropleth so a highlighted
    // region can never hide them.
    map.createPane('bdstBubbles');
    map.getPane('bdstBubbles').style.zIndex = 650;

    var baseLayers = {};
    var tiles = config.tiles || {};
    if (tiles.street) {
        baseLayers.Street = window.L.tileLayer(tiles.street.url, {
            attribution: tiles.street.attribution || '',
            maxZoom: 19
        });
    }
    if (tiles.satellite) {
        baseLayers.Satellite = window.L.tileLayer(tiles.satellite.url, {
            attribution: tiles.satellite.attribution || '',
            maxZoom: 19
        });
    }

    // Start on the street map (the shading is legible there); the satellite
    // view is one click away.
    var defaultBase = baseLayers.Street || baseLayers.Satellite;
    if (!defaultBase) {
        showFallback('The map tiles could not be configured.');
        return;
    }
    defaultBase.addTo(map);

    // Choropleth lives in a persistent layer so the layer switcher can keep
    // referencing it across auto-refresh re-renders.
    var regionLayer = window.L.geoJSON(null, {
        style: function (feature) {
            var info = lookup[feature.properties.code];
            return {
                color: '#ffffff',
                weight: 1,
                fillColor: colourFor(info ? info.band : 'withheld'),
                fillOpacity: satelliteOn ? 0.3 : 0.85
            };
        },
        onEachFeature: function (feature, layer) {
            var code = feature.properties.code;
            var info = lookup[code] || {
                name: feature.properties.name,
                band: 'withheld',
                label: 'Not published'
            };

            var ref = regionRefs[code] || (regionRefs[code] = {});
            ref.polygon = layer;

            layer.bindTooltip(
                '<strong>' + escapeHtml(info.name) + '</strong><br>' + escapeHtml(info.label),
                { sticky: true }
            );
            layer.on('click', function () {
                loadRegion(code);
            });
            layer.on('mouseover', function () {
                if (selectedCode !== code) {
                    layer.setStyle({ weight: 2 });
                }
            });
            layer.on('mouseout', function () {
                layer.setStyle({
                    weight: selectedCode === code ? 3 : 1,
                    color: selectedCode === code ? '#0c1e21' : '#ffffff'
                });
            });
        }
    });

    // Choropleth is visible from the start; the layer switcher can still
    // hide it (auto-refresh re-renders keep referencing the same layer).
    regionLayer.addTo(map);

    var overlays = { 'Regional shading': regionLayer };
    var layerControl = window.L.control.layers(baseLayers, overlays, {
        position: 'topright',
        collapsed: window.matchMedia('(max-width: 767px)').matches
    }).addTo(map);

    map.on('baselayerchange', function () {
        satelliteOn = !!(baseLayers.Satellite && typeof baseLayers.Street === 'undefined'
            ? false
            : map.hasLayer(baseLayers.Satellite));
        if (regionLayer && Object.keys(regionRefs).length) {
            regionLayer.setStyle({ fillOpacity: satelliteOn ? 0.3 : 0.85 });
        }
    });

    map.zoomControl.setPosition('topright');

    var districtLayer = null;
    var bubbleGroup = null;
    var lookup = {};
    var regionRefs = {};
    var selectedCode = '';
    var districtsGeoPromise = null;
    var fitted = false;

    function districtsGeo() {
        if (!districtsGeoPromise) {
            districtsGeoPromise = fetch(config.districtsGeo).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            });
        }
        return districtsGeoPromise;
    }

    /* ----------------------------------------------------------------------
       Green location pins (proportional SVG teardrops)
       ---------------------------------------------------------------------- */

    // The pin is a 24x32-viewbox teardrop; its tip is the anchor so the pin
    // stands exactly on the region reference point.
    var PIN_PATH = 'M12 0 C5.4 0 0 5.4 0 12 c0 5.6 4.2 11.2 8.3 15.5 L12 32 l3.7 -4.5 C19.8 23.2 24 17.6 24 12 C24 5.4 18.6 0 12 0 Z';

    // Width grows with the square root of the count (area-proportional), so a
    // region with four times the members reads as a pin with twice the area.
    function pinSizeFor(count, max) {
        if (!count || count <= 0) {
            return 0;
        }
        return 20 + 32 * Math.sqrt(count / Math.max(1, max));
    }

    function pinSvg(size) {
        var w = Math.max(10, size);
        var h = w * (32 / 24);
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + w.toFixed(1) +
            '" height="' + h.toFixed(1) + '" viewBox="0 0 24 32" aria-hidden="true" focusable="false">' +
            '<path d="' + PIN_PATH + '" fill="' + BUBBLE + '" stroke="#ffffff" stroke-width="1.4"/>' +
            '<circle cx="12" cy="12.5" r="4.6" fill="#ffffff" fill-opacity="0.92"/>' +
            '</svg>';
    }

    function setPinState(marker, on) {
        if (!marker || !marker.getElement) {
            return;
        }
        var el = marker.getElement();
        if (el) {
            el.classList.toggle('is-active', !!on);
        }
    }

    function renderPins(regions, max) {
        if (bubbleGroup) {
            map.removeLayer(bubbleGroup);
        }
        bubbleGroup = window.L.layerGroup().addTo(map);

        regions.forEach(function (region) {
            var count = region.count;
            if (!count || count <= 0) {
                return;
            }
            var size = pinSizeFor(count, max);
            var w = Math.max(10, size);
            var h = w * (32 / 24);
            var marker = window.L.marker([region.lat, region.lon], {
                pane: 'bdstBubbles',
                icon: window.L.divIcon({
                    className: 'bdst-pin',
                    html: pinSvg(size),
                    iconSize: [w, h],
                    iconAnchor: [w / 2, h - 0.5]
                })
            });

            marker.bindTooltip(
                '<strong>' + escapeHtml(region.name) + '</strong><br>' + escapeHtml(memberLabel(count)),
                { sticky: true }
            );
            marker.on('mouseover', function () {
                var el = marker.getElement();
                if (el) {
                    el.classList.add('is-hover');
                }
            });
            marker.on('mouseout', function () {
                var el = marker.getElement();
                if (el) {
                    el.classList.remove('is-hover');
                }
            });
            marker.on('click', function () {
                loadRegion(region.code);
            });

            var ref = regionRefs[region.code] || (regionRefs[region.code] = {});
            ref.marker = marker;
            ref.radius = size;

            marker.addTo(bubbleGroup);
        });
    }

    function renderPinKey(max) {
        if (!pinKeyEl || !max || max <= 0) {
            return;
        }
        var values = [];
        [1, Math.max(2, Math.round(max / 2)), max].forEach(function (value) {
            if (values.indexOf(value) === -1) {
                values.push(value);
            }
        });

        var html = '<span class="bdst-key-title">Members per region</span>';
        values.forEach(function (value) {
            var size = Math.min(pinSizeFor(value, max), 44);
            html += '<span class="bdst-key-item">' +
                '<span class="bdst-key-pin">' + pinSvg(size) + '</span>' +
                '<span class="bdst-key-label">' + escapeHtml(value) + '</span>' +
                '</span>';
        });
        pinKeyEl.innerHTML = html;
        pinKeyEl.hidden = false;
    }

    /* ----------------------------------------------------------------------
       Legend
       ---------------------------------------------------------------------- */

    function renderLegend(entries) {
        if (!legendEl || !Array.isArray(entries) || !entries.length) {
            return;
        }
        legendEl.innerHTML = '';
        entries.forEach(function (entry) {
            var li = document.createElement('li');
            li.innerHTML = '<span class="bdst-swatch bdst-swatch--' + escapeHtml(entry.key) + '"></span>' +
                '<span>' + escapeHtml(entry.label) + '</span>';
            legendEl.appendChild(li);
        });
    }

    /* ----------------------------------------------------------------------
       Selection + district panel
       ---------------------------------------------------------------------- */

    function clearDistrictLayer() {
        if (districtLayer) {
            map.removeLayer(districtLayer);
            districtLayer = null;
        }
    }

    function highlight(code) {
        selectedCode = code || '';
        Object.keys(regionRefs).forEach(function (key) {
            var ref = regionRefs[key];
            var on = key === selectedCode;
            if (ref.polygon) {
                ref.polygon.setStyle({ weight: on ? 3 : 1, color: on ? '#0c1e21' : '#ffffff' });
            }
            if (ref.marker) {
                setPinState(ref.marker, on);
            }
        });
    }

    function setDistrictMode() {
        if (regionViewEl) {
            regionViewEl.hidden = true;
        }
        if (districtViewEl) {
            districtViewEl.hidden = false;
        }
        if (filterEl) {
            filterEl.hidden = true;
        }
    }

    function setRegionMode() {
        if (regionViewEl) {
            regionViewEl.hidden = false;
        }
        if (districtViewEl) {
            districtViewEl.hidden = true;
        }
        if (filterEl) {
            filterEl.hidden = false;
        }
    }

    function markActiveRow(code) {
        var rows = document.querySelectorAll('.bdst-row[data-code]');
        Array.prototype.forEach.call(rows, function (row) {
            if (row.getAttribute('data-code') === code) {
                row.classList.add('is-active');
            } else {
                row.classList.remove('is-active');
            }
        });
    }

    function renderDistrictRows(region) {
        if (!districtListEl) {
            return;
        }
        districtListEl.innerHTML = '';
        var items = region.items || [];

        if (!items.length) {
            var empty = document.createElement('li');
            empty.className = 'bdst-empty';
            empty.textContent = region.total
                ? 'District breakdown is not available for this region.'
                : 'No approved members recorded in this region yet.';
            districtListEl.appendChild(empty);
            return;
        }

        var max = 0;
        items.forEach(function (item) {
            if (item.count && item.count > max) {
                max = item.count;
            }
        });

        items.forEach(function (item) {
            var count = item.count === null ? 0 : item.count;
            var pct = max > 0 ? Math.max(5, Math.round(count * 100 / max)) : 0;
            var li = document.createElement('li');
            li.className = 'bdst-row';
            li.innerHTML =
                '<span class="bdst-row-inner">' +
                '<span class="bdst-row-body">' +
                '<span class="bdst-row-name">' + escapeHtml(item.name) + '</span>' +
                '<span class="bdst-row-track"><span class="bdst-row-fill" style="width:' + pct + '%"></span></span>' +
                '</span>' +
                '<span class="bdst-row-count">' + escapeHtml(item.count === null ? item.label : String(item.count)) + '</span>' +
                '</span>';
            districtListEl.appendChild(li);
        });
    }

    function resetDetail() {
        clearDistrictLayer();
        highlight('');
        markActiveRow('');
        if (regionSelect) {
            regionSelect.value = '';
        }
        setRegionMode();
        if (titleEl) {
            titleEl.textContent = TITLE_REGIONS;
        }
        if (noteEl) {
            noteEl.textContent = NOTE_REGIONS;
        }
        if (districtListEl) {
            districtListEl.innerHTML = '';
        }
        if (regionLayer && Object.keys(regionRefs).length) {
            map.fitBounds(regionLayer.getBounds(), { padding: [16, 16] });
        }
        setStatus('Showing the whole country.');
    }

    function showDistrictOutlines(code) {
        districtsGeo().then(function (geo) {
            clearDistrictLayer();
            var features = geo.features.filter(function (feature) {
                return feature.properties.region_code === code;
            });
            if (!features.length) {
                return;
            }
            districtLayer = window.L.geoJSON(
                { type: 'FeatureCollection', features: features },
                {
                    style: { color: '#0c1e21', weight: 1, fill: false, dashArray: '4 3' },
                    onEachFeature: function (feature, layer) {
                        layer.bindTooltip(escapeHtml(feature.properties.name), { sticky: true });
                    }
                }
            ).addTo(map);
            map.fitBounds(districtLayer.getBounds(), { padding: [24, 24] });
        }).catch(function () {
            // Outlines are a nicety; the panel already lists the districts.
        });
    }

    function loadRegion(code) {
        var info = lookup[code];
        if (!info) {
            return;
        }

        if (regionSelect) {
            regionSelect.value = code;
        }
        setDistrictMode();
        markActiveRow(code);
        highlight(code);
        if (titleEl) {
            titleEl.textContent = info.name;
        }
        if (noteEl) {
            noteEl.textContent = 'Loading district breakdown\u2026';
        }
        if (districtListEl) {
            districtListEl.innerHTML = '';
        }
        setStatus('Loading districts for ' + info.name + '.');

        getJson(config.districtsUrl + '?region=' + encodeURIComponent(code))
            .then(function (payload) {
                var region = payload.region;
                if (!region) {
                    throw new Error('empty payload');
                }

                renderDistrictRows(region);
                showDistrictOutlines(code);

                if (noteEl) {
                    if (region.published) {
                        var total = region.total === null ? 0 : region.total;
                        noteEl.textContent = total > 0
                            ? (total === 1 ? '1 approved member in this region.' : total + ' approved members in this region.')
                            : 'No approved members recorded in this region yet.';
                    } else if (region.reason === 'below_cohort') {
                        noteEl.textContent = 'Too few members in this region to publish a breakdown.';
                    } else {
                        noteEl.textContent = 'Geographic detail is not published.';
                    }
                }
                setStatus('Showing districts for ' + region.region + '.');
            })
            .catch(function () {
                if (noteEl) {
                    noteEl.textContent = 'District details could not be loaded. Please try again.';
                }
                setStatus('District details could not be loaded.');
            });
    }

    /* ----------------------------------------------------------------------
       Ranked region list (built in JS so auto-refresh can re-render it)
       ---------------------------------------------------------------------- */

    function barPercent(count, max) {
        if (!max || max <= 0 || !count || count <= 0) {
            return 0;
        }
        return Math.max(5, Math.round(count * 100 / max));
    }

    // Mirrors bdst_dash_rank_row() in dashboard.php - the JS and the server
    // must produce the same row for the same figure.
    function regionRowHtml(row, rank, max, zero) {
        var count = (row.count === null || row.count === undefined) ? null : Number(row.count);
        var percent = barPercent(count, max);
        var display = count === null ? String(row.label || 'Not published') : fmt(count);
        var srCount = count === null ? '' : (count === 1 ? ' member' : ' members');

        return '<li class="bdst-row' + (zero ? ' bdst-row--zero' : '') + '"' +
            ' data-code="' + escapeHtml(row.code) + '"' +
            ' data-name="' + escapeHtml(row.name) + '"' +
            ' data-count="' + (count === null ? '' : count) + '"' +
            (zero ? ' data-zero="1"' : '') + '>' +
            '<button type="button" class="bdst-row-btn">' +
            '<span class="bdst-row-rank" aria-hidden="true">' + (rank === null ? '&ndash;' : rank) + '</span>' +
            '<span class="bdst-row-body">' +
            '<span class="bdst-row-name">' + escapeHtml(row.name) + '</span>' +
            '<span class="bdst-row-track"><span class="bdst-row-fill" style="width:' + percent + '%"></span></span>' +
            '</span>' +
            '<span class="bdst-row-count">' + escapeHtml(display) +
            (srCount === '' ? '' : '<span class="visually-hidden">' + srCount + '</span>') +
            '</span>' +
            '</button>' +
            '</li>';
    }

    // Mirrors bdst_dashboard_sort_regions(): most members first, then name.
    function sortRegions(regions) {
        return regions.slice().sort(function (a, b) {
            var ac = a.count === null ? 0 : Number(a.count);
            var bc = b.count === null ? 0 : Number(b.count);
            if (ac !== bc) {
                return bc - ac;
            }
            return a.name < b.name ? -1 : (a.name > b.name ? 1 : 0);
        });
    }

    function renderRows(payload) {
        var ranked = [];
        var zeros = [];
        var max = payload.max_count || 0;

        sortRegions(payload.regions).forEach(function (row) {
            if (row.count !== null && Number(row.count) > 0) {
                ranked.push(row);
            } else {
                zeros.push(row);
            }
        });

        var html = '';
        ranked.forEach(function (row, i) {
            html += regionRowHtml(row, i + 1, max, false);
        });
        if (rankListEl) {
            rankListEl.innerHTML = html || '<li class="bdst-empty">No approved members are recorded yet.</li>';
        }

        var zeroHtml = '';
        zeros.forEach(function (row) {
            zeroHtml += regionRowHtml(row, null, max, true);
        });
        if (zeroBox) {
            zeroBox.innerHTML = zeroHtml;
        }

        zeroCount = zeros.length;
        if (zeroToggle) {
            zeroToggle.hidden = zeroCount === 0;
            zeroToggle.textContent =
                'Show regions with no members (' + zeroCount + ')';
            zeroToggle.setAttribute('aria-expanded', zeroBox ? String(!zeroBox.hidden) : 'false');
        }
        if (zeroBox && zeroCount === 0) {
            zeroBox.hidden = true;
        }

        wireRows();
        applyFilter();
        markActiveRow(selectedCode);
    }

    function setZeroOpen(open) {
        if (zeroBox) {
            zeroBox.hidden = !open;
        }
        if (zeroToggle) {
            zeroToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            zeroToggle.textContent =
                (open ? 'Hide' : 'Show') + ' regions with no members (' + zeroCount + ')';
        }
    }

    function applyFilter() {
        var query = filterEl ? (filterEl.value || '').trim().toLowerCase() : '';
        var rows = document.querySelectorAll('.bdst-row[data-name]');
        Array.prototype.forEach.call(rows, function (row) {
            var name = (row.getAttribute('data-name') || '').toLowerCase();
            row.hidden = query !== '' && name.indexOf(query) === -1;
        });
        setZeroOpen(query !== '' && zeroCount > 0);
    }

    function wireRows() {
        var rows = document.querySelectorAll('.bdst-row[data-code]');
        Array.prototype.forEach.call(rows, function (row) {
            if (row.getAttribute('data-wired')) {
                return;
            }
            row.setAttribute('data-wired', '1');
            var code = row.getAttribute('data-code');
            var button = row.querySelector('.bdst-row-btn');
            if (!button) {
                return;
            }
            button.addEventListener('click', function () {
                loadRegion(code);
            });
            row.addEventListener('mouseenter', function () {
                if (!selectedCode) {
                    highlight(code);
                }
            });
            row.addEventListener('mouseleave', function () {
                if (!selectedCode) {
                    highlight('');
                }
            });
        });
    }

    /* ----------------------------------------------------------------------
       Donut chart + coverage (requester-facing "at a glance" card)
       ---------------------------------------------------------------------- */

    function arcPath(rOuter, rInner, startDeg, endDeg) {
        function pt(r, a) {
            var rad = (a - 90) * Math.PI / 180;
            return [60 + r * Math.cos(rad), 60 + r * Math.sin(rad)];
        }
        var large = (endDeg - startDeg) > 180 ? 1 : 0;
        var sO = pt(rOuter, startDeg);
        var eO = pt(rOuter, endDeg);
        var sI = pt(rInner, endDeg);
        var eI = pt(rInner, startDeg);
        return 'M' + sO[0].toFixed(3) + ' ' + sO[1].toFixed(3) +
            ' A' + rOuter + ' ' + rOuter + ' 0 ' + large + ' 1 ' + eO[0].toFixed(3) + ' ' + eO[1].toFixed(3) +
            ' L' + sI[0].toFixed(3) + ' ' + sI[1].toFixed(3) +
            ' A' + rInner + ' ' + rInner + ' 0 ' + large + ' 0 ' + eI[0].toFixed(3) + ' ' + eI[1].toFixed(3) + ' Z';
    }

    function renderDonut(payload) {
        if (!donutEl) {
            return;
        }
        var regions = (payload.regions || []).filter(function (r) {
            return r.count !== null && Number(r.count) > 0;
        });
        var total = regions.reduce(function (sum, r) {
            return sum + Number(r.count);
        }, 0);

        if (!total) {
            donutEl.innerHTML = '';
            if (donutLegendEl) {
                donutLegendEl.innerHTML = '';
            }
            setText(donutNoteEl, payload.published
                ? 'No approved members recorded yet.'
                : 'Geographic detail is not published.');
            donutEl.setAttribute('aria-label', payload.published
                ? 'No approved members recorded yet.'
                : 'Geographic detail is not published.');
            return;
        }

        var sorted = regions.slice().sort(function (a, b) {
            return Number(b.count) - Number(a.count);
        });
        var top = sorted.slice(0, 5);
        var restCount = sorted.slice(5).reduce(function (sum, r) {
            return sum + Number(r.count);
        }, 0);
        var slices = top.map(function (r) {
            return { name: r.name, count: Number(r.count) };
        });
        if (restCount > 0) {
            slices.push({ name: 'Other regions', count: restCount });
        }

        var svg = '<svg viewBox="0 0 120 120" width="150" height="150" role="presentation" focusable="false">';
        var angle = 0;
        slices.forEach(function (slice, i) {
            var frac = slice.count / total;
            var sweep = frac * 360;
            var colour = DONUT_COLOURS[i % DONUT_COLOURS.length];
            if (sweep >= 359.9) {
                svg += '<circle cx="60" cy="60" r="36" fill="none" stroke="' + colour +
                    '" stroke-width="16"/>';
            } else if (sweep > 0.1) {
                svg += '<path d="' + arcPath(44, 28, angle, angle + sweep) + '" fill="' + colour + '"/>';
            }
            angle += sweep;
        });
        svg += '<circle cx="60" cy="60" r="17" fill="#ffffff"/>';
        svg += '<text x="60" y="57" text-anchor="middle" font-size="15" font-weight="700" fill="#0c1e21">' +
            escapeHtml(payload.regions.length ? String(Math.round(total)) : '0') + '</text>';
        svg += '<text x="60" y="70" text-anchor="middle" font-size="8" fill="#5a6772">members</text>';
        svg += '</svg>';

        donutEl.innerHTML = svg;

        var legendHtml = '';
        slices.forEach(function (slice, i) {
            var pct = (slice.count * 100 / total);
            legendHtml += '<li>' +
                '<span class="bdst-donut-swatch" style="background:' + DONUT_COLOURS[i % DONUT_COLOURS.length] + '"></span>' +
                '<span class="bdst-donut-name">' + escapeHtml(slice.name) + '</span>' +
                '<span class="bdst-donut-stat">' + escapeHtml(memberLabel(slice.count)) + '</span>' +
                '<span class="bdst-donut-pct">' + pct.toFixed(0) + '%</span>' +
                '</li>';
        });
        if (donutLegendEl) {
            donutLegendEl.innerHTML = legendHtml;
        }

        var topShare = Math.round(slices[0].count * 100 / total);
        setText(donutNoteEl, topShare + '% of members are based in ' + escapeHtml(slices[0].name) + '.');
        donutEl.setAttribute('aria-label',
            'Donut chart of member share by region. ' +
            slices.map(function (s) {
                return s.name + ' ' + Math.round(s.count * 100 / total) + ' percent';
            }).join(', ') + '.');
    }

    function totalFor(key) {
        if (!coverageEl) {
            return 0;
        }
        var value = Number(coverageEl.getAttribute('data-total-' + key)) || 0;
        return value;
    }

    function setCoverageBar(id, value) {
        var bar = document.getElementById(id);
        var text = document.getElementById(id + '-text');
        if (value === null || value === undefined) {
            if (bar) {
                bar.style.width = '0%';
            }
            if (text) {
                text.textContent = 'Not published';
            }
            return;
        }
        var total = totalFor(id.indexOf('regions') !== -1 ? 'regions' : 'districts');
        var pct = total > 0 ? Math.round(Number(value) * 100 / total) : 0;
        if (bar) {
            bar.style.width = Math.min(100, pct) + '%';
        }
        if (text) {
            text.textContent = fmt(value) + ' / ' + fmt(total);
        }
    }

    function renderCoverage(summary) {
        var totals = (summary && summary.totals) || {};
        setCoverageBar('bdst-coverage-regions', totals.regions_covered);
        setCoverageBar('bdst-coverage-districts', totals.districts_covered);
    }

    /* ----------------------------------------------------------------------
       KPI strip + timestamps
       ---------------------------------------------------------------------- */

    function topRegion(regions) {
        var top = null;
        (regions || []).forEach(function (r) {
            if (r.count !== null && Number(r.count) > 0) {
                if (!top || Number(r.count) > Number(top.count)) {
                    top = r;
                }
            }
        });
        return top;
    }

    function flash(el) {
        if (!el) {
            return;
        }
        el.classList.remove('is-updated');
        void el.offsetWidth; /* restart the animation */
        el.classList.add('is-updated');
    }

    function renderKpis(summary, regions) {
        var totals = (summary && summary.totals) || {};
        var members = (totals.members === null || totals.members === undefined) ? null : Number(totals.members);

        var elMembers = document.getElementById('bdst-kpi-members');
        var elRegions = document.getElementById('bdst-kpi-regions');
        var elDistricts = document.getElementById('bdst-kpi-districts');
        var elTop = document.getElementById('bdst-kpi-top');
        var elTopSub = document.getElementById('bdst-kpi-top-sub');

        var memberText = members === null ? '&ndash;' : fmt(members);
        var regionsText = (totals.regions_covered === null || totals.regions_covered === undefined) ? '&ndash;' : fmt(totals.regions_covered);
        var districtsText = (totals.districts_covered === null || totals.districts_covered === undefined) ? '&ndash;' : fmt(totals.districts_covered);

        var top = topRegion(regions);
        var topText = top ? escapeHtml(top.name) : '&ndash;';
        var topSubText = top ? escapeHtml(memberLabel(top.count)) : 'No members yet';

        if (elMembers && elMembers.textContent !== memberText) {
            setText(elMembers, memberText);
            flash(elMembers);
        }
        if (elRegions && elRegions.textContent !== regionsText) {
            setText(elRegions, regionsText);
            flash(elRegions);
        }
        if (elDistricts && elDistricts.textContent !== districtsText) {
            setText(elDistricts, districtsText);
            flash(elDistricts);
        }
        if (elTop && elTop.textContent !== topText) {
            setText(elTop, topText);
            flash(elTop);
        }
        if (elTopSub && elTopSub.textContent !== topSubText) {
            setText(elTopSub, topSubText);
        }
    }

    function fmtTimestamp(iso) {
        if (!iso) {
            return '';
        }
        var d = new Date(iso);
        if (isNaN(d.getTime())) {
            return '';
        }
        var months = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        function pad(n) {
            return n < 10 ? '0' + n : String(n);
        }
        return d.getUTCDate() + ' ' + months[d.getUTCMonth()] + ' ' + d.getUTCFullYear() +
            ', ' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ' UTC';
    }

    function renderTimes(summary) {
        var ts = fmtTimestamp(summary ? summary.generated_at : '');
        setText(updatedEl, ts ? 'Figures generated ' + escapeHtml(ts) + '.' : '');
        setText(tableUpdatedEl, ts
            ? 'Figures generated ' + escapeHtml(ts) + '. Updated automatically as registrations are approved.'
            : '');
    }

    /* ----------------------------------------------------------------------
       Trend chart (CSS bars, no charting dependency)
       ---------------------------------------------------------------------- */

    function shortTick(label, period) {
        if (!label) {
            return '';
        }
        if (period === 'yearly') {
            return label;
        }
        if (period === 'monthly') {
            var parts = String(label).split(' ');
            var month = (parts[0] || '').slice(0, 3);
            var year = (parts[1] || '').slice(-2);
            return month + (year ? " '" + year : '');
        }
        return label;
    }

    function renderTrend(trend) {
        if (!trendChartEl) {
            return;
        }
        var buckets = trend.buckets || [];
        var peak = Number(trend.peak) || 0;
        var published = !!trend.published;

        if (!published || !buckets.length) {
            if (trendEmptyEl) {
                trendEmptyEl.hidden = false;
            }
            if (trendDataEl) {
                trendDataEl.hidden = true;
            }
            if (trendRangeEl) {
                trendRangeEl.textContent = '';
            }
            if (trendRowsEl) {
                trendRowsEl.innerHTML = '';
            }
            if (trendBarsEl) {
                trendBarsEl.innerHTML = '';
            } else {
                trendChartEl.innerHTML = '';
            }
            trendChartEl.setAttribute('aria-label', 'No approved members recorded yet, so there is no trend to show.');
            return;
        }

        if (trendEmptyEl) {
            trendEmptyEl.hidden = true;
        }
        if (trendDataEl) {
            trendDataEl.hidden = false;
        }

        var first = buckets[0];
        var last = buckets[buckets.length - 1];
        if (trendRangeEl) {
            trendRangeEl.textContent = ' (' + first.label + ' to ' + last.label + ')';
        }

        var dense = buckets.length > 26;
        var tickStep = Math.max(1, Math.ceil(buckets.length / 12));
        var html = '<div class="bdst-bars' + (dense ? ' bdst-bars--dense' : '') + '">';
        buckets.forEach(function (bucket, i) {
            var count = Number(bucket.count) || 0;
            var height = peak > 0 && count > 0 ? Math.max(2, Math.round(count * 100 / peak)) : 0;
            var showTick = i % tickStep === 0 || i === buckets.length - 1;
            html += '<div class="bdst-bar-col' + (count === 0 ? ' bdst-bar-col--zero' : '') + '"' +
                ' title="' + escapeHtml(bucket.label) + ': ' + fmt(count) + ' new, ' + fmt(bucket.cumulative) + ' to date">' +
                '<span class="bdst-bar-val">' + (count > 0 ? escapeHtml(fmt(count)) : '') + '</span>' +
                '<span class="bdst-bar" style="height:' + height + '%"></span>' +
                '<span class="bdst-bar-tick"' + (showTick ? '' : ' hidden') + '>' +
                escapeHtml(shortTick(bucket.label, trend.period)) + '</span>' +
                '</div>';
        });
        html += '</div>';
        if (trendBarsEl) {
            trendBarsEl.innerHTML = html;
        } else {
            trendChartEl.innerHTML = html;
        }
        trendChartEl.setAttribute('aria-label',
            'Bar chart of approved members added per period, ' + escapeHtml(first.label) + ' to ' + escapeHtml(last.label) + '.');

        if (trendRowsEl) {
            var rows = '';
            buckets.forEach(function (bucket) {
                rows += '<tr>' +
                    '<th scope="row">' + escapeHtml(bucket.label) + '</th>' +
                    '<td class="bdst-table-num">' + escapeHtml(fmt(bucket.count)) + '</td>' +
                    '<td class="bdst-table-num">' + escapeHtml(fmt(bucket.cumulative)) + '</td>' +
                    '</tr>';
            });
            trendRowsEl.innerHTML = rows;
        }
    }

    function loadTrend(period) {
        period = period || trendPeriod;
        var url = config.trendsUrl + '?period=' + encodeURIComponent(period);
        return getJson(url).then(function (trend) {
            trendPeriod = trend.period || period;
            renderTrend(trend);
        }).catch(function () {
            // The server-rendered chart stays in place; nothing to do.
        });
    }

    /* ----------------------------------------------------------------------
       CSV export (client-side Blob)
       ---------------------------------------------------------------------- */

    function csvValue(value) {
        var s = String(value === null || value === undefined ? '' : value);
        if (/[",\r\n]/.test(s)) {
            s = '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function exportCsv() {
        var payload = lastRegionsPayload;
        if (!payload) {
            return;
        }
        var lines = ['Region,Approved members'];
        sortRegions(payload.regions).forEach(function (row) {
            var count = row.count === null ? (row.label || 'Not published') : row.count;
            lines.push(csvValue(row.name) + ',' + csvValue(count));
        });

        var summary = lastSummary || {};
        var totals = summary.totals || {};
        lines.push('');
        lines.push('Total approved members,' + csvValue(totals.members === null || totals.members === undefined ? '' : totals.members));
        if (totals.regions_covered !== null && totals.regions_covered !== undefined) {
            lines.push('Regions represented,' + csvValue(totals.regions_covered));
        }
        if (totals.districts_covered !== null && totals.districts_covered !== undefined) {
            lines.push('Districts represented,' + csvValue(totals.districts_covered));
        }
        if ((summary.generated_at || '').toString()) {
            lines.push('Figures generated,' + csvValue(fmtTimestamp(summary.generated_at)));
        }

        var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        var stamp = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        a.href = url;
        a.download = (config.exportName || 'bds-tanzania-members-by-region') + '-' +
            stamp.getFullYear() + '-' + pad(stamp.getMonth() + 1) + '-' + pad(stamp.getDate()) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    /* ----------------------------------------------------------------------
       Rendering the map from a payload
       ---------------------------------------------------------------------- */

    function renderMap(payload, geo) {
        lookup = {};
        regionRefs = {};
        selectedCode = '';
        payload.regions.forEach(function (region) {
            lookup[region.code] = region;
        });

        var max = payload.max_count || 0;

        regionLayer.clearLayers();
        regionLayer.addData(geo);

        renderPins(payload.regions, max);
        renderPinKey(max);

        // Fits the country on the first render only - auto-refresh and the
        // manual refresh must never yank the visitor's current view.
        if (!fitted && regionLayer.getBounds().isValid()) {
            map.fitBounds(regionLayer.getBounds(), { padding: [16, 16] });
            map.setMaxBounds(regionLayer.getBounds().pad(0.5));
            fitted = true;
        }

        renderLegend(payload.published
            ? payload.legend
            : [{ key: 'withheld', label: 'Not published' }]);

        setStatus(payload.published
            ? 'Map ready. Select a region to see its districts.'
            : 'Map ready.');
    }

    /* ----------------------------------------------------------------------
       Auto-refresh
       ---------------------------------------------------------------------- */

    function applyPayload(regionsPayload, summaryPayload, silent) {
        var regionsChanged = JSON.stringify(regionsPayload.regions) !== lastRegionsJson;
        var summaryChanged = summaryPayload ? JSON.stringify(summaryPayload) !== lastSummaryJson : false;

        if (regionsChanged || !lastRegionsPayload) {
            renderMap(regionsPayload, cachedGeo);
            renderRows(regionsPayload);
            renderDonut(regionsPayload);
            renderKpis(summaryPayload || {}, regionsPayload.regions);
        } else if (summaryChanged && summaryPayload) {
            renderKpis(summaryPayload, regionsPayload.regions);
        }

        if (summaryPayload && summaryChanged) {
            renderCoverage(summaryPayload);
            renderTimes(summaryPayload);
        }

        lastRegionsPayload = regionsPayload;
        lastRegionsJson = JSON.stringify(regionsPayload.regions);
        if (summaryPayload) {
            lastSummary = summaryPayload;
            lastSummaryJson = JSON.stringify(summaryPayload);
        }
        if (!silent && regionsChanged) {
            setStatus('Figures updated.');
        }
    }

    var cachedGeo = null;

    function refreshSilent() {
        if (refreshing || document.hidden) {
            return;
        }
        refreshing = true;
        Promise.all([
            getJson(config.regionsUrl),
            config.summaryUrl ? getJson(config.summaryUrl) : Promise.resolve(null)
        ]).then(function (results) {
            applyPayload(results[0], results[1], true);
        }).catch(function () {
            // Keep whatever is on screen; the next poll or a manual refresh retries.
        }).then(function () {
            refreshing = false;
        });
    }

    function refreshAll() {
        var spinning = refreshBtn ? refreshBtn.querySelector('.fa-rotate') : null;
        if (spinning) {
            spinning.classList.add('is-spinning');
        }
        refreshing = true;
        // The trend is its own request; let it run in parallel rather than
        // chained, so the main figures are not held hostage by it.
        loadTrend(trendPeriod).catch(function () { /* tolerated */ });
        Promise.all([
            getJson(config.regionsUrl),
            config.summaryUrl ? getJson(config.summaryUrl) : Promise.resolve(null)
        ]).then(function (results) {
            applyPayload(results[0], results[1], false);
        }).catch(function () {
            // Keep whatever is on screen; the next poll or manual refresh retries.
        }).then(function () {
            refreshing = false;
            if (spinning) {
                spinning.classList.remove('is-spinning');
            }
        });
    }

    function startAutoRefresh() {
        var seconds = Number(config.refreshSeconds) || 0;
        if (seconds <= 0) {
            return;
        }
        refreshTimer = window.setInterval(refreshSilent, seconds * 1000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshSilent();
                loadTrend(trendPeriod);
            }
        });
    }

    /* ----------------------------------------------------------------------
       Wiring
       ---------------------------------------------------------------------- */

    if (regionSelect) {
        regionSelect.addEventListener('change', function () {
            if (regionSelect.value) {
                loadRegion(regionSelect.value);
            } else {
                resetDetail();
            }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', resetDetail);
    }

    if (zeroToggle) {
        zeroToggle.addEventListener('click', function () {
            setZeroOpen(zeroBox ? zeroBox.hidden : false);
        });
    }

    if (filterEl) {
        filterEl.addEventListener('input', applyFilter);
        filterEl.addEventListener('search', applyFilter);
    }

    var segButtons = document.querySelectorAll('.bdst-seg[data-period]');
    Array.prototype.forEach.call(segButtons, function (button) {
        button.addEventListener('click', function () {
            var period = button.getAttribute('data-period');
            trendPeriod = period;
            Array.prototype.forEach.call(segButtons, function (other) {
                var on = other === button;
                other.classList.toggle('is-active', on);
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            loadTrend(period);
        });
    });

    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshAll);
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', exportCsv);
    }

    /* ----------------------------------------------------------------------
       Boot
       ---------------------------------------------------------------------- */

    wireRows();

    Promise.all([
        getJson(config.regionsUrl),
        fetch(config.regionsGeo).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
    ]).then(function (results) {
        cachedGeo = results[1];
        lastRegionsPayload = results[0];
        lastRegionsJson = JSON.stringify(results[0].regions);
        renderMap(results[0], results[1]);
        renderRows(results[0]);
        renderDonut(results[0]);
        renderTrend(config.trend || { period: trendPeriod, buckets: [] });

        if (config.summaryUrl) {
            getJson(config.summaryUrl).then(function (summary) {
                lastSummary = summary;
                lastSummaryJson = JSON.stringify(summary);
                renderKpis(summary, results[0].regions);
                renderCoverage(summary);
                renderTimes(summary);
            }).catch(function () {
                // The server-rendered KPIs stay put.
            });
        }

        startAutoRefresh();
    }).catch(function () {
        showFallback('The map could not be loaded. The ranked list and the table below carry the same figures.');
    });
}());