/*
 * GEO Location editor (Flovatar / Psyche Likee setPosition, Đ10) on free OpenStreetMap services, no API key.
 *  - search-as-you-type: GET /api/v2/geo/search?q=  (our cached proxy -> Photon)
 *  - Enter / explicit search: ...&submit=1          (proxy may fall back to Nominatim; never used for autocomplete)
 *  - "lat, lon" typed or clicked on the map: no geocoding, only a cached reverse lookup for the label
 *  - click-on-map picker: Leaflet (self-hosted, loaded only when the map is opened) + tiles from window.GEO_CONFIG
 * Only lat/lon go on chain. Everything fails gracefully (coordinates and the map keep working without the geocoder).
 * Data/map © OpenStreetMap contributors.
 */

const cache = new Map();
const MAX_CACHE = 200;
const LEAFLET = '/vendor/leaflet-1.9.4/';
let leafletPromise = null;

function cfg() {
    return window.GEO_CONFIG || {};
}

function apiBase() {
    return (window.HEADLESS && window.HEADLESS.apiBase) || '';
}

function remember(key, value) {
    if (cache.size >= MAX_CACHE) {
        cache.delete(cache.keys().next().value);
    }
    cache.set(key, value);
    return value;
}

/** "45.46, 9.19" / "45.46 9.19" / "45.46;9.19" -> {lat, lon} (range-checked) or null */
export function parseLatLon(text) {
    const m = String(text || '').trim().match(/^(-?\d{1,2}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)$/);
    if (!m) return null;
    const lat = parseFloat(m[1]), lon = parseFloat(m[2]);
    if (!isFinite(lat) || !isFinite(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) return null;
    return {lat, lon};
}

export function formatLatLon(lat, lon) {
    return Number(lat).toFixed(5) + ', ' + Number(lon).toFixed(5);
}

async function getJson(path, params) {
    const r = await window.axios.get(apiBase() + path, {params, timeout: 10000});
    return r.data;
}

export async function search(q, explicit = false) {
    q = String(q || '').trim().replace(/\s+/g, ' ').slice(0, 120);
    const key = 's|' + q.toLowerCase() + '|' + (explicit ? 1 : 0);
    if (cache.has(key)) return cache.get(key);
    const data = await getJson('/api/v2/geo/search', explicit ? {q, submit: 1} : {q});
    const out = {ok: !!data.ok, source: data.source || null, results: Array.isArray(data.results) ? data.results : []};
    return out.ok ? remember(key, out) : out;
}

export async function reverse(lat, lon) {
    const key = 'r|' + Number(lat).toFixed(5) + ',' + Number(lon).toFixed(5);
    if (cache.has(key)) return cache.get(key);
    try {
        const data = await getJson('/api/v2/geo/reverse', {lat: Number(lat).toFixed(5), lon: Number(lon).toFixed(5)});
        return data.ok ? remember(key, data.label || null) : null;
    } catch (e) {
        return null;
    }
}

export function loadLeaflet() {
    if (window.L && window.L.map) return Promise.resolve(window.L);
    if (leafletPromise) return leafletPromise;
    leafletPromise = new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = LEAFLET + 'leaflet.css';
        document.head.appendChild(css);
        const s = document.createElement('script');
        s.src = LEAFLET + 'leaflet.js';
        s.async = true;
        s.onload = () => (window.L ? resolve(window.L) : reject(new Error('Leaflet missing')));
        s.onerror = () => { leafletPromise = null; reject(new Error('Leaflet failed to load')); };
        document.head.appendChild(s);
    });
    return leafletPromise;
}

/**
 * Shared Alpine state + methods for the editor. latKey/lonKey = the component's existing coordinate fields
 * (flovatarLat/flovatarLon, collectibleLat/collectibleLon); searchAddress/foundAddress already exist there.
 */
