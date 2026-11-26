/**
 * Asteroid Islands (ISLANDS_MODE; KB notes/islands-build.md) — lazy feature bundle "i".
 *
 * Alpine components:
 *   islandsBelt        /islands             full-viewport WebGL belt (lazy chunk "b", ../islands/belt/*) with filters, search,
 *                                           tooltip + modal; island list as fallback / accessible view
 *   islandsBuilder     /islands/build       builder v2 (Sportvatar-builder UX): tabs Deeds / scene layers / Tint with
 *                                           thumbnail grids, live animated preview, randomize, permalink hash; redeem =
 *                                           pick 1 deed or fuse 5 (packs can be opened in place), review dialog (deeds are
 *                                           BURNED), sign the terraform tx. No size figures for users (sizeMeter flag)
 *   islandPage         /islands/{id}        scene with premium residents, standard-resident carousel, owner tools
 *                                           (name/description, residents: set / move / clear)
 *   islandsCollection  /collection/{addr}   "Islands" tab
 *
 * Chain-first: wallet data is read in the browser with FCL scripts (resources/js/islands/cadence.mjs); transactions are
 * resources/js/flow/island-*.tx.js (passkey-compatible through ./fcl-tx). The server only indexes events and serves
 * island/template JSON. No fees, no payment UI, no re-terraform.
 * Inert unless the page printed window.ISLANDS.
 */
import Alpine from 'alpinejs';
import * as fcl from '@onflow/fcl';
import {WALLET_CADENCE, PREVIEW_CADENCE, RESIDENTS_CADENCE, WALLET_NFTS_CADENCE, DISPLAYS_CADENCE, OWNERSHIP_CADENCE} from '../islands/cadence.mjs';
import {composeScene, displayFix, sceneBytes, islandRarity, validateScene, friendlyChainError, isHexColor, slotList, httpUrl, designation, setDesignationPrefix, normalizeAddress, RARITIES,
    DISPLAY_LAYER, isSharedLayer, isMutableLayer, templateFits, layerOptions, orderedLayers, templateScreen, screenFragment, insertScreen, screenBox, SCREEN_PLACEHOLDER, planOps,
    residentGroupSvg, injectResidents, slotBox, residentTransform} from '../islands/compose.mjs';
import {withWatermark} from '../islands/watermark.mjs';
import {islandsFromManifest, demoIslands, parseIslandQuery} from '../islands/belt/orbits.mjs';
import {islandTerraformTx} from '../flow/island-terraform.tx';
import {islandSetNameDescriptionTx} from '../flow/island-set-name-description.tx';
import {islandOpenPackTx} from '../flow/island-open-pack.tx';
import {islandUpdateResidentsTx} from '../flow/island-update-residents.tx';
import {islandSetLayerTx} from '../flow/island-set-layer.tx';

const CFG = window.ISLANDS || null;
const SLICE = 4000;

// ------------------------------------------------------------------------------------------------ shared helpers

let chainCfg = null;
const templateCache = {};

function setup() {
    if (CFG && CFG.contract) { fcl.config().put('0xIslands', CFG.contract); }
    if (CFG) { setDesignationPrefix(CFG.designationPrefix); }
}

/** WebGL probe without keeping a context (the belt scene is a lazy chunk; this decides belt vs list first). */
function webglLikely() {
    try {
        const c = document.createElement('canvas');
        const gl = window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl'));
        if (!gl) { return false; }
        const lose = gl.getExtension('WEBGL_lose_context');
        if (lose) { lose.loseContext(); }
        return true;
    } catch (e) { return false; }
}

async function getJson(url) {
    const r = await fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
    if (!r.ok) { const e = new Error('HTTP ' + r.status); e.status = r.status; throw e; }
    return r.json();
}

async function loadConfig() {
    if (!chainCfg) { chainCfg = await getJson('/api/islands/config'); }
    return chainCfg;
}

async function loadTemplate(id) {
    if (!templateCache[id]) { templateCache[id] = getJson('/api/islands/templates/' + id); }
    return templateCache[id];
}

async function refreshIsland(id, owner, tx) {
    const ax = window.axios;
    if (!ax) { return null; }
    try {
        const body = {};
        if (owner) { body.owner = owner; }
        if (tx) { body.tx = tx; }   // server reads the island at this transaction's block
        const r = await ax.post('/api/islands/' + id + '/refresh', body);
        return r.data;
    } catch (e) { return null; }
}

function walletKind(user) {
    if (!user) { return ''; }
    if (user.authType === 'passkey' || (window.extSession && window.extSession.active)) { return 'passkey'; }
    return window.detectWallet ? window.detectWallet(user) : 'flow';
}

function txErrorText(e) {
    const s = String((e && (e.message || e.errorMessage)) || e || '');
    if (/declined|rejected|cancel/i.test(s)) { return 'You cancelled the transaction in your wallet.'; }
    const m = /error: (?:assertion failed|panic): ([^\n]+)/i.exec(s) || /message: "?([^"\n]+)/i.exec(s);
    return m ? m[1].trim() : (s.length > 300 ? s.slice(0, 300) + '\u2026' : s) || 'The transaction failed.';
}

async function sealed(txId) {
    const tx = await fcl.tx(txId).onceSealed();
    if (tx.statusCode !== 0 || tx.errorMessage) { throw new Error(tx.errorMessage || 'Transaction failed'); }
    return tx;
}

async function readInventory(address) {
    let start = 0;
    let acc = null;
    for (let guard = 0; guard < 100; guard++) {
        const r = await fcl.query({cadence: WALLET_CADENCE, args: (arg, t) => [arg(address, t.Address), arg(String(start), t.Int), arg(String(SLICE), t.Int)]});
        if (!acc) { acc = r; } else { acc.deeds = acc.deeds.concat(r.deeds || []); }
        start += SLICE;
        if (start >= Number(r.componentTotal || 0)) { break; }
    }
    acc.deeds = (acc.deeds || []).map((d) => ({id: Number(d.id), templateId: Number(d.templateId), rarity: d.rarity, name: d.name}))
        .sort((a, b) => a.id - b.id);
    return acc;
}

function inRanges(id, ranges) {
    const n = Number(id);
    return (ranges || []).some(([a, b]) => n >= a && n <= b);
}

function shortAddr(a) { return a ? a.slice(0, 6) + '\u2026' + a.slice(-4) : ''; }

/** Common wallet-connection state for every component. */
function walletMixin() {
    return {
        user: null,
        wallet: '',
        watchUser(cb) {
            window.addEventListener('user-loaded', (e) => { this.onUserBase(e.detail && e.detail.user, cb); });
            window.addEventListener('logged-out', () => { this.user = null; this.wallet = ''; cb && cb(null); });
            if (window.user && window.user.addr) { this.onUserBase(window.user, cb); }
        },
        onUserBase(user, cb) {
            if (!user || !user.addr) { return; }
            const addr = normalizeAddress(user.addr);
            if (this.user && this.user.addr === addr) { return; }
            this.user = {addr, authType: user.authType || ''};
            this.wallet = walletKind(user);
            cb && cb(this.user);
        },
        connect() { fcl.authenticate(); },
        explorer(txId) { return (CFG.network === 'mainnet' ? 'https://www.flowscan.io/tx/' : 'https://testnet.flowscan.io/tx/') + txId; },
        short: shortAddr,
        designation,
        dapper() { return this.wallet === 'dapper'; },
    };
}

// ------------------------------------------------------------------------------------------------ components

