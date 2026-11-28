/**
 * FUB v1 (FUB_MODE; KB notes/fub/FUB-BUILD.md). Lazy bundle "u" (App\Support\Stealth), inert unless window.FUB.
 * (resources/js/app/fub.js is the unrelated 2022 /fub demo page.)
 *
 * Alpine components:
 *   fubHome      /fub           wallet state (vouchers, islands, FUBs) + my drafts + new draft
 *   fubStudio    /fub/studio/N  manual editor (layers, items, SVG upload, preview, budget) + Commit / Publish / powers
 *                               + optional design-assistant machine (a section of the options column; talks to the
 *                               assistant API with its own login). Layout = the Flovatar builder shell (.fub-shell).
 *   fubBuilder   /fub/N         F.U.N. builder for buyers (pick, preview, quote, mint)
 *   fubReport    report dialog on FUB / F.U.N. pages
 *
 * User SVG is shown ONLY through <img> (server: /fub-content/*, sandbox CSP). Chain writes by the creator/buyer are
 * wallet transactions (Cadence generated from the tested .cdc files: ../fub/cadence.mjs); draft writes go through
 * the site API (the operator signs them server-side, scoped to drafts).
 */
import Alpine from 'alpinejs';
import * as fcl from '@onflow/fcl';
import {mutate} from '../flow/fcl-tx';
import * as C from '../fub/cadence.mjs';
import {precheckSvg, fmtDust, fmtBytes, curvePrice, totalOf, selectionProblems, randomPicks, pickIds, txErrorText, apiErrorText, capacityOf, RARITIES, fmtDust2, mmss, refillProblem, parseDust, voiceLimit, voiceCost, receiptText, receiptShort, tankPercent, messagesLeft, fmtDustShort} from '../fub/util.mjs';
import {mdToHtml} from '../fub/md.mjs';
import {islandLabel, islandBlockReason, refillOptions, fmtFlow, fmtPct} from '../fub/util.mjs';
import {getDustSwapQuoteScript} from '../flow/get-dust-swap-quote.script';
import {ufix, payPlan, dustPerFlow, clampSlippage} from './dust-swap-math';

const CFG = window.FUB || null;
// ĐUST ⇄ FLOW swap config (window.DUST_SWAP, config flovatar.dustSwap): "pay with FLOW" for tank refills. Absent = ĐUST only.
const SWAP = window.DUST_SWAP && window.DUST_SWAP.enabled ? window.DUST_SWAP : null;
const REFILL_IMPACT_WARN = 5; // % — thin-pool warning in the refill dialog (Luca 2026-11-28)

if (CFG) {
    if (CFG.contract) { fcl.config().put('0xFUB', CFG.contract); }
    if (CFG.islands) { fcl.config().put('0xIslands', CFG.islands); }
}
if (SWAP) {
    fcl.config().put('0xSwapRouter', SWAP.router).put('0xSwapConfig', SWAP.swapConfig).put('0xSwapFactory', SWAP.factory);
}

