/* ==========================================================================
   BDS Tanzania - public member map (/dashboard)
   Builds the Leaflet choropleth + green proportional-symbol bubbles from the
   JSON endpoints and GeoJSON boundaries, and drives the ranked region list
   and the district drill-down. This file contains no figures.
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
    var bubbleKeyEl    = document.getElementById('bdst-bubble-key');
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

    // Default copy is read from the server-rendered markup so the two never
    // drift apart.
    var TITLE_REGIONS = titleEl ? titleEl.textContent : 'Regions by membership';
    var NOTE_REGIONS  = noteEl ? noteEl.textContent : '';

    var zeroCount = zeroBox ? zeroBox.querySelectorAll('.bdst-row').length : 0;

    function setStatus(message) {
        if (statusEl) {
            statusEl.textContent = message || '';
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
        if (bubbleKeyEl) {
            bubbleKeyEl.hidden = true;
        }
        setStatus(message);
    }

    if (typeof window.L === 'undefined') {
        showFallback('The interactive map could not start. The ranked list and the table below carry the same figures.');
        return;
    }

    /* ----------------------------------------------------------------------
       Map shell
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

    // Bubbles live in their own pane above the choropleth so a highlighted
    // region can never hide them.
    map.createPane('bdstBubbles');
    map.getPane('bdstBubbles').style.zIndex = 650;

    if (config.tileUrl) {
        window.L.tileLayer(config.tileUrl, {
            attribution: config.attribution || '',
            maxZoom: 19
        }).addTo(map);
    }

    map.zoomControl.setPosition('topright');

    var regionLayer = null;
    var districtLayer = null;
    var bubbleGroup = null;
    var lookup = {};
    var regionRefs = {};
    var selectedCode = '';
    var districtsGeoPromise = null;

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
       Green proportional bubbles
       ---------------------------------------------------------------------- */

    // Area-proportional: radius grows with the square root of the count, so a
    // region with four times the members reads as a bubble with twice the
    // radius. A single member still gets a small but clearly visible dot.
    function radiusFor(count, max) {
        if (!count || count <= 0) {
            return 0;
        }
        return 4.5 + 15.5 * Math.sqrt(count / Math.max(1, max));
    }

    function memberLabel(count) {
        return count === 1 ? '1 member' : count + ' members';
    }

    function renderBubbles(regions, max) {
        if (bubbleGroup) {
            map.removeLayer(bubbleGroup);
        }
        bubbleGroup = window.L.layerGroup().addTo(map);

        regions.forEach(function (region) {
            var count = region.count;
            if (!count || count <= 0) {
                return;
            }
            var radius = radiusFor(count, max);
            var marker = window.L.circleMarker([region.lat, region.lon], {
                pane: 'bdstBubbles',
                radius: radius,
                color: '#ffffff',
                weight: 2,
                fillColor: BUBBLE,
                fillOpacity: 0.55
            });

            marker.bindTooltip(
                '<strong>' + escapeHtml(region.name) + '</strong><br>' + escapeHtml(memberLabel(count)),
                { sticky: true }
            );
            marker.on('mouseover', function () {
                marker.setStyle({ fillOpacity: 0.78 });
                marker.setRadius(radius * 1.18);
            });
            marker.on('mouseout', function () {
                marker.setStyle({ fillOpacity: selectedCode === region.code ? 0.85 : 0.55 });
                marker.setRadius(radius);
            });
            marker.on('click', function () {
                loadRegion(region.code);
            });

            var ref = regionRefs[region.code] || (regionRefs[region.code] = {});
            ref.marker = marker;
            ref.radius = radius;

            marker.addTo(bubbleGroup);
        });
    }

    function renderBubbleKey(max) {
        if (!bubbleKeyEl || !max || max <= 0) {
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
            var size = radiusFor(value, max) * 2;
            html += '<span class="bdst-key-item">' +
                '<span class="bdst-key-dot" style="width:' + size.toFixed(1) + 'px;height:' + size.toFixed(1) + 'px"></span>' +
                '<span class="bdst-key-label">' + escapeHtml(value) + '</span>' +
                '</span>';
        });
        bubbleKeyEl.innerHTML = html;
        bubbleKeyEl.hidden = false;
    }

    /* ----------------------------------------------------------------------
       Legend
       ---------------------------------------------------------------------- */

    function renderLegend(entries) {
        // Validate before clearing: the legend is also rendered server-side,
        // so a malformed payload must leave that fallback in place rather
        // than blanking the legend (and, via the caller's catch, tearing down
        // a map that is actually working).
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
                ref.marker.setStyle({ fillOpacity: on ? 0.85 : 0.55 });
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
        if (regionLayer) {
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
       Ranked region list
       ---------------------------------------------------------------------- */

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
        setZeroOpen(query !== '');
    }

    function wireRows() {
        var rows = document.querySelectorAll('.bdst-row[data-code]');
        Array.prototype.forEach.call(rows, function (row) {
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

        if (zeroToggle) {
            zeroToggle.setAttribute('aria-expanded', 'false');
            zeroToggle.addEventListener('click', function () {
                setZeroOpen(zeroBox ? zeroBox.hidden : false);
            });
        }

        if (filterEl) {
            filterEl.addEventListener('input', applyFilter);
            filterEl.addEventListener('search', applyFilter);
        }
    }

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

    /* ----------------------------------------------------------------------
       Choropleth
       ---------------------------------------------------------------------- */

    function renderMap(payload, geo) {
        lookup = {};
        regionRefs = {};
        selectedCode = '';
        payload.regions.forEach(function (region) {
            lookup[region.code] = region;
        });

        var max = payload.max_count || 0;

        regionLayer = window.L.geoJSON(geo, {
            style: function (feature) {
                var info = lookup[feature.properties.code];
                return {
                    color: '#ffffff',
                    weight: 1,
                    fillColor: colourFor(info ? info.band : 'withheld'),
                    fillOpacity: 0.9
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
        }).addTo(map);

        renderBubbles(payload.regions, max);
        renderBubbleKey(max);

        var bounds = regionLayer.getBounds();
        map.fitBounds(bounds, { padding: [16, 16] });
        map.setMaxBounds(bounds.pad(0.5));

        renderLegend(payload.published
            ? payload.legend
            : [{ key: 'withheld', label: 'Not published' }]);

        setStatus(payload.published
            ? 'Map ready. Select a region to see its districts.'
            : 'Map ready.');
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
        renderMap(results[0], results[1]);
    }).catch(function () {
        showFallback('The map could not be loaded. The ranked list and the table below carry the same figures.');
    });
}());
