/**
 * WebGL belt of /islands (lazy chunk "b", loaded by resources/js/app/islands.js only on that page).
 *
 * A cloud-covered planet with thunderstorms at the origin, every island on its own tilted Kepler orbit around it
 * (./orbits.mjs; positions are computed in the vertex shader, so 2,000+ islands cost a handful of draw calls and no
 * per-frame CPU work), rarity halos, a starfield / nebula in the brand palette, drag / zoom camera with idle rotation.
 *
 * Textures (same origin, App\Services\Islands\IslandBelt): far islands sample 96x128 cells of atlas pages (one
 * instanced draw call per page, loaded one after another); the few islands that are large on screen get their own
 * 512 px sprite. Depth: islands are alpha-to-coverage cut-outs that write depth, so they sort among themselves and
 * disappear behind the planet without any CPU sorting.
 *
 * Motion safety: lightning only lights a patch of the planet (never the whole frame), strong flashes are capped at
 * three per second, and with prefers-reduced-motion flashes become slow dim swells, bolts are off, orbits and the idle
 * rotation slow down.
 */
import {
    AdditiveBlending, BackSide, BufferAttribute, BufferGeometry, CanvasTexture, Color, ConeGeometry, DynamicDrawUsage, FrontSide, Group,
    InstancedBufferAttribute, InstancedBufferGeometry, LinearFilter, LinearMipmapLinearFilter, LineBasicMaterial, LineLoop, Matrix4,
    Mesh, MeshBasicMaterial, OrthographicCamera, PerspectiveCamera, PlaneGeometry, Points, Scene, ShaderMaterial, SphereGeometry,
    Texture, Vector3, Vector4, WebGLRenderer, WebGLRenderTarget,
} from 'three';
import {OrbitControls} from 'three/examples/jsm/controls/OrbitControls.js';
import {BELT_EPOCH, BELT_INNER, BELT_OUTER, PLANET_RADIUS, RARITY_COLORS, homeFrame, meanAnomalyAt, orbitFor, orbitPath, rand01, sizeScaleFor, solveKepler} from './orbits.mjs';
import * as GL from './shaders';

const TAU = Math.PI * 2;
const FOV = 46;
const STORMS = 8;
const SUN = new Vector3(-0.62, 0.38, 0.69).normalize();
/** Nova City sits here under the clouds (planet object space). Only hinted at: see PLANET_FRAG cityLights + the spire. */
const CITY = new Vector3(0.36, 0.30, 0.88).normalize();
const ATMO_RADIUS = PLANET_RADIUS * 1.42;
const MIN_PX = 18;          // islands never get smaller than this on screen (CSS px)
const NEAR_PX = 150;        // on-screen height from which an island gets its own sprite
const HOVER_PX = 46;        // a highlighted island is drawn at least this tall, so its art can be read
const MAX_FRAC = 0.42;      // ...and, unless highlighted, never taller than this share of the viewport (close fly-bys)
const REBASE_SECONDS = 1800; // shader time is folded back into the orbit phases (float precision)

function rnd(a, b) { return a + Math.random() * (b - a); }

export function webglAvailable() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch (e) { return false; }
}

export class BeltScene {
    /**
     * @param {HTMLCanvasElement} canvas
     * @param {{reducedMotion?:boolean, low?:boolean, onHover?:Function, onSelect?:Function, onTrack?:Function,
     *          onLost?:Function, onInteract?:Function}} opts
     */
    constructor(canvas, opts = {}) {
        this.canvas = canvas;
        this.opts = opts;
        this.reduced = !!opts.reducedMotion;
        this.low = !!opts.low;
        this.items = [];
        this.index = new Map();
        this.pages = new Map();
        this.near = new Map();
        this.texCache = new Map();
        this.imgCache = new Map();
        this.hoverId = null;
        this.highlightId = null;
        this.followId = null;
        this.returning = false;
        this.running = false;
        this.disposed = false;
        this.clock = 0;          // scene seconds (drives clouds, storms)
        this.orbitClock = 0;     // seconds since the orbit phases were last rebased
        this.motion = this.reduced ? 0.25 : 1;
        this.stats = {fps: 0, ms: 0, dpr: 1, islands: 0, calls: 0, triangles: 0, tier: this.low ? 'low' : 'high', near: 0, pagesLoaded: 0, pages: 0};
        this._frames = [];
        this._tmp = new Vector3();
        this._tmp2 = new Vector3();
        this._vp = new Matrix4();

        const renderer = new WebGLRenderer({canvas, antialias: true, alpha: false, powerPreference: 'high-performance', stencil: false});
        this.renderer = renderer;
        const gl = renderer.getContext();
        this.coverage = !!(renderer.capabilities.isWebGL2 && gl.getContextAttributes().antialias && gl.getParameter(gl.SAMPLES) > 1);
        this.maxDpr = Math.min(window.devicePixelRatio || 1, this.low ? 1.5 : 2);
        this.dpr = this.maxDpr;
        this.minDpr = Math.min(this.maxDpr, this.low ? 0.85 : 1);
        renderer.setPixelRatio(this.dpr);
        renderer.setClearColor(new Color('#0c0520'), 1);
        renderer.sortObjects = true;

        this.scene = new Scene();
        this.camera = new PerspectiveCamera(FOV, 1, 0.5, 2400);
        this.uTime = {value: 0};        // orbit time
        this.uCloud = {value: 0};       // cloud / twinkle time
        this.uPxWorld = {value: 0.001};
        this.uMaxPx = {value: 400};
        this.uDpr = {value: this.dpr};
        this.uViewport = {value: new Vector4(1, 1, 0, 0)};

        this._buildSky();
        this._buildPlanet();
        this._buildRocks();
        this._buildOrbitLine();
        this._buildControls();
        this._bind();
        this.resize();
        // Link every program now, while the page still shows its loading state. Bolts, spire tips and the orbit line start
        // hidden and would otherwise be compiled on first use: a hitch in the middle of the animation.
        const c0 = performance.now();
        renderer.compile(this.scene, this.camera);
        this.compileMs = Math.round(performance.now() - c0);
        this._nextStrike = 0.6;
        this._strong = [];
    }

    // ------------------------------------------------------------------------------------------------ static scenery