// ---- in-page dialogs: focus handling (no native alert/confirm anywhere in the FUB pages)
const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
function focusables(root) { return root ? Array.from(root.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null) : []; }
function focusFirst(root, selector) {
    if (!root) { return; }
    const el = (selector && root.querySelector(selector)) || focusables(root)[0] || root;
    el.focus();
}
/** Keep Tab / Shift+Tab inside the dialog. */
function trapTab(e, root) {
    const f = focusables(root);
    if (!f.length) { e.preventDefault(); return; }
    const first = f[0];
    const last = f[f.length - 1];
    if (e.shiftKey && (document.activeElement === first || !root.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (document.activeElement === last || !root.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
}

const ax = () => window.axios;

// design-assistant panel texts / read-only tool names (the assistant's name, levels and prices come from its API)
const A_UNREACHABLE = 'The assistant is not reachable right now. Close and reopen the panel to try again.';
const A_SLOW = 'Still thinking\u2026 this level can take a few minutes.';
const A_READS = ['get_draft', 'get_item_svg', 'validate_svg'];

async function sealed(txId) {
    const tx = await fcl.tx(txId).onceSealed();
    if (tx.statusCode !== 0 || tx.errorMessage) { throw new Error(tx.errorMessage || 'Transaction failed'); }
    return tx;
}

function walletMixin() {
    return {
        user: null,
        wallet: '',
        initWallet(cb) {
            window.addEventListener('user-loaded', (e) => { this.onUser(e.detail && e.detail.user, cb); });
            window.addEventListener('logged-out', () => { this.user = null; this.wallet = ''; cb && cb(null); });
            if (window.user && window.user.addr) { this.onUser(window.user, cb); }
        },
        onUser(user, cb) {
            if (!user || !user.addr) { return; }
            if (this.wallet === user.addr) { return; }
            this.user = user;
            this.wallet = user.addr;
            cb && cb(user);
        },
        connect() { fcl.authenticate(); },
        // The wallet can be connected (FCL) without a server session: the drafts API needs the signed login
        // (account-proof or signed message, /login). On a 401 the page shows a "Verify wallet" button.
        needsVerify: false,
        verifying: false,
        verifyWallet() {
            if (!window.user || !window.user.addr) { this.connect(); return; }
            this.verifying = true;
            window.addEventListener('login-failed', () => { this.verifying = false; this.error = 'The wallet signature was not accepted. Please try again.'; }, {once: true});
            // success: handleLoginResponse reloads the page with the server session
            Promise.resolve(window.createSignature(window.loginMessage)).finally(() => { setTimeout(() => { this.verifying = false; }, 20000); });
        },
        fmtDust, fmtBytes, fmtDust2, fmtDustShort, mmss,
        short(a) { return a ? a.slice(0, 6) + '\u2026' + a.slice(-4) : ''; },
    };
}

async function walletFub(address) {
    const r = await fcl.query({cadence: C.WALLET_FUB_SCRIPT, args: (arg, t) => [arg(address, t.Address)]});
    const vouchers = {};
    for (const [rar, ids] of Object.entries(r.vouchers || {})) { vouchers[rar] = (ids || []).map(Number).sort((a, b) => a - b); }
    const islands = Object.entries(r.freeIslands || {}).map(([id, rarity]) => ({id: Number(id), rarity})).sort((a, b) => a.id - b.id);
    // every island with its FUB state (commit step: taken islands shown disabled with the reason)
    const allIslands = (r.islands || islands.map((i) => ({...i, free: true, reason: ''})))
        .map((i) => ({id: Number(i.id), rarity: i.rarity, name: i.name || '', free: !!i.free, reason: i.reason || '', fubId: i.fubId == null ? null : Number(i.fubId)}))
        .sort((a, b) => (b.free - a.free) || (a.id - b.id));
    return {vouchers, islands, allIslands, fubs: (r.fubs || []).map(Number), funCollection: !!r.funCollection};
}

async function dustBalance(address) {
    try { return Number(await fcl.query({cadence: C.DUST_BALANCE_SCRIPT, args: (arg, t) => [arg(address, t.Address)]})); } catch (e) { return 0; }
}

// ------------------------------------------------------------------------------------------------------------ home

Alpine.data('fubHome', () => ({
    ...walletMixin(),
    loading: false,
    error: '',
    state: null,
    drafts: [],
    fubs: [],
    newName: '',
    creating: false,
    init() { this.initWallet((u) => u && this.load()); },
    get voucherCount() { return this.state ? Object.values(this.state.vouchers).reduce((n, l) => n + l.length, 0) : 0; },
    async load() {
        this.loading = true;
        this.error = '';
        try {
            this.state = await walletFub(this.wallet);
            const r = await ax().get('/api/fub/drafts');
            this.drafts = r.data.drafts;
            this.fubs = r.data.fubs;
        } catch (e) {
            this.needsVerify = !!(e.response && e.response.status === 401);
            this.error = this.needsVerify ? 'Your wallet is connected. Verify it once with a free signature to see your drafts.' : apiErrorText(e);
        }
        this.loading = false;
    },
    async create() {
        if (!this.newName.trim()) { this.error = 'Give your FUB a name.'; return; }
        this.creating = true;
        try {
            const r = await ax().post('/api/fub/drafts', {name: this.newName.trim()});
            window.location.href = '/fub/studio/' + r.data.id;
        } catch (e) { this.error = apiErrorText(e); this.creating = false; }
    },
}));

// ------------------------------------------------------------------------------------------------------------ studio

const EMPTY_ITEM = () => ({id: null, layer: '', name: '', description: '', rarity: 'common', price: 10, supply: 'unlimited', max_mint: 100, increase: 0.01, price_cap: 50, large: false, file: null, svgText: '', check: null});

Alpine.data('fubStudio', (draftId) => ({
    ...walletMixin(),
    RARITIES,
    id: draftId,
    d: null,
    loading: true,
    busy: '',
    error: '',
    notice: '',
    chain: null,         // wallet state (vouchers, islands)
    dust: 0,
    tab: 'layers',
    newLayer: {name: '', required: true},
    form: EMPTY_ITEM(),
    formOpen: false,
    picks: {},
    previewRev: 0,
    selVouchers: [],
    preflightData: null,
    islandId: null,
    commitIsland: null,  // island chosen at commit (permanent binding)
    acceptTerms: false,
    dlg: null,           // generic confirm dialog {title, body, warn, ok, danger}
    rf: null,            // tank refill dialog state
    islandLabel, islandBlockReason, fmtFlow, fmtPct,
    royaltyNew: null,
    payoutNew: '',
    poll: null,
    assistant: null,     // assistant machine state
    assistantName: 'Design assistant', // the real name comes from the assistant API (never hardcoded here)
    init() {
        this.initWallet((u) => u && this.load());
        this.aPeek();
    },
    async load() {
        this.loading = true;
        this.error = '';
        try {
            const r = await ax().get('/api/fub/drafts/' + this.id);
            this.setDraft(r.data);
            this.chain = await walletFub(this.wallet);
            this.dust = await dustBalance(this.wallet);
        } catch (e) {
            this.needsVerify = !!(e.response && e.response.status === 401);
            this.error = this.needsVerify ? 'Your wallet is connected. Verify it once with a free signature to open this draft.' : apiErrorText(e);
        }
        this.loading = false;
    },
    setDraft(d) {
        this.d = d;
        for (const l of d.layers) {
            if (this.picks[l.key] && !d.items.find((i) => i.id === this.picks[l.key] && i.layer === l.key)) { delete this.picks[l.key]; }
            if (!this.picks[l.key]) { const f = d.items.find((i) => i.layer === l.key); if (f) { this.picks[l.key] = f.id; } }
        }
        this.previewRev++;
        if (['pending', 'running'].includes(d.syncState)) { this.startPoll(); } else { this.stopPoll(); }
    },
    startPoll() {
        if (this.poll) { return; }
        this.poll = setInterval(async () => {
            try { const r = await ax().get('/api/fub/drafts/' + this.id); this.setDraft(r.data); } catch (e) { /* keep polling */ }
        }, 4000);
    },
    stopPoll() { if (this.poll) { clearInterval(this.poll); this.poll = null; } },
    async api(method, url, body, label) {
        this.busy = label || 'Saving\u2026';
        this.error = '';
        try {
            const r = await ax()({method, url: '/api/fub/drafts/' + this.id + url, data: body});
            const snap = r.data.draft || r.data;
            if (snap && snap.layers) { this.setDraft(snap); }
            return r.data;
        } catch (e) { this.error = apiErrorText(e); return null; } finally { this.busy = ''; }
    },
    // ----- derived
    get hasAssistant() { return !!(CFG && CFG.assistantUrl); },
    get published() { return this.d && this.d.status === 'published'; },
    get committed() { return this.d && !!this.d.chainFubId; },
    get budget() { return (this.d && this.d.budget) || {}; },
    pct(a, b) { return b ? Math.min(100, Math.round(100 * a / b)) : 0; },
    itemsOf(key) { return this.d ? this.d.items.filter((i) => i.layer === key) : []; },
    draftItemImg(it) { return '/fub-content/draft-item/' + it.id + '.svg?h=' + (it.sha3 || '').slice(0, 10); },
    get previewUrl() {
        if (!this.d) { return ''; }
        return '/fub-content/draft/' + this.d.id + '.svg?i=' + pickIds(this.d.layers, this.picks).join(',') + '&r=' + this.d.revision + '.' + this.previewRev;
    },
    get capacity() {
        const c = {common: 0, epic: 0, legendary: 0};
        if (this.chain) { for (const r of Object.keys(c)) { c[r] = (this.chain.vouchers[r] || []).filter((id) => this.selVouchers.includes(id)).length; } }
        return capacityOf(c);
    },
    // ----- layers
    async addLayer() {
        if (!this.newLayer.name.trim()) { this.error = 'Name the layer.'; return; }
        if (await this.api('post', '/layers', {name: this.newLayer.name.trim(), required: this.newLayer.required}, 'Adding layer\u2026')) { this.newLayer = {name: '', required: true}; }
    },
    renameLayer(l) { const n = prompt('Layer name', l.name); if (n && n.trim()) { this.api('patch', '/layers/' + l.key, {name: n.trim()}); } },
    toggleRequired(l) { this.api('patch', '/layers/' + l.key, {required: !l.required}); },
    moveLayer(i, dir) {
        const keys = this.d.layers.map((l) => l.key);
        const j = i + dir;
        if (j < 0 || j >= keys.length) { return; }
        [keys[i], keys[j]] = [keys[j], keys[i]];
        this.api('post', '/layers/reorder', {keys}, 'Reordering\u2026');
    },
    async deleteLayer(l) {
        const n = this.itemsOf(l.key).length;
        if (await this.ask({title: 'Delete layer?', body: 'Delete layer \u201c' + l.name + '\u201d' + (n ? ' and its ' + n + ' item(s)' : '') + '?', ok: 'Delete', danger: true})) { this.api('delete', '/layers/' + l.key + '?confirm=1', null, 'Deleting\u2026'); }
    },
    // ----- generic in-page confirm (replaces window.confirm): resolves true / false
    ask(o) {
        return new Promise((resolve) => {
            this.dlgReturn = document.activeElement;
            this.dlgResolve = resolve;
            this.dlg = {title: o.title || 'Are you sure?', body: o.body || '', warn: o.warn || '', ok: o.ok || 'Continue', danger: !!o.danger};
            this.$nextTick(() => focusFirst(this.$refs.dlg, '[data-dlg-cancel]'));
        });
    },
    dlgClose(v) {
        const r = this.dlgResolve;
        this.dlg = null;
        this.dlgResolve = null;
        if (this.dlgReturn && this.dlgReturn.focus) { this.dlgReturn.focus(); }
        if (r) { r(!!v); }
    },
    dlgKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); this.dlgClose(false); }
        else if (e.key === 'Tab') { trapTab(e, this.$refs.dlg); }
    },
    // ----- items
    openItem(it, layerKey) {
        this.form = EMPTY_ITEM();
        this.form.layer = layerKey || (this.d.layers[0] && this.d.layers[0].key) || '';
        if (it) {
            Object.assign(this.form, {id: it.id, layer: it.layer, name: it.name, description: it.description || '', rarity: it.rarity, price: it.price,
                max_mint: it.maxMint || 100, increase: it.increase || 0.01, price_cap: it.priceCap || it.price, large: it.large,
                supply: it.increase > 0 ? 'curve' : (it.maxMint > 0 ? 'capped' : 'unlimited')});
        }
        this.formOpen = true;
    },
    async pickFile(ev) {
        const f = ev.target.files && ev.target.files[0];
        if (!f) { return; }
        this.form.file = f;
        this.form.svgText = await f.text();
        this.form.check = precheckSvg(this.form.svgText);
        if (this.form.check.ok) {
            const r = await ax().post('/api/fub/drafts/validate-svg', {svg: this.form.svgText, large: this.form.large}).catch((e) => ({data: {ok: false, errors: [apiErrorText(e)]}}));
            this.form.check = {...this.form.check, server: r.data};
        }
        if (!this.form.name) { this.form.name = f.name.replace(/\.svg$/i, '').replace(/[-_]+/g, ' ').slice(0, 32); }
    },
    itemPayload() {
        const f = this.form;
        const p = {layer: f.layer, name: f.name, description: f.description, rarity: f.rarity, price: Number(f.price), large: !!f.large};
        p.max_mint = f.supply === 'unlimited' ? 0 : Number(f.max_mint);
        p.increase = f.supply === 'curve' ? Number(f.increase) : 0;
        p.price_cap = f.supply === 'curve' ? Number(f.price_cap) : Number(f.price);
        return p;
    },
    async saveItem() {
        const f = this.form;
        if (!f.id && !f.svgText) { this.error = 'Choose an SVG file.'; return; }
        if (Number(f.price) < 1) { this.error = 'The minimum price is \u01101.'; return; }
        let ok;
        if (!f.id) {
            ok = await this.api('post', '/items', {...this.itemPayload(), svg: f.svgText}, 'Checking and saving the item\u2026');
        } else {
            ok = await this.api('patch', '/items/' + f.id, this.itemPayload(), 'Saving\u2026');
            if (ok && f.svgText) { ok = await this.api('post', '/items/' + f.id + '/svg', {svg: f.svgText, large: !!f.large}, 'Replacing the art\u2026'); }
        }
        if (ok) {
            const rep = ok.report;
            this.notice = rep && rep.removed && rep.removed.length ? 'Saved. The sanitiser removed: ' + rep.removed.slice(0, 6).map((x) => (x && x.what) || x).join(', ') + (rep.removed.length > 6 ? '\u2026' : '') : 'Saved.';
            this.formOpen = false;
        }
    },
    async deleteItem(it) { if (await this.ask({title: 'Delete item?', body: 'Delete \u201c' + it.name + '\u201d?', ok: 'Delete', danger: true})) { this.api('delete', '/items/' + it.id + '?confirm=1', null, 'Deleting\u2026'); } },
    pick(l, it) {
        if (this.picks[l.key] === it.id && !l.required) { delete this.picks[l.key]; } else { this.picks[l.key] = it.id; }
        this.picks = {...this.picks};
        this.previewRev++;
    },
    // a random combination of the draft's items in the preview (like the builders' Randomize)
    shuffle() {
        const picks = {};
        for (const l of this.d.layers) {
            const pool = this.itemsOf(l.key);
            if (pool.length && (l.required || Math.random() < 0.7)) { picks[l.key] = pool[Math.floor(Math.random() * pool.length)].id; }
        }
        this.picks = picks;
        this.previewRev++;
    },
    async saveSettings(patch) { await this.api('patch', '', patch); },
    // ----- commit (burn vouchers) / absorb
    toggleVoucher(id) {
        const i = this.selVouchers.indexOf(id);
        if (i >= 0) { this.selVouchers.splice(i, 1); } else if (this.selVouchers.length < 30) { this.selVouchers.push(id); }
    },
    get commitIslandObj() { return this.chain && this.commitIsland ? (this.chain.allIslands || []).find((i) => i.id === this.commitIsland) || null : null; },
    pickCommitIsland(i) { if (i && i.free && !this.busy) { this.commitIsland = i.id; } },
    async commit() {
        if (!this.selVouchers.length) { this.error = 'Pick at least one FUB voucher.'; return; }
        const isl = this.commitIslandObj;
        if (!isl || !isl.free) { this.error = 'Pick the island for your FUB building.'; return; }
        const ok = await this.ask({
            title: 'Commit and burn?',
            body: 'This burns ' + this.selVouchers.length + ' FUB voucher(s) for good and puts your FUB building on ' + islandLabel(isl) + '.',
            warn: 'This is permanent: the FUB stays on this island forever, even if you sell or transfer the island. One FUB per island.',
            ok: 'Commit and burn', danger: true,
        });
        if (!ok) { return; }
        this.busy = 'Waiting for your wallet\u2026';
        this.error = '';
        try {
            const txId = await mutate({cadence: C.COMMIT_TX, args: (arg, t) => [arg(this.selVouchers.map(String), t.Array(t.UInt64)), arg(this.d.name, t.String), arg(String(isl.id), t.UInt64)], limit: 9999});
            this.busy = 'Committing on chain\u2026';
            await sealed(txId);
            const r = await ax().post('/api/fub/drafts/' + this.id + '/link', {tx: txId});
            this.setDraft(r.data);
            this.selVouchers = [];
            this.commitIsland = null;
            this.chain = await walletFub(this.wallet);
            this.notice = 'Committed! FUB #' + r.data.chainFubId + ' is yours, its building stands on ' + islandLabel(isl) + '. Your items are now being written to the chain.';
        } catch (e) { this.error = e.response ? apiErrorText(e) : txErrorText(e); }
        this.busy = '';
    },
    async absorb() {
        if (!this.selVouchers.length) { return; }
        if (!(await this.ask({title: 'Absorb vouchers?', body: 'Absorbing burns ' + this.selVouchers.length + ' more voucher(s) into this FUB for good.', ok: 'Absorb and burn', danger: true}))) { return; }
        this.busy = 'Waiting for your wallet\u2026';
        try {
            const txId = await mutate({cadence: C.ABSORB_TX, args: (arg, t) => [arg(String(this.d.chainFubId), t.UInt64), arg(this.selVouchers.map(String), t.Array(t.UInt64))], limit: 9999});
            this.busy = 'Absorbing on chain\u2026';
            await sealed(txId);
            const r = await ax().post('/api/fub/drafts/' + this.id + '/absorbed', {tx: txId});
            this.setDraft(r.data);
            this.selVouchers = [];
            this.chain = await walletFub(this.wallet);
        } catch (e) { this.error = e.response ? apiErrorText(e) : txErrorText(e); }
        this.busy = '';
    },
    resync() { this.api('post', '/sync', null, 'Starting sync\u2026'); },
    // ----- publish
    async preflight() {
        this.busy = 'Checking\u2026';
        try { this.preflightData = (await ax().get('/api/fub/drafts/' + this.id + '/preflight')).data; } catch (e) { this.error = apiErrorText(e); }
        this.busy = '';
        // island chosen at commit (permanent); only FUBs committed before 2026-11-28 (testnet) still pick it here
        if (this.preflightData && this.preflightData.islandId) { this.islandId = Number(this.preflightData.islandId); }
        else if (this.chain && this.chain.islands.length && !this.islandId) { this.islandId = this.chain.islands[0].id; }
    },
    async publish() {
        const p = this.preflightData;
        if (!p || p.blockers.length) { return; }
        if (!this.islandId) { this.error = 'Pick the island for your FUB building.'; return; }
        if (!this.acceptTerms) { this.error = 'Please accept the creator terms.'; return; }
        if (!(await this.ask({title: 'Publish now?', body: 'Publishing is final: items, prices and supply rules can never change. Your FUB opens on Island #' + this.islandId + '.', ok: 'Publish', danger: true}))) { return; }
        this.busy = 'Waiting for your wallet\u2026';
        this.error = '';
        try {
            const txId = await mutate({cadence: C.PUBLISH_TX, args: (arg, t) => [arg(String(p.fubId), t.UInt64), arg(String(this.islandId), t.UInt64), arg(p.manifest, t.String), arg(p.tosHash, t.String)], limit: 9999});
            this.busy = 'Publishing on chain\u2026';
            await sealed(txId);
            await ax().post('/api/fub/' + p.fubId + '/refresh', {tx: txId});
            window.location.href = '/fub/' + p.fubId + '?published=1';
        } catch (e) { this.error = e.response ? apiErrorText(e) : txErrorText(e); this.busy = ''; }
    },
    // ----- after publish (Q25 powers)
    async creatorTx(cadence, args, label) {
        this.busy = 'Waiting for your wallet\u2026';
        this.error = '';
        try {
            const txId = await mutate({cadence, args, limit: 9999});
            this.busy = label;
            await sealed(txId);
            await ax().post('/api/fub/' + this.d.chainFubId + '/refresh', {tx: txId});
            await this.load();
            this.notice = 'Done.';
        } catch (e) { this.error = e.response ? apiErrorText(e) : txErrorText(e); }
        this.busy = '';
    },
    async setStatus(action) {
        if (action === 'close' && !(await this.ask({title: 'Close for good?', body: 'Closing ends minting for good.', ok: 'Close', danger: true}))) { return; }
        this.creatorTx(C.SET_STATUS_TX, (arg, t) => [arg(String(this.d.chainFubId), t.UInt64), arg(action, t.String)], 'Updating\u2026');
    },
    lowerRoyalty() {
        const v = Number(this.royaltyNew);
        if (!(v >= 0 && v <= 10)) { this.error = 'Royalty is 0-10 %.'; return; }
        this.creatorTx(C.LOWER_ROYALTY_TX, (arg, t) => [arg(String(this.d.chainFubId), t.UInt64), arg((v / 100).toFixed(8), t.UFix64)], 'Lowering royalty\u2026');
    },
    setPayout() {
        if (!/^0x[0-9a-fA-F]{16}$/.test(this.payoutNew)) { this.error = 'Enter a Flow address (0x + 16 hex).'; return; }
        this.creatorTx(C.SET_PAYOUT_TX, (arg, t) => [arg(String(this.d.chainFubId), t.UInt64), arg(this.payoutNew, t.Address)], 'Changing payout\u2026');
    },
    async commitAdditions() {
        await this.preflight();
        const p = this.preflightData;
        if (!p || !p.pending) { this.error = 'No staged additions on chain yet.'; return; }
        if (p.blockers.length) { return; }
        if (!(await this.ask({title: 'Make items live?', body: 'Make ' + p.pendingIds.length + ' new item(s) live? They can never change after this.', ok: 'Make live'}))) { return; }
        this.creatorTx(C.COMMIT_ADDITIONS_TX, (arg, t) => [arg(String(p.fubId), t.UInt64), arg(p.pending, t.String)], 'Making the new items live\u2026');
    },
    async deleteDraft() {
        if (!(await this.ask({title: 'Delete this draft?', body: 'Delete this draft and its uploaded art now? (A committed FUB stays on chain unpublished.)', ok: 'Delete', danger: true}))) { return; }
        await this.api('delete', '?confirm=1', null, 'Deleting\u2026');
        window.location.href = '/fub';
    },
    // ----- design assistant (optional; its name, sizes and indicative burn come from its /api/v1/meta at runtime)
    // Metered: refill any amount; each message burns ĐUST for the work it did; a run goes on while the tank can pay;
    // voice up to 10 minutes with a live timer + Đ counter. Shown as a vending machine in the options column (the
    // "assistant" section): display (name + tank gauge), size buttons, coin slot (refill), glass window (the
    // conversation, the only scrollable part), dispenser (text, voice, send).
    async aMeta() {
        const m = await fetch(CFG.assistantUrl + '/api/v1/meta');
        if (!m.ok) { throw new Error('meta ' + m.status); }
        const j = await m.json();
        if (j && j.name) { this.assistantName = j.name; }
        return j;
    },
    // the section button shows the assistant's name before it is opened
    aPeek() { if (this.hasAssistant) { this.aMeta().catch(() => { /* the button keeps its generic label */ }); } },
    async openAssistant() {
        if (!CFG.assistantUrl) { return; }
        this.assistant = this.assistant || {open: true, meta: null, token: '', conv: null, messages: [], input: '', level: '', busy: '', error: '', tank: null, recording: false,
            refillAmount: '', confirm: null, dry: false, convTotal: 0, live: null, recSec: 0, recLimit: 0, voiceNote: '', stick: true, unseen: 0, slotOpen: false, slotSeen: false, wide: false, peak: 0};
        this.assistant.open = true;
        this.tab = 'assistant';
        const A = this.assistant;
        // wide machine (controls beside the glass): the coin slot starts open; narrow: behind its button
        this.$nextTick(() => {
            const vm = this.$root.querySelector('[data-fub=assistant]');
            const cs = vm ? getComputedStyle(vm) : null; // same measure as the container query: the content box
            A.wide = !!(vm && vm.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight) >= 540);
            if (A.wide && !A.slotSeen) { A.slotOpen = true; }
            A.slotSeen = true;
        });
        if (!A.busy) { A.error = ''; } // reopening retries: an old "not reachable" must not stick
        A.peak = A.peak || Number(localStorage.getItem('fubA:peak:' + this.wallet) || 0);
        try {
            A.meta = await this.aMeta();
        } catch (e) { A.error = A_UNREACHABLE; return; }
        A.level = A.level || (A.meta.levels[0] && A.meta.levels[0].key);
        A.meta.refill = A.meta.refill || {min: 100, max: 10000000, chips: A.meta.refillPresets || []};
        A.refillAmount = A.refillAmount || String((A.meta.refill.chips || [])[1] || (A.meta.refill.chips || [])[0] || '');
        A.token = A.token || sessionStorage.getItem('fubA:' + this.wallet) || '';
        if (!A.token) { return; }
        try {
            await this.aRefresh();
            await this.aResume();
        } catch (e) { A.error = e.message; }
    },
    async aFetch(path, opts = {}) {
        const A = this.assistant;
        let r;
        try {
            r = await fetch(CFG.assistantUrl + path, {...opts, headers: {'Accept': 'application/json', 'Content-Type': 'application/json', ...(A.token ? {'Authorization': 'Bearer ' + A.token} : {}), ...(opts.headers || {})}});
        } catch (e) { throw Object.assign(new Error(A_UNREACHABLE), {status: 0}); }
        const j = await r.json().catch(() => ({}));
        if (r.status === 401) { A.token = ''; sessionStorage.removeItem('fubA:' + this.wallet); }
        if (!r.ok) { throw Object.assign(new Error(r.status === 401 ? 'Your assistant session expired. Please sign in again.' : (j.message || ('Error ' + r.status))), {status: r.status}); }
        if (A.error === A_UNREACHABLE) { A.error = ''; } // reachable again
        return j;
    },
    async aLogin() {
        const A = this.assistant;
        A.busy = 'Sign the login message in your wallet\u2026';
        A.error = '';
        try {
            const n = await this.aFetch('/api/v1/auth/nonce', {method: 'POST', body: JSON.stringify({address: this.wallet})});
            const hex = Array.from(new TextEncoder().encode(n.message)).map((b) => b.toString(16).padStart(2, '0')).join('');
            const sigs = await fcl.currentUser.signUserMessage(hex);
            const r = await this.aFetch('/api/v1/auth/verify', {method: 'POST', body: JSON.stringify({address: this.wallet, nonce: n.nonce, signatures: sigs})});
            A.token = r.token;
            sessionStorage.setItem('fubA:' + this.wallet, r.token);
            await this.aRefresh();
        } catch (e) { A.error = txErrorText(e); }
        A.busy = '';
    },
    async aRefresh() {
        const A = this.assistant;
        A.tank = await this.aFetch('/api/v1/tank');
        if (!A.conv) {
            const g = (await ax().post('/api/fub/drafts/' + this.id + '/grant')).data;
            A.conv = await this.aFetch('/api/v1/conversations', {method: 'POST', body: JSON.stringify({draft_id: this.id, grant: g.grant})});
            A.messages = A.conv.messages || [];
            A.convTotal = Number(A.conv.conversationTotal || 0);
        }
    },
    aLevelBurn(l) { return '\u2248 ' + fmtDust(Math.round(Number(l.indicativeDust || 0))) + ' / message'; },
    // tank gauge: what is left of the last fill (the fill mark is remembered per wallet)
    aSeeTank(balance) {
        const A = this.assistant;
        const b = Number(balance) || 0;
        if (A && b > (Number(A.peak) || 0)) { this.aPeak(b); }
    },
    aPeak(b) { this.assistant.peak = b; try { localStorage.setItem('fubA:peak:' + this.wallet, String(b)); } catch (e) { /* private mode */ } },
    aTankPct() { const A = this.assistant; return A && A.tank ? tankPercent(A.tank.balance, A.peak) : 0; },
    aTankLow() { const A = this.assistant; const l = this.aLevel(); return !!(A && A.tank) && (Number(A.tank.balance) < Number((l && l.minToStart) || 0) || this.aTankPct() <= 15); },
    aNote() { const A = this.assistant; return (A && A.meta && A.meta.note) || 'Each message burns \u0110UST for the work the assistant actually does; the run continues while your tank can pay.'; },
    aMsgsLeft() { const A = this.assistant; const l = this.aLevel(); return A && A.tank && l ? messagesLeft(A.tank.balance, l.indicativeDust, l.name) : ''; },
    // conversation window: follow the latest message unless the user scrolled up (then offer a jump button)
    aScrolled(el) {
        const A = this.assistant;
        if (!A || !el.clientHeight) { return; }
        A.stick = el.scrollHeight - el.scrollTop - el.clientHeight < 48;
        if (A.stick) { A.unseen = 0; }
    },
    aFollow(el, n) {
        const A = this.assistant;
        if (!A) { return; }
        const added = Math.max(0, n - (el._n === undefined ? n : el._n));
        el._n = n;
        const stick = A.stick !== false;
        // pinned to the bottom again after the receipt lines of a new reply are laid out
        const pin = () => { if (A.stick !== false) { el.scrollTop = el.scrollHeight; } };
        this.$nextTick(() => {
            if (stick) { pin(); requestAnimationFrame(pin); setTimeout(pin, 150); } else if (added) { A.unseen = (A.unseen || 0) + added; }
        });
    },
    aJump() {
        const A = this.assistant;
        const el = this.$root.querySelector('[data-fub=assistant-msgs]');
        A.stick = true;
        A.unseen = 0;
        if (el) { el.scrollTop = el.scrollHeight; }
    },
    // assistant replies: untrusted text -> safe Markdown subset (escapes everything, attribute-less tags only)
    aMd(s) { return mdToHtml(s); },
    aReceiptShort(rc) {
        let t = receiptShort(rc);
        const n = ((rc && rc.actions) || []).filter((a) => a.ok && !A_READS.includes(a.tool)).length;
        if (n) { t += (t ? ' \u00b7 ' : '') + n + (n === 1 ? ' change' : ' changes'); }
        return t;
    },
    aRefillError() { const A = this.assistant; return A && A.meta ? refillProblem(A.refillAmount, A.meta.refill.min, A.meta.refill.max) : ''; },
    aVoiceLimit() { const A = this.assistant; return A && A.meta ? voiceLimit(A.tank && A.tank.voice, A.meta.voice) : 0; },
    aVoiceRate() { const A = this.assistant; return A && A.meta && A.meta.voice ? Number(A.meta.voice.dustPerSecond || 0) : 0; },
    aRecCost() { const A = this.assistant; return voiceCost(A ? A.recSec : 0, this.aVoiceRate()); },
    aLevel() { const A = this.assistant; return A && A.meta ? A.meta.levels.find((l) => l.key === A.level) : null; },
    async aSend() {
        const A = this.assistant;
        const text = (A.input || '').trim();
        if (!text || !A.conv || A.busy) { return; }
        const lvl = this.aLevel();
        // no fixed price: the tank must hold about one step of this level (the run then goes on while the tank pays)
        if (lvl && A.tank && Number(A.tank.balance) < Number(lvl.minToStart || 0)) { A.dry = true; A.error = 'Your tank needs at least ' + fmtDust2(lvl.minToStart) + ' to start a ' + lvl.name + ' message. Refill to continue.'; return; }
        A.busy = 'Thinking\u2026';
        A.error = '';
        A.dry = false;
        A.live = {burned: 0, steps: 0};
        A.stick = true; // your own message always scrolls into view
        A.unseen = 0;
        A.messages.push({role: 'user', content: text});
        A.input = '';
        A.sentAt = Date.now();
        // thinking levels outlast the request: after a while say so (the API then answers 202 and we poll)
        const slow = setTimeout(() => { if (A.busy === 'Thinking\u2026') { A.busy = A_SLOW; } }, 12000);

        try {
            const r = await this.aFetch('/api/v1/conversations/' + A.conv.id + '/messages', {method: 'POST', body: JSON.stringify({content: text, level: A.level})});
            await this.aFinish(r, false);
        } catch (e) {
            A.error = e.message;
            await this.aTank();
        }
        clearTimeout(slow);
        A.busy = '';
    },
    // Reply, or a background run (202 {pending: {run, poll}}) that we poll until its reply is in the conversation.
    async aFinish(r, resumed) {
        const A = this.assistant;
        if (r.pending) {
            A.busy = A_SLOW;
            if (r.tank) { A.tank = {...(A.tank || {}), ...r.tank}; }
            sessionStorage.setItem('fubA:run:' + this.id, r.pending.poll);
            r = await this.aPoll(r.pending.poll);
            if (!r) { A.error = 'Lost contact with this request. Its reply will appear in the conversation when it is done: close and reopen the assistant in a few minutes.'; return; }
            sessionStorage.removeItem('fubA:run:' + this.id);
        }
        if (resumed) {
            const c = await this.aFetch('/api/v1/conversations/' + A.conv.id); // the reply is stored there already
            A.messages = c.messages || [];
        } else if (r.message) {
            A.messages.push(r.message);
        }
        if (r.pendingConfirm) { A.confirm = r.pendingConfirm; }
        const rc = r.message && r.message.receipt ? r.message.receipt : {};
        const st = rc.status || '';
        A.live = null;
        if (rc.conversationTotal !== undefined && rc.conversationTotal !== null) { A.convTotal = Number(rc.conversationTotal); }
        if (r.conversationTotal !== undefined && r.conversationTotal !== null) { A.convTotal = Number(r.conversationTotal); }
        if (st === 'refunded') { A.error = 'That request did not work out, so nothing was charged. Please try again.'; }
        if (st === 'failed') { A.error = 'That request stopped part-way. The changes listed in its receipt were saved; you paid only for the tokens used.'; }
        if (st === 'stopped' && rc.stopReason === 'tank') { A.dry = true; } // tank-dry state: Refill, then say "continue"
        await this.aTank();
        await this.load();
    },
    async aPoll(url) {
        const A = this.assistant;
        const t0 = Date.now();
        let fails = 0;
        let writes = -1;
        while (Date.now() - t0 < 25 * 60 * 1000) { // the server starts no new step after 15 min (the last one may take 5 more)
            await new Promise((ok) => setTimeout(ok, 4000));
            let r;
            try { r = await this.aFetch(url); fails = 0; } catch (e) {
                if ([401, 403, 404].includes(e.status) || ++fails >= 10) { return null; }
                continue; // transient: keep polling
            }
            if (!r.pending) { return r; }
            const s = Math.round((Date.now() - (A.sentAt || t0)) / 1000); // time since Send, not since polling began
            // live Đ burned so far (per-step debits) + steps, from the run status
            const burned = Number((r.run && r.run.burned) || (r.pending && r.pending.burned) || 0);
            const steps = Number((r.run && r.run.steps) || (r.pending && r.pending.steps) || 0);
            A.live = {burned, steps};
            if (r.tank) { A.tank = {...(A.tank || {}), balance: r.tank.balance}; }
            A.busy = A_SLOW + ' ' + mmss(s) + ' \u00b7 ' + fmtDust2(burned) + ' burned' + (steps ? ' \u00b7 ' + steps + (steps === 1 ? ' step' : ' steps') : '');
            const n = ((r.run && r.run.actions) || []).filter((a) => a.ok && !A_READS.includes(a.tool)).length;
            if (n !== writes) { // show new layers/items as they land
                if (writes >= 0) { try { this.setDraft((await ax().get('/api/fub/drafts/' + this.id)).data); } catch (e) { /* next time */ } }
                writes = n;
            }
        }
        return null;
    },
    // After a page reload / reopen: pick up a background run that was still thinking.
    async aResume() {
        const A = this.assistant;
        const url = sessionStorage.getItem('fubA:run:' + this.id);
        if (!url || A.busy || !A.conv) { return; }
        A.sentAt = null;
        A.busy = A_SLOW;
        try {
            const r = await this.aFetch(url);
            await this.aFinish(r, true);
        } catch (e) {
            sessionStorage.removeItem('fubA:run:' + this.id);
            if (e.status !== 404) { A.error = e.message; }
        }
        A.busy = '';
    },
    async aTank() {
        const A = this.assistant;
        try { A.tank = await this.aFetch('/api/v1/tank'); } catch (e) { /* keep the last known value */ }
    },
    aReceipt(rc) {
        if (!rc) { return ''; }
        const A = this.assistant;
        const l = A && A.meta ? A.meta.levels.find((x) => x.key === rc.level) : null;
        let t = receiptText(rc, l ? l.name : rc.level);
        const n = (rc.actions || []).filter((a) => a.ok && !A_READS.includes(a.tool)).length;
        if (n) { t += ' \u00b7 ' + n + (n === 1 ? ' change' : ' changes'); }
        return t;
    },
    aBurned(rc) { return rc && !['refunded', 'blocked', 'running'].includes(rc.status) ? fmtDust2(rc.charged) + ' burned' : ''; },
    async aConfirm(yes) {
        const A = this.assistant;
        const c = A.confirm;
        A.confirm = null;
        if (!c) { return; }
        try {
            const r = await this.aFetch('/api/v1/conversations/' + A.conv.id + '/confirm', {method: 'POST', body: JSON.stringify({action_id: c.id, approve: !!yes})});
            if (r.message) { A.messages.push(r.message); }
            if (r.tank) { A.tank = {...(A.tank || {}), ...r.tank}; }
            await this.load();
        } catch (e) { A.error = e.message; }
    },
    // ----- tank refill: in-page vending-machine dialog (burn notice, balances, pay with ĐUST or FLOW via auto-swap)
    async aRefill() {
        const A = this.assistant;
        const bad = this.aRefillError();
        if (bad) { A.error = bad; return; }
        const amt = parseDust(A.refillAmount);
        this.rfReturn = document.activeElement;
        this.rf = {amount: amt, method: this.dust >= amt || !SWAP ? 'dust' : 'flow', slippage: SWAP ? clampSlippage(SWAP.slippageDefault || 1, SWAP.slippageMax || 3) : 1,
            q: null, plan: null, qErr: '', qAt: 0, now: Date.now(), busy: '', error: '', loading: !!SWAP};
        this.$nextTick(() => focusFirst(this.$refs.rf, '[data-rf-method="' + this.rf.method + '"]'));
        this.dust = await dustBalance(this.wallet);
        if (SWAP) {
            await this.rfQuote();
            if (this.rf && this.rf.method === 'dust' && this.dust < amt && this.rfOpts().flowOk) { this.rf.method = 'flow'; }
            if (this.rf && this.rf.method === 'flow' && !this.rfOpts().flowOk && this.dust >= amt) { this.rf.method = 'dust'; }
            clearInterval(this.rfTimer);
            this.rfTimer = setInterval(() => this.rfQuote(), 15000); // live quote every ~15 s while open
        }
        clearInterval(this.rfClock);
        this.rfClock = setInterval(() => { if (this.rf) { this.rf.now = Date.now(); } }, 1000);
    },
    /** FLOW needed for exactly rf.amount ĐUST (router getAmountsIn) + price impact + max with slippage. Fails closed. */
    async rfQuote() {
        const R = this.rf;
        if (!R || !SWAP || R.busy) { return; }
        R.loading = true;
        try {
            const q = await getDustSwapQuoteScript(true, true, ufix(R.amount), this.wallet);
            if (!this.rf || this.rf.amount !== R.amount) { return; } // dialog closed / another amount meanwhile
            const pair = String(q.pairAddress || '').toLowerCase();
            if (SWAP.pair && pair !== String(SWAP.pair).toLowerCase()) { throw new Error('unexpected pool ' + pair); }
            const r = this.rf;
            r.q = {flowIn: Number(q.amountIn), reserveDust: Number(q.reserveDust), reserveFlow: Number(q.reserveFlow), flowAvailable: Number(q.flowAvailable), dustBalance: Number(q.dustBalance)};
            r.plan = payPlan({quoteIn: r.q.flowIn, priceOut: r.amount, reserveIn: r.q.reserveFlow, reserveOut: r.q.reserveDust, available: r.q.flowAvailable,
                slippagePct: r.slippage, warnPct: REFILL_IMPACT_WARN, blockPct: SWAP.impactBlock || 10});
            r.rate = dustPerFlow(r.q.reserveDust, r.q.reserveFlow);
            r.qErr = '';
            r.qAt = Date.now();
            this.dust = r.q.dustBalance;
        } catch (e) {
            if (this.rf) { this.rf.q = null; this.rf.plan = null; this.rf.qErr = 'The FLOW price is not available right now.'; }
        }
        if (this.rf) { this.rf.loading = false; }
    },
    rfSlippage(v) {
        const R = this.rf;
        if (!R || R.busy) { return; }
        R.slippage = clampSlippage(v, (SWAP && SWAP.slippageMax) || 3);
        if (R.q) { R.plan = payPlan({quoteIn: R.q.flowIn, priceOut: R.amount, reserveIn: R.q.reserveFlow, reserveOut: R.q.reserveDust, available: R.q.flowAvailable,
            slippagePct: R.slippage, warnPct: REFILL_IMPACT_WARN, blockPct: SWAP.impactBlock || 10}); }
    },
    rfOpts() { const R = this.rf; return R ? refillOptions({amount: R.amount, dust: this.dust, plan: R.plan, swapOn: !!SWAP, quoteError: R.qErr}) : {}; },
    rfSwapOn() { return !!SWAP; },
    rfSlippages() { const m = (SWAP && SWAP.slippageMax) || 3; return [0.5, 1, 2, 3].filter((x) => x <= m); },
    rfCanPay() { const R = this.rf; const o = this.rfOpts(); return !!R && !R.busy && (R.method === 'flow' ? o.flowOk : o.dustOk); },
    rfAge() { const R = this.rf; return R && R.qAt ? Math.max(0, Math.round((R.now - R.qAt) / 1000)) : null; },
    rfClose() {
        if (this.rf && this.rf.busy) { return; } // never hide a wallet / chain step in progress
        clearInterval(this.rfTimer);
        clearInterval(this.rfClock);
        this.rf = null;
        if (this.rfReturn && this.rfReturn.focus) { this.rfReturn.focus(); }
    },
    rfKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); this.rfClose(); }
        else if (e.key === 'Tab') { trapTab(e, this.$refs.rf); }
    },
    async rfPay() {
        const A = this.assistant;
        const R = this.rf;
        if (!R || !this.rfCanPay()) { return; }
        R.error = '';
        R.busy = 'Preparing\u2026';
        try {
            const ref = (await this.aFetch('/api/v1/tank/refills/intent', {method: 'POST', body: JSON.stringify({amount: R.amount})})).ref;
            let txId;
            if (R.method === 'flow') {
                R.busy = '';
                await this.rfQuote(); // fresh price right before the wallet opens
                if (!this.rfOpts().flowOk) { throw new Error(this.rfOpts().flowReason || 'The FLOW price changed.'); }
                R.busy = 'Waiting for your wallet\u2026';
                const deadline = Math.floor(Date.now() / 1000) + ((SWAP && SWAP.deadlineSeconds) || 300);
                const maxIn = ufix(R.plan.maxIn, 'up');
                txId = await mutate({cadence: C.REFILL_TANK_FLOW_TX, args: (arg, t) => [arg(ufix(R.amount), t.UFix64), arg(maxIn, t.UFix64), arg(ufix(deadline), t.UFix64), arg(ref, t.String)], limit: 9999});
                R.busy = 'Swapping FLOW and burning on chain\u2026';
            } else {
                R.busy = 'Waiting for your wallet\u2026';
                txId = await mutate({cadence: C.REFILL_TANK_TX, args: (arg, t) => [arg(ufix(R.amount), t.UFix64), arg(ref, t.String)], limit: 9999});
                R.busy = 'Burning on chain\u2026';
            }
            A.busy = R.busy;
            await sealed(txId);
            await this.aFetch('/api/v1/tank/refills', {method: 'POST', body: JSON.stringify({tx: txId})});
            await this.aTank();
            if (A.tank) { this.aPeak(Number(A.tank.balance) || 0); } // a refill is the new "full" mark of the gauge
            A.dry = false;
            A.error = '';
            A.refillNote = fmtDust(R.amount) + ' burned into your tank' + (R.method === 'flow' ? ' (paid with FLOW)' : '') + '.';
            if (!A.wide) { A.slotOpen = false; }
            this.dust = await dustBalance(this.wallet);
            R.busy = '';
            A.busy = '';
            this.rfClose();
        } catch (e) {
            R.error = e && e.response ? apiErrorText(e) : txErrorText(e);
            R.busy = '';
            A.busy = '';
        }
    },
    async aRecord() {
        const A = this.assistant;
        if (A.recording) { if (A.rec) { A.rec.stop(); } return; }
        await this.aTank(); // fresh affordable seconds
        const maxSec = this.aVoiceLimit();
        if (maxSec < 1) { A.error = 'Your tank cannot pay for voice right now (' + fmtDust2(this.aVoiceRate() * 60) + ' per minute). Refill first.'; A.dry = true; return; }
        A.voiceNote = '';
        try {
            const stream = await navigator.mediaDevices.getUserMedia({audio: true});
            const chunks = [];
            let rec;
            try { rec = new MediaRecorder(stream, {audioBitsPerSecond: 32000}); } catch (e) { rec = new MediaRecorder(stream); } // ~2.4 MB for 10 min
            A.rec = rec;
            A.recSec = 0;
            A.recLimit = maxSec;
            const t0 = Date.now();
            // live timer + Đ counter, updated every second; auto-stop at the limit (10 min or what the tank affords)
            const tick = setInterval(() => {
                A.recSec = Math.min(maxSec, (Date.now() - t0) / 1000);
                if (A.recSec >= maxSec && rec.state === 'recording') { rec.stop(); }
            }, 1000);
            rec.ondataavailable = (e) => chunks.push(e.data);
            rec.onstop = async () => {
                clearInterval(tick);
                A.recording = false;
                A.recSec = Math.min(maxSec, (Date.now() - t0) / 1000);
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(chunks, {type: rec.mimeType || 'audio/webm'});
                const fd = new FormData();
                fd.append('audio', blob, 'voice.webm');
                fd.append('level', A.level);
                if (A.conv) { fd.append('conversation_id', A.conv.id); }
                A.busy = 'Transcribing ' + mmss(A.recSec) + '\u2026';
                try {
                    const r = await fetch(CFG.assistantUrl + '/api/v1/transcriptions', {method: 'POST', body: fd, headers: {'Accept': 'application/json', 'Authorization': 'Bearer ' + A.token}});
                    const j = await r.json().catch(() => ({}));
                    if (!r.ok) { throw new Error(j.message || (r.status === 413 ? 'This recording is too large to upload.' : 'Transcription failed')); }
                    A.input = (A.input ? A.input + ' ' : '') + j.text; // the user reviews/edits before sending
                    A.voiceNote = 'Voice ' + mmss(j.seconds) + ' transcribed \u00b7 ' + fmtDust2(j.charged) + ' charged';
                    if (j.tank) { A.tank = {...(A.tank || {}), balance: j.tank.balance, voice: {...((A.tank && A.tank.voice) || {}), affordableSeconds: j.tank.voiceSeconds}}; }
                    if (j.conversationTotal !== undefined && j.conversationTotal !== null) { A.convTotal = Number(j.conversationTotal); }
                } catch (e) { A.error = e.message; }
                A.busy = '';
                A.recSec = 0;
            };
            rec.start(1000);
            A.recording = true;
        } catch (e) { A.error = 'Microphone not available.'; }
    },
}));

