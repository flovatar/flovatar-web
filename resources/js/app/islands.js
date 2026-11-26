/**
 * Asteroid Islands (ISLANDS_MODE; KB notes/islands-build.md) — lazy feature bundle "i".
 *
 * Alpine components:
 *   islandsBelt        /islands             belt overview + "My islands" tab (connected wallet)
 *   islandsBuilder     /islands/build       redeem: wallet inventory (land deeds, TerraKit packs → open), pick 1 deed or fuse 5,
 *                                           build the scene (rarity-locked templates, budget meter, tint), live preview,
 *                                           confirm (deeds are BURNED), sign the terraform tx
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
import {composeScene, sceneBytes, islandRarity, validateScene, isHexColor, slotList, httpUrl, designation, normalizeAddress, RARITIES} from '../islands/compose.mjs';
import {islandTerraformTx} from '../flow/island-terraform.tx';
import {islandSetResidentsTx} from '../flow/island-set-residents.tx';
import {islandClearResidentsTx} from '../flow/island-clear-residents.tx';
import {islandMoveResidentTx} from '../flow/island-move-resident.tx';
import {islandSetNameDescriptionTx} from '../flow/island-set-name-description.tx';
import {islandOpenPackTx} from '../flow/island-open-pack.tx';

const CFG = window.ISLANDS || null;
const SLICE = 4000;

// ------------------------------------------------------------------------------------------------ shared helpers

let chainCfg = null;
const templateCache = {};

function setup() {
    if (CFG && CFG.contract) { fcl.config().put('0xIslands', CFG.contract); }
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

async function refreshIsland(id, owner) {
    const ax = window.axios;
    if (!ax) { return null; }
    try {
        const r = await ax.post('/api/islands/' + id + '/refresh', owner ? {owner} : {});
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
    Alpine.data('islandsBelt', () => ({
        ...walletMixin(),
        tab: 'belt',
        mine: [],
        inv: null,
        mineStatus: 'idle',
        init() {
            if (window.location.hash === '#mine') { this.tab = 'mine'; }
            this.watchUser(() => this.loadMine());
        },
        showMine() { this.tab = 'mine'; history.replaceState(null, '', '#mine'); if (this.user) { this.loadMine(); } },
        async loadMine() {
            if (!this.user || this.mineStatus === 'loading') { return; }
            this.mineStatus = 'loading';
            try {
                const [list, inv] = await Promise.all([getJson('/api/islands?owner=' + this.user.addr), readInventory(this.user.addr)]);
                this.mine = list.islands || [];
                this.inv = inv;
                // freshly terraformed islands the indexer hasn't seen yet
                for (const id of (inv.islands || []).map(Number)) {
                    if (!this.mine.find((i) => i.id === id)) {
                        const d = await refreshIsland(id, this.user.addr);
                        if (d) { this.mine.push(d); }
                    }
                }
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
    Alpine.data('islandsBuilder', () => ({
        ...walletMixin(),
        cfg: null,
        status: 'idle',      // idle | loading | ready | error
        error: '',
        inv: null,
        packs: [],
        openingPack: null,
        packMessage: '',
        mode: 'single',      // single | fuse
        selected: [],        // deed ids
        step: 'deeds',       // deeds | build | confirm | signing | done
        layers: {},
        tintOn: false,
        tint: '#5fb04a',
        preview: '',
        previewBusy: false,
        chainCheck: '',      // '' | checking | ok | <error>
        understood: false,
        txId: '',
        txStatus: '',
        islandId: null,

        async init() {
            try { this.cfg = await loadConfig(); } catch (e) { this.status = 'error'; this.error = 'The island service is not reachable right now. Try again in a minute.'; return; }
            this.watchUser(() => this.loadInventory());
        },

        // ---------------- inventory
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
        rarities: RARITIES,
        get hasDeeds() { return !!(this.inv && this.inv.deeds.length); },

        async openPack(id) {
            if (this.openingPack) { return; }
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
                this.packMessage = 'Pack #' + id + ' wasn\u2019t opened: ' + txErrorText(e);
            }
            this.openingPack = null;
        },

        // ---------------- deed selection
        setMode(m) { this.mode = m; this.selected = []; },
        isSelected(id) { return this.selected.includes(id); },
        toggleDeed(d) {
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
        get rarity() {
            const ds = this.selectedDeeds;
            if (!ds.length || ds.some((d) => d.rarity !== ds[0].rarity)) { return null; }
            return islandRarity(ds[0].rarity, ds.length);
        },
        get selectionHint() {
            if (this.mode === 'single') { return this.selected.length ? 'Your island will be ' + this.rarity + '.' : 'Pick one land deed.'; }
            const n = this.selected.length;
            if (n < 5) { return 'Pick ' + (5 - n) + ' more deed' + (5 - n === 1 ? '' : 's') + ' of the same rarity.'; }
            return '5 ' + this.selectedRarity + ' deeds fuse into one ' + this.rarity + ' island.';
        },
        goBuild() {
            if (!this.rarity) { return; }
            this.step = 'build';
            this.defaultScene();
            window.scrollTo({top: 0, behavior: 'smooth'});
        },

        // ---------------- scene
        get sceneLayers() {
            const order = (this.cfg.sceneOrder || []).filter((e) => e[0] !== '@' && !e.endsWith(':front'));
            return order.filter((name) => ((this.cfg.layers[name] || {}).allowed || []).includes(this.rarity));
        },
        isRequired(name) { return ((this.cfg.layers[name] || {}).required || []).includes(this.rarity); },
        optionsFor(name) { return (this.cfg.templates || []).filter((t) => t.layer === name && t.rarity === this.rarity && t.enabled); },
        get infos() { const m = {}; for (const t of (this.cfg.templates || [])) { m[t.id] = t; } return m; },
        get tintAllowed() { return (this.cfg.tintRarities || []).includes(this.rarity); },
        get budget() { return (this.cfg.budget || {})[this.rarity] || 0; },
        get bytes() { return sceneBytes(this.layers, this.infos); },
        get budgetPct() { return this.budget ? Math.min(100, Math.round(this.bytes * 100 / this.budget)) : 0; },
        kb(n) { return (Math.round(Number(n || 0) / 100) / 10).toLocaleString() + ' KB'; },
        get problems() { return validateScene(this.cfg, this.rarity, this.layers, this.tintValue, this.infos); },
        get tintValue() { return this.tintAllowed && this.tintOn && isHexColor(this.tint) ? this.tint : null; },
        missingLayers() { return this.sceneLayers.filter((n) => this.isRequired(n) && !this.optionsFor(n).length); },
        defaultScene() {
            const layers = {};
            for (const name of this.sceneLayers) {
                const opts = this.optionsFor(name);
                if (this.isRequired(name) && opts.length) { layers[name] = opts[0].id; }
            }
            this.layers = layers;
            this.tintOn = false;
            this.renderPreview();
        },
        pick(name, id) {
            const l = {...this.layers};
            if (id === null || id === '' || id === undefined) { delete l[name]; } else { l[name] = Number(id); }
            this.layers = l;
            this.renderPreview();
        },
        async renderPreview() {
            this.previewBusy = true;
            this.chainCheck = '';
            try {
                const ids = Object.values(this.layers);
                const tpls = {};
                for (const t of await Promise.all(ids.map((id) => loadTemplate(id)))) { tpls[t.id] = t; }
                const svg = composeScene(this.cfg, this.layers, this.tintValue, null, tpls);
                if (this.preview) { URL.revokeObjectURL(this.preview); }
                this.preview = URL.createObjectURL(new Blob([svg], {type: 'image/svg+xml'}));
            } catch (e) { console.log(e); }
            this.previewBusy = false;
        },
        goConfirm() {
            if (this.problems.length) { return; }
            this.step = 'confirm';
            this.understood = false;
            this.checkOnChain();
            window.scrollTo({top: 0, behavior: 'smooth'});
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
            } catch (e) { this.chainCheck = txErrorText(e); }
        },
        layerName(name) { const id = this.layers[name]; return id && this.infos[id] ? this.infos[id].name : 'none'; },

        // ---------------- sign
        get canSign() { return this.step === 'confirm' && this.understood && this.chainCheck === 'ok' && !this.dapper() && !!this.user; },
        async terraform() {
            if (!this.canSign) { return; }
            this.step = 'signing';
            this.txStatus = 'Confirm the transaction in your wallet\u2026';
            this.error = '';
            try {
                this.txId = await islandTerraformTx(this.selected, this.layers, this.tintValue);
                this.txStatus = 'Terraforming\u2026 waiting for the Flow blockchain to seal the transaction.';
                const tx = await sealed(this.txId);
                const ev = (tx.events || []).find((e) => /\.FlovatarAsteroidIsland\.Terraformed$/.test(e.type));
                this.islandId = ev ? Number(ev.data.id) : null;
                this.txStatus = 'Your island is ready.';
                this.step = 'done';
                if (this.islandId) {
                    await refreshIsland(this.islandId, this.user.addr);
                    setTimeout(() => { window.location.href = '/islands/' + this.islandId + '?new=1'; }, 1200);
                }
            } catch (e) {
                this.step = 'confirm';
                this.txStatus = '';
                this.error = this.dapper() ? 'Dapper Wallet can\u2019t sign this transaction.' : txErrorText(e);
                this.loadInventory();
            }
        },
    }));

    // --------------------------------------------------------------------------- /islands/{id}
    Alpine.data('islandPage', (islandId) => ({
        ...walletMixin(),
        id: Number(islandId),
        cfg: null,
        island: null,
        status: 'loading',
        live: {},            // chainSlot -> {valid, reason}
        displays: {},        // type|id -> {name, thumbnail}
        isNew: false,
        // owner tools
        editing: false,
        nameInput: '',
        descInput: '',
        busy: false,
        notice: '',
        noticeKind: '',
        picker: null,        // {slots: [chainSlot...], premium: bool, multi: bool}
        walletNfts: null,
        walletStatus: 'idle',
        pickerSel: [],
        pickerFilter: '',
        moveFrom: null,

        async init() {
            const qs = new URLSearchParams(window.location.search);
            this.isNew = qs.has('new');
            try { this.cfg = await loadConfig(); } catch (e) { /* page still renders from the island JSON */ }
            await this.load(qs.get('owner'));
            this.watchUser(() => {});
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
            this.status = 'ready';
            this.liveCheck();
            this.loadDisplays();
        },
        async reload() {
            const d = await refreshIsland(this.id, this.island ? this.island.owner : null);
            if (d) { this.island = d; } else { this.island = await getJson('/api/islands/' + this.id); }
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
            return {'not-owned': 'Moved out: the owner no longer holds this NFT', 'island-owner-changed': 'Set by a previous owner', 'island-not-stored': 'Island not in a wallet'}[why] || '';
        },
        get premiumResidents() { return this.residents.filter((r) => r.premium); },
        get standardResidents() {
            return this.residents.filter((r) => !r.premium && r.chainSlot !== 'caretaker')
                .sort((a, b) => Number(a.slot.slice(1)) - Number(b.slot.slice(1)));
        },
        get premiumSlots() { return this.slots.filter((s) => s.premium); },
        get standardSlots() { return this.slots.filter((s) => !s.premium); },
        get freeStandard() { return this.standardSlots.filter((s) => !this.residentAt(s.chain)); },
        get invalidCount() { return this.residents.filter((r) => !this.isValid(r)).length; },
        familyLabel(r) { return r.family && this.cfg && this.cfg.families && this.cfg.families[r.family] ? this.cfg.families[r.family].label : ''; },
        display(r) { return this.displays[r.type + '|' + r.id] || {}; },
        thumb(r) {
            if (r.family === 'Flovatar') { return '/api/image/r/flovatar/' + r.id + '/thumb.jpg'; }
            if (r.family === 'Flobot') { return '/api/image/r/flobot/' + r.id + '/thumb.jpg'; }
            if (r.family === 'FlovatarDustCollectible') { return '/api/image/r/collectible/' + r.id + '/thumb.jpg'; }
            return httpUrl(this.display(r).thumbnail);
        },
        nftName(r) { return this.display(r).name || (r.type.split('.')[2] || 'NFT') + ' #' + r.id; },
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
            const rs = this.residents.filter((r) => !this.displays[r.type + '|' + r.id]);
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
        scrollCarousel(dir) { const el = this.$refs.carousel; if (el) { el.scrollBy({left: dir * el.clientWidth * 0.8, behavior: 'smooth'}); } },

        // ---------------- owner actions
        say(text, kind) { this.notice = text; this.noticeKind = kind || 'info'; },
        async run(label, fn) {
            if (this.busy) { return; }
            if (this.dapper()) { this.say('Dapper Wallet can\u2019t sign island transactions. Use the Flow Wallet that holds this island.', 'error'); return; }
            this.busy = true;
            this.say(label + ': confirm in your wallet\u2026');
            try {
                const txId = await fn();
                this.say(label + ': waiting for the Flow blockchain\u2026');
                await sealed(txId);
                await this.reload();
                this.say(label + ': done.', 'ok');
                return true;
            } catch (e) {
                this.say(label + ' failed: ' + txErrorText(e), 'error');
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

        // picker
        async openPicker(slots, premium, multi) {
            this.picker = {slots, premium, multi};
            this.pickerSel = [];
            this.pickerFilter = '';
            if (!this.walletNfts) { await this.loadWalletNfts(); }
        },
        closePicker() { this.picker = null; },
        async loadWalletNfts() {
            this.walletStatus = 'loading';
            try {
                const skip = [];
                const out = await fcl.query({cadence: WALLET_NFTS_CADENCE, args: (arg, t) => [arg(this.user.addr, t.Address), arg('0', t.Int), arg('300', t.Int), arg(skip, t.Array(t.String))]});
                this.walletNfts = (out || []).map((n) => ({type: n.type, id: Number(n.id), name: n.name, thumbnail: n.thumbnail, total: Number(n.total)}))
                    .filter((n) => !(n.type === 'A.' + CFG.contract.slice(2) + '.FlovatarAsteroidIsland.NFT' && n.id === this.id));
                this.walletStatus = 'done';
            } catch (e) { console.log(e); this.walletStatus = 'error'; }
        },
        isPremiumType(type) { return !!((this.cfg && this.cfg.premiumTypes) || CFG.premiumTypes || {})[type]; },
        onIsland(n) { return this.residents.some((r) => r.type === n.type && r.id === n.id); },
        get pickerItems() {
            if (!this.walletNfts || !this.picker) { return []; }
            const f = this.pickerFilter.trim().toLowerCase();
            return this.walletNfts.filter((n) => (!this.picker.premium || this.isPremiumType(n.type)) && !this.onIsland(n)
                && (!f || (n.name || '').toLowerCase().includes(f) || n.type.toLowerCase().includes(f) || String(n.id) === f));
        },
        pickerKey(n) { return n.type + '|' + n.id; },
        pickerToggle(n) {
            const k = this.pickerKey(n);
            if (this.pickerSel.includes(k)) { this.pickerSel = this.pickerSel.filter((x) => x !== k); return; }
            const max = this.picker.multi ? this.picker.slots.length : 1;
            this.pickerSel = max === 1 ? [k] : (this.pickerSel.length < max ? [...this.pickerSel, k] : this.pickerSel);
        },
        typeLabel(type) { const p = String(type).split('.'); return p[2] || type; },
        nftThumb(n) {
            if (n.type.endsWith('.Flovatar.NFT') && this.isPremiumType(n.type)) { return '/api/image/r/flovatar/' + n.id + '/thumb.jpg'; }
            return httpUrl(n.thumbnail);
        },
        async confirmPicker() {
            if (!this.picker || !this.pickerSel.length) { return; }
            const items = this.pickerSel.map((k) => { const [type, id] = k.split('|'); return {type, id: Number(id)}; });
            const slots = this.picker.slots.slice(0, items.length);
            // pre-flight: the contract must be able to verify each NFT in this wallet
            try {
                const ok = await fcl.query({cadence: OWNERSHIP_CADENCE, args: (arg, t) => [
                    arg(this.user.addr, t.Address), arg(items.map((i) => i.type), t.Array(t.String)), arg(items.map((i) => String(i.id)), t.Array(t.UInt64)),
                ]});
                const bad = items.filter((_, i) => !ok[i]);
                if (bad.length) {
                    this.say('These NFTs can\u2019t be verified as yours by the island contract (their collection isn\u2019t published the standard way): '
                        + bad.map((b) => this.typeLabel(b.type) + ' #' + b.id).join(', '), 'error');
                    return;
                }
            } catch (e) { /* the transaction re-checks anyway */ }
            this.picker = null;
            await this.run('Set resident' + (items.length > 1 ? 's' : ''), () => islandSetResidentsTx(this.id, slots, items.map((i) => i.type), items.map((i) => i.id)));
        },
        addStandard() {
            const free = this.freeStandard.map((s) => s.chain);
            if (!free.length) { this.say('All standard slots are taken. Clear one first.', 'error'); return; }
            this.openPicker(free, false, true);
        },
        setSlot(s) { this.openPicker([s.chain], s.premium, false); },
        clearSlot(chainSlot) { return this.run('Clear slot', () => islandClearResidentsTx(this.id, [chainSlot])); },
        clearInvalid() {
            const slots = this.residents.filter((r) => !this.isValid(r)).map((r) => r.chainSlot);
            if (slots.length) { return this.run('Clear moved-out residents', () => islandClearResidentsTx(this.id, slots)); }
        },
        startMove(r) { this.moveFrom = this.moveFrom === r.chainSlot ? null : r.chainSlot; },
        moveTargets() {
            const from = this.residents.find((r) => r.chainSlot === this.moveFrom);
            if (!from) { return []; }
            return this.slots.filter((s) => s.chain !== this.moveFrom && (!s.premium || from.family));
        },
        moveTo(chainSlot) {
            const from = this.moveFrom;
            this.moveFrom = null;
            if (from && chainSlot) { return this.run('Move resident', () => islandMoveResidentTx(this.id, from, chainSlot)); }
        },
        slotLabel(chain) { const s = this.slots.find((x) => x.chain === chain); return s ? s.slot : chain; },
    }));
}