    _buildSky() {
        const w = this.low ? 1024 : 2048;
        const rt = new WebGLRenderTarget(w, w / 2, {depthBuffer: false, stencilBuffer: false});
        rt.texture.minFilter = LinearFilter;
        rt.texture.magFilter = LinearFilter;
        rt.texture.generateMipmaps = false;
        const bakeScene = new Scene();
        const bakeMat = new ShaderMaterial({vertexShader: GL.BAKE_VERT, fragmentShader: GL.NEBULA_FRAG, defines: {OCTAVES: 5}, depthTest: false, depthWrite: false});
        const quad = new Mesh(new PlaneGeometry(2, 2), bakeMat);
        quad.frustumCulled = false;
        bakeScene.add(quad);
        this.renderer.setRenderTarget(rt);
        this.renderer.render(bakeScene, new OrthographicCamera(-1, 1, 1, -1, 0, 1));
        this.renderer.setRenderTarget(null);
        quad.geometry.dispose();
        bakeMat.dispose();
        this.skyTarget = rt;

        this.sky = new Mesh(new SphereGeometry(1500, 48, 24), new MeshBasicMaterial({map: rt.texture, side: BackSide, depthTest: false, depthWrite: false, fog: false}));
        this.sky.renderOrder = -10;
        this.sky.frustumCulled = false;
        this.scene.add(this.sky);

        const count = this.low ? 900 : 2000;
        const pos = new Float32Array(count * 3);
        const star = new Float32Array(count * 4);
        for (let i = 0; i < count; i++) {
            const z = rand01(i, 31) * 2 - 1, a = rand01(i, 32) * TAU, r = Math.sqrt(1 - z * z);
            pos[i * 3] = r * Math.cos(a); pos[i * 3 + 1] = z; pos[i * 3 + 2] = r * Math.sin(a);
            const big = rand01(i, 33);
            star[i * 4] = 1.1 + 2.3 * big * big * big;
            star[i * 4 + 1] = rand01(i, 34) * TAU;
            star[i * 4 + 2] = 0.4 + rand01(i, 35) * 1.6;
            star[i * 4 + 3] = rand01(i, 36);
        }
        const g = new BufferGeometry();
        g.setAttribute('position', new BufferAttribute(pos, 3));
        g.setAttribute('aStar', new BufferAttribute(star, 4));
        this.stars = new Points(g, new ShaderMaterial({
            vertexShader: GL.STARS_VERT, fragmentShader: GL.STARS_FRAG, transparent: true, blending: AdditiveBlending, depthWrite: false,
            uniforms: {uTime: this.uCloud, uDpr: this.uDpr, uTwinkle: {value: this.reduced ? 0 : 1}},
        }));
        this.stars.frustumCulled = false;
        this.stars.renderOrder = 1;
        this.scene.add(this.stars);
    }

    _buildPlanet() {
        this.storm = [];
        for (let i = 0; i < STORMS; i++) { this.storm.push(new Vector4(0, 1, 0, 0)); }
        this.cells = this.storm.map(() => ({pulses: [], until: 0}));
        this.systems = [];
        // one storm system always hangs over the city (its flashes are what gives the glimpses), five drift around
        this.systems.push({dir: CITY.clone(), axis: new Vector3(0, 1, 0), speed: 0, weight: 1.5});
        for (let i = 0; i < 5; i++) {
            const lat = rnd(-0.75, 0.75), lon = rnd(0, TAU);
            this.systems.push({
                dir: new Vector3(Math.cos(lat) * Math.cos(lon), Math.sin(lat), Math.cos(lat) * Math.sin(lon)),
                axis: new Vector3(rnd(-0.3, 0.3), 1, rnd(-0.3, 0.3)).normalize(), speed: rnd(-0.012, 0.012), weight: rnd(0.5, 1.5),
            });
        }
        this.uSunObj = {value: SUN.clone()};
        this.planetMat = new ShaderMaterial({
            vertexShader: GL.PLANET_VERT, fragmentShader: GL.PLANET_FRAG,
            defines: this.low ? {OCTAVES: 4} : {OCTAVES: 5, RELIEF: 1},
            uniforms: {uTime: this.uCloud, uSun: {value: SUN}, uSunObj: this.uSunObj, uStorm: {value: this.storm}, uCity: {value: CITY}},
        });
        this.planetMat.extensions.derivatives = true; // fwidth() on WebGL1
        this.planet = new Mesh(new SphereGeometry(PLANET_RADIUS, this.low ? 64 : 96, this.low ? 40 : 64), this.planetMat);
        this.planet.rotation.z = 0.3;
        this.scene.add(this.planet);

        this.atmo = new Mesh(new SphereGeometry(ATMO_RADIUS, 64, 40), new ShaderMaterial({
            vertexShader: GL.ATMO_VERT, fragmentShader: GL.ATMO_FRAG, transparent: true, blending: AdditiveBlending, depthWrite: false, side: FrontSide,
            uniforms: {uSun: {value: SUN}, uRadius: {value: PLANET_RADIUS}},
        }));
        this.atmo.renderOrder = 2;
        this.scene.add(this.atmo);

        // crystal spires: three thin shards at the city; they stay under the cloud deck and only now and then a tip
        // rises a little above it (never more than the tip)
        this.uSpireFlash = {value: 0};
        const spireMat = new ShaderMaterial({vertexShader: GL.SPIRE_VERT, fragmentShader: GL.SPIRE_FRAG, uniforms: {uFlash: this.uSpireFlash}});
        this.spires = new Group();
        this.spires.quaternion.setFromUnitVectors(new Vector3(0, 1, 0), CITY);
        this.spireParts = [];
        for (const [x, z, r, h, peak] of [[0, 0, 0.17, 2.2, 0.62], [0.42, -0.2, 0.11, 1.6, 0.30], [-0.3, 0.33, 0.09, 1.4, 0.22]]) {
            const cone = new Mesh(new ConeGeometry(r, h, 5, 1), spireMat);
            cone.rotation.y = x * 7 + z * 3;
            cone.userData = {x, z, h, peak};
            this.spires.add(cone);
            this.spireParts.push(cone);
        }
        this.planet.add(this.spires);
        this._spireNext = rnd(6, 16);
        this._spireFrom = -100;
        this._spireHold = 0;
        this._placeSpires(0);

        // lightning bolts: a small pool of screen-space ribbons riding on the cloud tops (children of the planet)
        this.bolts = [];
        const MAXP = 72;
        for (let b = 0; b < (this.reduced ? 0 : 3); b++) {
            const g = new BufferGeometry();
            const mk = (n) => { const a = new BufferAttribute(new Float32Array(MAXP * 2 * n), n); a.setUsage(DynamicDrawUsage); return a; };
            g.setAttribute('position', mk(3));
            g.setAttribute('aPrev', mk(3));
            g.setAttribute('aNext', mk(3));
            g.setAttribute('aSide', mk(2));
            // Ribbon triangles face either way, depending on the bolt's direction on screen, so every segment is indexed
            // in both windings: exactly one of the two is front-facing. (A DoubleSide transparent material would make
            // three draw it in two passes and compile two more programs the first time a bolt shows: a hitch.)
            g.setIndex(new BufferAttribute(new Uint16Array(MAXP * 12), 1));
            g.index.setUsage(DynamicDrawUsage);
            g.setDrawRange(0, 0);
            const power = {value: 0};
            const m = new Mesh(g, new ShaderMaterial({
                vertexShader: GL.BOLT_VERT, fragmentShader: GL.BOLT_FRAG, transparent: true, blending: AdditiveBlending, depthWrite: false,
                uniforms: {uViewport: this.uViewport, uWidth: {value: 4}, uPower: power},
            }));
            m.frustumCulled = false;
            m.renderOrder = 6;
            m.visible = false;
            this.planet.add(m);
            this.bolts.push({mesh: m, power, cell: -1, until: 0, max: MAXP});
        }
    }

