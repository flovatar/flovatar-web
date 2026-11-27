/**
 * FUB v1 (FUB_MODE; KB notes/fub/FUB-BUILD.md). Lazy bundle "u" (App\Support\Stealth), inert unless window.FUB.
 * (resources/js/app/fub.js is the unrelated 2022 /fub demo page.)
 *
 * Alpine components:
 *   fubHome      /fub           wallet state (vouchers, islands, FUBs) + my drafts + new draft
 *   fubStudio    /fub/studio/N  manual editor (layers, items, SVG upload, preview, budget) + Commit / Publish / powers
 *                               + optional design-assistant panel (talks to the assistant API with its own login)
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
import {precheckSvg, fmtDust, fmtBytes, curvePrice, totalOf, selectionProblems, randomPicks, pickIds, txErrorText, apiErrorText, capacityOf, RARITIES} from '../fub/util.mjs';

const CFG = window.FUB || null;

if (CFG) {
    if (CFG.contract) { fcl.config().put('0xFUB', CFG.contract); }
    if (CFG.islands) { fcl.config().put('0xIslands', CFG.islands); }
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
        fmtDust, fmtBytes,
        short(a) { return a ? a.slice(0, 6) + '\u2026' + a.slice(-4) : ''; },
    };
}

async function walletFub(address) {
    const r = await fcl.query({cadence: C.WALLET_FUB_SCRIPT, args: (arg, t) => [arg(address, t.Address)]});
    const vouchers = {};
    for (const [rar, ids] of Object.entries(r.vouchers || {})) { vouchers[rar] = (ids || []).map(Number).sort((a, b) => a - b); }
    const islands = Object.entries(r.freeIslands || {}).map(([id, rarity]) => ({id: Number(id), rarity})).sort((a, b) => a.id - b.id);
    return {vouchers, islands, fubs: (r.fubs || []).map(Number), funCollection: !!r.funCollection};
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
            this.error = e.response && e.response.status === 401 ? 'Sign in with your wallet to see your drafts.' : apiErrorText(e);
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
    acceptTerms: false,
    royaltyNew: null,
    payoutNew: '',
    poll: null,
    assistant: null,     // assistant panel state
    init() {
        this.initWallet((u) => u && this.load());
    },
    async load() {
        this.loading = true;
        this.error = '';
        try {
            const r = await ax().get('/api/fub/drafts/' + this.id);
            this.setDraft(r.data);
            this.chain = await walletFub(this.wallet);
            this.dust = await dustBalance(this.wallet);
        } catch (e) { this.error = apiErrorText(e); }
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
    deleteLayer(l) {
        const n = this.itemsOf(l.key).length;
        if (confirm('Delete layer "' + l.name + '"' + (n ? ' and its ' + n + ' item(s)' : '') + '?')) { this.api('delete', '/layers/' + l.key + '?confirm=1', null, 'Deleting\u2026'); }
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
    deleteItem(it) { if (confirm('Delete "' + it.name + '"?')) { this.api('delete', '/items/' + it.id + '?confirm=1', null, 'Deleting\u2026'); } },
    pick(l, it) {
        if (this.picks[l.key] === it.id && !l.required) { delete this.picks[l.key]; } else { this.picks[l.key] = it.id; }
        this.picks = {...this.picks};
        this.previewRev++;
    },
    async saveSettings(patch) { await this.api('patch', '', patch); },
    // ----- commit (burn vouchers) / absorb
    toggleVoucher(id) {
        const i = this.selVouchers.indexOf(id);
        if (i >= 0) { this.selVouchers.splice(i, 1); } else if (this.selVouchers.length < 30) { this.selVouchers.push(id); }
    },
    async commit() {
        if (!this.selVouchers.length) { this.error = 'Pick at least one FUB voucher.'; return; }
        if (!confirm('Commit burns ' + this.selVouchers.length + ' FUB voucher(s) for good. Continue?')) { return; }
        this.busy = 'Waiting for your wallet\u2026';
        this.error = '';
        try {
            const txId = await mutate({cadence: C.COMMIT_TX, args: (arg, t) => [arg(this.selVouchers.map(String), t.Array(t.UInt64)), arg(this.d.name, t.String)], limit: 9999});
            this.busy = 'Committing on chain\u2026';
            await sealed(txId);
            const r = await ax().post('/api/fub/drafts/' + this.id + '/link', {tx: txId});
            this.setDraft(r.data);
            this.selVouchers = [];
            this.chain = await walletFub(this.wallet);
            this.notice = 'Committed! FUB #' + r.data.chainFubId + ' is yours. Your items are now being written to the chain.';
        } catch (e) { this.error = e.response ? apiErrorText(e) : txErrorText(e); }
        this.busy = '';
    },
    async absorb() {
        if (!this.selVouchers.length) { return; }
        if (!confirm('Absorbing burns ' + this.selVouchers.length + ' more voucher(s) into this FUB. Continue?')) { return; }
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
        if (this.chain && this.chain.islands.length && !this.islandId) { this.islandId = this.chain.islands[0].id; }
    },
    async publish() {
        const p = this.preflightData;
        if (!p || p.blockers.length) { return; }
        if (!this.islandId) { this.error = 'Pick the island for your FUB building.'; return; }
        if (!this.acceptTerms) { this.error = 'Please accept the creator terms.'; return; }
        if (!confirm('Publishing is final: items, prices and supply rules can never change, and the FUB building on island #' + this.islandId + ' is permanent. Publish now?')) { return; }
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
    setStatus(action) {
        if (action === 'close' && !confirm('Closing ends minting for good. Continue?')) { return; }
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
        if (!confirm('Make ' + p.pendingIds.length + ' new item(s) live? They can never change after this.')) { return; }
        this.creatorTx(C.COMMIT_ADDITIONS_TX, (arg, t) => [arg(String(p.fubId), t.UInt64), arg(p.pending, t.String)], 'Making the new items live\u2026');
    },
    async deleteDraft() {
        if (!confirm('Delete this draft and its uploaded art now? (A committed FUB stays on chain unpublished.)')) { return; }
        await this.api('delete', '?confirm=1', null, 'Deleting\u2026');
        window.location.href = '/fub';
    },
    // ----- design assistant (optional; name, levels and prices come from its /api/v1/meta at runtime)
    async openAssistant() {
        if (!CFG.assistantUrl) { return; }
        this.assistant = this.assistant || {open: true, meta: null, token: '', conv: null, messages: [], input: '', level: '', busy: '', error: '', tank: null, recording: false, refillAmount: 0, confirm: null};
        this.assistant.open = true;
        const A = this.assistant;
        if (!A.busy) { A.error = ''; } // reopening retries: an old "not reachable" must not stick
        try {
            const m = await fetch(CFG.assistantUrl + '/api/v1/meta');
            if (!m.ok) { throw new Error('meta ' + m.status); }
            A.meta = await m.json();
        } catch (e) { A.error = A_UNREACHABLE; return; }
        A.level = A.level || (A.meta.levels[0] && A.meta.levels[0].key);
        A.refillAmount = A.refillAmount || (A.meta.refillPresets || [])[0] || 0;
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
        }
    },
    aLevel() { const A = this.assistant; return A && A.meta ? A.meta.levels.find((l) => l.key === A.level) : null; },
    async aSend() {
        const A = this.assistant;
        const text = (A.input || '').trim();
        if (!text || !A.conv || A.busy) { return; }
        const lvl = this.aLevel();
        if (lvl && A.tank && Number(A.tank.balance) < Number(lvl.price)) { A.error = 'Your tank needs at least ' + fmtDust(lvl.price) + ' for this level.'; return; }
        A.busy = 'Thinking\u2026';
        A.error = '';
        A.messages.push({role: 'user', content: text});
        A.input = '';
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
            if (r.tank) { A.tank = {...(A.tank || {}), ...r.tank}; } // already reserved: shows the lower tank right away
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
        const st = r.message && r.message.receipt ? r.message.receipt.status : '';
        if (st === 'refunded') { A.error = 'That request did not work out, so your \u0110UST was refunded. Please try again.'; }
        if (st === 'failed') { A.error = 'That request stopped part-way. The changes listed in its receipt were saved.'; }
        await this.aTank();
        await this.load();
    },
    async aPoll(url) {
        const A = this.assistant;
        const t0 = Date.now();
        let fails = 0;
        let writes = -1;
        while (Date.now() - t0 < 17 * 60 * 1000) { // the server stops a run after 15 min (and refunds it)
            await new Promise((ok) => setTimeout(ok, 4000));
            let r;
            try { r = await this.aFetch(url); fails = 0; } catch (e) {
                if ([401, 403, 404].includes(e.status) || ++fails >= 10) { return null; }
                continue; // transient: keep polling
            }
            if (!r.pending) { return r; }
            const s = Math.round((Date.now() - t0) / 1000);
            A.busy = A_SLOW + ' ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
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
        const parts = [l ? l.name : rc.level];
        if (rc.status === 'running') { parts.push(fmtDust(rc.charged) + ' reserved'); }
        else if (rc.status === 'refunded') { parts.push('refunded, nothing charged'); }
        else if (rc.status === 'blocked') { parts.push('not run, nothing charged'); }
        else { parts.push('charged ' + fmtDust(rc.charged)); }
        if (rc.status === 'failed') { parts.push('stopped part-way'); }
        const n = (rc.actions || []).filter((a) => a.ok && !A_READS.includes(a.tool)).length;
        if (n) { parts.push(n + (n === 1 ? ' change' : ' changes')); }
        if (rc.seconds) { parts.push(Math.round(rc.seconds) + ' s'); }
        if (rc.balanceAfter !== undefined) { parts.push('tank ' + fmtDust(rc.balanceAfter)); }
        return 'Receipt #' + rc.run + ': ' + parts.join(' \u00b7 ');
    },
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
    async aRefill() {
        const A = this.assistant;
        const amt = Number(A.refillAmount);
        if (!amt) { return; }
        if (!confirm('Refilling burns ' + fmtDust(amt) + ' from your wallet. Continue?')) { return; }
        A.busy = 'Waiting for your wallet\u2026';
        try {
            const ref = (await this.aFetch('/api/v1/tank/refills/intent', {method: 'POST', body: JSON.stringify({amount: amt})})).ref;
            const txId = await mutate({cadence: C.REFILL_TANK_TX, args: (arg, t) => [arg(amt.toFixed(8), t.UFix64), arg(ref, t.String)], limit: 9999});
            A.busy = 'Burning on chain\u2026';
            await sealed(txId);
            A.tank = await this.aFetch('/api/v1/tank/refills', {method: 'POST', body: JSON.stringify({tx: txId})});
            this.dust = await dustBalance(this.wallet);
        } catch (e) { A.error = txErrorText(e); }
        A.busy = '';
    },
    async aRecord() {
        const A = this.assistant;
        if (A.recording) { if (A.rec) { A.rec.stop(); } return; }
        const lvl = this.aLevel();
        const maxSec = (lvl && lvl.voiceSeconds) || 30;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({audio: true});
            const chunks = [];
            const rec = new MediaRecorder(stream);
            A.rec = rec;
            rec.ondataavailable = (e) => chunks.push(e.data);
            rec.onstop = async () => {
                A.recording = false;
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(chunks, {type: rec.mimeType || 'audio/webm'});
                const fd = new FormData();
                fd.append('audio', blob, 'voice.webm');
                fd.append('level', A.level);
                A.busy = 'Transcribing\u2026';
                try {
                    const r = await fetch(CFG.assistantUrl + '/api/v1/transcriptions', {method: 'POST', body: fd, headers: {'Accept': 'application/json', 'Authorization': 'Bearer ' + A.token}});
                    const j = await r.json();
                    if (!r.ok) { throw new Error(j.message || 'Transcription failed'); }
                    A.input = j.text; // the user reviews/edits before sending
                } catch (e) { A.error = e.message; }
                A.busy = '';
            };
            rec.start();
            A.recording = true;
            setTimeout(() => { if (rec.state === 'recording') { rec.stop(); } }, maxSec * 1000);
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
