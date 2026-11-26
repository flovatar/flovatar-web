/**
 * Asteroid belt of /islands: orbit model shared by the WebGL scene (GPU: vertex shader, same maths) and the CPU side
 * (picking, camera follow, tests). Pure functions, no DOM, no three.js: `node --test tests/js/islands-belt.test.mjs`.
 *
 * Every island gets its own Kepler orbit around the planet at the origin (belt plane = XZ, +Y up). All elements are a
 * hash of the island id, and the mean anomaly runs on wall-clock time from a fixed epoch, so an island is at the same
 * place for every visitor and keeps moving between visits.
 */

export const RARITIES = ['common', 'epic', 'legendary'];
/** Brand rarity fills (brand.flovatar.com §9.3): common #F5F5F5, epic #FFEE50, legendary #EB3CF2. */
export const RARITY_COLORS = ['#F5F5F5', '#FFEE50', '#EB3CF2'];

export const PLANET_RADIUS = 10;
export const BELT_INNER = 17;
export const BELT_OUTER = 62;
/** Closest an island may come to the planet centre (cloud tops are at PLANET_RADIUS). */
export const MIN_PERIAPSIS = 14.5;
/** Orbital period (seconds) at BELT_INNER; outer orbits follow Kepler's third law (T ~ a^1.5). */
export const PERIOD_INNER = 150;
/** Sprite height in world units per rarity (width = 3/4 of it, like the island art). */
export const ISLAND_SIZE = [3.3, 4.1, 5.2];
/**
 * A handful of islands can be drawn large; a full belt needs smaller sprites or it hides the planet. Scale of the
 * sprite size for a belt of n islands: 1.5 up to about 70 islands, then shrinking with the square root (1 at 160),
 * never below 0.5. Only the drawn size changes with n, never an orbit.
 */
export function sizeScaleFor(n) {
    return Math.max(0.5, Math.min(1.5, Math.sqrt(160 / Math.max(1, n))));
}

/**
 * Default ("home") camera framing for a belt whose islands reach the given apoapsis radii, on a viewport of the given
 * aspect (width / height) with a vertical field of view of `fov` degrees.
 *
 * - A handful of islands: frame the inner three quarters of them closely (the outer few drift in and out of
 *   the frame as the camera turns). The framed radius then widens smoothly (log scale) to the whole belt from about
 *   160 islands on, i.e. the old "fit the belt" view.
 * - Portrait screens: the camera looks down more steeply (the belt ellipse gets rounder and uses the tall screen) and
 *   the framing is fitted to the narrow width, allowing a slight crop at the sides.
 * Returns {dist, elevation (rad), radius (framed world radius)}.
 */
export function homeFrame(apo, aspect = 16 / 9, fov = 46) {
    const n = apo.length;
    const s = Array.from(apo).sort((a, b) => a - b);
    const q = (p) => s[Math.min(n - 1, Math.max(0, Math.floor((n - 1) * p)))];
    const full = n ? 0.5 * q(0.85) + 0.5 * s[n - 1] : BELT_INNER * 1.5;
    const close = Math.min(full, Math.max(PLANET_RADIUS * 2.3, n ? q(0.75) + 3 : BELT_INNER * 1.5));
    const t = Math.max(0, Math.min(1, Math.log(Math.max(1, n) / 8) / Math.log(160 / 8)));
    const w = t * t * (3 - 2 * t);
    const radius = close + (full - close) * w;
    const a = Math.max(0.3, Math.min(4, aspect || 1));
    const portrait = Math.max(0, Math.min(0.6, 1 - a));
    const elevation = Math.min(1.0, 0.30 + portrait * 1.2);
    const tv = Math.tan(fov * Math.PI / 360);
    const th = tv * a;
    const crop = 1 + portrait * 0.5;                           // portrait: the ring may overflow the sides a little
    const dh = (1.08 - portrait * 0.25) * radius / (th * crop);
    // near edge of the tilted ring (bottom of the frame): height R sin(el) at depth d - R cos(el), plus room for the
    // island art standing on it
    const dv = radius * (Math.sin(elevation) / tv + Math.cos(elevation)) + 6;
    const dist = Math.max(dh, dv, PLANET_RADIUS * 1.6 / Math.min(tv, th), PLANET_RADIUS * 2.6);
    return {dist: Math.min(dist, 320), elevation, radius};
}