    /** rise 0 = every tip under the clouds, 1 = tips at their highest point. */
    _placeSpires(rise) {
        for (const c of this.spireParts) {
            const u = c.userData;
            const tip = PLANET_RADIUS - 0.30 + (0.30 + u.peak) * rise;
            c.position.set(u.x, tip - u.h / 2, u.z);
        }
        this.spires.visible = rise > 0.001;
    }

    _spire(t) {
        if (t > this._spireNext) {
            this._spireFrom = t;
            this._spireHold = rnd(7, 14);
            this._spireNext = t + this._spireHold + rnd(35, 80);
        }
        const x = t - this._spireFrom, ramp = 5;
        let rise = 0;
        if (x >= 0 && x < this._spireHold + ramp * 2) {
            const u = x < ramp ? x / ramp : (x < ramp + this._spireHold ? 1 : 1 - (x - ramp - this._spireHold) / ramp);
            rise = u * u * (3 - 2 * u);
        }
        this._placeSpires(rise);
        this.uSpireFlash.value = this.storm.reduce((m, s) => Math.max(m, s.w * Math.max(0, (s.x * CITY.x + s.y * CITY.y + s.z * CITY.z - 0.9) * 10)), 0);
    }

    _buildRocks() {
        const count = this.low ? 700 : 1800;
        const P = new Float32Array(count * 3), Q = new Float32Array(count * 3), K = new Float32Array(count * 3), R = new Float32Array(count * 2);
        const now = Date.now() / 1000;
        this.rockOrbits = [];
        for (let i = 0; i < count; i++) {
            const o = orbitFor(900000 + i, 0);
            // rocks hug the belt plane a little more than the islands
            P.set(o.P, i * 3); Q.set(o.Q, i * 3);
            P[i * 3 + 1] *= 0.6; Q[i * 3 + 1] *= 0.6;
            K[i * 3] = o.e; K[i * 3 + 1] = meanAnomalyAt(o, now); K[i * 3 + 2] = o.n * this.motion;
            R[i * 2] = 0.05 + 0.20 * Math.pow(rand01(i, 41), 3);
            R[i * 2 + 1] = 0.30 + 0.50 * rand01(i, 42);
            this.rockOrbits.push(o);
        }
        const g = new BufferGeometry();
        g.setAttribute('position', new BufferAttribute(new Float32Array(count * 3), 3));
        g.setAttribute('aP', new BufferAttribute(P, 3));
        g.setAttribute('aQ', new BufferAttribute(Q, 3));
        g.setAttribute('aKep', new BufferAttribute(K, 3));
        g.setAttribute('aRock', new BufferAttribute(R, 2));
        this.rocks = new Points(g, new ShaderMaterial({
            vertexShader: GL.ROCK_VERT, fragmentShader: GL.ROCK_FRAG,
            uniforms: {uTime: this.uTime, uDpr: this.uDpr, uPxWorld: this.uPxWorld},
        }));
        this.rocks.frustumCulled = false;
        this.scene.add(this.rocks);
    }

    _buildOrbitLine() {
        const g = new BufferGeometry();
        g.setAttribute('position', new BufferAttribute(new Float32Array(160 * 3), 3));
        this.orbitLine = new LineLoop(g, new LineBasicMaterial({color: 0xffffff, transparent: true, opacity: 0.42, depthWrite: false}));
        this.orbitLine.frustumCulled = false;
        this.orbitLine.renderOrder = 4;
        this.orbitLine.visible = false;
        this.scene.add(this.orbitLine);
    }

    _buildControls() {
        const c = new OrbitControls(this.camera, this.canvas);
        c.enablePan = false;
        c.enableDamping = true;
        c.dampingFactor = 0.08;
        c.rotateSpeed = 0.55;
        c.zoomSpeed = 0.8;
        c.minDistance = PLANET_RADIUS * 1.75;
        c.maxDistance = 210;
        c.minPolarAngle = 0.12 * Math.PI;
        c.maxPolarAngle = 0.88 * Math.PI;
        this.controls = c;
        this._lastInput = -100;
        this._userZoomed = false;
        c.addEventListener('start', () => { this._lastInput = performance.now() / 1000; this._dragging = true; this._userZoomed = true; this.opts.onInteract && this.opts.onInteract(); });
        c.addEventListener('end', () => { this._lastInput = performance.now() / 1000; this._dragging = false; });
        this.home();
    }

    /**
     * Default view, seen from a little above the belt plane (orbits.mjs homeFrame): close while the belt holds a handful
     * of islands, widening as it fills up; steeper and fitted to the width on portrait screens.
     */
    home(animate = false) {
        const f = homeFrame(this.apo || [], this.camera.aspect || 1, FOV);
        const dist = f.dist;
        this.homeDist = dist;
        this.homeElevation = f.elevation;
        this.controls.maxDistance = Math.max(210, dist * 1.3);
        if (animate) {
            this.followId = null;
            this.returning = true;
            this.controls.minDistance = PLANET_RADIUS * 1.75;
            return;
        }
        const el = f.elevation;
        this.controls.target.set(0, 0, 0);
        this.camera.position.set(Math.sin(0.5) * Math.cos(el) * dist, Math.sin(el) * dist, Math.cos(0.5) * Math.cos(el) * dist);
        this.controls.update();
    }

    // ------------------------------------------------------------------------------------------------ islands

    /**
     * @param {Array} items island objects (./orbits.mjs islandsFromManifest / demoIslands)
     * @param {{grid:{cols:number,rows:number,w:number,h:number,size:number}, atlases:Object<string,string>, sprite:string}} tex
     */
    setIslands(items, tex) {
        this._clearIslands();
        this.items = items;
        this.tex = tex;
        this.index = new Map();
        const n = items.length;
        const now = Date.now() / 1000;
        this.orbitClock = 0;
        this.uTime.value = 0;
        this.orbits = new Array(n);
        this.phase = new Float64Array(n);     // mean anomaly at orbitClock = 0
        this.state = new Uint8Array(n);
        this.slotOf = new Array(n);           // [pageKey, index in page]
        const byPage = new Map();
        const scale = sizeScaleFor(n);
        const apo = [];
        for (let i = 0; i < n; i++) {
            const it = items[i];
            this.index.set(it.id, i);
            const o = orbitFor(it.id, it.r);
            o.size *= scale;
            apo.push(o.a * (1 + o.e));
            this.orbits[i] = o;
            this.phase[i] = meanAnomalyAt(o, now);
            const key = it.hasCell ? it.page : '_';
            if (!byPage.has(key)) { byPage.set(key, []); }
            this.slotOf[i] = [key, byPage.get(key).length];
            byPage.get(key).push(i);
        }
        // the default view frames the belt that exists today (a few inner islands -> closer, the full belt -> wider)
        this.apo = apo;
        // halos add up: the more islands, the fainter each one (common islands lose theirs in a full belt)
        const dim = Math.max(0.3, Math.min(1, Math.sqrt(120 / Math.max(1, n))));
        this.glowGain = new Vector3(this.low || n > 600 ? 0 : 0.10 * dim, 0.26 * dim, 0.42 * dim);
        const g = tex.grid;
        const gridU = new Vector4(g.cols, g.rows, g.w / g.size, g.h / g.size);
        for (const [key, list] of byPage) {
            const page = this._makePage(list, gridU, key);
            this.pages.set(key, page);
            this.scene.add(page.mesh, page.glow);
        }
        this.stats.islands = n;
        this.stats.pages = [...byPage.keys()].filter((k) => k !== '_').length;
        this.stats.pagesLoaded = 0;
        if (!this._userZoomed && this.followId === null) { this.home(); }
        this._loadPages();
    }

