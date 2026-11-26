// /islands WebGL belt: orbit model, search parsing, manifest rows, demo belt. `node --test tests/js/*.test.mjs`
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    BELT_EPOCH, BELT_INNER, BELT_OUTER, MIN_PERIAPSIS, PLANET_RADIUS, demoIslands, islandsFromManifest, meanAnomalyAt, orbitFor, orbitPath,
    parseIslandQuery, positionAt, rand01, sizeScaleFor, solveKepler, homeFrame,
} from '../../resources/js/islands/belt/orbits.mjs';
import {designation, setDesignationPrefix, getDesignationPrefix} from '../../resources/js/islands/compose.mjs';

const len = (v) => Math.hypot(v[0], v[1], v[2]);

test('orbits are deterministic in the island id and stay clear of the planet', () => {
    assert.deepEqual(orbitFor(7, 1), orbitFor(7, 1));
    assert.notDeepEqual(orbitFor(7, 1).P, orbitFor(8, 1).P);
    let steep = 0;
    for (let id = 1; id <= 3000; id++) {
        const o = orbitFor(id, id % 3);
        assert.ok(o.a >= BELT_INNER && o.a <= BELT_OUTER, 'semi-major axis in the belt');
        assert.ok(o.e >= 0 && o.e < 0.3, 'low eccentricity');
        assert.ok(o.a * (1 - o.e) >= MIN_PERIAPSIS - 1e-9, 'periapsis outside the atmosphere');
        assert.ok(Math.abs(o.inc) <= 43 * Math.PI / 180);
        if (Math.abs(o.inc) > 21 * Math.PI / 180) { steep++; }
        // every point of the path is between periapsis and apoapsis, and outside the planet
        for (const M of [0, 1, 2.5, 4, 6]) {
            const r = len(positionAt(o, M));
            assert.ok(r > PLANET_RADIUS * 1.4 && r <= o.a * (1 + o.e) + 1e-6 && r >= o.a * (1 - o.e) - 1e-6);
        }
    }
    assert.ok(steep > 150 && steep < 400, 'about one in twelve on a steep orbit: ' + steep);
});

test('Kepler solver: 3 Newton steps are exact enough for the belt (same code as the vertex shader)', () => {
    for (const e of [0, 0.05, 0.15, 0.26]) {
        for (let M = 0; M < 6.3; M += 0.37) {
            const E = solveKepler(M, e);
            assert.ok(Math.abs(E - e * Math.sin(E) - M) < 2e-6, `residual e=${e} M=${M}`);
        }
    }
});

test('positions follow wall-clock time: same place for everyone, one period later the same place again', () => {
    const o = orbitFor(42, 0);
    const t = BELT_EPOCH + 123456.789;
    const a = positionAt(o, meanAnomalyAt(o, t));
    const b = positionAt(o, meanAnomalyAt(o, t + 2 * Math.PI / o.n));
    const c = positionAt(o, meanAnomalyAt(o, t + 30));
    assert.ok(len([a[0] - b[0], a[1] - b[1], a[2] - b[2]]) < 1e-6);
    assert.ok(len([a[0] - c[0], a[1] - c[1], a[2] - c[2]]) > 0.5, 'it moves');
    assert.ok(meanAnomalyAt(o, BELT_EPOCH - 5) >= 0);
    // inner islands are faster than outer ones (Kepler's third law)
    const inner = [...Array(400).keys()].map((i) => orbitFor(i + 1)).sort((x, y) => x.a - y.a);
    assert.ok(inner[0].n > inner[399].n * 2);
    // the orbit line passes through the island position
    const path = orbitPath(o, 720);
    let best = Infinity;
    for (let k = 0; k < 720; k++) { best = Math.min(best, len([path[k * 3] - a[0], path[k * 3 + 1] - a[1], path[k * 3 + 2] - a[2]])); }
    assert.ok(best < 0.3);
});

test('sprites shrink as the belt fills up (orbits never change)', () => {
    assert.equal(sizeScaleFor(1), 1.5);
    assert.equal(sizeScaleFor(7), 1.5);
    assert.equal(sizeScaleFor(71), 1.5);
    assert.equal(sizeScaleFor(160), 1);
    assert.ok(Math.abs(sizeScaleFor(640) - 0.5) < 1e-9);
    assert.equal(sizeScaleFor(2000), 0.5);
    assert.equal(sizeScaleFor(20000), 0.5);
});

test('rand01 is a stable hash in [0, 1)', () => {
    assert.equal(rand01(5, 3), rand01(5, 3));
    let sum = 0;
    for (let i = 0; i < 5000; i++) { const v = rand01(i, 1); assert.ok(v >= 0 && v < 1); sum += v; }
    assert.ok(Math.abs(sum / 5000 - 0.5) < 0.02);
});