/** 2026-11-26T00:00:00Z: positions are a function of (id, now - epoch). */
export const BELT_EPOCH = 1795651200;

const TAU = Math.PI * 2;

/** 32-bit integer hash of (id, salt) -> [0, 1). */
export function rand01(id, salt = 0) {
    let h = (Math.imul(id | 0, 0x9E3779B1) ^ Math.imul((salt | 0) + 0x7F4A7C15, 0x85EBCA6B)) >>> 0;
    h ^= h >>> 16; h = Math.imul(h, 0x7FEB352D) >>> 0;
    h ^= h >>> 15; h = Math.imul(h, 0x846CA68B) >>> 0;
    h ^= h >>> 16;
    return (h >>> 0) / 4294967296;
}

/**
 * Orbital elements of an island (deterministic in id; rarity only sets the sprite size).
 * a semi-major axis, e eccentricity, inc inclination, node longitude of the ascending node, peri argument of
 * periapsis, m0 mean anomaly at BELT_EPOCH, n mean motion (rad/s), P/Q in-plane basis vectors scaled so that
 * position = P * (cos E - e) + Q * sin E.
 */
export function orbitFor(id, rarity = 0) {
    const a = BELT_INNER + (BELT_OUTER - BELT_INNER) * rand01(id, 1);
    let e = 0.02 + 0.24 * Math.pow(rand01(id, 2), 1.6);
    e = Math.min(e, 1 - MIN_PERIAPSIS / a);
    // mostly near the belt plane (triangular spread, +-20 deg), one in twelve on a steep orbit (up to +-42 deg)
    const spread = (rand01(id, 3) + rand01(id, 4) + rand01(id, 5)) / 1.5 - 1;
    const steep = rand01(id, 6) < 1 / 12;
    const inc = (steep ? 22 + 20 * rand01(id, 7) : 3 + 17 * Math.abs(spread)) * (spread < 0 ? -1 : 1) * Math.PI / 180;
    const node = TAU * rand01(id, 8);
    const peri = TAU * rand01(id, 9);
    const m0 = TAU * rand01(id, 10);
    const n = TAU / (PERIOD_INNER * Math.pow(a / BELT_INNER, 1.5));

    const cO = Math.cos(node), sO = Math.sin(node), cw = Math.cos(peri), sw = Math.sin(peri), ci = Math.cos(inc), si = Math.sin(inc);
    // perifocal basis in a z-up frame, then (x, y, z) -> (x, z, -y) for the y-up scene
    const px = cO * cw - sO * sw * ci, py = sO * cw + cO * sw * ci, pz = sw * si;
    const qx = -cO * sw - sO * cw * ci, qy = -sO * sw + cO * cw * ci, qz = cw * si;
    const b = a * Math.sqrt(1 - e * e);
    return {
        id, a, e, inc, node, peri, m0, n,
        P: [a * px, a * pz, -a * py],
        Q: [b * qx, b * qz, -b * qy],
        size: ISLAND_SIZE[rarity] || ISLAND_SIZE[0],
        seed: rand01(id, 11),
    };
}

/** Mean anomaly (0..2pi) at a wall-clock time (unix seconds). Doubles: exact enough for years. */
export function meanAnomalyAt(orbit, unixSeconds) {
    const m = (orbit.m0 + orbit.n * (unixSeconds - BELT_EPOCH)) % TAU;
    return m < 0 ? m + TAU : m;
}

/** Eccentric anomaly; same start value and Newton steps as the vertex shader. */
export function solveKepler(M, e, steps = 3) {
    let E = M + e * Math.sin(M);
    for (let k = 0; k < steps; k++) { E -= (E - e * Math.sin(E) - M) / (1 - e * Math.cos(E)); }
    return E;
}