    _attrs(list) {
        const n = list.length;
        const P = new Float32Array(n * 3), Q = new Float32Array(n * 3), K = new Float32Array(n * 3), L = new Float32Array(n * 4), S = new Float32Array(n);
        for (let k = 0; k < n; k++) {
            const i = list[k], o = this.orbits[i], it = this.items[i];
            P.set(o.P, k * 3); Q.set(o.Q, k * 3);
            K[k * 3] = o.e; K[k * 3 + 1] = this.phase[i]; K[k * 3 + 2] = o.n * this.motion;
            L[k * 4] = o.size; L[k * 4 + 1] = it.r; L[k * 4 + 2] = o.seed; L[k * 4 + 3] = it.hasCell ? it.slot : -1;
            S[k] = this.state[i];
        }
        const state = new InstancedBufferAttribute(S, 1);
        state.setUsage(DynamicDrawUsage);
        const kep = new InstancedBufferAttribute(K, 3);
        return {aP: new InstancedBufferAttribute(P, 3), aQ: new InstancedBufferAttribute(Q, 3), aKep: kep, aLook: new InstancedBufferAttribute(L, 4), aState: state};
    }

    _geometry(attrs, count) {
        const quad = this._quad || (this._quad = new PlaneGeometry(1, 1));
        const g = new InstancedBufferGeometry();
        g.index = quad.index;
        g.setAttribute('position', quad.attributes.position);
        g.setAttribute('uv', quad.attributes.uv);
        for (const k of Object.keys(attrs)) { g.setAttribute(k, attrs[k]); }
        g.instanceCount = count;
        return g;
    }

    _islandMaterial(gridU, map) {
        return new ShaderMaterial({
            vertexShader: GL.ISLAND_VERT, fragmentShader: GL.ISLAND_FRAG, alphaToCoverage: this.coverage,
            uniforms: {
                uTime: this.uTime, uGrid: {value: gridU}, uPxWorld: this.uPxWorld, uMaxPx: this.uMaxPx, uMinPx: {value: MIN_PX}, uHoverPx: {value: HOVER_PX}, uSway: {value: this.reduced ? 0 : 1},
                uMap: {value: map || null}, uHasMap: {value: map ? 1 : 0}, uCoverage: {value: this.coverage ? 1 : 0}, uHaze: {value: new Color('#1a0b3a')},
            },
        });
    }

    _makePage(list, gridU, key) {
        const attrs = this._attrs(list);
        const mesh = new Mesh(this._geometry(attrs, list.length), this._islandMaterial(gridU, null));
        mesh.frustumCulled = false;
        const glow = new Mesh(this._geometry(attrs, list.length), new ShaderMaterial({
            vertexShader: GL.GLOW_VERT, fragmentShader: GL.GLOW_FRAG, transparent: true, blending: AdditiveBlending, depthWrite: false,
            uniforms: {uTime: this.uTime, uPxWorld: this.uPxWorld, uMaxPx: this.uMaxPx, uMinPx: {value: MIN_PX}, uHoverPx: {value: HOVER_PX}, uGain: {value: this.glowGain}},
        }));
        glow.frustumCulled = false;
        glow.renderOrder = 3;
        return {key, list, attrs, mesh, glow, loaded: false};
    }

    _clearIslands() {
        for (const p of this.pages.values()) {
            this.scene.remove(p.mesh, p.glow);
            p.mesh.geometry.dispose(); p.glow.geometry.dispose();
            p.mesh.material.dispose(); p.glow.material.dispose();
            if (p.texture) { p.texture.dispose(); }
        }
        this.pages.clear();
        for (const id of [...this.near.keys()]) { this._dropNear(id); }
        this.hoverId = this.highlightId = this.followId = null;
        this.orbitLine.visible = false;
    }

    _image(url) {
        if (!this.imgCache.has(url)) {
            this.imgCache.set(url, new Promise((resolve, reject) => {
                const img = new Image();
                img.decoding = 'async';
                img.onload = () => resolve(img);
                img.onerror = () => reject(new Error('image ' + url));
                img.src = url;
            }));
        }
        return this.imgCache.get(url);
    }

    _texture(source) {
        let src = source;
        if (this.low && source.width > 1024) {
            // phones: half-size atlas pages (a quarter of the GPU memory)
            src = document.createElement('canvas');
            src.width = source.width / 2; src.height = source.height / 2;
            src.getContext('2d').drawImage(source, 0, 0, src.width, src.height);
        }
        const t = src instanceof HTMLCanvasElement ? new CanvasTexture(src) : new Texture(src);
        t.premultiplyAlpha = true;
        t.generateMipmaps = true;
        t.minFilter = LinearMipmapLinearFilter;
        t.magFilter = LinearFilter;
        t.anisotropy = Math.min(4, this.renderer.capabilities.getMaxAnisotropy());
        t.needsUpdate = true;
        return t;
    }

    /** Atlas pages one after another (real pages first; demo pages are composed from them in the browser). */
    async _loadPages() {
        const token = this._loadToken = {};
        const tex = this.tex;
        const keys = [...this.pages.keys()].filter((k) => k !== '_');
        const real = keys.filter((k) => !/^d/.test(k)).sort((a, b) => a - b);
        const demo = keys.filter((k) => /^d/.test(k));
        const sources = new Map();
        const need = new Set(real);
        for (const k of demo) { for (const i of this.pages.get(k).list) { need.add(this.items[i].srcPage); } }
        for (const k of [...need].sort((a, b) => a - b)) {
            const url = tex.atlases[k];
            if (!url) { continue; }
            try {
                const img = await this._image(url);
                if (token !== this._loadToken || this.disposed) { return; }
                sources.set(k, img);
                if (this.pages.has(k)) { this._applyPage(k, this._texture(img)); }
            } catch (e) { console.warn('[belt] atlas page failed', k); }
        }
        for (const k of demo) {
            if (token !== this._loadToken || this.disposed) { return; }
            await new Promise((r) => setTimeout(r, 0));
            const g = tex.grid;
            const c = document.createElement('canvas');
            c.width = c.height = g.size;
            const ctx = c.getContext('2d');
            const canFilter = typeof ctx.filter === 'string';
            for (const i of this.pages.get(k).list) {
                const it = this.items[i];
                const src = sources.get(it.srcPage);
                if (!src) { continue; }
                if (canFilter) { ctx.filter = it.hue ? 'hue-rotate(' + it.hue + 'deg)' : 'none'; }
                ctx.drawImage(src, (it.srcSlot % g.cols) * g.w, Math.floor(it.srcSlot / g.cols) * g.h, g.w, g.h,
                    (it.slot % g.cols) * g.w, Math.floor(it.slot / g.cols) * g.h, g.w, g.h);
            }
            this._applyPage(k, this._texture(c));
        }
    }

