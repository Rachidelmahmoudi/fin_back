/**
 * FinPharma — public/js/map.js
 *
 * Carte des pages liste (pharmacies / médecins / laboratoires) et fiches.
 * Adaptateur à deux moteurs, choisi automatiquement :
 *   - Google Maps si une clé est fournie (<meta name="fp-maps-key">),
 *   - Leaflet + OpenStreetMap (gratuit) sinon, ou si Google échoue à charger.
 *
 * Comportement style Findoctor : clic sur un marker => infobulle (carte
 * mini-fiche) ; clic sur « Voir sur la carte » dans la liste => centre la
 * carte et ouvre l'infobulle ; marqueurs regroupés (clusters).
 *
 * Chargement paresseux : aucune librairie de carte n'est téléchargée tant
 * que le conteneur n'approche pas de la zone visible (coût Google Maps et
 * performance : le chargement initial de la page n'est pas ralenti).
 *
 * Données attendues sur le conteneur [data-map="list"] :
 *   data-pharmacies='[{id,name,address,lat,lng,onDuty,url,phone,photo}]'
 */
(function () {
    'use strict';

    var MOROCCO_CENTER = { lat: 31.7917, lng: -7.0926 };
    var CDN = {
        leafletCss: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css',
        leafletJs: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js',
        clusterCss: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.min.css',
        clusterJs: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.min.js',
        gClusterJs: 'https://cdn.jsdelivr.net/npm/@googlemaps/markerclusterer@2.5.3/dist/index.min.js'
    };

    /* ---------- utilitaires ---------- */

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function safeUrl(u) {
        // Les URLs viennent de notre propre serveur ; on refuse tout de même javascript:
        return /^(https?:)?\//.test(u || '') ? u : '#';
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src; s.async = true;
            s.onload = resolve;
            s.onerror = function () { reject(new Error('load ' + src)); };
            document.head.appendChild(s);
        });
    }

    function loadCss(href) {
        var l = document.createElement('link');
        l.rel = 'stylesheet'; l.href = href;
        document.head.appendChild(l);
    }

    function pinSvg(duty) {
        var color = duty ? '#E85D4B' : '#04BDAF';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42">' +
            '<path d="M17 1C8.7 1 2 7.6 2 15.8 2 26.5 17 41 17 41s15-14.5 15-25.2C32 7.6 25.3 1 17 1z" fill="' + color + '" stroke="#fff" stroke-width="2"/>' +
            '<path d="M17 9v14M10 16h14" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
    }

    function pinDataUri(duty) {
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(pinSvg(duty));
    }

    /** Contenu HTML de l'infobulle (identique pour les deux moteurs). */
    function infoHtml(p) {
        var label = (document.documentElement.lang === 'ar') ? 'عرض الملف' : 'Voir la fiche';
        return '<div class="fp-info">' +
            (p.photo ? '<img class="fp-info__img" src="' + escapeHtml(safeUrl(p.photo)) + '" alt="" width="64" height="64">' : '') +
            '<div class="fp-info__body">' +
            '<strong class="fp-info__name">' + escapeHtml(p.name) + '</strong>' +
            (p.address ? '<span class="fp-info__addr">' + escapeHtml(p.address) + '</span>' : '') +
            (p.phone ? '<a class="fp-info__phone" href="tel:' + escapeHtml(p.phone) + '">' + escapeHtml(p.phone) + '</a>' : '') +
            '<a class="fp-info__link" href="' + escapeHtml(safeUrl(p.url)) + '">' + label + ' &rarr;</a>' +
            '</div></div>';
    }

    function readItems(container) {
        var items = [];
        try { items = JSON.parse(container.getAttribute('data-pharmacies') || '[]'); } catch (e) { items = []; }
        return items.map(function (p) {
            p.lat = parseFloat(p.lat); p.lng = parseFloat(p.lng);
            return p;
        }).filter(function (p) { return isFinite(p.lat) && isFinite(p.lng) && (p.lat || p.lng); });
    }

    function showEmpty(container) {
        container.innerHTML = '<div class="map-empty">' + escapeHtml(container.getAttribute('data-empty-label') || 'Carte non disponible') + '</div>';
    }

    /* ---------- moteur Leaflet / OpenStreetMap ---------- */

    function leafletDriver() {
        function ready() {
            if (window.L && L.markerClusterGroup) { return Promise.resolve(); }
            loadCss(CDN.leafletCss); loadCss(CDN.clusterCss);
            return (window.L ? Promise.resolve() : loadScript(CDN.leafletJs)).then(function () {
                return loadScript(CDN.clusterJs);
            });
        }

        return {
            name: 'leaflet',
            mount: function (container, items, onSelect, single) {
                return ready().then(function () {
                    var map = L.map(container, { scrollWheelZoom: false });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 19
                    }).addTo(map);

                    var group = single ? L.layerGroup() : L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });
                    var markers = {}, bounds = [];

                    items.forEach(function (p) {
                        var icon = L.divIcon({ className: 'fp-pin', html: pinSvg(p.onDuty), iconSize: [34, 42], iconAnchor: [17, 41], popupAnchor: [0, -38] });
                        var m = L.marker([p.lat, p.lng], { icon: icon });
                        m.bindPopup(infoHtml(p), { className: 'fp-popup', maxWidth: 280 });
                        m.on('click', function () { onSelect(p.id); });
                        group.addLayer(m);
                        markers[p.id] = m;
                        bounds.push([p.lat, p.lng]);
                    });
                    group.addTo(map);

                    if (single) { map.setView(bounds[0], 16); }
                    else if (bounds.length > 1) { map.fitBounds(bounds, { padding: [30, 30] }); }
                    else { map.setView(bounds[0], 14); }

                    return {
                        open: function (id) {
                            var m = markers[id]; if (!m) { return; }
                            var show = function () { map.setView(m.getLatLng(), Math.max(map.getZoom(), 16)); m.openPopup(); };
                            if (group.zoomToShowLayer) { group.zoomToShowLayer(m, show); } else { show(); }
                        },
                        resize: function () { map.invalidateSize(); }
                    };
                });
            }
        };
    }

    /* ---------- moteur Google Maps ---------- */

    var googlePromise = null;
    function loadGoogle(key) {
        if (window.google && google.maps && google.maps.Map) { return Promise.resolve(); }
        if (googlePromise) { return googlePromise; }
        googlePromise = new Promise(function (resolve, reject) {
            window.__fpMapsReady = resolve;
            // Erreur de clé / facturation / referrer : Google appelle gm_authFailure.
            window.gm_authFailure = function () { reject(new Error('gm_authFailure')); };
            loadScript('https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
                '&callback=__fpMapsReady&loading=async&language=' + encodeURIComponent(document.documentElement.lang || 'fr'))
                .catch(reject);
        });
        return googlePromise;
    }

    function googleDriver(key) {
        return {
            name: 'google',
            mount: function (container, items, onSelect, single) {
                return loadGoogle(key).then(function () {
                    return single ? Promise.resolve() : loadScript(CDN.gClusterJs).catch(function () { /* clusters facultatifs */ });
                }).then(function () {
                    var map = new google.maps.Map(container, {
                        center: MOROCCO_CENTER, zoom: 6,
                        mapTypeControl: false, streetViewControl: false, fullscreenControl: true,
                        gestureHandling: 'cooperative',
                        styles: [{ featureType: 'poi.business', stylers: [{ visibility: 'off' }] }]
                    });
                    var info = new google.maps.InfoWindow({ maxWidth: 300 });
                    var markers = {}, list = [], bounds = new google.maps.LatLngBounds();

                    items.forEach(function (p) {
                        var m = new google.maps.Marker({
                            position: { lat: p.lat, lng: p.lng },
                            title: p.name,
                            icon: { url: pinDataUri(p.onDuty), scaledSize: new google.maps.Size(34, 42), anchor: new google.maps.Point(17, 41) }
                        });
                        m.addListener('click', function () { select(p); onSelect(p.id); });
                        markers[p.id] = { marker: m, data: p };
                        list.push(m);
                        bounds.extend(m.getPosition());
                    });

                    if (!single && window.markerClusterer && markerClusterer.MarkerClusterer) {
                        new markerClusterer.MarkerClusterer({ map: map, markers: list });
                    } else {
                        list.forEach(function (m) { m.setMap(map); });
                    }

                    if (single || items.length === 1) { map.setCenter(bounds.getCenter()); map.setZoom(16); }
                    else { map.fitBounds(bounds, 40); }

                    function select(p) {
                        info.setContent(infoHtml(p));
                        info.open({ map: map, anchor: markers[p.id].marker });
                    }

                    return {
                        open: function (id) {
                            var e = markers[id]; if (!e) { return; }
                            map.panTo(e.marker.getPosition());
                            if (map.getZoom() < 15) { map.setZoom(16); }
                            select(e.data);
                        },
                        resize: function () { google.maps.event.trigger(map, 'resize'); }
                    };
                });
            }
        };
    }

    /* ---------- orchestration ---------- */

    function mount(container, single) {
        var items = single ? singleItem(container) : readItems(container);
        if (!items.length) { showEmpty(container); return; }

        var cards = document.querySelectorAll('[data-pharmacy-card]');
        function highlightCard(id) {
            cards.forEach(function (c) {
                c.classList.toggle('pharmacy-card--active', c.getAttribute('data-pharmacy-card') === String(id));
            });
            var card = document.querySelector('[data-pharmacy-card="' + id + '"]');
            if (card && card.scrollIntoView) { card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        }

        var key = (document.querySelector('meta[name="fp-maps-key"]') || {}).content || '';
        var attempt = key ? googleDriver(key).mount(container, items, highlightCard, single)
                          : Promise.reject(new Error('no-key'));

        attempt.catch(function () {
            // Repli automatique : Leaflet/OSM (clé absente, invalide, quota, réseau).
            container.innerHTML = '';
            container.className = container.className.replace(/\bgm-style\b/g, '');
            return leafletDriver().mount(container, items, highlightCard, single);
        }).then(function (api) {
            if (!api) { return; }
            cards.forEach(function (card) {
                var id = card.getAttribute('data-pharmacy-card');
                card.querySelectorAll('[data-view-on-map]').forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        api.open(id);
                        if (window.matchMedia('(max-width: 900px)').matches) {
                            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                });
            });
            var toggle = document.querySelector('[data-map-toggle]');
            if (toggle) {
                toggle.addEventListener('click', function () {
                    document.body.classList.toggle('map-open');
                    setTimeout(api.resize, 50);
                });
            }
        }).catch(function () { showEmpty(container); });
    }

    function singleItem(container) {
        var lat = parseFloat(container.getAttribute('data-lat'));
        var lng = parseFloat(container.getAttribute('data-lng'));
        if (!lat || !lng) { return []; }
        return [{
            id: 'single', name: container.getAttribute('data-name') || '', address: container.getAttribute('data-address') || '',
            lat: lat, lng: lng, onDuty: false, url: container.getAttribute('data-url') || '#',
            phone: container.getAttribute('data-phone') || '', photo: ''
        }];
    }

    function lazyMount(container, single) {
        if (!('IntersectionObserver' in window)) { mount(container, single); return; }
        var io = new IntersectionObserver(function (entries) {
            if (entries.some(function (e) { return e.isIntersecting; })) {
                io.disconnect();
                mount(container, single);
            }
        }, { rootMargin: '300px' });
        io.observe(container);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var listMap = document.querySelector('[data-map="list"]');
        var detailMap = document.querySelector('[data-map="detail"]');
        if (listMap) { lazyMount(listMap, false); }
        if (detailMap) { lazyMount(detailMap, true); }
    });
})();