/** Position for mean anomaly M, written into out[0..2] (out may be an array or a typed array at `offset`). */
export function positionAt(orbit, M, out = [0, 0, 0], offset = 0) {
    const E = solveKepler(M, orbit.e);
    const c = Math.cos(E) - orbit.e, s = Math.sin(E);
    out[offset] = orbit.P[0] * c + orbit.Q[0] * s;
    out[offset + 1] = orbit.P[1] * c + orbit.Q[1] * s;
    out[offset + 2] = orbit.P[2] * c + orbit.Q[2] * s;
    return out;
}

/** Points of the whole ellipse (for the orbit line of the highlighted island). */
export function orbitPath(orbit, segments = 160) {
    const out = new Float32Array(segments * 3);
    for (let k = 0; k < segments; k++) {
        const E = TAU * k / segments;
        const c = Math.cos(E) - orbit.e, s = Math.sin(E);
        out[k * 3] = orbit.P[0] * c + orbit.Q[0] * s;
        out[k * 3 + 1] = orbit.P[1] * c + orbit.Q[1] * s;
        out[k * 3 + 2] = orbit.P[2] * c + orbit.Q[2] * s;
    }
    return out;
}

/**
 * Search box -> island id: "7", "#7", "0007", "K313/0007", "k313 7", "k-313 #0007" (any sector-style prefix).
 * null when the text is not a designation or id (the caller then searches names).
 */
export function parseIslandQuery(text) {
    const s = String(text || '').trim().toLowerCase();
    if (s === '') { return null; }
    const m = /^(?:[a-z]{1,3}[\s-]*\d{2,4}[\s/#-]+)?#?\s*(\d{1,9})$/.exec(s);
    if (!m) { return null; }
    const id = parseInt(m[1], 10);
    return id > 0 ? id : null;
}

export function designationOf(id, prefix = 'K313/') {
    return prefix + String(id).padStart(4, '0');
}

/** Manifest rows ([id, rarity, name, residents, ownerIndex, updated, hasCell, key]) -> island objects. */
export function islandsFromManifest(m) {
    const per = (m.cell && m.cell.per) || 336;
    const prefix = m.prefix || 'K313/';
    return (m.islands || []).map((r) => {
        const id = r[0];
        const rarity = RARITIES[r[1]] || 'common';
        return {
            id, r: r[1] | 0, rarity, name: r[2] || '', residents: r[3] | 0,
            capacity: (m.capacity && m.capacity[rarity]) || 0,
            owner: r[4] >= 0 ? (m.owners[r[4]] || null) : null,
            updated: r[5] | 0, hasCell: !!r[6], key: r[7] || '',
            page: String(Math.floor((id - 1) / per)), slot: (id - 1) % per,
            designation: designationOf(id, prefix), url: '/islands/' + id, demo: false,
        };
    });
}

/**
 * ?demo: `total` placeholder islands that reuse the art of the real ones (never stored, never linked as real).
 * Ids continue after the highest real id; rarities 70 / 24 / 6 %. Each gets its own cell on a demo atlas page
 * ("d0", "d1", ...) so texture memory and draw calls match a real belt of that size.
 */
export function demoIslands(real, total, per = 336, prefix = 'K313/') {
    const withArt = real.filter((i) => i.hasCell);
    const out = [];
    if (!withArt.length || total <= 0) { return out; }
    let id = real.reduce((mx, i) => Math.max(mx, i.id), 0);
    for (let k = 0; k < total; k++) {
        id++;
        const u = rand01(id, 21);
        const r = u < 0.70 ? 0 : (u < 0.94 ? 1 : 2);
        const src = withArt[Math.floor(rand01(id, 22) * withArt.length)];
        const cap = [5, 25, 125][r];
        out.push({
            id, r, rarity: RARITIES[r], name: '', residents: Math.floor(rand01(id, 23) * rand01(id, 24) * (cap + 1)), capacity: cap,
            owner: null, updated: 0, hasCell: true, key: src.key, page: 'd' + Math.floor(k / per), slot: k % per,
            designation: designationOf(id, prefix), url: src.url, demo: true, src: src.id, srcPage: src.page, srcSlot: src.slot,
            hue: Math.floor(rand01(id, 25) * 5) * 12 - 24,
        });
    }
    return out;
}