    _applyPage(key, texture) {
        const p = this.pages.get(key);
        if (!p) { texture.dispose(); return; }
        p.texture = texture;
        p.mesh.material.uniforms.uMap.value = texture;
        p.mesh.material.uniforms.uHasMap.value = 1;
        p.loaded = true;
        this.stats.pagesLoaded++;
    }

    _setState(i, s) {
        if (this.state[i] === s) { return; }
        this.state[i] = s;
        this._pushState(i);
    }

    _pushState(i) {
        const [key, k] = this.slotOf[i];
        const p = this.pages.get(key);
        const id = this.items[i].id;
        const hidden = this.near.has(id) && this.near.get(id).ready;
        if (p) {
            p.attrs.aState.array[k] = hidden ? 3 : this.state[i];
            p.attrs.aState.needsUpdate = true;
        }
        if (hidden) {
            const m = this.near.get(id);
            m.attrs.aState.array[0] = this.state[i];
            m.attrs.aState.needsUpdate = true;
        }
    }

    /** mask(item) -> true = shown normally, false = filtered out (small, dark, not pickable). null clears. */
    setFilter(mask) {
        for (let i = 0; i < this.items.length; i++) {
            const out = mask ? !mask(this.items[i]) : false;
            const cur = this.state[i];
            if (out && cur !== 2) { this._setState(i, 2); } else if (!out && cur === 2) { this._setState(i, this.items[i].id === this.highlightId ? 1 : 0); }
        }
    }

    setHighlight(id) {
        if (id === this.highlightId) { return; }
        const prev = this.index.get(this.highlightId);
        if (prev !== undefined && this.state[prev] === 1) { this._setState(prev, 0); }
        this.highlightId = id;
        const i = this.index.get(id);
        if (i === undefined) { this.highlightId = null; this.orbitLine.visible = false; return; }
        if (this.state[i] !== 2) { this._setState(i, 1); }
        this.orbitLine.geometry.attributes.position.array.set(orbitPath(this.orbits[i], 160));
        this.orbitLine.geometry.attributes.position.needsUpdate = true;
        this.orbitLine.material.color.set(RARITY_COLORS[this.items[i].r] || '#ffffff');
        this.orbitLine.visible = true;
    }

    /** Fly to an island and keep it in the centre while it moves; null = back to the whole belt. */
    focus(id) {
        if (id === null || !this.index.has(id)) { this.home(true); return false; }
        this.followId = id;
        this.returning = false;
        this._userZoomed = false;
        this.controls.minDistance = 4;
        this._lastInput = performance.now() / 1000;
        return true;
    }

    // ------------------------------------------------------------------------------------------------ positions

    _pos(i, out) {
        const o = this.orbits[i];
        const E = solveKepler(this.phase[i] + o.n * this.motion * this.orbitClock, o.e);
        const c = Math.cos(E) - o.e, s = Math.sin(E);
        out.x = o.P[0] * c + o.Q[0] * s; out.y = o.P[1] * c + o.Q[1] * s; out.z = o.P[2] * c + o.Q[2] * s;
        return out;
    }

    _rebase() {
        const t = this.orbitClock;
        for (let i = 0; i < this.items.length; i++) { this.phase[i] = (this.phase[i] + this.orbits[i].n * this.motion * t) % TAU; }
        for (const p of this.pages.values()) {
            for (let k = 0; k < p.list.length; k++) { p.attrs.aKep.array[k * 3 + 1] = this.phase[p.list[k]]; }
            p.attrs.aKep.needsUpdate = true;
        }
        for (const [id, m] of this.near) { m.attrs.aKep.array[1] = this.phase[this.index.get(id)]; m.attrs.aKep.needsUpdate = true; }
        const rk = this.rocks.geometry.attributes.aKep;
        for (let i = 0; i < this.rockOrbits.length; i++) { rk.array[i * 3 + 1] = (rk.array[i * 3 + 1] + rk.array[i * 3 + 2] * t) % TAU; }
        rk.needsUpdate = true;
        this.orbitClock = 0;
        this.uTime.value = 0;
    }

    /** Screen rectangle of island i: {x, y (centre, CSS px), h (height px), w (view depth)} or null when behind the camera. */
    _project(i, out) {
        const p = this._pos(i, this._tmp);
        const e = this._vp.elements;
        const w = e[3] * p.x + e[7] * p.y + e[11] * p.z + e[15];
        if (w < 0.6) { return null; }
        const x = (e[0] * p.x + e[4] * p.y + e[8] * p.z + e[12]) / w;
        const y = (e[1] * p.x + e[5] * p.y + e[9] * p.z + e[13]) / w;
        out.x = (x * 0.5 + 0.5) * this.width;
        out.y = (0.5 - y * 0.5) * this.height;
        const st = this.state[i];
        const world = this.orbits[i].size * (st === 2 ? 0.55 : (st === 1 ? 1.16 : 1));
        out.h = Math.max(world / (this.uPxWorld.value * w), st === 1 ? HOVER_PX : MIN_PX * (st === 2 ? 0.6 : 1));
        if (st !== 1) { out.h = Math.min(out.h, this.uMaxPx.value); }
        out.w = w;
        return out;
    }

    /** Is the point hidden behind the planet (as seen from the camera)? */
    _occluded(p) {
        const c = this.camera.position;
        const dx = p.x - c.x, dy = p.y - c.y, dz = p.z - c.z;
        const len = Math.sqrt(dx * dx + dy * dy + dz * dz);
        const t = -(c.x * dx + c.y * dy + c.z * dz) / len; // distance along the ray to the point closest to the planet centre
        if (t <= 0 || t >= len) { return false; }
        const cx = c.x + dx / len * t, cy = c.y + dy / len * t, cz = c.z + dz / len * t;
        return cx * cx + cy * cy + cz * cz < PLANET_RADIUS * PLANET_RADIUS * 0.96;
    }

