// Unit tests for the OpenStreetMap GEO Location editor helpers (resources/js/geo.js): `node --test tests/js/*.test.mjs`
import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = globalThis.window || {};
const {parseLatLon, formatLatLon, geoEditor} = await import('../../resources/js/geo.js');

test('parseLatLon accepts "lat, lon" variants and range-checks', () => {
    assert.deepEqual(parseLatLon('45.46, 9.19'), {lat: 45.46, lon: 9.19});
    assert.deepEqual(parseLatLon(' -33.9 151.2 '), {lat: -33.9, lon: 151.2});
    assert.deepEqual(parseLatLon('10;-170.5'), {lat: 10, lon: -170.5});
    assert.equal(parseLatLon('91, 0'), null);
    assert.equal(parseLatLon('0, 181'), null);
    assert.equal(parseLatLon('Milan'), null);
    assert.equal(parseLatLon(''), null);
});

test('formatLatLon uses 5 decimals', () => {
    assert.equal(formatLatLon(45.4642, 9.19), '45.46420, 9.19000');
});

test('geoEditor: map toggle flips the reactive flag (no stray globals)', () => {
    const o = Object.assign({lat: 0, lon: 0, foundAddress: '', searchAddress: '', $nextTick() {}, $refs: {}}, geoEditor('lat', 'lon'));
    o.geoToggleMap();
    assert.equal(o.geoMapOpen, true);
    o.geoToggleMap();
    assert.equal(o.geoMapOpen, false);
});

test('geoEditor: typed coordinates set the point without geocoding; clearing resets it', () => {
    window.axios = {get: () => Promise.reject(new Error('offline'))};
    const o = Object.assign({lat: 0, lon: 0, foundAddress: '', searchAddress: '45.5, 9.2', $nextTick() {}, $refs: {}}, geoEditor('lat', 'lon'));
    o.geocodeAddress();
    assert.equal(o.lat, 45.5);
    assert.equal(o.lon, 9.2);
    assert.equal(o.foundAddress, '45.50000, 9.20000');
    o.searchAddress = '';
    o.geocodeAddress();
    assert.equal(o.lat, 0);
    assert.equal(o.foundAddress, '');
});