// ------------------------------------------------------------------------------------------------------------ builder

Alpine.data('fubBuilder', (fubId) => ({
    ...walletMixin(),
    id: fubId,
    fub: null,
    items: [],
    layer: '',
    picks: {},
    quote: null,
    dust: 0,
    busy: '',
    error: '',
    minted: null,
    async init() {
        this.initWallet(async (u) => { if (u) { this.dust = await dustBalance(this.wallet); } });
        await this.load();
        this.picks = randomPicks(this.fub.layers, this.items);
        this.requote();
    },
    async load() {
        const r = await ax().get('/api/fub/' + this.id);
        this.fub = r.data;
        this.items = r.data.itemList.filter((i) => i.live);
        if (!this.layer && this.fub.layers.length) { this.layer = this.fub.layers[0].key; }
    },
    itemsOf(key) { return this.items.filter((i) => i.layer === key); },
    pick(l, it) {
        if (this.picks[l.key] === it.id && !l.required) { delete this.picks[l.key]; } else { this.picks[l.key] = it.id; }
        this.picks = {...this.picks};
        this.requote();
    },
    dice() { this.picks = randomPicks(this.fub.layers, this.items); this.requote(); },
    get previewUrl() { return this.fub ? '/fub-content/fub/' + this.id + '.svg?i=' + pickIds(this.fub.layers, this.picks).join(',') : ''; },
    get total() { return totalOf(this.picks, this.items); },
    get problems() { return this.fub ? selectionProblems(this.fub.layers, this.picks, this.items) : []; },
    priceOf(it) { return curvePrice(it.price, it.increase, it.priceCap, it.minted); },
    async requote() {
        this.quote = null;
        if (this.problems.length) { return; }
        const keys = Object.keys(this.picks);
        try {
            this.quote = await fcl.query({cadence: C.QUOTE_SCRIPT, args: (arg, t) => [arg(String(this.id), t.UInt64), arg(keys, t.Array(t.String)), arg(keys.map((k) => String(this.picks[k])), t.Array(t.UInt64))]});
        } catch (e) { this.quote = null; }
    },
    reasonText(r) {
        return ({'not-open': 'Minting is not open right now.', 'suspended': 'This FUB is suspended.', 'sold-out': 'This FUB is sold out.', 'combination-taken': 'Someone already minted this exact combination. Change one item.'})[r] || r;
    },
    async mint() {
        if (!this.wallet) { this.connect(); return; }
        await this.requote();
        const q = this.quote;
        if (!q || !q.available) { this.error = q ? this.reasonText(q.reason) : 'Pick a complete F.U.N. first.'; return; }
        if (this.dust < Number(q.price)) { this.error = 'You need ' + fmtDust(q.price) + ' (you have ' + fmtDust(this.dust) + ').'; return; }
        const keys = Object.keys(this.picks);
        this.busy = 'Waiting for your wallet\u2026';
        this.error = '';
        try {
            const txId = await mutate({cadence: C.MINT_TX, args: (arg, t) => [arg(String(this.id), t.UInt64), arg(keys, t.Array(t.String)), arg(keys.map((k) => String(this.picks[k])), t.Array(t.UInt64)), arg(Number(q.price).toFixed(8), t.UFix64)], limit: 9999});
            this.busy = 'Minting on chain\u2026';
            const tx = await sealed(txId);
            const ev = (tx.events || []).find((e) => /\.FlovatarUniverseBuilder\.FunMinted$/.test(e.type));
            await ax().post('/api/fub/' + this.id + '/refresh', {tx: txId});
            this.minted = ev ? Number(ev.data.id) : -1;
            this.dust = await dustBalance(this.wallet);
            await this.load();
            this.requote();
        } catch (e) { this.error = txErrorText(e); }
        this.busy = '';
    },
}));

// ------------------------------------------------------------------------------------------------------------ report

Alpine.data('fubReport', (fubId, itemId = null, funId = null) => ({
    open: false, reason: 'stolen-art', details: '', sent: '', error: '',
    async send() {
        try {
            const r = await ax().post('/api/fub/report', {fub: fubId, item: itemId, fun: funId, reason: this.reason, details: this.details});
            this.sent = r.data.message;
        } catch (e) { this.error = apiErrorText(e); }
    },
}));