test('search box: designation, number, any sector-style prefix', () => {
    assert.equal(parseIslandQuery('7'), 7);
    assert.equal(parseIslandQuery(' #0007 '), 7);
    assert.equal(parseIslandQuery('K313/0007'), 7);
    assert.equal(parseIslandQuery('k313 7'), 7);
    assert.equal(parseIslandQuery('k-313 #12'), 12);
    assert.equal(parseIslandQuery('K-334/0042'), 42);
    assert.equal(parseIslandQuery('2000'), 2000);
    assert.equal(parseIslandQuery('313'), 313);
    assert.equal(parseIslandQuery('Meadow Outpost'), null);
    assert.equal(parseIslandQuery('0'), null);
    assert.equal(parseIslandQuery(''), null);
});

test('designation prefix comes from the contract setting (K313/ fallback)', () => {
    assert.equal(designation(7), 'K313/0007');
    assert.equal(setDesignationPrefix('NC-9 '), 'NC-9');
    assert.equal(designation(12345), 'NC-912345');
    assert.equal(setDesignationPrefix('<script>'), 'NC-9', 'unsafe prefixes are ignored');
    assert.equal(setDesignationPrefix(null), 'NC-9');
    setDesignationPrefix('K313/');
    assert.equal(getDesignationPrefix(), 'K313/');
});

const manifest = {
    prefix: 'K313/', capacity: {common: 5, epic: 25, legendary: 125}, cell: {w: 96, h: 128, cols: 21, rows: 16, per: 336, size: 2048},
    atlases: {0: '/api/islands/belt/atlas/0-abc.webp'}, owners: ['0x27407eda8e2bb47c'],
    islands: [[1, 0, '', 0, 0, 1795651200, 1, 'aaaaaaaaaaaa'], [2, 1, 'Homium', 4, 0, 1795651300, 1, 'bbbbbbbbbbbb'], [337, 2, '', 0, -1, 0, 0, '']],
};

test('manifest rows -> islands (cell = fixed by the id)', () => {
    const list = islandsFromManifest(manifest);
    assert.equal(list.length, 3);
    assert.deepEqual({...list[1]}, {
        id: 2, r: 1, rarity: 'epic', name: 'Homium', residents: 4, capacity: 25, owner: '0x27407eda8e2bb47c', updated: 1795651300, hasCell: true,
        key: 'bbbbbbbbbbbb', page: '0', slot: 1, designation: 'K313/0002', url: '/islands/2', demo: false,
    });
    assert.equal(list[2].page, '1');
    assert.equal(list[2].slot, 0);
    assert.equal(list[2].owner, null);
    assert.equal(list[2].hasCell, false);
});

test('demo belt: N placeholders that reuse real art, own cells, never real ids', () => {
    const real = islandsFromManifest(manifest);
    const demo = demoIslands(real, 2000, 336, 'K313/');
    assert.equal(demo.length, 2000);
    assert.equal(demo[0].id, 338);
    assert.equal(demo[0].designation, 'K313/0338');
    assert.ok(demo.every((d) => d.demo && d.hasCell && [1, 2].includes(d.src) && /^d\d+$/.test(d.page) && d.slot < 336));
    assert.equal(new Set(demo.map((d) => d.page + ':' + d.slot)).size, 2000);
    assert.equal(new Set(demo.map((d) => d.page)).size, 6);
    const share = (r) => demo.filter((d) => d.r === r).length / 2000;
    assert.ok(share(0) > 0.64 && share(0) < 0.76 && share(2) > 0.03 && share(2) < 0.09);
    assert.deepEqual(demoIslands(real, 2000), demoIslands(real, 2000), 'deterministic');
    assert.deepEqual(demoIslands([], 100), []);
    assert.deepEqual(demoIslands(real.filter((i) => !i.hasCell), 100), []);
});

test('home view: close for a handful of islands, widening as the belt fills, portrait fits the width', () => {
    const apo = (n) => Array.from({length: n}, (_, i) => { const o = orbitFor(i + 1, 0); return o.a * (1 + o.e); });
    const tv = Math.tan(23 * Math.PI / 180);
    const d = (n, aspect) => homeFrame(apo(n), aspect).dist;
    // closer while the belt is small; monotonic as it fills up
    assert.ok(d(7, 1.6) < 50, 'desktop, 7 islands: close framing');
    assert.ok(d(7, 1.6) < d(20, 1.6) && d(20, 1.6) < d(160, 1.6) && d(160, 1.6) <= d(2007, 1.6) + 1e-9);
    // the full belt still fits a landscape screen (framed radius vs half-width at that distance)
    const full = homeFrame(apo(2007), 1.6);
    assert.ok(full.radius <= full.dist * tv * 1.6 * 1.001);
    // portrait: steeper look-down, width-fitted (at most a ~25% crop), and never inside the planet's halo
    for (const n of [1, 7, 2007]) {
        const f = homeFrame(apo(n), 390 / 844);
        assert.ok(f.elevation > 0.8 && f.elevation <= 1.0);
        assert.ok(f.radius <= f.dist * tv * (390 / 844) * 1.27 + 1e-9, 'portrait n=' + n);
        assert.ok(f.dist > PLANET_RADIUS * 2.6);
    }
    assert.equal(homeFrame(apo(7), 1.6).elevation, 0.3);
    // empty belt: still a sane view
    assert.ok(homeFrame([], 1.6).dist > PLANET_RADIUS * 2.5);
});