    /** Island under a client-space point (nearest to the camera wins), or null. */
    pick(clientX, clientY) {
        const rect = this.canvas.getBoundingClientRect();
        const px = clientX - rect.left, py = clientY - rect.top;
        const r = {x: 0, y: 0, h: 0, w: 0};
        let best = -1, bestW = Infinity;
        for (let i = 0; i < this.items.length; i++) {
            if (this.state[i] === 2) { continue; }
            if (!this._project(i, r) || r.w >= bestW) { continue; }
            const dx = px - r.x, dy = py - r.y;
            // the island body sits in the lower two thirds of its 3:4 frame; tiny islands get a finger-sized target
            const hit = r.h < 30
                ? (dx * dx + (dy - r.h * 0.1) * (dy - r.h * 0.1) < 15 * 15)
                : (Math.abs(dx) < r.h * 0.36 && dy > -r.h * 0.26 && dy < r.h * 0.47);
            if (hit && !this._occluded(this._tmp)) { best = i; bestW = r.w; }
        }
        return best < 0 ? null : this.items[best];
    }

    /** Is the central body (the cloud-covered rock) under this client-space point? */
    pickCenter(clientX, clientY) {
        const rect = this.canvas.getBoundingClientRect();
        const x = ((clientX - rect.left) / this.width) * 2 - 1, y = 1 - ((clientY - rect.top) / this.height) * 2;
        const dir = this._tmp.set(x, y, 0.5).unproject(this.camera).sub(this.camera.position).normalize();
        const c = this.camera.position;
        const t = -(c.x * dir.x + c.y * dir.y + c.z * dir.z);
        if (t <= 0) { return false; }
        const cx = c.x + dir.x * t, cy = c.y + dir.y * t, cz = c.z + dir.z * t;
        return cx * cx + cy * cy + cz * cz < PLANET_RADIUS * PLANET_RADIUS;
    }

    /** Island or {center: true} under the point, or null. */
    pickAny(clientX, clientY) {
        return this.pick(clientX, clientY) || (this.pickCenter(clientX, clientY) ? {center: true} : null);
    }

    /** Where an island is on screen right now (client coordinates). */
    screenOf(id) {
        const i = this.index.get(id);
        if (i === undefined) { return null; }
        const r = {x: 0, y: 0, h: 0, w: 0};
        if (!this._project(i, r)) { return null; }
        const rect = this.canvas.getBoundingClientRect();
        return {x: r.x + rect.left, y: r.y + rect.top, h: r.h, visible: !this._occluded(this._tmp) && r.x > -40 && r.y > -40 && r.x < this.width + 40 && r.y < this.height + 40};
    }

    // ------------------------------------------------------------------------------------------------ near LOD

    _updateNear() {
        const r = {x: 0, y: 0, h: 0, w: 0};
        const want = [];
        for (let i = 0; i < this.items.length; i++) {
            const it = this.items[i];
            if (!it.key || this.state[i] === 2) { continue; }
            if (!this._project(i, r)) { continue; }
            if (r.h < NEAR_PX && it.id !== this.followId) { continue; }
            if (r.x < -r.h || r.y < -r.h || r.x > this.width + r.h || r.y > this.height + r.h) { continue; }
            want.push([r.h + (it.id === this.followId ? 1e6 : 0), it.id]);
        }
        want.sort((a, b) => b[0] - a[0]);
        const keep = new Set(want.slice(0, this.low ? 6 : 14).map((x) => x[1]));
        const now = this.clock;
        for (const [id, m] of this.near) {
            if (keep.has(id)) { m.seen = now; } else if (now - m.seen > 3) { this._dropNear(id); }
        }
        for (const id of keep) { if (!this.near.has(id)) { this._addNear(id); } }
        this.stats.near = this.near.size;
    }

    _spriteUrl(it) {
        return this.tex.sprite.replace('{id}', it.demo ? it.src : it.id).replace('{key}', it.key);
    }

    _addNear(id) {
        const i = this.index.get(id);
        const it = this.items[i];
        const url = this._spriteUrl(it);
        const attrs = this._attrs([i]);
        attrs.aLook.array[3] = 0;
        const entry = {attrs, url, seen: this.clock, ready: false, mesh: null};
        this.near.set(id, entry);
        let cached = this.texCache.get(url);
        if (!cached) {
            cached = {refs: 0, promise: this._image(url).then((img) => this._texture(img))};
            this.texCache.set(url, cached);
        }
        cached.refs++;
        cached.promise.then((texture) => {
            if (this.near.get(id) !== entry || this.disposed) { return; }
            const mat = this._islandMaterial(new Vector4(1, 1, 1, 1), texture);
            entry.mesh = new Mesh(this._geometry(attrs, 1), mat);
            entry.mesh.frustumCulled = false;
            this.scene.add(entry.mesh);
            entry.ready = true;
            this._pushState(i);
        }).catch(() => { /* keeps the atlas cell */ });
    }

    _dropNear(id) {
        const m = this.near.get(id);
        if (!m) { return; }
        this.near.delete(id);
        if (m.mesh) { this.scene.remove(m.mesh); m.mesh.geometry.dispose(); m.mesh.material.dispose(); }
        const c = this.texCache.get(m.url);
        if (c && --c.refs <= 0) { this.texCache.delete(m.url); this.imgCache.delete(m.url); c.promise.then((t) => t.dispose()).catch(() => {}); }
        const i = this.index.get(id);
        if (i !== undefined) { this._pushState(i); }
    }

    // ------------------------------------------------------------------------------------------------ thunderstorms

    _strike(t) {
        // pick a storm system, then a spot inside it
        let total = 0;
        for (const s of this.systems) { total += s.weight; }
        let pickAt = Math.random() * total, sys = this.systems[0];
        for (const s of this.systems) { pickAt -= s.weight; if (pickAt <= 0) { sys = s; break; } }
        const dir = this._jitter(sys.dir, rnd(0, 0.24));
        let slot = 0, low = Infinity;
        for (let i = 0; i < STORMS; i++) { if (this.cells[i].until < low) { low = this.cells[i].until; slot = i; } }
        const cell = this.cells[slot];
        this.storm[slot].set(dir.x, dir.y, dir.z, 0);
        cell.pulses = [];
        if (this.reduced) {
            cell.pulses.push({t, amp: rnd(0.22, 0.4), rise: 0.6, decay: 1.0});
            cell.until = t + 4;
            return;
        }
        let amp = rnd(0.5, 1.0);
        // at most three strong flashes in any second (the rest stay dim)
        this._strong = this._strong.filter((x) => t - x < 1);
        let at = t;
        const count = 1 + Math.floor(Math.random() * 3);
        for (let k = 0; k < count; k++) {
            let a = k === 0 ? amp : amp * rnd(0.4, 0.75);
            if (a > 0.55) { if (this._strong.length >= 3) { a = 0.45; } else { this._strong.push(at); } }
            cell.pulses.push({t: at, amp: a, rise: 0.045, decay: rnd(0.13, 0.24)});
            at += rnd(0.14, 0.26);
        }
        cell.until = at + 1.2;
        if (amp > 0.62 && Math.random() < 0.55) { this._bolt(slot, dir, t); }
    }

    _jitter(dir, angle) {
        const t = this._tmp.set(rnd(-1, 1), rnd(-1, 1), rnd(-1, 1)).cross(dir).normalize();
        return dir.clone().multiplyScalar(Math.cos(angle)).addScaledVector(t, Math.sin(angle)).normalize();
    }