export function geoEditor(latKey, lonKey) {
    // Leaflet objects stay outside Alpine's reactive proxies (closure per component instance)
    let map = null, marker = null;
    return {
        geoSuggestions: [],
        geoBusy: false,
        geoError: '',
        geoMapOpen: false,
        geoSeq: 0,
        geoLastQ: null,

        geoSetPoint(lat, lon, label) {
            this[latKey] = lat;
            this[lonKey] = lon;
            this.foundAddress = label || formatLatLon(lat, lon);
            this.geoSyncMarker(true);
        },
        geoClear() {
            this[latKey] = 0;
            this[lonKey] = 0;
            this.foundAddress = '';
        },
        geocodeAddress(explicit = false) {
            const q = String(this.searchAddress || '').trim();
            // the Flovatar page also calls this from a searchAddress watcher: don't let that repeat (and supersede)
            // a lookup that is already done or running for the same text
            if (!explicit && q === this.geoLastQ) return;
            this.geoLastQ = q;
            const seq = ++this.geoSeq;
            this.geoError = '';
            if (!q) {
                this.geoSuggestions = [];
                this.geoClear();
                return;
            }
            const point = parseLatLon(q);
            if (point) {
                this.geoSuggestions = [];
                this.geoSetPoint(point.lat, point.lon);
                reverse(point.lat, point.lon).then(label => {
                    if (seq === this.geoSeq && label) this.foundAddress = label + ' (' + formatLatLon(point.lat, point.lon) + ')';
                });
                return;
            }
            if (q.length < 3 && !explicit) {
                this.geoSuggestions = [];
                return;
            }
            this.geoBusy = true;
            search(q, explicit).then(r => {
                if (seq !== this.geoSeq) return; // a newer keystroke won
                this.geoBusy = false;
                if (!r.ok) {
                    this.geoError = 'Address search is unavailable right now. Type coordinates (lat, lon) or pick the spot on the map.';
                    return;
                }
                this.geoSuggestions = r.results;
                if (r.results.length) {
                    const first = r.results[0];
                    this.geoSetPoint(first.lat, first.lon, first.label);
                } else {
                    this.geoClear();
                    this.geoError = explicit ? 'Nothing found. Try another address, coordinates (lat, lon) or the map.'
                        : 'Nothing found yet. Press Enter to search harder, or pick the spot on the map.';
                }
            }).catch(() => {
                if (seq !== this.geoSeq) return;
                this.geoBusy = false;
                this.geoError = 'Address search is unavailable right now. Type coordinates (lat, lon) or pick the spot on the map.';
            });
        },
        geoPick(s) {
            this.geoSeq++;
            this.geoSuggestions = [];
            this.geoError = '';
            this.geoSetPoint(s.lat, s.lon, s.label);
        },
        geoToggleMap() {
            mapOpen = !mapOpen;
            if (!mapOpen) return;
            this.$nextTick(() => {
                loadLeaflet().then(L => {
                    const el = this.$refs.geoMap;
                    if (!el) return;
                    if (map) {
                        map.invalidateSize();
                        this.geoSyncMarker(true);
                        return;
                    }
                    const c = cfg();
                    const has = this[latKey] || this[lonKey];
                    map = L.map(el, {worldCopyJump: true}).setView(has ? [this[latKey], this[lonKey]] : [25, 0], has ? 11 : 2);
                    L.tileLayer(c.tileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: c.tileAttribution || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    }).addTo(map);
                    this.geoSyncMarker(false);
                    if (this.geoCanEdit()) {
                        map.on('click', e => {
                            const lat = Math.max(-90, Math.min(90, e.latlng.lat));
                            const lon = ((e.latlng.lng + 540) % 360) - 180; // wrap world copies to -180..180
                            this.geoSeq++;
                            this.geoLastQ = formatLatLon(lat, lon);
                            this.searchAddress = this.geoLastQ;
                            this.geoSuggestions = [];
                            this.geoError = '';
                            this.geoSetPoint(lat, lon);
                            const seq = this.geoSeq;
                            reverse(lat, lon).then(label => {
                                if (seq === this.geoSeq && label) this.foundAddress = label + ' (' + formatLatLon(lat, lon) + ')';
                            });
                        });
                    }
                }).catch(() => {
                    this.geoError = 'The map could not be loaded. You can still type an address or coordinates.';
                    mapOpen = false;
                });
            });
        },
        geoSyncMarker(pan) {
            if (!map || !window.L) return;
            const lat = this[latKey], lon = this[lonKey];
            if (!lat && !lon) return;
            if (marker) {
                marker.setLatLng([lat, lon]);
            } else {
                // default icon paths, explicit so Leaflet doesn't guess them from the CSS url
                const icon = window.L.icon({
                    iconUrl: LEAFLET + 'images/marker-icon.png', iconRetinaUrl: LEAFLET + 'images/marker-icon-2x.png',
                    shadowUrl: LEAFLET + 'images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], shadowSize: [41, 41],
                });
                marker = window.L.marker([lat, lon], {icon}).addTo(map);
            }
            if (pan) map.setView([lat, lon], Math.max(map.getZoom(), 11));
        },
        geoCanEdit() {
            return typeof this.isOwned === 'function' ? !!this.isOwned() : false;
        },
    };
}

window.FlovatarGeo = {parseLatLon, formatLatLon, search, reverse, loadLeaflet, geoEditor};
