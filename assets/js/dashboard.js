/* ==========================================================================
   BDS Tanzania - public member map (/dashboard)
   Builds the Leaflet choropleth from the JSON endpoints + GeoJSON boundaries.
   Data and geometry are fetched at runtime; this file contains no figures.
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

    var statusEl = document.getElementById('bdst-map-status');
    var fallbackEl = document.getElementById('bdst-map-fallback');
    var legendEl = document.getElementById('bdst-legend');
    var detailTitleEl = document.getElementById('bdst-detail-title');
    var detailNoteEl = document.getElementById('bdst-detail-note');
    var detailListEl = document.getElementById('bdst-detail-list');
    var regionSelect = document.getElementById('bdst-region-select');
    var resetBtn = document.getElementById('bdst-detail-reset');

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
        withheld: cssVar('--bdst-band-withheld', '#dde3e7'),
        zero: cssVar('--bdst-band-zero', '#eef4f7'),
        lt_min: cssVar('--bdst-band-lt-min', '#cfe8f4'),
        low: cssVar('--bdst-band-low', '#9bd0e9'),
        mid: cssVar('--bdst-band-mid', '#4fa8d4'),
        high: cssVar('--bdst-band-high', '#1a6f96')
    };

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
        setStatus(message);
    }

    if (typeof window.L === 'undefined') {
        showFallback('The interactive map could not start. The table below lists the same figures.');
        return;
    }

    /* ----------------------------------------------------------------------
       Map shell
       ---------------------------------------------------------------------- */

    // Reveal the map box (see the .is-ready rule in dashboard.css) before
    // Leaflet measures it, so the collapsed no-JS state does not leave
    // Leaflet with a zero-sized container.
    if (mapEl) {
        mapEl.classList.add('is-ready');
    }

    var map = window.L.map(mapEl, {
        center: [-6.4, 34.9],
        zoom: 6,
        minZoom: 4,
        maxZoom: 13,
        scrollWheelZoom: false,
        attributionControl: true
    });

    if (config.tileUrl) {
        window.L.tileLayer(config.tileUrl, {
            attribution: config.attribution || '',
            maxZoom: 19
        }).addTo(map);
    }

    map.zoomControl.setPosition('topright');

    var regionLayer = null;
    var districtLayer = null;
    var lookup = {};
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
       Detail panel
       ---------------------------------------------------------------------- */

    function clearDistrictLayer() {
        if (districtLayer) {
            map.removeLayer(districtLayer);
            districtLayer = null;
        }
    }

    function resetHighlight() {
        if (!regionLayer) {
            return;
        }
        regionLayer.eachLayer(function (layer) {
            layer.setStyle({ weight: 1, color: '#ffffff' });
        });
    }

    function renderDetailItems(items) {
        detailListEl.innerHTML = '';
        items.forEach(function (item) {
            var li = document.createElement('li');
            var muted = item.count === null;
            li.innerHTML =
                '<span class="bdst-detail-name">' +
                '<span class="bdst-swatch bdst-swatch--' + escapeHtml(item.band) + '"></span>' +
                '<span>' + escapeHtml(item.name) + '</span>' +
                '</span>' +
                '<span class="bdst-detail-count' + (muted ? ' is-muted' : '') + '">' +
                escapeHtml(item.count === null ? item.label : String(item.count)) +
                '</span>';
            detailListEl.appendChild(li);
        });
    }

    function resetDetail() {
        clearDistrictLayer();
        resetHighlight();
        if (regionSelect) {
            regionSelect.value = '';
        }
        if (detailTitleEl) {
            detailTitleEl.textContent = 'District breakdown';
        }
        if (detailNoteEl) {
            detailNoteEl.textContent = 'Select a region on the map, or use the list above, to see its districts.';
        }
        if (detailListEl) {
            detailListEl.innerHTML = '';
        }
        if (resetBtn) {
            resetBtn.hidden = true;
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
        if (detailTitleEl) {
            detailTitleEl.textContent = info.name;
        }
        if (detailListEl) {
            detailListEl.innerHTML = '';
        }
        if (resetBtn) {
            resetBtn.hidden = false;
        }
        if (detailNoteEl) {
            detailNoteEl.textContent = 'Loading district breakdown...';
        }
        setStatus('Loading districts for ' + info.name + '.');

        if (regionLayer) {
            regionLayer.eachLayer(function (layer) {
                var selected = layer.feature.properties.code === code;
                layer.setStyle({ weight: selected ? 3 : 1, color: selected ? '#0c1e21' : '#ffffff' });
                if (selected && layer.bringToFront) {
                    layer.bringToFront();
                }
            });
        }

        getJson(config.districtsUrl + '?region=' + encodeURIComponent(code))
            .then(function (payload) {
                var region = payload.region;
                if (!region) {
                    throw new Error('empty payload');
                }

                renderDetailItems(region.items || []);
                showDistrictOutlines(code);

                if (detailNoteEl) {
                    if (region.published) {
                        detailNoteEl.textContent = region.total === null
                            ? 'Districts with members in this region.'
                            : region.total + ' approved member(s) across this region.';
                    } else if (region.reason === 'below_cohort') {
                        detailNoteEl.textContent = 'Too few members in this region to publish a breakdown ' +
                            'without identifying individuals.';
                    } else {
                        detailNoteEl.textContent = 'Geographic detail is not published yet.';
                    }
                }
                setStatus('Showing districts for ' + region.region + '.');
            })
            .catch(function () {
                if (detailNoteEl) {
                    detailNoteEl.textContent = 'District details could not be loaded. Please try again.';
                }
                setStatus('District details could not be loaded.');
            });
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
        payload.regions.forEach(function (region) {
            lookup[region.code] = region;
        });

        regionLayer = window.L.geoJSON(geo, {
            style: function (feature) {
                var info = lookup[feature.properties.code];
                return {
                    color: '#ffffff',
                    weight: 1,
                    fillColor: colourFor(info ? info.band : 'withheld'),
                    fillOpacity: 0.88
                };
            },
            onEachFeature: function (feature, layer) {
                var info = lookup[feature.properties.code] || {
                    name: feature.properties.name,
                    band: 'withheld',
                    label: 'Not published yet'
                };

                layer.bindTooltip(
                    '<strong>' + escapeHtml(info.name) + '</strong><br>' + escapeHtml(info.label),
                    { sticky: true }
                );
                layer.on('click', function () {
                    loadRegion(feature.properties.code);
                });
                layer.on('mouseover', function () {
                    layer.setStyle({ weight: 2 });
                });
                layer.on('mouseout', function () {
                    var selected = regionSelect && regionSelect.value === feature.properties.code;
                    layer.setStyle({ weight: selected ? 3 : 1 });
                });
            }
        }).addTo(map);

        var bounds = regionLayer.getBounds();
        map.fitBounds(bounds, { padding: [16, 16] });
        map.setMaxBounds(bounds.pad(0.5));

        renderLegend(payload.published
            ? payload.legend
            : [{ key: 'withheld', label: 'Not published yet' }]);

        setStatus(payload.published
            ? 'Map ready. Select a region to see its districts.'
            : 'Map ready. Geographic detail is currently withheld.');
    }

    /* ----------------------------------------------------------------------
       Boot
       ---------------------------------------------------------------------- */

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
    }).catch(function (err) {
        showFallback('The map could not be loaded. The table below lists the same figures.');
    });
}());