    /** A jagged cloud-to-cloud bolt from `dir` to a nearby spot, plus one or two branches. */
    _bolt(cellIndex, dir, t) {
        const bolt = this.bolts.find((b) => b.until < t);
        if (!bolt) { return; }
        const end = this._jitter(dir, rnd(0.10, 0.34));
        const strips = [this._path(dir, end, 20, 0.022)];
        const branches = 1 + (Math.random() < 0.5 ? 1 : 0);
        for (let b = 0; b < branches; b++) {
            const from = strips[0][4 + Math.floor(Math.random() * 10)];
            const fromDir = from.clone().normalize();
            strips.push(this._path(fromDir, this._jitter(fromDir, rnd(0.05, 0.14)), 9, 0.016));
        }
        const g = bolt.mesh.geometry;
        const pos = g.attributes.position.array, prev = g.attributes.aPrev.array, next = g.attributes.aNext.array, side = g.attributes.aSide.array, idx = g.index.array;
        let v = 0, ii = 0;
        for (let s = 0; s < strips.length; s++) {
            const pts = strips[s];
            if (v / 2 + pts.length > bolt.max) { break; }
            for (let k = 0; k < pts.length; k++) {
                const p = pts[k], a = pts[Math.max(0, k - 1)], b = pts[Math.min(pts.length - 1, k + 1)];
                for (let sd = -1; sd <= 1; sd += 2) {
                    pos.set([p.x, p.y, p.z], v * 3); prev.set([a.x, a.y, a.z], v * 3); next.set([b.x, b.y, b.z], v * 3);
                    side.set([sd, (s === 0 ? 0 : 0.45) + (k / (pts.length - 1)) * (s === 0 ? 1 : 0.55)], v * 2);
                    v++;
                }
                if (k > 0) { const o = v - 4; idx.set([o, o + 1, o + 2, o + 1, o + 3, o + 2, o + 2, o + 1, o, o + 2, o + 3, o + 1], ii); ii += 12; }
            }
        }
        for (const k of ['position', 'aPrev', 'aNext', 'aSide']) { g.attributes[k].needsUpdate = true; }
        g.index.needsUpdate = true;
        g.setDrawRange(0, ii);
        bolt.cell = cellIndex;
        bolt.until = t + rnd(0.22, 0.36);
        bolt.start = t;
        bolt.mesh.visible = true;
    }

    _path(a, b, segments, wobble) {
        const out = [];
        const axis = this._tmp2.copy(a).cross(b).normalize();
        let off = 0;
        for (let k = 0; k <= segments; k++) {
            const u = k / segments;
            off = (off + rnd(-wobble, wobble)) * 0.82;
            const p = a.clone().lerp(b, u).normalize();
            const env = Math.sin(Math.PI * u);
            p.addScaledVector(axis, off * (0.35 + env)).normalize().multiplyScalar(PLANET_RADIUS * (1.006 + 0.014 * env));
            out.push(p);
        }
        return out;
    }

    _storms(t, dt) {
        for (const s of this.systems) { s.dir.applyAxisAngle(s.axis, s.speed * dt); }
        if (t >= this._nextStrike) {
            this._strike(t);
            const mean = this.reduced ? 2.8 : 0.72;
            this._nextStrike = t + Math.min(mean * 3.5, Math.max(0.12, -Math.log(1 - Math.random()) * mean));
        }
        for (let i = 0; i < STORMS; i++) {
            let v = 0;
            for (const p of this.cells[i].pulses) {
                const x = t - p.t;
                if (x <= 0) { continue; }
                v += p.amp * (x < p.rise ? x / p.rise : Math.exp(-(x - p.rise) / p.decay));
            }
            this.storm[i].w = Math.min(1, v);
        }
        for (const b of this.bolts) {
            if (!b.mesh.visible) { continue; }
            if (t > b.until) { b.mesh.visible = false; b.power.value = 0; continue; }
            const life = (t - b.start) / (b.until - b.start);
            b.power.value = Math.min(1, this.storm[b.cell].w * 1.3) * (1 - life * life) * (0.75 + 0.25 * Math.sin(t * 70));
        }
    }

    // ------------------------------------------------------------------------------------------------ input

    _bind() {
        const c = this.canvas;
        this._on = [];
        const on = (el, ev, fn, o) => { el.addEventListener(ev, fn, o); this._on.push([el, ev, fn, o]); };
        let down = null;
        on(c, 'pointerdown', (e) => { down = {x: e.clientX, y: e.clientY, t: performance.now(), id: e.pointerId}; });
        on(c, 'pointermove', (e) => {
            if (e.pointerType === 'touch') { return; }
            this._pointer = {x: e.clientX, y: e.clientY};
            this._pointerDirty = true;
        });
        on(c, 'pointerleave', (e) => { if (e.pointerType !== 'touch') { this._pointer = null; this._hover(null, 0, 0); } });
        on(c, 'pointerup', (e) => {
            if (!down || down.id !== e.pointerId) { return; }
            const moved = Math.hypot(e.clientX - down.x, e.clientY - down.y), held = performance.now() - down.t;
            down = null;
            if (moved > 7 || held > 600) { return; }
            const it = this.pickAny(e.clientX, e.clientY);
            this.opts.onSelect && this.opts.onSelect(it, {x: e.clientX, y: e.clientY, pointerType: e.pointerType || 'mouse'});
        });
        on(c, 'pointercancel', () => { down = null; });
        on(c, 'webglcontextlost', (e) => { e.preventDefault(); this.stop(); this.opts.onLost && this.opts.onLost(); });
        on(document, 'visibilitychange', () => { if (document.hidden) { this._pause(); } else if (this._wanted) { this._resume(); } });
        if (typeof ResizeObserver !== 'undefined') {
            this._ro = new ResizeObserver(() => this.resize());
            this._ro.observe(c.parentElement || c);
        } else { on(window, 'resize', () => this.resize()); }
    }

    _hover(item, x, y) {
        const id = item ? (item.center ? 'center' : item.id) : null;
        if (id !== this.hoverId) {
            this.hoverId = id;
            this.canvas.style.cursor = item ? (item.center ? 'help' : 'pointer') : '';
        }
        this.opts.onHover && this.opts.onHover(item, x, y);
    }

    resize() {
        const el = this.canvas.parentElement || this.canvas;
        const w = Math.max(1, el.clientWidth), h = Math.max(1, el.clientHeight);
        if (w === this.width && h === this.height && this.dpr === this._appliedDpr) { return; }
        const first = !this.width;
        this.width = w; this.height = h;
        this._appliedDpr = this.dpr;
        this.renderer.setPixelRatio(this.dpr);
        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.uPxWorld.value = 2 * Math.tan(FOV * Math.PI / 360) / h;
        this.uMaxPx.value = Math.max(120, h * MAX_FRAC);
        this.uDpr.value = this.dpr;
        this.uViewport.value.set(w * this.dpr / 2, h * this.dpr / 2, 0, 0);
        for (const b of this.bolts) { b.mesh.material.uniforms.uWidth.value = 4.6 * this.dpr; }
        if (first || (!this._userZoomed && this.followId === null)) { this.home(); }
        if (!this.running) { this._render(0); }
    }