if (CFG) {
    setup();

    // --------------------------------------------------------------------------- /islands
    // Full-viewport WebGL belt (lazy chunk "b": ../islands/belt/scene.js) + filters / search / tooltip / modal, with
    // the island list as the fallback (no WebGL, prefers-reduced-motion, ?view=list) and as the accessible view.
    // The three.js scene and the island array live OUTSIDE Alpine's reactive data (a Proxy around either is slow).
    let beltScene = null;
    let beltItems = [];        // frozen island objects (real + ?demo placeholders)
    let beltTex = null;

    Alpine.data('islandsBelt', () => ({
        ...walletMixin(),
        view: 'list',            // belt | list
        ready: false,            // manifest loaded: the Alpine list replaces the server-rendered one
        loadError: false,
        canWebgl: false,
        reduced: false,
        sceneState: 'idle',      // idle | loading | ready | failed
        version: 0,              // bumped when beltItems changes
        total: 0,
        counts: {common: 0, epic: 0, legendary: 0},
        rarity: 'all',
        mineOnly: false,
        query: '',
        searchMsg: '',
        suggestions: [],
        listLimit: 60,
        listFocus: null,
        hoverItem: null,
        pinItem: null,
        modal: null,
        detail: null,
        detailStatus: 'idle',
        demo: 0,
        fps: null,
        showFps: false,
        hint: true,
        touch: false,
        panelOpen: true,
        following: false,
        inv: null,
        mineStatus: 'idle',
        center: (CFG.belt && CFG.belt.center) || '',
        sector: CFG.sector || 'K313',

        init() {
            const qs = new URLSearchParams(window.location.search);
            this.reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            this.canWebgl = window.__beltGL !== undefined ? !!window.__beltGL : webglLikely();
            const want = qs.get('view');
            this.view = want === 'list' || !this.canWebgl ? 'list' : (want === 'belt' || !this.reduced ? 'belt' : 'list');
            this.showFps = qs.get('fps') === '1';
            this.touch = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
            this.panelOpen = !(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
            if (window.location.hash === '#mine') { this.mineOnly = true; }
            if (CFG.belt && CFG.belt.demo && qs.has('demo')) {
                const n = parseInt(qs.get('demo'), 10);
                this.demo = Math.max(0, Math.min(5000, n > 1 ? n : (n === 0 ? 0 : (CFG.belt.demoDefault || 2000))));
            }
            this._focus = parseInt(qs.get('focus'), 10) || null;
            this.watchUser((u) => { if (u) { this.loadMine(); } else { this.mineOnly = false; this.applyFilter(); } });
            this.$watch('rarity', () => this.applyFilter());
            this.$watch('mineOnly', () => this.applyFilter());
            this.load();
        },

        async load() {
            try {
                const m = await getJson('/api/islands/belt');
                setDesignationPrefix(m.prefix);
                let items = islandsFromManifest(m);
                beltTex = {grid: m.cell, atlases: m.atlases || {}, sprite: m.sprite};
                if (this.demo > 0) { items = items.concat(demoIslands(items, this.demo, m.cell.per, m.prefix)); }
                this.setItems(items);
                this.ready = true;
                if (this.view === 'belt') { await this.startScene(); }
                if (this._focus) { const i = this.find(this._focus); if (i) { this.go(i); } }
            } catch (e) {
                console.error(e);
                this.loadError = true;
                this.view = 'list';
            }
        },

        setItems(items) {
            beltItems = Object.freeze(items.map((i) => Object.freeze(i)));
            const c = {common: 0, epic: 0, legendary: 0};
            for (const i of beltItems) { c[i.rarity]++; }
            this.counts = c;
            this.total = beltItems.length;
            this.version++;
            if (beltScene) { beltScene.setIslands(beltItems, beltTex); this.applyFilter(); }
        },

        // ---------------------------------------------------------------- 3D scene
        async startScene() {
            if (beltScene) { beltScene.start(); return; }
            if (this.sceneState === 'loading') { return; }
            this.sceneState = 'loading';
            try {
                const {BeltScene} = await import(/* webpackChunkName: "b" */ '../islands/belt/scene');
                await this.$nextTick();
                const low = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches) || Math.min(window.innerWidth, window.innerHeight) < 600;
                beltScene = new BeltScene(this.$refs.canvas, {
                    reducedMotion: this.reduced, low,
                    onHover: (item, x, y) => this.onHover(item, x, y),
                    onSelect: (item, e) => this.onSelect(item, e),
                    onTrack: (s) => this.onTrack(s),
                    onInteract: () => { this.hint = false; },
                    onStats: (st) => { if (this.showFps) { this.fps = {...st}; } },
                    onLost: () => { this.sceneState = 'failed'; this.view = 'list'; },
                });
                window.__belt = beltScene; // QA / fps measurements
                beltScene.setIslands(beltItems, beltTex);
                this.applyFilter();
                this.syncInsets();
                beltScene.start();
                this.sceneState = 'ready';
                window.addEventListener('resize', () => this.syncInsets());
                this.$watch('panelOpen', () => this.$nextTick(() => this.syncInsets()));
                document.documentElement.classList.add('belt-3d');
            } catch (e) {
                console.error(e);
                this.sceneState = 'failed';
                this.view = 'list';
                document.documentElement.classList.remove('belt-3d');
            }
        },

        /** Tell the scene which part of the canvas the panel covers (left on desktop, top on phones). */
        syncInsets() {
            if (!beltScene || !this.$refs.stage) { return; }
            const panel = this.$root.querySelector('.belt-panel');
            const st = this.$refs.stage.getBoundingClientRect();
            if (!panel) { beltScene.setInsets(0, 0); return; }
            const r = panel.getBoundingClientRect();
            const phone = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
            if (phone) { beltScene.setInsets(0, Math.min(st.height * 0.35, Math.max(0, r.bottom - st.top))); } else { beltScene.setInsets(Math.min(st.width * 0.4, Math.max(0, r.right - st.left)), 0); }
        },

        setView(v) {
            if (v === this.view) { return; }
            this.view = v;
            const u = new URL(window.location.href);
            u.searchParams.set('view', v);
            history.replaceState(null, '', u.toString());
            this.hoverItem = null;
            if (v === 'belt') {
                document.documentElement.classList.add('belt-3d');
                this.$nextTick(() => this.startScene().then(() => beltScene && beltScene.resize()));
            } else {
                document.documentElement.classList.remove('belt-3d');
                if (beltScene) { beltScene.stop(); }
                this.pinItem = null;
            }
        },

        // ---------------------------------------------------------------- filters / list
        matches(i) {
            if (this.rarity !== 'all' && i.rarity !== this.rarity) { return false; }
            if (this.mineOnly && (!this.user || i.owner !== this.user.addr)) { return false; }
            return true;
        },
        filtering() { return this.rarity !== 'all' || this.mineOnly; },
        applyFilter() {
            this.listLimit = 60;
            if (beltScene) { beltScene.setFilter(this.filtering() ? (i) => this.matches(i) : null); }
            if (this.pinItem && !this.pinItem.center && !this.matches(this.pinItem)) { this.unpin(); }
        },
        filtered() { return this.version >= 0 && this.filtering() ? beltItems.filter((i) => this.matches(i)) : beltItems; },
        listItems() { return this.filtered().slice(0, this.listLimit); },
        shown() { return this.filtered().length; },
        mineCount() { return this.version >= 0 && this.user ? beltItems.filter((i) => i.owner === this.user.addr).length : 0; },
        toggleMine() {
            this.mineOnly = !this.mineOnly;
            history.replaceState(null, '', this.mineOnly ? '#mine' : window.location.pathname + window.location.search);
        },
        find(id) { return beltItems.find((i) => i.id === id) || null; },
        image(i) {
            if (!i) { return ''; }
            if (i.key && beltTex) { return beltTex.sprite.replace('{id}', i.demo ? i.src : i.id).replace('{key}', i.key); }
            return '/islands/' + i.id + '/full.svg';
        },
        title(i) { return i ? (i.name || (i.demo ? 'Placeholder island' : 'Unnamed island')) : ''; },

        // ---------------------------------------------------------------- search
        onQuery() {
            this.searchMsg = '';
            const q = this.query.trim().toLowerCase();
            this.suggestions = q.length >= 2 && parseIslandQuery(q) === null
                ? beltItems.filter((i) => i.name && i.name.toLowerCase().includes(q)).slice(0, 6) : [];
        },
        search() {
            const q = this.query.trim();
            if (q === '') { return; }
            const id = parseIslandQuery(q);
            const hit = id !== null ? this.find(id) : (this.suggestions[0] || beltItems.find((i) => i.name && i.name.toLowerCase().includes(q.toLowerCase())) || null);
            if (!hit) {
                this.searchMsg = id !== null ? 'No island ' + designation(id) + ' in the belt yet.' : 'No island with that name. Try a number, e.g. ' + designation(1) + '.';
                return;
            }
            this.go(hit);
        },
        /** Fly the camera to an island (3D) or scroll to its card (list). */
        go(i) {
            this.suggestions = [];
            this.searchMsg = '';
            this.query = i.designation;
            if (!this.matches(i)) { this.rarity = 'all'; this.mineOnly = false; this.applyFilter(); }
            if (this.view === 'belt' && beltScene) {
                this.hint = false;
                this.following = beltScene.focus(i.id);
                this.pin(i);
                if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) { this.panelOpen = false; }
                return;
            }
            const at = this.filtered().indexOf(i);
            if (at >= this.listLimit) { this.listLimit = at + 12; }
            this.listFocus = i.id;
            this.$nextTick(() => {
                const el = this.$root.querySelector('[data-island-card="' + i.id + '"]');
                if (el) { el.scrollIntoView({behavior: this.reduced ? 'auto' : 'smooth', block: 'center'}); el.focus({preventScroll: true}); }
            });
        },
        resetView() {
            if (beltScene) { beltScene.focus(null); }
            this.following = false;
            this.unpin();
        },

        // ---------------------------------------------------------------- tooltip
        tipItem() { return this.hoverItem || this.pinItem; },
        tipPinned() { return !this.hoverItem && !!this.pinItem; },
        onHover(item, x, y) {
            if (this.modal) { return; }
            const cur = this.hoverItem;
            const same = item ? (cur && (item.center ? !!cur.center : cur.id === item.id && !cur.center)) : !cur;
            if (!same) {
                this.hoverItem = item || null;
                this.syncHighlight();
                if (!item && this.pinItem && this.pinItem.center && this._pinAt) { this.placeTip(this._pinAt.x + 14, this._pinAt.y + 14, this._pinAt.x - 14); }
            }
            if (item) { this.placeTip(x + 16, y + 18, x - 16); }
        },
        onSelect(item, e) {
            this.hint = false;
            if (!item) { this.unpin(); return; }
            if (item.center) { this.hoverItem = null; this.pinItem = item; this._pinAt = {x: e.x, y: e.y}; this.syncHighlight(); this.$nextTick(() => this.placeTip(e.x + 14, e.y + 14, e.x - 14)); return; }
            if (e.pointerType === 'touch' || e.pointerType === 'pen') {
                // first tap = tooltip + highlight, second tap on the same island = details
                if (this.pinItem && this.pinItem.id === item.id) { this.openModal(item); } else { this.pin(item); }
                return;
            }
            this.openModal(item);
        },
        pin(i) { this.hoverItem = null; this.pinItem = i; this.syncHighlight(); },
        unpin() { this.pinItem = null; this.syncHighlight(); },
        syncHighlight() {
            const t = this.tipItem();
            if (beltScene) { beltScene.setHighlight(t && !t.center ? t.id : null); }
        },
        /** Scene callback, every frame while an island is highlighted: keep the pinned tooltip above it. */
        onTrack(s) {
            if (this.hoverItem || !this.pinItem || this.pinItem.center) { return; }
            const tip = this.$refs.tip;
            if (!tip) { return; }
            tip.style.visibility = s.visible ? 'visible' : 'hidden';
            const w = tip.offsetWidth, h = tip.offsetHeight, r = this.$refs.stage.getBoundingClientRect();
            let x = s.x - r.left - w / 2, y = s.y - r.top - s.h * 0.34 - h - 12;
            if (y < 70) { y = s.y - r.top + s.h * 0.5 + 12; }
            x = Math.max(8, Math.min(r.width - w - 8, x));
            y = Math.max(8, Math.min(r.height - h - 8, y));
            tip.style.transform = 'translate3d(' + Math.round(x) + 'px,' + Math.round(y) + 'px,0)';
        },
        placeTip(x, y, flipX) {
            const tip = this.$refs.tip;
            if (!tip) { return; }
            const r = this.$refs.stage.getBoundingClientRect();
            const w = tip.offsetWidth || 220, h = tip.offsetHeight || 80;
            let tx = x - r.left, ty = y - r.top;
            if (tx + w > r.width - 8) { tx = flipX - r.left - w; }
            if (ty + h > r.height - 8) { ty = y - r.top - h - 36; }
            tip.style.visibility = 'visible';
            tip.style.transform = 'translate3d(' + Math.round(Math.max(8, tx)) + 'px,' + Math.round(Math.max(8, ty)) + 'px,0)';
        },

        // ---------------------------------------------------------------- modal
        async openModal(i) {
            if (!i || i.center) { return; }
            this._returnFocus = document.activeElement;
            this.hoverItem = null;
            this.modal = i;
            this.detail = null;
            this.detailStatus = i.demo ? 'demo' : 'loading';
            document.documentElement.classList.add('belt-modal-open');
            this.$nextTick(() => { const b = this.$refs.modalClose; if (b) { b.focus(); } });
            if (i.demo) { return; }
            try {
                const d = await getJson('/api/islands/' + i.id);
                if (this.modal === i) { this.detail = d; this.detailStatus = 'done'; }
            } catch (e) { if (this.modal === i) { this.detailStatus = 'error'; } }
        },
        closeModal() {
            if (!this.modal) { return; }
            this.modal = null;
            document.documentElement.classList.remove('belt-modal-open');
            const el = this._returnFocus;
            if (el && el.focus && document.contains(el)) { el.focus({preventScroll: true}); }
        },
        /** Keep Tab inside the dialog. */
        trapFocus(e) {
            const els = [...this.$refs.modalCard.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter((el) => el.offsetParent !== null);
            if (!els.length) { e.preventDefault(); return; }
            const first = els[0], last = els[els.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        },
        flyFromModal() { const i = this.modal; this.closeModal(); if (i) { if (this.view !== 'belt') { this.setView('belt'); } this.$nextTick(() => this.go(i)); } },
        onEscape() {
            if (this.modal) { this.closeModal(); } else if (this.suggestions.length) { this.suggestions = []; } else if (this.pinItem) { this.unpin(); } else if (this.following) { this.resetView(); }
        },
        premiumResidents() { return this.detail ? (this.detail.residentsList || []).filter((r) => r.premium && r.valid && r.slot !== 'caretaker') : []; },
        standardCount() { return this.detail ? (this.detail.residentsList || []).filter((r) => !r.premium && r.valid && r.slot !== 'caretaker').length : 0; },
        residentThumb(r) {
            if (r.family === 'Flovatar') { return '/api/image/r/flovatar/' + r.id + '/thumb.jpg'; }
            if (r.family === 'Flobot') { return '/api/image/r/flobot/' + r.id + '/thumb.jpg'; }
            if (r.family === 'FlovatarDustCollectible') { return '/api/image/r/collectible/' + r.id + '/thumb.jpg'; }
            return '';
        },
        familyLabel(r) { return (CFG.families && CFG.families[r.family] && CFG.families[r.family].label) || 'NFT'; },
        terraformed() {
            if (!this.detail || !this.detail.terraformedAt) { return ''; }
            const d = new Date(this.detail.terraformedAt);
            return isNaN(d) ? '' : d.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: 'numeric'});
        },

        // ---------------------------------------------------------------- my islands (connected wallet)
        async loadMine() {
            if (!this.user || this.mineStatus === 'loading') { return; }
            this.mineStatus = 'loading';
            try {
                const inv = await readInventory(this.user.addr);
                this.inv = inv;
                // freshly terraformed islands the indexer hasn't seen yet: read them from chain and add them to the belt
                const fresh = [];
                for (const id of (inv.islands || []).map(Number)) {
                    if (!this.find(id)) {
                        const d = await refreshIsland(id, this.user.addr);
                        if (d) {
                            const r = Math.max(0, RARITIES.indexOf(d.rarity));
                            fresh.push({id: d.id, r, rarity: d.rarity, name: d.rawName || '', residents: d.residents, capacity: d.capacity, owner: d.owner, updated: 0,
                                hasCell: false, key: '', page: '_', slot: 0, designation: d.designation, url: d.url, demo: false});
                        }
                    }
                }
                if (fresh.length) { this.setItems([...beltItems.filter((i) => !i.demo), ...fresh, ...beltItems.filter((i) => i.demo)]); }
                this.mineStatus = 'done';
            } catch (e) { console.log(e); this.mineStatus = 'error'; }
        },
        deedCount(r) { return this.inv ? this.inv.deeds.filter((d) => d.rarity === r).length : 0; },
        packCount() { return this.inv ? (this.inv.packs || []).filter((p) => inRanges(p, CFG.packRanges)).length : 0; },
    }));

    // --------------------------------------------------------------------------- /collection/{address}
    Alpine.data('islandsCollection', () => ({
        isl: [],
        islStatus: 'idle',
        init() {
            // `address` comes from the parent collectionData scope (may resolve later for /collection/{name})
            const load = async (a) => {
                a = normalizeAddress(a);
                if (!a || this.islStatus === 'loading' || this.islFor === a) { return; }
                this.islFor = a;
                this.islStatus = 'loading';
                try { this.isl = (await getJson('/api/islands?owner=' + a)).islands || []; this.islStatus = 'done'; } catch (e) { this.islStatus = 'error'; }
            };
            load(this.address);
            this.$watch('address', (a) => load(a));
        },
        islFor: null,
    }));

    // --------------------------------------------------------------------------- /islands/build
    // Builder v2 (KB islands-build.md §E): Sportvatar-builder layout and interaction — category tabs (Deeds, the scene
    // layers, Tint) with thumbnail grids, a big live preview with rarity / burn badges and a tool bar, review + sign in
    // a dialog. Anyone can play with it (a preview rarity without a wallet); terraforming needs the deed(s).
    // Size budgets are never shown to users (sizeMeter = internal flag ISLANDS_SHOW_SIZE_METER).
    const svgs = {};               // template id -> {svg, svgFront, svgLength, svgFrontLength} (outside Alpine: big strings)
    const thumbUrls = new Map();   // scene key -> object URL
    const LAYER_LABELS = {sky: 'Sky', base: 'Base', structure: 'Structure', fub: 'FUB', display: 'Display', foreground: 'Foreground', fx: 'Effects'};
    const TAB_ICONS = ['deeds', 'sky', 'base', 'structure', 'fub', 'display', 'foreground', 'fx', 'tint'];
    // what each tab tells the player (round 4: skies change any time, effects are permanent, the display shows NFTs)
    const LAYER_NOTES = {
        sky: 'You can change the sky any time.',
        fx: 'Effects are permanent.',
        display: 'Your island\u2019s display shows your other NFTs: every NFT you place on its display slots plays on this screen. Visitors tap it to browse them.',
    };
    const TINTS = ['#EF2AC1', '#91E3F9', '#FFEE50', '#52F618', '#FF6E03', '#AED7F7', '#8A4BFF', '#E8384F', '#2FD4B1'];
    // chance that Randomize fills an optional layer
    const RANDOM_FILL = {sky: 1, structure: 0.85, fub: 0.5, display: 1, foreground: 0.65, fx: 0.6};

    async function loadSvg(id) {
        if (!svgs[id]) { svgs[id] = await loadTemplate(id); }
        return svgs[id];
    }

    /** Builder scene: the chosen parts; a display shows the placeholder of the first standard NFT on its screen. */
    function builderSvg(cfg, layers, tint) {
        let svg = composeScene(cfg, layers, tint, null, svgs);
        const did = layers[DISPLAY_LAYER];
        if (did) {
            const info = (cfg.templates || []).find((t) => t.id === did);
            const screen = templateScreen(info);
            if (screen) { svg = insertScreen(svg, screenFragment(screen, SCREEN_PLACEHOLDER, null, 'S1'), svgs[did]); }
        }
        return svg;
    }

    function sceneUrl(cfg, layers, tint) {
        return URL.createObjectURL(new Blob([displayFix(builderSvg(cfg, layers, tint))], {type: 'image/svg+xml'}));
    }

    Alpine.data('islandsBuilder', () => ({
        ...walletMixin(),
        cfg: null,
        cfgError: false,
        status: 'idle',      // wallet inventory: idle | loading | ready | error
        error: '',
        inv: null,
        packs: [],
        openingPack: null,
        packMessage: '',
        mode: 'single',      // single | fuse
        selected: [],        // deed ids
        tab: 'deeds',        // deeds | <layer> | tint
        previewRarity: 'common',
        layers: {},
        tintOn: false,
        tint: TINTS[0],
        preview: '',
        previewBusy: false,
        thumbs: {},          // option key -> object URL (current tab)
        notice: '',
        sizeMeter: !!CFG.sizeMeter,
        // review / sign
        review: false,
        snap: null,          // what the dialog shows / signs
        step: 'build',       // build | review | signing | done
        chainCheck: '',      // '' | checking | ok | <message>
        understood: false,
        txId: '',
        txStatus: '',
        txError: '',
        islandId: null,
        tints: TINTS,
        rarities: RARITIES,

        async init() {
            try { this.cfg = await loadConfig(); } catch (e) { this.cfgError = true; return; }
            this.readHash();
            if (!Object.keys(this.layers).length) { this.defaultScene(); } else { this.renderPreview(); }
            this.prefetch(this.rarity);
            this.$watch('rarity', (r, old) => {
                if (!r || r === old) { return; }
                const kept = Object.entries(this.layers).every(([name, id]) => templateFits(this.cfg, this.infos[id], name, r));
                if (!kept) {
                    this.notice = this.fromDeeds ? 'Your island will be ' + r + ', so the parts switched to ' + r + ' art: every part matches the island\u2019s rarity.' : '';
                    this.defaultScene();
                }
                if (!this.tintAllowed) { this.tintOn = false; }
                if (this.tab !== 'deeds' && this.tab !== 'tint' && !this.sceneLayers.includes(this.tab)) { this.tab = 'base'; }
                if (this.tab === 'tint' && !this.tintAllowed) { this.tab = 'base'; }
                this.prefetch(r);
            });
            this.$watch('tab', () => this.renderThumbs());
            this.$watch('notice', (n) => { clearTimeout(this._noticeTimer); if (n) { this._noticeTimer = setTimeout(() => { this.notice = ''; }, 7000); } });
            this.watchUser((u) => { if (u) { this.loadInventory(); } else { this.inv = null; this.selected = []; this.status = 'idle'; } });
        },

        // ---------------- wallet inventory
        async loadInventory() {
            if (!this.user) { return; }
            this.status = 'loading';
            this.error = '';
            try {
                this.inv = await readInventory(this.user.addr);
                this.packs = (this.inv.packs || []).map(Number).filter((p) => inRanges(p, CFG.packRanges)).sort((a, b) => a - b);
                this.selected = this.selected.filter((id) => this.inv.deeds.some((d) => d.id === id));
                this.status = 'ready';
            } catch (e) {
                console.log(e);
                this.status = 'error';
                this.error = 'We couldn\u2019t read your wallet from the Flow blockchain. Try again.';
            }
        },
        deedsOf(r) { return this.inv ? this.inv.deeds.filter((d) => d.rarity === r) : []; },
        get hasDeeds() { return !!(this.inv && this.inv.deeds.length); },
        get deedCount() { return this.inv ? this.inv.deeds.length : 0; },

        async openPack(id) {
            if (this.openingPack) { return; }
            if (this.dapper()) { this.packMessage = 'Dapper Wallet can\u2019t open this pack here. Use Flow Wallet.'; return; }
            this.openingPack = id;
            this.packMessage = '';
            const before = new Set((this.inv.deeds || []).map((d) => d.id));
            try {
                const txId = await islandOpenPackTx(id);
                this.packMessage = 'Opening pack #' + id + '\u2026 waiting for the blockchain.';
                await sealed(txId);
                await this.loadInventory();
                const fresh = this.inv.deeds.filter((d) => !before.has(d.id));
                this.packMessage = 'Pack #' + id + ' opened.' + (fresh.length ? ' New land deed: ' + fresh.map((d) => d.rarity + ' #' + d.id).join(', ') + '.' : '');
            } catch (e) {
                this.packMessage = 'Pack #' + id + ' wasn\u2019t opened: ' + friendlyChainError(txErrorText(e));
            }
            this.openingPack = null;
        },

        // ---------------- deed selection
        setMode(m) { if (this.mode !== m) { this.mode = m; this.selected = []; } },
        isSelected(id) { return this.selected.includes(id); },
        pickIndex(id) { return this.selected.indexOf(id) + 1; },
        deedDisabled(d) { return this.mode === 'fuse' && d.rarity === 'legendary'; },
        toggleDeed(d) {
            if (this.deedDisabled(d)) { return; }
            this.notice = '';
            if (this.mode === 'single') { this.selected = this.isSelected(d.id) ? [] : [d.id]; return; }
            if (this.isSelected(d.id)) { this.selected = this.selected.filter((x) => x !== d.id); return; }
            const r = this.selectedRarity;
            if (r && r !== d.rarity) { this.selected = []; }
            if (this.selected.length < 5) { this.selected = [...this.selected, d.id]; }
        },
        pickFive(r) { this.mode = 'fuse'; this.selected = this.deedsOf(r).slice(0, 5).map((d) => d.id); },
        canFuse(r) { return r !== 'legendary' && this.deedsOf(r).length >= 5; },
        get selectedDeeds() { return this.inv ? this.inv.deeds.filter((d) => this.selected.includes(d.id)) : []; },
        get selectedRarity() { const d = this.selectedDeeds[0]; return d ? d.rarity : null; },
        /** The deed choice is complete: 1 deed, or 5 of one rarity in fuse mode. */
        get deedsReady() {
            const ds = this.selectedDeeds;
            if (this.mode === 'single') { return ds.length === 1; }
            return ds.length === 5 && ds.every((d) => d.rarity === ds[0].rarity) && !!islandRarity(ds[0].rarity, 5);
        },
        /** Rarity being designed: from the deed choice (also while fusing is incomplete), else the preview rarity. */
        get rarity() {
            const r = this.selectedRarity;
            if (r) { return this.mode === 'fuse' ? (islandRarity(r, 5) || r) : r; }
            return this.previewRarity;
        },
        get fromDeeds() { return !!this.selectedRarity; },
        setPreviewRarity(r) { if (!this.fromDeeds && RARITIES.includes(r)) { this.previewRarity = r; } },
        get selectionHint() {
            if (!this.user) { return 'Connect the wallet that holds your TerraKit land deeds.'; }
            if (this.status === 'loading') { return 'Reading your wallet\u2026'; }
            if (!this.hasDeeds) { return this.packs.length ? 'Open a TerraKit pack to get its land deed.' : 'This wallet has no TerraKit land deeds.'; }
            if (this.mode === 'single') { return this.selected.length ? 'One ' + this.selectedRarity + ' deed: a ' + this.rarity + ' island.' : 'Pick the land deed to redeem.'; }
            const n = this.selected.length;
            if (n < 5) { return n ? 'Pick ' + (5 - n) + ' more ' + this.selectedRarity + ' deed' + (5 - n === 1 ? '' : 's') + ' to fuse.' : 'Pick 5 deeds of the same rarity.'; }
            return '5 ' + this.selectedRarity + ' deeds fuse into one ' + this.rarity + ' island.';
        },
        deedImage(d) { return '/api/image/template/' + d.templateId; },

        // ---------------- tabs
        get sceneLayers() {
            if (!this.cfg) { return []; }
            return orderedLayers(this.cfg).filter((name) => ((this.cfg.layers[name] || {}).allowed || []).includes(this.rarity));
        },
        get tabs() {
            const t = [{key: 'deeds', label: 'Deeds'}];
            for (const name of this.sceneLayers) { t.push({key: name, label: this.layerLabel(name)}); }
            if (this.tintAllowed) { t.push({key: 'tint', label: 'Tint'}); }
            return t;
        },
        layerLabel(name) { return LAYER_LABELS[name] || name; },
        /** Short note under a layer's info card (sky: changeable later, effects: permanent, display: what it shows). */
        layerNote(name) {
            if (name === 'sky' && !this.isMutable('sky')) { return ''; }   // only once the contract really allows it
            return LAYER_NOTES[name] || '';
        },
        /** The owner can change this layer after terraforming (contract mutableLayers, e.g. the sky). */
        isMutable(name) { return isMutableLayer(this.cfg, name); },
        isShared(name) { return isSharedLayer(this.cfg, name); },
        setTab(k) { this.tab = k; this.$nextTick(() => { const g = this.$refs.grid; if (g) { g.scrollTop = 0; g.scrollLeft = 0; } }); },
        isLayerTab() { return this.tab !== 'deeds' && this.tab !== 'tint'; },
        tabBadge(k) {
            if (k === 'deeds') { return this.selected.length ? String(this.selected.length) : ''; }
            return '';
        },
        /** Category icon of a tab (public/images/islands/tab-<key>[-on].svg; "on" = brand colours, shown on the active tab). */
        tabIcon(k, on) { return TAB_ICONS.includes(k) ? '/images/islands/tab-' + k + (on ? '-on' : '') + '.svg' : ''; },
        /** An optional layer left on None: its tab icon is dimmed. */
        tabEmpty(k) { return k !== 'deeds' && k !== 'tint' && !this.layers[k]; },
        nextTab() {
            const keys = this.tabs.map((t) => t.key);
            const i = keys.indexOf(this.tab);
            if (i >= 0 && i < keys.length - 1) { this.setTab(keys[i + 1]); }
        },

        // ---------------- scene
        /** Required by the contract, and (round 4, Luca) the display as soon as there is display art. */
        isRequired(name) {
            if (((this.cfg.layers[name] || {}).required || []).includes(this.rarity)) { return true; }
            return name === DISPLAY_LAYER && this.optionsFor(name).length > 0;
        },
        /** Shared layers (sky, effects, display) list every enabled template, whatever the rarity; others are rarity-locked. */
        optionsFor(name) { return layerOptions(this.cfg, name, this.rarity); },
        rarityTag(t) { return t && t.rarity !== this.rarity && t.rarity !== 'any' ? t.rarity : ''; },
        get infos() { const m = {}; for (const t of ((this.cfg && this.cfg.templates) || [])) { m[t.id] = t; } return m; },
        get tintAllowed() { return !!this.cfg && (this.cfg.tintRarities || []).includes(this.rarity); },
        get tintValue() { return this.tintAllowed && this.tintOn && isHexColor(this.tint) ? this.tint.toLowerCase() : null; },
        get problems() {
            if (!this.cfg) { return []; }
            const p = validateScene(this.cfg, this.rarity, this.layers, this.tintValue, this.infos);
            if (this.isRequired(DISPLAY_LAYER) && this.sceneLayers.includes(DISPLAY_LAYER) && !this.layers[DISPLAY_LAYER]) { p.push('Pick a display'); }
            return p;
        },
        get selectedInfo() { const id = this.layers[this.tab]; return id ? this.infos[id] || null : null; },
        isAnimated(t) { return !!(t && t.metadata && (t.metadata.animated || /anim/i.test(String(t.metadata.motion || '')))) || /\(animated\)/i.test((t && t.description) || ''); },
        usedBy(t) { const n = Number((t && t.usage) || 0); return n === 1 ? '1 island' : n + ' islands'; },
        missingLayers() { return this.sceneLayers.filter((n) => this.isRequired(n) && !this.optionsFor(n).length); },
        defaultScene() {
            const layers = {};
            for (const name of this.sceneLayers) {
                const opts = this.optionsFor(name);
                if (opts.length && (this.isRequired(name) || name === 'sky' || name === 'structure')) { layers[name] = opts[0].id; }
            }
            this.layers = layers;
            this.tintOn = false;
            this.changed();
        },
        randomize() {
            const layers = {};
            for (const name of this.sceneLayers) {
                const opts = this.optionsFor(name);
                if (!opts.length) { continue; }
                if (this.isRequired(name) || Math.random() < (RANDOM_FILL[name] ?? 0.6)) { layers[name] = opts[Math.floor(Math.random() * opts.length)].id; }
            }
            this.layers = layers;
            if (this.tintAllowed) {
                this.tintOn = Math.random() < 0.45;
                if (this.tintOn) { this.tint = TINTS[Math.floor(Math.random() * TINTS.length)]; }
            }
            this.notice = '';
            this.changed();
        },
        pick(name, id) {
            const l = {...this.layers};
            if (id === null || id === '' || id === undefined) {
                if (this.isRequired(name)) { return; }
                delete l[name];
            } else { l[name] = Number(id); }
            this.layers = l;
            this.notice = '';
            this.changed();
        },
        setTint(v) {
            if (v === null) { this.tintOn = false; } else if (isHexColor(v)) { this.tint = v; this.tintOn = true; }
            this.changed();
        },
        changed() {
            this.writeHash();
            this.renderPreview();
            this.renderThumbs();
        },
        prefetch(r) {
            if (!this.cfg) { return; }
            for (const t of (this.cfg.templates || []).filter((x) => x.enabled && (x.rarity === r || templateFits(this.cfg, x, x.layer, r)))) { loadSvg(t.id).catch(() => {}); }
        },
        async renderPreview() {
            if (!this.cfg) { return; }
            this.previewBusy = true;
            try {
                await Promise.all(Object.values(this.layers).map((id) => loadSvg(id)));
                const url = sceneUrl(this.cfg, this.layers, this.tintValue);
                const old = this.preview;
                this.preview = url;
                if (old) { setTimeout(() => URL.revokeObjectURL(old), 2000); }
            } catch (e) { console.log(e); }
            this.previewBusy = false;
        },
        /** Option thumbnails of the active tab: the current scene with that option swapped in (no sky, except on Sky). */
        optionLayers(name, id) {
            const l = {...this.layers};
            if (name !== 'sky') { delete l.sky; }
            if (id === null) { delete l[name]; } else { l[name] = id; }
            return l;
        },
        thumbKey(name, id) { return name + ':' + (id === null ? 'none' : id); },
        thumb(name, id) { return this.thumbs[this.thumbKey(name, id)] || ''; },
        async renderThumbs() {
            if (!this.cfg) { return; }
            // a number, not an object: Alpine wraps stored objects in proxies, so an identity check would never match
            const token = this._thumbToken = (this._thumbToken || 0) + 1;
            const jobs = [];
            if (this.isLayerTab()) {
                const name = this.tab;
                if (!this.isRequired(name)) { jobs.push([this.thumbKey(name, null), this.optionLayers(name, null), this.tintValue]); }
                for (const t of this.optionsFor(name)) { jobs.push([this.thumbKey(name, t.id), this.optionLayers(name, t.id), this.tintValue]); }
            }
            const next = {};
            for (const [key, layers, tint] of jobs) {
                const sig = JSON.stringify([layers, tint]);
                let url = thumbUrls.get(sig);
                if (!url) {
                    try { await Promise.all(Object.values(layers).map((id) => loadSvg(id))); } catch (e) { continue; }
                    if (token !== this._thumbToken) { return; }
                    url = sceneUrl(this.cfg, layers, tint);
                    thumbUrls.set(sig, url);
                }
                next[key] = url;
            }
            if (token !== this._thumbToken) { return; }
            this.thumbs = next;
            // keep the cache bounded: drop URLs no longer shown
            if (thumbUrls.size > 160) {
                const live = new Set(Object.values(next));
                for (const [sig, url] of thumbUrls) { if (!live.has(url)) { URL.revokeObjectURL(url); thumbUrls.delete(sig); } }
            }
        },

        // ---------------- permalink (#r=epic&sky=28&base=31&tint=ef2ac1), like the Sportvatar builder's hash
        writeHash() {
            const p = new URLSearchParams();
            p.set('r', this.rarity);
            for (const name of this.sceneLayers) { if (this.layers[name]) { p.set(name, String(this.layers[name])); } }
            if (this.tintValue) { p.set('tint', this.tintValue.slice(1)); }
            history.replaceState(null, '', window.location.pathname + window.location.search + '#' + p.toString());
        },
        readHash() {
            const h = window.location.hash.replace(/^#/, '');
            if (!h) { return; }
            const p = new URLSearchParams(h);
            const r = p.get('r');
            if (RARITIES.includes(r)) { this.previewRarity = r; }
            const layers = {};
            for (const name of this.sceneLayers) {
                const id = Number(p.get(name));
                const t = this.infos[id];
                if (templateFits(this.cfg, t, name, this.rarity)) { layers[name] = id; }
            }
            if (this.sceneLayers.every((n) => !this.isRequired(n) || layers[n])) { this.layers = layers; }
            const tint = '#' + (p.get('tint') || '');
            if (this.tintAllowed && isHexColor(tint)) { this.tint = tint; this.tintOn = true; }
        },

        // ---------------- tools
        /** PNG of the design. Always stamped "UNMINTED ISLAND" (like the Flovatar / Sportvatar builders): nothing that
         *  leaves the builder can pass for a terraformed island. Island pages render real islands and never use this. */
        async savePng() {
            if (!this.cfg || !this.preview) { return; }
            let url = '';
            try {
                await Promise.all(Object.values(this.layers).map((id) => loadSvg(id)));
                const svg = withWatermark(displayFix(builderSvg(this.cfg, this.layers, this.tintValue)));
                if (!svg.includes('id="isb-unminted"')) { return; }
                url = URL.createObjectURL(new Blob([svg], {type: 'image/svg+xml'}));
                const img = new Image();
                img.src = url;
                await img.decode();
                const c = document.createElement('canvas');
                c.width = 1200; c.height = Math.round(1200 * 4033 / 3025);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                const b = await new Promise((res) => c.toBlob(res, 'image/png'));
                if (!b) { return; }
                const a = document.createElement('a');
                a.href = URL.createObjectURL(b);
                a.download = 'island-' + this.rarity + '-unminted.png';
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 4000);
            } catch (e) { console.log(e); }
            if (url) { URL.revokeObjectURL(url); }
        },
        // size meter: internal art-production aid only (ISLANDS_SHOW_SIZE_METER)
        get budget() { return (this.cfg && this.cfg.budget || {})[this.rarity] || 0; },
        get bytes() { return this.cfg ? sceneBytes(this.layers, this.infos) : 0; },
        get budgetPct() { return this.budget ? Math.min(100, Math.round(this.bytes * 100 / this.budget)) : 0; },
        kb(n) { return (Math.round(Number(n || 0) / 100) / 10).toLocaleString() + ' KB'; },

        // ---------------- main button (Sportvatar: one call to action under the preview, a warning tooltip above it)
        get cta() {
            if (!this.cfg) { return {label: 'Terraform', disabled: true, hint: ''}; }
            if (!this.user) { return {label: 'Connect to terraform', action: 'connect', hint: ''}; }
            if (this.dapper()) { return {label: 'Terraform', disabled: true, hint: 'Dapper Wallet can\u2019t sign this transaction. Use Flow Wallet.'}; }
            if (this.status === 'loading') { return {label: 'Reading wallet\u2026', disabled: true, hint: ''}; }
            if (this.status === 'error') { return {label: 'Try again', action: 'reload', hint: this.error}; }
            if (!this.hasDeeds) { return {label: this.packs.length ? 'Open a pack' : 'No land deeds', action: this.packs.length ? 'deeds' : '', disabled: !this.packs.length, hint: this.selectionHint}; }
            if (!this.deedsReady) { return {label: this.mode === 'fuse' ? 'Pick 5 deeds' : 'Pick your deed', action: 'deeds', hint: this.tab === 'deeds' ? '' : this.selectionHint}; }
            if (this.missingLayers().length) { return {label: 'Terraform', disabled: true, hint: 'No ' + this.rarity + ' art for ' + this.missingLayers().map((n) => this.layerLabel(n)).join(', ') + ' yet.'}; }
            if (this.problems.length) { return {label: 'Terraform', disabled: true, hint: this.problems[0]}; }
            return {label: 'Terraform island', action: 'review', hint: ''};
        },
        ctaClick() {
            const c = this.cta;
            if (c.disabled) { return; }
            if (c.action === 'connect') { this.connect(); } else if (c.action === 'reload') { this.loadInventory(); } else if (c.action === 'review') { this.openReview(); } else if (c.action === 'deeds') {
                this.setTab('deeds');
                this.$nextTick(() => { const g = this.$refs.grid; if (g && g.scrollIntoView) { g.scrollIntoView({block: 'nearest', behavior: 'smooth'}); } });
            }
        },
        get stepState() {
            return {
                deeds: this.deedsReady || this.step === 'done' ? 'done' : (this.tab === 'deeds' && !this.review ? 'active' : ''),
                design: this.review || this.step === 'done' ? 'done' : (this.tab !== 'deeds' ? 'active' : ''),
                terraform: this.step === 'done' ? 'done' : (this.review ? 'active' : ''),
            };
        },
        get burnText() {
            if (!this.fromDeeds) { return 'Pick a land deed to terraform \u2022 '; }
            const n = this.selected.length;
            if (this.mode === 'fuse' && n < 5) { return 'Fuse 5 ' + this.selectedRarity + ' deeds \u2022 ' + n + ' of 5 picked \u2022 '; }
            return 'You will burn ' + (n === 1 ? 'a ' + this.selectedRarity + ' deed' : n + ' ' + this.selectedRarity + ' deeds') + ' \u2022 ';
        },

        // ---------------- review + sign (dialog)
        openReview() {
            if (!this.deedsReady || this.problems.length || !this.user || this.dapper()) { return; }
            this._returnFocus = document.activeElement;
            // snapshot: the dialog keeps showing what is being signed (the inventory reloads after the burn)
            const deeds = this.selectedDeeds.map((d) => ({id: d.id, rarity: d.rarity, templateId: d.templateId}));
            this.snap = {
                rarity: this.rarity, deeds, fused: deeds.length === 5, deedRarity: this.selectedRarity, tint: this.tintValue,
                parts: this.sceneLayers.map((n) => ({key: n, layer: this.layerLabel(n), name: this.layerName(n),
                    note: n === 'fx' ? 'permanent' : (this.isMutable(n) ? 'change any time' : '')})),
                mutable: this.sceneLayers.filter((n) => this.isMutable(n)),
                url: sceneUrl(this.cfg, this.layers, this.tintValue),
            };
            this.review = true;
            this.step = 'review';
            this.understood = false;
            this.txError = '';
            this.txStatus = '';
            document.documentElement.classList.add('isb-modal-open');
            this.checkOnChain();
            this.$nextTick(() => { const el = this.$refs.reviewClose; if (el) { el.focus(); } });
        },
        closeReview() {
            if (this.step === 'signing') { return; }
            this.review = false;
            if (this.step !== 'done') { this.step = 'build'; }
            document.documentElement.classList.remove('isb-modal-open');
            const el = this._returnFocus;
            if (el && el.focus && document.contains(el)) { el.focus({preventScroll: true}); }
        },
        onEscape() { if (this.review) { this.closeReview(); } },
        trapFocus(e) {
            const card = this.$refs.reviewCard;
            if (!card) { return; }
            const els = [...card.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter((el) => el.offsetParent !== null);
            if (!els.length) { e.preventDefault(); return; }
            const first = els[0], last = els[els.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        },
        async checkOnChain() {
            this.chainCheck = 'checking';
            try {
                const entries = Object.entries(this.layers);
                await fcl.query({cadence: PREVIEW_CADENCE, args: (arg, t) => [
                    arg(this.rarity, t.String),
                    arg(entries.map(([k, v]) => ({key: k, value: String(v)})), t.Dictionary({key: t.String, value: t.UInt64})),
                    arg(this.tintValue, t.Optional(t.String)),
                ]});
                this.chainCheck = 'ok';
            } catch (e) { this.chainCheck = friendlyChainError(txErrorText(e)) || 'The island contract can\u2019t check this scene right now.'; }
        },
        layerName(name) { const id = this.layers[name]; return id && this.infos[id] ? this.infos[id].name : 'None'; },
        get canSign() { return this.step === 'review' && this.understood && this.chainCheck === 'ok' && this.deedsReady && !this.dapper() && !!this.user; },
        async terraform() {
            if (!this.canSign) { return; }
            this.step = 'signing';
            this.txStatus = 'Confirm the transaction in your wallet\u2026';
            this.txError = '';
            try {
                this.txId = await islandTerraformTx(this.selected, this.layers, this.tintValue);
                this.txStatus = 'Terraforming\u2026 waiting for the Flow blockchain to seal the transaction.';
                const tx = await sealed(this.txId);
                const ev = (tx.events || []).find((e) => /\.FlovatarAsteroidIsland\.Terraformed$/.test(e.type));
                this.islandId = ev ? Number(ev.data.id) : null;
                // index it right away (the island page reads the DB first), then show the success dialog
                if (this.islandId) { this.txStatus = 'Your island is ready\u2026'; await refreshIsland(this.islandId, this.user.addr, this.txId); }
                this.txStatus = '';
                this.step = 'done';
                this.loadInventory();
            } catch (e) {
                this.step = 'review';
                this.txStatus = '';
                this.txError = this.dapper() ? 'Dapper Wallet can\u2019t sign this transaction.' : friendlyChainError(txErrorText(e));
                this.loadInventory();
            }
        },
        startOver() {
            this.review = false;
            this.step = 'build';
            this.islandId = null;
            this.txId = '';
            document.documentElement.classList.remove('isb-modal-open');
            this.setTab('deeds');
        },
    }));

    // --------------------------------------------------------------------------- /islands/{id}
    // Island page (round 4, KB islands-build.md "Round 4 spec"): the scene with in-scene hotspots (premium slots at the
    // art's anchors, the display screen), details, and for the owner a collection box (tabs Flovatar family / All NFTs,
    // search) with pick-and-place: drag an NFT (or click it, then a slot) onto a premium slot or onto the display; every
    // change is a pending edit until Save = ONE transaction (clears + sets). Clicking the display zooms the scene into
    // the screen and shows the standard residents as a carousel. Owner layer tools: Change sky (any time) and the
    // one-time Choose display. While edits or a layer preview are pending, the scene is composed in the browser
    // (same composition as the server / contract) so the owner sees the result before signing.
    const SCENE_W = 3025.27;
    const SCENE_H = 4033.12;
    const MAX_SETS_PER_TX = 25;
    const artCache = new Map();     // url -> Promise<data URI | ''>  (outside Alpine: big strings)
    const liveSvgs = {};            // template id -> template JSON
    let touchDragging = false;      // a long-press drag is running (blocks page scrolling)

    function sceneSize(cfg) {
        const m = /viewBox="\s*[-\d.]+\s+[-\d.]+\s+([\d.]+)\s+([\d.]+)"/.exec((cfg && cfg.svgOpen) || '');
        return m ? [Number(m[1]), Number(m[2])] : [SCENE_W, SCENE_H];
    }

    /** Same-origin (or CORS-enabled) image -> data: URI, cached; '' when it can't be read (then an HTML overlay is used). */
    function dataUri(url) {
        if (!url) { return Promise.resolve(''); }
        if (url.startsWith('data:image/')) { return Promise.resolve(url); }
        if (!artCache.has(url)) {
            artCache.set(url, fetch(url, {credentials: 'omit'}).then((r) => {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.blob();
            }).then((b) => (/^image\//.test(b.type) && b.size < 4e6 ? new Promise((res) => {
                const fr = new FileReader();
                fr.onload = () => res(String(fr.result || ''));
                fr.onerror = () => res('');
                fr.readAsDataURL(b);
            }) : '')).catch(() => ''));
        }
        return artCache.get(url);
    }

    Alpine.data('islandPage', (islandId) => ({
        ...walletMixin(),
        id: Number(islandId),
        cfg: null,
        island: null,
        status: 'loading',
        live: {},            // chainSlot -> {valid, reason}
        displays: {},        // type|id -> {name, thumbnail}
        isNew: false,
        // owner: name / description
        editing: false,
        nameInput: '',
        descInput: '',
        busy: false,
        notice: '',
        noticeKind: '',
        // scene
        liveUrl: '',         // browser-composed scene while edits / a layer preview are pending
        liveBusy: false,
        overlays: [],        // premium residents the browser couldn't inline (cross-origin art): drawn as <img>
        screenOverlay: '',   // screen image the browser couldn't inline
        sceneVer: 0,
        // display zoom
        zoom: false,
        zoomOn: false,       // transform applied (animation running / done)
        zoomIdx: 0,
        camStyle: '',
        zoomBox: '',
        // collection + pick & place
        walletNfts: null,
        walletStatus: 'idle',
        colTab: 'family',
        colQuery: '',
        sheetOpen: false,
        plan: {},            // chainSlot -> {type, id} | null   (desired state)
        planVer: 0,
        selected: null,      // collection key 'type|id' or 'slot:<chainSlot>' (a placed resident being moved)
        drag: null,          // {key, label, img, x, y, family}
        dropHover: '',
        // layer tools
        layerPick: null,     // {layer, choice, original, oneTime}
        pickThumbs: {},

        async init() {
            const qs = new URLSearchParams(window.location.search);
            this.isNew = qs.has('new');
            try { this.cfg = await loadConfig(); } catch (e) { /* page still renders from the island JSON */ }
            await this.load(qs.get('owner'));
            this.watchUser(() => { if (this.isOwner) { this.loadWalletNfts(); } });
            this._onResize = () => { if (this.zoom) { this.applyZoom(false); } };
            window.addEventListener('resize', this._onResize);
            // touch drag: a non-passive touchmove listener must exist BEFORE the gesture starts, else Chrome makes the
            // touchmoves non-cancelable, scrolls and fires pointercancel; it only blocks scrolling during a drag
            document.addEventListener('touchmove', (e) => { if (touchDragging && e.cancelable) { e.preventDefault(); } }, {passive: false});
        },
        async load(owner) {
            try {
                this.island = await getJson('/api/islands/' + this.id);
            } catch (e) {
                const who = normalizeAddress(owner) || (window.user && window.user.addr ? normalizeAddress(window.user.addr) : null);
                this.island = who ? await refreshIsland(this.id, who) : null;
            }
            if (!this.island) { this.status = 'missing'; return; }
            this.nameInput = this.island.rawName || '';
            this.descInput = this.island.description || '';
            this.resetPlan();
            this.status = 'ready';
            this.liveCheck();
            this.loadDisplays();
        },
        /**
         * Re-read the island from chain (server refresh). The access node behind the server can be a block behind the
         * wallet's "sealed": with `expect(island)` the refresh is retried a few times until the change shows.
         */
        async reload(expect, tx) {
            let d = null;
            // the server reads the island at the tx's block; retry anyway while a lagging access node catches up
            for (let i = 0; i < 9; i++) {
                d = await refreshIsland(this.id, this.island ? this.island.owner : null, tx);
                if (!expect || (d && expect(d))) { break; }
                if (i === 2) { this.say('Updating the island\u2026 the blockchain is catching up.'); }
                await new Promise((r) => setTimeout(r, Math.min(4000, 1500 + i * 500)));
            }
            if (d) { this.island = d; } else { this.island = await getJson('/api/islands/' + this.id); }
            this.resetPlan();
            this.liveCheck();
            this.loadDisplays();
        },
        get isOwner() { return !!(this.user && this.island && this.user.addr === this.island.owner); },
        get residents() { return (this.island && this.island.residentsList) || []; },
        get slots() { return (this.island && this.island.slots) || []; },
        residentAt(chainSlot) { return this.residents.find((r) => r.chainSlot === chainSlot) || null; },
        validity(r) {
            const l = this.live[r.chainSlot];
            if (l && l.type === r.type && Number(l.id) === r.id) { return l; }
            return {valid: r.valid, reason: r.reason || ''};
        },
        isValid(r) { return this.validity(r).valid; },
        reasonText(r) {
            const why = this.validity(r).reason;
            return {'not-owned': 'Moved out: the owner no longer holds this NFT', 'island-owner-changed': 'Set by a previous owner', 'island-not-stored': 'Island not in a wallet', 'slot-invalid': 'This slot no longer exists', 'type-not-allowed': 'This NFT type isn\u2019t allowed here'}[why] || '';
        },
        get premiumSlots() { return this.slots.filter((s) => s.premium); },
        get standardSlots() { return this.slots.filter((s) => !s.premium); },
        get invalidCount() { return this.residents.filter((r) => !this.isValid(r)).length; },
        familyLabel(r) { return r.family && this.cfg && this.cfg.families && this.cfg.families[r.family] ? this.cfg.families[r.family].label : ''; },
        display(r) { return this.displays[r.type + '|' + r.id] || {}; },
        familyOf(type) { return ((this.cfg && this.cfg.premiumTypes) || CFG.premiumTypes || {})[type] || null; },
        thumb(r) {
            const f = r.family || this.familyOf(r.type);
            if (f === 'Flovatar') { return '/api/image/r/flovatar/' + r.id + '/thumb.jpg'; }
            if (f === 'Flobot') { return '/api/image/r/flobot/' + r.id + '/thumb.jpg'; }
            if (f === 'FlovatarDustCollectible') { return '/api/image/r/collectible/' + r.id + '/thumb.jpg'; }
            return httpUrl(this.display(r).thumbnail || r.thumbnail);
        },
        nftName(r) { return this.display(r).name || r.name || (this.typeLabel(r.type) + ' #' + r.id); },
        typeLabel(type) {
            const f = this.familyOf(type);
            if (f && this.cfg && this.cfg.families && this.cfg.families[f]) { return this.cfg.families[f].label; }
            const p = String(type).split('.'); return p[2] || type;
        },
        /** Page of an NFT: the Flovatar-site pages for its own collections, else the owner's collection page. */
        nftLink(r) {
            const hex = String(CFG.flovatar || '').replace(/^0x/, '');
            const f = r.family || this.familyOf(r.type);
            if (f === 'Flovatar') { return '/flovatars/' + r.id; }
            if (f === 'Flobot') { return '/flobots/' + r.id; }
            if (f === 'FlovatarDustCollectible') { return '/collectibles/' + r.id; }
            if (hex && r.type === 'A.' + hex + '.FlovatarComponent.NFT') { return '/components/' + r.id; }
            if (r.type === 'A.' + String(CFG.contract || '').replace(/^0x/, '') + '.FlovatarAsteroidIsland.NFT') { return '/islands/' + r.id; }
            return this.island ? '/collection/' + this.island.owner : '#';
        },
        async liveCheck() {
            if (!this.island || !this.island.owner) { return; }
            try {
                const list = await fcl.query({cadence: RESIDENTS_CADENCE, args: (arg, t) => [arg(this.island.owner, t.Address), arg(String(this.id), t.UInt64)]});
                const m = {};
                for (const s of (list || [])) { m[s.slot] = {valid: !!s.valid, reason: s.reason || '', type: s.nftType, id: Number(s.nftId)}; }
                this.live = m;
            } catch (e) { console.log(e); }
        },
        async loadDisplays() {
            const rs = this.residents.filter((r) => !this.displays[r.type + '|' + r.id] && !r.name);
            if (!rs.length || !this.island.owner) { return; }
            try {
                const out = await fcl.query({cadence: DISPLAYS_CADENCE, args: (arg, t) => [
                    arg(this.island.owner, t.Address), arg(rs.map((r) => r.type), t.Array(t.String)), arg(rs.map((r) => String(r.id)), t.Array(t.UInt64)),
                ]});
                const m = {...this.displays};
                for (const d of (out || [])) { m[d.type + '|' + d.id] = {name: d.name, thumbnail: d.thumbnail, owned: d.owned === 'yes'}; }
                this.displays = m;
            } catch (e) { console.log(e); }
        },

        // ---------------- scene geometry (percent boxes over the scene image)
        get size() { return sceneSize(this.cfg); },
        pct(box) {
            if (!box) { return 'display:none'; }
            const [W, H] = this.size;
            return 'left:' + (box.x / W * 100) + '%;top:' + (box.y / H * 100) + '%;width:' + (box.w / W * 100) + '%;height:' + (box.h / H * 100) + '%';
        },
        tierScale() { return Number(((this.cfg && this.cfg.tierScale) || {})[this.island.rarity] || 0.16); },
        familyScale(type) {
            const rs = ((this.cfg && this.cfg.residentScale) || {})[type];
            if (Number.isFinite(Number(rs))) { return Number(rs); }
            const f = this.familyOf(type);
            return f && this.cfg && this.cfg.families && this.cfg.families[f] ? Number(this.cfg.families[f].scale || 1) : 1;
        },
        anchorOf(slot) { const a = this.island && this.island.anchors && this.island.anchors[slot]; return Array.isArray(a) ? a : null; },
        hotspots() {
            if (!this.island) { return []; }
            return this.premiumSlots.map((s) => {
                const a = this.anchorOf(s.slot);
                if (!a) { return null; }
                const r = this.planned(s.chain);
                return {slot: s.slot, chain: s.chain, box: slotBox(a, this.tierScale(), r ? this.familyScale(r.type) : 1), r};
            }).filter(Boolean);
        },
        get screen() { return this.island && this.island.display && this.island.display.screen ? this.island.display.screen : null; },
        previewScreen() {
            const lp = this.layerPick;
            if (lp && lp.layer === DISPLAY_LAYER && lp.choice) { return templateScreen((this.cfg.templates || []).find((t) => t.id === lp.choice)); }
            return this.screen;
        },
        screenRect() { return screenBox(this.previewScreen()); },
        get hasDisplay() { return !!this.screen; },

        // ---------------- pick & place: plan = desired residents per chain slot
        currentMap() {
            const m = {};
            for (const s of this.slots) { const r = this.residentAt(s.chain); m[s.chain] = r ? {type: r.type, id: r.id} : null; }
            return m;
        },
        resetPlan() { this.plan = this.currentMap(); this.planVer++; this.selected = null; this.refreshLive(); },
        planned(chain) {
            const p = this.plan[chain];
            if (!p) { return null; }
            const cur = this.residentAt(chain);
            if (cur && cur.type === p.type && cur.id === p.id) { return cur; }
            const n = (this.walletNfts || []).find((x) => x.type === p.type && x.id === p.id);
            return {type: p.type, id: p.id, family: this.familyOf(p.type), name: n ? n.name : '', thumbnail: n ? n.thumbnail : '', pending: true, valid: true, chainSlot: chain};
        },
        get ops() { return this.planVer >= 0 && this.island ? planOps(this.currentMap(), this.plan) : {clears: [], sets: []}; },
        get changeCount() {
            if (this.planVer < 0 || !this.island) { return 0; }
            const cur = this.currentMap();
            return Object.keys(this.plan).filter((s) => {
                const a = cur[s], b = this.plan[s];
                return (a ? a.type + '|' + a.id : '') !== (b ? b.type + '|' + b.id : '');
            }).length;
        },
        get dirty() { return this.changeCount > 0; },
        slotChanged(chain) {
            const a = this.currentMap()[chain], b = this.plan[chain];
            return this.planVer >= 0 && (a ? a.type + '|' + a.id : '') !== (b ? b.type + '|' + b.id : '');
        },
        planSlotOf(type, id) { return Object.keys(this.plan).find((s) => this.plan[s] && this.plan[s].type === type && this.plan[s].id === id) || null; },
        /** Standard residents in slot order (the display's carousel; S1 = on the screen). `pending` = the plan. */
        displayList(pending) {
            if (!this.island) { return []; }
            const out = [];
            for (const s of this.standardSlots) {
                const r = pending ? this.planned(s.chain) : this.residentAt(s.chain);
                if (r) { out.push({...r, slot: s.slot, chainSlot: s.chain}); }
            }
            return out;
        },
        get carousel() { return this.displayList(false).filter((r) => this.isValid(r)); },
        get premiumList() { return this.premiumSlots.map((s) => this.residentAt(s.chain)).filter(Boolean); },
        freeStandardSlot() { const s = this.standardSlots.find((x) => !this.plan[x.chain]); return s ? s.chain : null; },
        setPlan(chain, val) { this.plan = {...this.plan, [chain]: val}; this.planVer++; this.refreshLive(); },

        nftKey(n) { return n.type + '|' + n.id; },
        nftFromKey(k) {
            if (!k) { return null; }
            if (k.startsWith('slot:')) { const chain = k.slice(5); const p = this.plan[chain]; return p ? {...p, fromSlot: chain} : null; }
            const [type, id] = k.split('|');
            const n = (this.walletNfts || []).find((x) => x.type === type && x.id === Number(id));
            return n ? {type: n.type, id: n.id} : {type, id: Number(id)};
        },
        isPremiumType(type) { return !!this.familyOf(type); },
        /** Can the selected / dragged item go to this target? target = chain slot or 'display'. */
        canDrop(key, target) {
            const it = this.nftFromKey(key);
            if (!it || !this.isOwner || this.busy) { return false; }
            if (target === 'display') {
                if (!this.hasDisplay && !this.standardSlots.length) { return false; }
                const from = it.fromSlot || this.planSlotOf(it.type, it.id);
                if (from && this.standardSlots.some((s) => s.chain === from)) { return true; } // already on the display
                return !!this.freeStandardSlot();
            }
            const s = this.slots.find((x) => x.chain === target);
            if (!s) { return false; }
            if (s.premium && !this.isPremiumType(it.type)) { return false; }
            if (it.fromSlot) {
                // swapping: the resident coming back must fit the source slot too
                const other = this.plan[target];
                const src = this.slots.find((x) => x.chain === it.fromSlot);
                return !(other && src && src.premium && !this.isPremiumType(other.type));
            }
            return true;
        },
        /** Place / move an item onto a target. Same NFT already elsewhere on the island = a move. */
        place(key, target) {
            if (!this.canDrop(key, target)) {
                const it = this.nftFromKey(key);
                if (it && target !== 'display' && !this.isPremiumType(it.type)) { this.say('Scene slots are for Flovatars, Flobots, Sportvatars and Psyche Likees. Drop other NFTs on the display.', 'error'); }
                return false;
            }
            const it = this.nftFromKey(key);
            const val = {type: it.type, id: it.id};
            const plan = {...this.plan};
            const from = it.fromSlot || this.planSlotOf(it.type, it.id);
            if (target === 'display') {
                if (from && this.standardSlots.some((s) => s.chain === from)) { this.selected = null; return true; } // already on the display
                const free = this.standardSlots.find((s) => !plan[s.chain] || s.chain === from);
                if (!free) { this.say('The display is full: remove an NFT from it first.', 'error'); return false; }
                if (from) { plan[from] = null; }
                plan[free.chain] = val;
            } else {
                if (from === target) { this.selected = null; return true; }
                const prev = plan[target];
                if (from) { plan[from] = prev && it.fromSlot ? prev : null; } // dragging a placed resident onto another = swap
                plan[target] = val;
            }
            this.plan = plan;
            this.planVer++;
            this.selected = null;
            this.refreshLive();
            return true;
        },
        removeFrom(chain) { if (this.plan[chain]) { this.setPlan(chain, null); } if (this.selected === 'slot:' + chain) { this.selected = null; } },
        /** Move a display resident to the front (S1 = the one on the screen) by swapping. */
        showFirst(chain) {
            const first = this.standardSlots[0];
            if (!first || first.chain === chain) { return; }
            const plan = {...this.plan};
            [plan[first.chain], plan[chain]] = [plan[chain] || null, plan[first.chain] || null];
            this.plan = plan; this.planVer++; this.refreshLive();
        },
        clearInvalidPlan() {
            const plan = {...this.plan};
            for (const r of this.residents) { if (!this.isValid(r) && plan[r.chainSlot] && plan[r.chainSlot].type === r.type && plan[r.chainSlot].id === r.id) { plan[r.chainSlot] = null; } }
            this.plan = plan; this.planVer++; this.refreshLive();
        },
        discard() { this.resetPlan(); this.say('', ''); },
        // click-to-place
        selectNft(n) {
            const k = this.nftKey(n);
            this.selected = this.selected === k ? null : k;
            if (this.selected && this.sheetOpen && window.matchMedia && window.matchMedia('(max-width: 991px)').matches) { this.sheetOpen = false; }
        },
        hotspotClick(h) {
            if (this.isOwner && this.selected) {
                if (this.selected === 'slot:' + h.chain) { this.selected = null; return; }
                this.place(this.selected, h.chain);
                return;
            }
            if (this.isOwner && this.plan[h.chain]) { this.selected = 'slot:' + h.chain; return; }
            if (!this.isOwner && h.r) { window.location.href = this.nftLink(h.r); }
        },
        displayClick() {
            if (this.isOwner && this.selected) { this.place(this.selected, 'display'); return; }
            this.openZoom();
        },
        targetActive(target) { const k = (this.drag && this.drag.key) || this.selected; return !!k && this.canDrop(k, target); },

        // pointer drag (mouse + touch + pen): ghost follows the pointer, drop = element under the pointer with data-drop.
        // Mouse / pen: drag starts after 7 px. Touch: long-press (280 ms without moving) starts the drag, so normal swipes
        // still scroll the collection; while dragging, touchmove is cancelled so the page doesn't scroll.
        dragStart(e, key) {
            if (!this.isOwner || this.busy || (e.button !== undefined && e.button > 0)) { return; }
            const it = this.nftFromKey(key);
            if (!it) { return; }
            const touch = e.pointerType === 'touch';
            const start = {x: e.clientX, y: e.clientY};
            const el = e.currentTarget;
            const img = el.querySelector('img');
            const label = (el.getAttribute('data-label') || '');
            let moved = false;
            let armed = !touch;
            let timer = null;
            const begin = (x, y) => {
                moved = true;
                if (touch) { touchDragging = true; }
                this.drag = {key, label, img: img ? img.currentSrc || img.src : '', x, y};
                if (this.sheetOpen && touch) { this.sheetOpen = false; }
                document.documentElement.classList.add('isp-dragging');
                if (touch && navigator.vibrate) { try { navigator.vibrate(12); } catch (err) { /* optional */ } }
            };
            if (touch) { timer = setTimeout(() => { armed = true; begin(start.x, start.y); }, 280); }
            const blockScroll = (ev) => { if (moved && ev.cancelable) { ev.preventDefault(); } };
            let scrolling = false;
            let lastY = start.y;
            const move = (ev) => {
                const far = Math.hypot(ev.clientX - start.x, ev.clientY - start.y) >= 7;
                if (!armed) {
                    // items are touch-action: none (so a long-press drag can't be stolen by a pan): a swipe that starts
                    // before the long-press scrolls the collection grid by hand
                    if (far || scrolling) {
                        clearTimeout(timer);
                        scrolling = true;
                        const g = el.closest('.isp-col-grid');
                        if (g) { g.scrollTop += lastY - ev.clientY; }
                        lastY = ev.clientY;
                    }
                    return;
                }
                if (!moved) { if (!far) { return; } begin(ev.clientX, ev.clientY); }
                ev.preventDefault();
                this.drag = {...this.drag, x: ev.clientX, y: ev.clientY};
                // near the top / bottom edge: scroll the page so far drop targets come into view
                const edge = 70;
                if (ev.clientY < edge) { window.scrollBy(0, -Math.ceil((edge - ev.clientY) / 4)); } else if (ev.clientY > window.innerHeight - edge) { window.scrollBy(0, Math.ceil((ev.clientY - window.innerHeight + edge) / 4)); }
                const t = document.elementFromPoint(ev.clientX, ev.clientY);
                const d = t && t.closest('[data-drop]');
                this.dropHover = d ? d.getAttribute('data-drop') : '';
            };
            const cleanup = () => {
                clearTimeout(timer);
                touchDragging = false;
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                window.removeEventListener('pointercancel', cancel);
                window.removeEventListener('touchmove', blockScroll);
                document.documentElement.classList.remove('isp-dragging');
            };
            const cancel = () => { cleanup(); this.drag = null; this.dropHover = ''; };
            const up = (ev) => {
                cleanup();
                if (scrolling) { this._suppressClick = Date.now(); return; }
                if (!moved) { return; }
                const t = document.elementFromPoint(ev.clientX, ev.clientY);
                const d = t && t.closest('[data-drop]');
                const target = d ? d.getAttribute('data-drop') : '';
                this.drag = null;
                this.dropHover = '';
                this._suppressClick = Date.now();
                if (target === 'remove' && it.fromSlot) { this.removeFrom(it.fromSlot); return; }
                if (target && target !== 'remove') { this.place(key, target); }
            };
            window.addEventListener('pointermove', move, {passive: false});
            window.addEventListener('pointerup', up);
            window.addEventListener('pointercancel', cancel);
            window.addEventListener('touchmove', blockScroll, {passive: false});
        },
        clicked(fn) { if (this._suppressClick && Date.now() - this._suppressClick < 350) { return; } fn(); },

        // ---------------- save = one transaction (clears + sets; > 25 sets are split)
        async save() {
            if (!this.dirty || this.busy) { return; }
            const {clears, sets} = this.ops;
            if (sets.length) {
                try {
                    const ok = await fcl.query({cadence: OWNERSHIP_CADENCE, args: (arg, t) => [
                        arg(this.user.addr, t.Address), arg(sets.map((x) => x.type), t.Array(t.String)), arg(sets.map((x) => String(x.id)), t.Array(t.UInt64)),
                    ]});
                    const bad = sets.filter((_, i) => !ok[i]);
                    if (bad.length) {
                        this.say('The island contract can\u2019t verify these NFTs as yours (their collection isn\u2019t published the standard way): '
                            + bad.map((b) => this.typeLabel(b.type) + ' #' + b.id).join(', '), 'error');
                        return;
                    }
                } catch (e) { /* the transaction re-checks anyway */ }
            }
            const batches = [];
            for (let i = 0; i < Math.max(1, sets.length); i += MAX_SETS_PER_TX) { batches.push(sets.slice(i, i + MAX_SETS_PER_TX)); }
            const label = batches.length > 1 ? 'Save (' + batches.length + ' approvals)' : 'Save';
            const want = {...this.plan};
            const expect = (d) => Object.entries(want).every(([slot, v]) => {
                const r = (d.residentsList || []).find((x) => x.chainSlot === slot);
                return v ? !!r && r.type === v.type && Number(r.id) === Number(v.id) : !r;
            });
            await this.run(label, async () => {
                let last = '';
                for (let b = 0; b < batches.length; b++) {
                    const part = batches[b];
                    last = await islandUpdateResidentsTx(this.id, b === 0 ? clears : [], part.map((x) => x.slot), part.map((x) => x.type), part.map((x) => x.id));
                    if (b < batches.length - 1) { await sealed(last); }
                }
                return last;
            }, expect);
        },

        // ---------------- collection (owner's NFTs, read in the browser)
        async loadWalletNfts() {
            if (!this.user || this.walletStatus === 'loading') { return; }
            this.walletStatus = 'loading';
            try {
                const out = await fcl.query({cadence: WALLET_NFTS_CADENCE, args: (arg, t) => [arg(this.user.addr, t.Address), arg('0', t.Int), arg('300', t.Int), arg([], t.Array(t.String))]});
                const self = 'A.' + CFG.contract.slice(2) + '.FlovatarAsteroidIsland.NFT';
                this.walletNfts = (out || []).map((n) => ({type: n.type, id: Number(n.id), name: n.name, thumbnail: n.thumbnail}))
                    .filter((n) => !(n.type === self && n.id === this.id))
                    .sort((a, b) => (this.isPremiumType(b.type) - this.isPremiumType(a.type)) || a.type.localeCompare(b.type) || a.id - b.id);
                this.walletStatus = 'done';
            } catch (e) { console.log(e); this.walletStatus = 'error'; }
        },
        get familyNfts() { return (this.walletNfts || []).filter((n) => this.isPremiumType(n.type)); },
        get colItems() {
            if (!this.walletNfts) { return []; }
            const f = this.colQuery.trim().toLowerCase();
            const list = this.colTab === 'family' ? this.familyNfts : this.walletNfts;
            return list.filter((n) => !f || (n.name || '').toLowerCase().includes(f) || this.typeLabel(n.type).toLowerCase().includes(f) || String(n.id) === f.replace(/^#/, ''));
        },
        nftThumb(n) {
            const f = this.familyOf(n.type);
            if (f === 'Flovatar') { return '/api/image/r/flovatar/' + n.id + '/thumb.jpg'; }
            if (f === 'Flobot') { return '/api/image/r/flobot/' + n.id + '/thumb.jpg'; }
            if (f === 'FlovatarDustCollectible') { return '/api/image/r/collectible/' + n.id + '/thumb.jpg'; }
            return httpUrl(n.thumbnail);
        },
        placedAt(n) { const s = this.planVer >= 0 ? this.planSlotOf(n.type, n.id) : null; return s ? this.slotLabel(s) : ''; },
        slotLabel(chain) { const s = this.slots.find((x) => x.chain === chain); return s ? s.slot : chain; },
        freeCounts() {
            const p = this.premiumSlots.filter((s) => !this.plan[s.chain]).length;
            const st = this.standardSlots.filter((s) => !this.plan[s.chain]).length;
            return {premium: p, standard: st};
        },

        // ---------------- browser-composed scene (pending edits / layer preview)
        artUrl(type, id) {
            const f = this.familyOf(type);
            if (f === 'Flovatar') { return '/api/image/nobg/' + id; }
            if (f === 'Flobot') { return '/api/image/flobot/nobg/' + id; }
            if (f === 'FlovatarDustCollectible') { return '/api/image/collectible/' + id; }
            return '';
        },
        liveLayers() {
            const l = {...((this.island && this.island.layers) || {})};
            const lp = this.layerPick;
            if (lp) { if (lp.choice) { l[lp.layer] = lp.choice; } else { delete l[lp.layer]; } }
            return l;
        },
        get needsLive() { return this.dirty || !!(this.layerPick && this.layerPick.choice !== this.layerPick.original); },
        get sceneSrc() { return this.needsLive && this.liveUrl ? this.liveUrl : (this.island ? this.island.full : ''); },
        async refreshLive() {
            if (!this.island || !this.cfg) { return; }
            const token = this._liveToken = (this._liveToken || 0) + 1;
            if (!this.needsLive) { this.overlays = []; this.screenOverlay = ''; return; }
            this.liveBusy = true;
            try {
                const layers = this.liveLayers();
                await Promise.all(Object.values(layers).map(async (id) => { if (!liveSvgs[id]) { liveSvgs[id] = await loadTemplate(id); } }));
                let svg = composeScene(this.cfg, layers, this.island.tint, this.id, liveSvgs);
                const back = [], front = [], dock = [], overlays = [];
                for (const s of this.premiumSlots) {
                    const r = this.planned(s.chain);
                    const a = this.anchorOf(s.slot);
                    if (!r || !a || (!r.pending && !this.isValid(r))) { continue; }
                    const fam = this.familyScale(r.type);
                    const href = await dataUri(this.artUrl(r.type, r.id));
                    if (href) { (a[3] === 'back' ? back : front).push([a[1], residentGroupSvg(s.slot, a, href, this.tierScale(), fam)]); } else {
                        const {k} = residentTransform(a, this.tierScale(), fam);
                        overlays.push({slot: s.slot, src: this.thumb(r), box: {x: a[0] - 1500 * k, y: a[1] - 2725 * k, w: 3000 * k, h: 3000 * k}});
                    }
                }
                const ct = this.residentAt('caretaker');
                const ca = this.anchorOf('caretaker');
                if (ct && ca && this.isValid(ct) && ct.family === 'Flobot') {
                    const href = await dataUri(this.artUrl(ct.type, ct.id));
                    if (href) { dock.push([ca[1], residentGroupSvg('caretaker', ca, href, this.tierScale(), this.familyScale(ct.type))]); }
                }
                const join = (l) => l.sort((x, y) => x[0] - y[0]).map((x) => x[1]).join('');
                svg = injectResidents(svg, join(back), join(front), join(dock));
                let screenOverlay = '';
                const screen = this.previewScreen();
                if (screen) {
                    const first = this.displayList(true).find((r) => r.pending || this.isValid(r));
                    let href = SCREEN_PLACEHOLDER;
                    if (first) {
                        const src = this.artUrl(first.type, first.id) || this.thumb(first);
                        href = await dataUri(src);
                        if (!href) { screenOverlay = this.thumb(first); }
                    }
                    if (href) { svg = insertScreen(svg, screenFragment(screen, href, this.id, first ? first.slot : 'S1'), liveSvgs[layers[DISPLAY_LAYER]]); }
                }
                if (token !== this._liveToken) { return; }
                const url = URL.createObjectURL(new Blob([displayFix(svg)], {type: 'image/svg+xml'}));
                const old = this.liveUrl;
                this.liveUrl = url;
                this.overlays = overlays;
                this.screenOverlay = screenOverlay;
                if (old) { setTimeout(() => URL.revokeObjectURL(old), 3000); }
            } catch (e) { console.log(e); }
            if (token === this._liveToken) { this.liveBusy = false; }
        },

        // ---------------- display zoom: the camera flies into the screen, the carousel opens over it
        openZoom() {
            if (!this.hasDisplay || this.zoom) { return; }
            this._zoomReturn = document.activeElement;
            this.zoom = true;
            this.zoomIdx = 0;
            this.$nextTick(() => {
                this.applyZoom(true);
                const btn = this.$refs.zoomClose;
                setTimeout(() => { if (btn) { btn.focus({preventScroll: true}); } }, this.reducedMotion() ? 0 : 450);
            });
        },
        reducedMotion() { return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); },
        applyZoom(animate) {
            const frame = this.$refs.frame;
            const b = screenBox(this.screen);
            if (!frame || !b) { return; }
            const fw = frame.clientWidth, fh = frame.clientHeight;
            const [W, H] = this.size;
            const bx = b.x / W * fw, by = b.y / H * fh, bw = b.w / W * fw, bh = b.h / H * fh;
            // the screen fills ~94 % of the frame width (or 72 % of its height), centred
            const s = Math.min(fw * 0.94 / bw, fh * 0.72 / bh, 14);
            const tx = fw / 2 - (bx + bw / 2) * s;
            const ty = fh * 0.46 - (by + bh / 2) * s;
            const t = 'translate(' + tx.toFixed(1) + 'px,' + ty.toFixed(1) + 'px)';
            this._zoomT = {t, s};
            clearTimeout(this._crispTimer);
            this.camStyle = 'transform:' + t + ' scale(' + s.toFixed(4) + ');' + (animate && !this.reducedMotion() ? '' : 'transition:none;');
            // a scaled <img> of an SVG stays rasterized at its layout size (blurry): once the camera has arrived, lay the
            // scene out at the zoomed size instead (same picture, crisp), unless that would be a huge bitmap
            if (fw * s <= 4200 && fh * s <= 5600) {
                this._crispTimer = setTimeout(() => {
                    if (this.zoom) { this.camStyle = 'transition:none;transform:' + t + ';width:' + (fw * s).toFixed(1) + 'px;height:' + (fh * s).toFixed(1) + 'px;right:auto;bottom:auto;'; }
                }, animate && !this.reducedMotion() ? 680 : 0);
            }
            const w = bw * s, h = bh * s;
            this.zoomBox = 'left:' + (fw / 2 - w / 2).toFixed(1) + 'px;top:' + (fh * 0.46 - h / 2).toFixed(1) + 'px;width:' + w.toFixed(1) + 'px;height:' + h.toFixed(1) + 'px';
            this.zoomOn = true;
        },
        closeZoom() {
            if (!this.zoom) { return; }
            clearTimeout(this._crispTimer);
            this.zoom = false;
            this.zoomOn = false;
            // back to the transform form (no visual jump), then animate out
            const z = this._zoomT;
            if (z && !this.reducedMotion()) {
                this.camStyle = 'transition:none;transform:' + z.t + ' scale(' + z.s.toFixed(4) + ');';
                requestAnimationFrame(() => requestAnimationFrame(() => { this.camStyle = ''; }));
            } else { this.camStyle = ''; }
            const el = this._zoomReturn;
            if (el && el.focus && document.contains(el)) { el.focus({preventScroll: true}); }
        },
        zoomGo(i) {
            const n = this.carousel.length;
            if (!n) { return; }
            this.zoomIdx = (i + n) % n;
            const track = this.$refs.track;
            const item = track && track.children[this.zoomIdx];
            if (item) { track.scrollTo({left: item.offsetLeft - track.offsetLeft, behavior: this.reducedMotion() ? 'auto' : 'smooth'}); }
        },
        zoomScrolled() {
            const track = this.$refs.track;
            if (!track || !track.clientWidth) { return; }
            this.zoomIdx = Math.max(0, Math.min(this.carousel.length - 1, Math.round(track.scrollLeft / track.clientWidth)));
        },
        zoomKey(e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); this.zoomGo(this.zoomIdx + 1); } else if (e.key === 'ArrowLeft') { e.preventDefault(); this.zoomGo(this.zoomIdx - 1); } else if (e.key === 'Escape') { e.preventDefault(); this.closeZoom(); } else if (e.key === 'Tab') {
                const els = [...this.$refs.zoomPanel.querySelectorAll('a[href], button:not([disabled])')].filter((x) => x.offsetParent !== null);
                if (!els.length) { return; }
                const first = els[0], last = els[els.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        },
        onEscape() {
            if (this.zoom) { this.closeZoom(); } else if (this.drag) { this.drag = null; } else if (this.selected) { this.selected = null; } else if (this.layerPick) { this.cancelLayer(); } else if (this.sheetOpen) { this.sheetOpen = false; }
        },

        // ---------------- owner layer tools: Change sky (any time), Choose display (once)
        canSetLayer(layer) { return this.isOwner && !!this.island && (this.island.settableLayers || []).includes(layer); },
        get canChangeSky() { return this.canSetLayer('sky'); },
        get canChooseDisplay() { return this.isOwner && !!this.island && !!this.island.canChooseDisplay; },
        layerOptionsOf(layer) { return this.cfg ? layerOptions(this.cfg, layer, this.island.rarity) : []; },
        layerRequired(layer) { return !!(this.cfg && ((this.cfg.layers[layer] || {}).required || []).includes(this.island.rarity)); },
        openLayer(layer) {
            if (!this.cfg || this.busy) { return; }
            const cur = (this.island.layers || {})[layer] || null;
            this.layerPick = {layer, choice: cur, original: cur, oneTime: layer === DISPLAY_LAYER && !cur};
            if (layer === DISPLAY_LAYER && !cur) { const o = this.layerOptionsOf(layer)[0]; if (o) { this.layerPick.choice = o.id; } }
            this.renderPickThumbs();
            this.refreshLive();
            this.$nextTick(() => { const el = this.$refs.layerPanel; if (el && el.scrollIntoView) { el.scrollIntoView({block: 'nearest', behavior: 'smooth'}); } });
        },
        chooseLayer(id) { if (!this.layerPick) { return; } this.layerPick = {...this.layerPick, choice: id}; this.refreshLive(); },
        cancelLayer() { this.layerPick = null; this.refreshLive(); },
        async renderPickThumbs() {
            const lp = this.layerPick;
            if (!lp) { return; }
            const out = {};
            // each option = this island's scene with that sky / display swapped in (screen: the NFT placeholder)
            const base = {...((this.island && this.island.layers) || {})};
            try { await Promise.all(Object.values(base).map(async (id) => { if (!liveSvgs[id]) { liveSvgs[id] = await loadTemplate(id); } })); } catch (e) { /* drawn without */ }
            for (const t of this.layerOptionsOf(lp.layer)) {
                try {
                    if (!liveSvgs[t.id]) { liveSvgs[t.id] = await loadTemplate(t.id); }
                    let svg = composeScene(this.cfg, {...base, [lp.layer]: t.id}, this.island.tint, null, liveSvgs);
                    const sc = templateScreen(t);
                    if (sc) { svg = insertScreen(svg, screenFragment(sc, SCREEN_PLACEHOLDER, 't' + t.id, 'S1'), liveSvgs[t.id]); }
                    out[t.id] = URL.createObjectURL(new Blob([displayFix(svg)], {type: 'image/svg+xml'}));
                } catch (e) { /* thumbnail missing, the name still shows */ }
            }
            if (this.layerPick === lp || (this.layerPick && this.layerPick.layer === lp.layer)) { Object.values(this.pickThumbs).forEach((u) => URL.revokeObjectURL(u)); this.pickThumbs = out; }
        },
        layerName(id) { const t = this.cfg && (this.cfg.templates || []).find((x) => x.id === id); return t ? t.name : (id ? '#' + id : 'None'); },
        async applyLayer() {
            const lp = this.layerPick;
            if (!lp || lp.choice === lp.original || this.busy) { return; }
            if (this.dirty) { this.say('Save or discard your resident changes first.', 'error'); return; }
            if (lp.oneTime && !lp.choice) { return; }
            const label = lp.layer === 'sky' ? 'Change sky' : 'Choose display';
            const ok = await this.run(label, () => islandSetLayerTx(this.id, lp.layer, lp.choice),
                (d) => Number((d.layers || {})[lp.layer] || 0) === Number(lp.choice || 0));
            if (ok) { this.layerPick = null; this.refreshLive(); }
        },

        // ---------------- owner actions
        say(text, kind) { this.notice = text; this.noticeKind = kind || 'info'; },
        async run(label, fn, expect) {
            if (this.busy) { return; }
            if (this.dapper()) { this.say('Dapper Wallet can\u2019t sign island transactions. Use the Flow Wallet that holds this island.', 'error'); return; }
            this.busy = true;
            this.say(label + ': confirm in your wallet\u2026');
            try {
                const txId = await fn();
                this.say(label + ': waiting for the Flow blockchain\u2026');
                await sealed(txId);
                await this.reload(expect, txId);
                this.say(label + ': done.', 'ok');
                return true;
            } catch (e) {
                this.say(label + ' failed: ' + friendlyChainError(txErrorText(e)), 'error');
                return false;
            } finally { this.busy = false; }
        },
        get maxName() { return (this.cfg && this.cfg.maxName) || 32; },
        get maxDesc() { return (this.cfg && this.cfg.maxDescription) || 500; },
        get nameError() {
            if (this.nameInput.length > this.maxName) { return 'At most ' + this.maxName + ' characters.'; }
            if (/[<>\u0000-\u001f]/.test(this.nameInput)) { return 'No < > or control characters.'; }
            return '';
        },
        async saveText() {
            if (this.nameError || this.descInput.length > this.maxDesc) { return; }
            const name = this.nameInput.trim() !== (this.island.rawName || '') && this.nameInput.trim() !== '' ? this.nameInput.trim() : null;
            const desc = this.descInput !== (this.island.description || '') ? this.descInput : null;
            if (name === null && desc === null) { this.editing = false; return; }
            if (await this.run('Save name', () => islandSetNameDescriptionTx(this.id, name, desc))) { this.editing = false; }
        },
    }));
}