    // ------------------------------------------------------------------------------------------------ loop

    start() { this._wanted = true; if (!document.hidden) { this._resume(); } }

    stop() { this._wanted = false; this._pause(); }

    _resume() {
        if (this.running || this.disposed) { return; }
        this.running = true;
        this._last = performance.now();
        const tick = (now) => {
            if (!this.running) { return; }
            this._raf = requestAnimationFrame(tick);
            const ms = now - this._last;
            this._last = now;
            this._render(Math.min(ms, 100) / 1000);
            this._measure(ms, now);
        };
        this._raf = requestAnimationFrame(tick);
    }

    _pause() {
        this.running = false;
        if (this._raf) { cancelAnimationFrame(this._raf); }
    }

    _render(dt) {
        this.clock += dt;
        this.orbitClock += dt;
        if (this.orbitClock > REBASE_SECONDS) { this._rebase(); }
        this.uTime.value = this.orbitClock;
        this.uCloud.value = this.clock * (this.reduced ? 0.4 : 1);
        this.planet.rotation.y += dt * 0.013 * this.motion;
        this.planet.updateMatrixWorld();
        this._tmpM = this._tmpM || new Matrix4();
        this.uSunObj.value.copy(SUN).transformDirection(this._tmpM.copy(this.planet.matrixWorld).invert());
        this._storms(this.clock, dt);
        this._spire(this.clock);
        this._camera(dt);
        this.sky.position.copy(this.camera.position);
        this.atmo.material.side = this.camera.position.length() > ATMO_RADIUS * 1.02 ? FrontSide : BackSide;
        this.camera.updateMatrixWorld();
        this._vp.multiplyMatrices(this.camera.projectionMatrix, this.camera.matrixWorldInverse);

        if (this._pointer && !this._dragging && (this._pointerDirty || (this._hoverTick = (this._hoverTick || 0) + 1) % 6 === 0)) {
            this._pointerDirty = false;
            this._hover(this.pickAny(this._pointer.x, this._pointer.y), this._pointer.x, this._pointer.y);
        }
        if ((this._lodTick = (this._lodTick || 0) + 1) % 15 === 1) { this._updateNear(); }
        if (this.highlightId !== null && this.opts.onTrack) {
            const s = this.screenOf(this.highlightId);
            if (s) { this.opts.onTrack(s); }
        }
        this.renderer.render(this.scene, this.camera);
    }

    _camera(dt) {
        const c = this.controls;
        const k = 1 - Math.exp(-dt * 3.2);
        const offset = this._tmp2.copy(this.camera.position).sub(c.target);
        if (this.followId !== null && this.index.has(this.followId)) {
            const i = this.index.get(this.followId);
            const p = this._pos(i, this._tmp);
            const dx = (p.x - c.target.x) * k, dy = (p.y - c.target.y) * k, dz = (p.z - c.target.z) * k;
            c.target.set(c.target.x + dx, c.target.y + dy, c.target.z + dz);
            if (!this._userZoomed) {
                const want = this.orbits[i].size * 3.4 / Math.min(1, Math.max(0.55, this.camera.aspect));
                const d = offset.length();
                offset.multiplyScalar((d + (want - d) * k) / d);
            }
            this.camera.position.copy(c.target).add(offset);
        } else if (this.returning) {
            c.target.multiplyScalar(1 - k);
            const d = offset.length();
            offset.multiplyScalar((d + (this.homeDist - d) * k) / d);
            this.camera.position.copy(c.target).add(offset);
            if (c.target.length() < 0.05 && Math.abs(d - this.homeDist) < 0.5) { this.returning = false; c.target.set(0, 0, 0); }
        }
        // idle: slow turn around the vertical axis
        const idle = performance.now() / 1000 - this._lastInput;
        if (!this._dragging && idle > 4 && !this.reduced) {
            const a = dt * 0.035 * Math.min(1, (idle - 4) / 3);
            const o = this._tmp2.copy(this.camera.position).sub(c.target);
            const cs = Math.cos(a), sn = Math.sin(a);
            this.camera.position.set(c.target.x + o.x * cs + o.z * sn, c.target.y + o.y, c.target.z - o.x * sn + o.z * cs);
        }
        c.update();
    }

    /** Frame-time watchdog: lowers the render scale when frames are slow, raises it again when there is headroom. */
    _measure(ms, now) {
        const f = this._frames;
        f.push(ms);
        if (f.length > 120) { f.shift(); }
        if (!this._statAt || now - this._statAt > 500) {
            this._statAt = now;
            // (stalls such as a tab switch or a texture upload are not a slow GPU: leave frames over 250 ms out)
            const recent = f.slice(-45).filter((x) => x < 250);
            if (!recent.length) { return; }
            const avg = recent.reduce((a, b) => a + b, 0) / recent.length;
            const info = this.renderer.info.render;
            Object.assign(this.stats, {fps: Math.round(1000 / avg), ms: Math.round(avg * 10) / 10, dpr: Math.round(this.dpr * 100) / 100, calls: info.calls, triangles: info.triangles});
            this.opts.onStats && this.opts.onStats(this.stats);
            if (f.length >= 60 && now - (this._tunedAt || 0) > 1800 && now > (this._lockUntil || 0)) {
                if (this._trial) {
                    // did the lower render scale help? If not, the GPU is not the limit (throttled tab, power saver,
                    // a 30 Hz display): go back to the sharper picture and leave it alone for a while
                    const t = this._trial;
                    this._trial = null;
                    if (avg > t.avg * 0.9) { this.dpr = t.dpr; this._lockUntil = now + 60000; this._tunedAt = now; this.resize(); return; }
                }
                if (avg > 22 && this.dpr > this.minDpr) {
                    this._trial = {avg, dpr: this.dpr};
                    this.dpr = Math.max(this.minDpr, this.dpr * (avg > 34 ? 0.72 : 0.86));
                    this._tunedAt = now; this._downAt = now; this.resize();
                } else if (avg < 13.5 && this.dpr < this.maxDpr && now - (this._downAt || -1e9) > 20000) {
                    this.dpr = Math.min(this.maxDpr, this.dpr * 1.12);
                    this._tunedAt = now; this.resize();
                }
            }
        }
    }

    dispose() {
        this.disposed = true;
        this.stop();
        for (const [el, ev, fn, o] of this._on) { el.removeEventListener(ev, fn, o); }
        if (this._ro) { this._ro.disconnect(); }
        this.controls.dispose();
        this._clearIslands();
        this.skyTarget.dispose();
        this.scene.traverse((o) => { if (o.geometry) { o.geometry.dispose(); } if (o.material) { o.material.dispose(); } });
        this.renderer.dispose();
    }
}

export {BELT_EPOCH, BELT_INNER, BELT_OUTER};
