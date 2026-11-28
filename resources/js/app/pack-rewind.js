/*
 * Pack Rewind (TESTNET ONLY, KB notes/pack-rewind.md) — lazy feature bundle "r".
 * Timeline of every pack Flovatar ever sold, re-minted on testnet with the original contents: buy (original price,
 * packs-v2 authorisation from the server), open, reveal, then mint in the builder. Inert unless window.PACK_REWIND.
 */
import Alpine from 'alpinejs';
import * as fcl from '@onflow/fcl';
import {rewindBuyFlowTx} from '../flow/rewind-buy-flow.tx';
import {rewindBuyDustWithFlowTx} from '../flow/rewind-buy-dust-with-flow.tx';
import {rewindSvBuyFlowTx} from '../flow/rewind-sv-buy-flow.tx';
import {rewindSvOpenTx} from '../flow/rewind-sv-open.tx';
import {packBuyV2Tx} from '../flow/pack-buy-v2.tx';
import {islandOpenPackTx} from '../flow/island-open-pack.tx';
import {getDustSwapQuoteScript} from '../flow/get-dust-swap-quote.script';
import {ufix} from './dust-swap-math';
import {txErrorText, apiErrorText} from '../fub/util.mjs';
import {fmtPrice, fmtNum, fmtDate, chapters, buyPlan, reasonText, itemImage, revealOrder, progress} from './pack-rewind-util.mjs';

const CFG = window.PACK_REWIND || null;

function setup() {
    if (!CFG) { return; }
    const c = fcl.config();
    if (CFG.sportvatar) { c.put('0xSportvatar', CFG.sportvatar); }
    if (CFG.swap) {
        c.put('0xSwapRouter', CFG.swap.router).put('0xSwapConfig', CFG.swap.config).put('0xSwapFactory', CFG.swap.factory);
    }
}

const WALLET_SCRIPT = `
import FungibleToken from 0xFungible
import FlovatarDustToken, FlovatarPack from 0xFlovatar
import SportvatarPack from 0xSportvatar
access(all) fun main(a: Address): {String: AnyStruct} {
    let acct = getAccount(a)
    var dust = 0.0
    if let v = acct.capabilities.borrow<&{FungibleToken.Balance}>(FlovatarDustToken.VaultBalancePath) { dust = v.balance }
    return {
        "flow": acct.availableBalance,
        "dust": dust,
        "flovatarPacks": FlovatarPack.getPacks(address: a) ?? [],
        "sportvatarPacks": SportvatarPack.getPacks(address: a) ?? []
    }
}`;

async function sealed(txId) {
    const tx = await fcl.tx(txId).onceSealed();
    if (tx.statusCode !== 0 || tx.errorMessage) { throw new Error(tx.errorMessage || 'Transaction failed'); }
    return tx;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

Alpine.data('packRewind', (initial) => ({
    series: initial || [],
    mine: {},
    address: null,
    wallet: '',            // 'flow' | 'dapper' | ''
    passkey: false,
    flow: null,
    dust: null,
    quote: null,           // FLOW needed for the ĐUST pack price (exact-out swap), null = unavailable
    owned: [],             // rewind packs in the wallet (unopened), from chain + /api/pack-rewind/packs
    busy: '',              // series key or 'open:<id>'
    status: '',
    error: '',
    needsVerify: false,
    grant: null,           // passkey starting FLOW {enabled, amount, state, txId}
    reveal: {open: false, stage: 'idle', pack: null, items: [], shown: 0},
    fmtPrice, fmtNum, fmtDate,

    get chapters() { return chapters(this.series); },
    get prog() { return progress(this.series, this.mine); },

    init() {
        setup();
        this.loadSeries();
        window.addEventListener('user-loaded', (e) => this.onUser(e.detail && e.detail.user));
        window.addEventListener('logged-out', () => { this.address = null; this.wallet = ''; this.owned = []; this.flow = null; });
        window.addEventListener('passkey-flow-grant', (e) => this.onGrant(e.detail || {}));
        if (window.user && window.user.addr) { this.onUser(window.user); }
        setInterval(() => { if (!document.hidden) { this.loadSeries(); } }, 30000);
    },

    onUser(user) {
        if (!user || !user.addr) { return; }
        this.address = user.addr;
        this.passkey = !!(window.extSession && window.extSession.active);
        this.wallet = (window.currentWallet === 'dapper' && !this.passkey) ? 'dapper' : 'flow';
        this.refresh();
        if (this.passkey) { this.loadGrant(); }
    },

    connect() { fcl.authenticate(); },

    async loadSeries() {
        try {
            const r = await window.axios.get('/api/pack-rewind/series');
            this.series = r.data.series || this.series;
            this.mine = r.data.mine || {};
        } catch (e) { /* keep the server-rendered list */ }
    },

    async refresh() {
        if (!this.address) { return; }
        try {
            const w = await fcl.query({cadence: WALLET_SCRIPT, args: (arg, t) => [arg(this.address, t.Address)]});
            this.flow = Number(w.flow);
            this.dust = Number(w.dust);
            const fl = (w.flovatarPacks || []).map(Number);
            const sv = (w.sportvatarPacks || []).map(Number);
            if (fl.length || sv.length) {
                const r = await window.axios.get('/api/pack-rewind/packs', {params: {flovatar: fl.join(','), sportvatar: sv.join(',')}});
                this.owned = (r.data.packs || []).sort((a, b) => (a.year - b.year) || (a.packId - b.packId));
            } else {
                this.owned = [];
            }
        } catch (e) {
            console.warn('pack-rewind wallet read failed', e);
        }
        this.loadQuote();
    },

    async loadQuote() {
        const dustSeries = this.series.find((s) => s.currency === 'DUST');
        if (!dustSeries || !CFG || !CFG.swap) { this.quote = null; return; }
        try {
            const q = await getDustSwapQuoteScript(true, true, ufix(dustSeries.price), null);
            this.quote = Number(q.amountIn);
        } catch (e) { this.quote = null; }
    },

    async loadGrant() {
        try {
            const r = await window.axios.get('/passkey/me');
            this.grant = r.data.flowGrant || null;
        } catch (e) { this.grant = null; }
    },

    onGrant(d) {
        if (d.state === 'pending') { this.grant = {...(this.grant || {}), state: 'pending'}; return; }
        if (d.error) { this.grant = {...(this.grant || {}), state: 'failed', error: d.error}; return; }
        this.grant = {...(this.grant || {}), state: d.state, txId: d.txId, amount: d.amount};
        setTimeout(() => this.refresh(), 2500);
    },

    claimGrant() { if (window.extFlowGrant) { window.extFlowGrant().catch(() => null); } },

    plan(s) {
        return buyPlan({series: s, wallet: this.address ? this.wallet : '', flow: this.flow, dust: this.dust, quoteIn: this.quote, slippagePct: CFG ? CFG.slippage : 3});
    },
    planText(s) { return reasonText(this.plan(s), s); },
    swapLabel(s) {
        const p = this.plan(s);
        return p.maxIn ? 'Pay \u2248 ' + fmtNum(this.quote, 2) + ' FLOW (max ' + fmtNum(p.maxIn, 2) + ')' : '';
    },
    soldPct(s) { return s.supply ? Math.round((s.sold / s.supply) * 100) : 0; },
    itemImage(it, pack) { return itemImage(it, pack.collection, CFG && CFG.sportvatarUrl); },
    builderUrl(pack) {
        if (pack.collection === 'sportvatar') { return CFG.sportvatarUrl + '/builder'; }
        return pack.series === 'terrakit' ? '/islands/build' : '/builder';
    },
    builderLabel(pack) {
        if (pack.collection === 'sportvatar') { return 'Build a Sportvatar'; }
        if (pack.series === 'terrakit') { return 'Terraform an island'; }
        return (pack.contents || []).some((c) => c.c === 'spark') ? 'Mint your Flovatar' : 'Wear your Flobits';
    },

    verifyWallet() {
        window.addEventListener('login-failed', () => { this.error = 'The wallet signature was not accepted. Please try again.'; }, {once: true});
        Promise.resolve(window.createSignature(window.loginMessage));
    },

    async buy(s, payWith = null) {
        if (this.busy) { return; }
        this.error = ''; this.needsVerify = false;
        const p = this.plan(s);
        const mode = payWith || p.mode;
        if (mode === 'none') { this.error = reasonText(p, s); return; }
        this.busy = s.key;
        let res = null;
        try {
            this.status = 'Reserving your ' + s.title + '\u2026';
            res = (await window.axios.post('/pack-rewind/reserve', {series: s.key})).data;
        } catch (e) {
            this.busy = ''; this.status = '';
            if (e.response && e.response.status === 401) {
                this.needsVerify = true;
                this.error = this.passkey ? 'Please sign in again with your passkey.' : 'Your wallet is connected. Verify it once with a free signature to buy.';
            } else {
                this.error = (e.response && e.response.data && e.response.data.error) || apiErrorText(e);
            }
            return;
        }
        try {
            this.status = 'Approve the payment in your ' + (this.passkey ? 'passkey prompt' : 'wallet') + '\u2026';
            let txId;
            if (res.collection === 'sportvatar') {
                txId = await rewindSvBuyFlowTx(res.packId, ufix(res.price), res.expiry, res.signature);
            } else if (res.currency === 'DUST' && mode === 'swap') {
                const deadline = Math.floor(Date.now() / 1000) + 300;
                txId = await rewindBuyDustWithFlowTx(res.packId, ufix(res.price), ufix(p.maxIn), deadline + '.0', res.expiry, res.signature);
            } else if (res.currency === 'DUST') {
                txId = await packBuyV2Tx(res.packId, res.price, res.expiry, res.signature);
            } else {
                txId = await rewindBuyFlowTx(res.packId, ufix(res.price), res.expiry, res.signature);
            }
            this.status = 'Waiting for the blockchain\u2026';
            await sealed(txId);
            this.status = 'Pack bought! Recording it\u2026';
            let pack = null;
            for (let i = 0; i < 3 && !pack; i++) {
                try { pack = (await window.axios.post('/pack-rewind/confirm', {txId, collection: res.collection, packId: res.packId})).data.pack; }
                catch (e) { await sleep(2500); }
            }
            this.status = '';
            await this.loadSeries();
            await this.refresh();
            const mineNow = pack || this.owned.find((o) => o.packId === res.packId && o.collection === res.collection);
            if (mineNow) { this.openReveal(mineNow, 'bought'); }
        } catch (e) {
            this.error = txErrorText(e);
            this.status = '';
        } finally {
            this.busy = '';
        }
    },

    openReveal(pack, stage = 'bought') {
        this.reveal = {open: true, stage, pack, items: revealOrder(pack.contents || []), shown: 0};
        this.$nextTick(() => { const b = document.querySelector('.rw-reveal [data-autofocus]'); if (b) { b.focus(); } });
    },

    closeReveal() { this.reveal.open = false; this.refresh(); },

    async open(pack) {
        if (this.busy) { return; }
        this.error = '';
        this.busy = 'open:' + pack.collection + pack.packId;
        if (!this.reveal.open) { this.openReveal(pack, 'bought'); }
        this.reveal.stage = 'opening';
        try {
            const txId = pack.collection === 'sportvatar' ? await rewindSvOpenTx(pack.packId) : await islandOpenPackTx(pack.packId);
            await sealed(txId);
            window.axios.post('/pack-rewind/opened', {txId, collection: pack.collection, packId: pack.packId}).catch(() => null);
            this.reveal.stage = 'reveal';
            for (let i = 1; i <= this.reveal.items.length; i++) {
                await sleep(i === this.reveal.items.length ? 1100 : 650);
                this.reveal.shown = i;
            }
            this.reveal.stage = 'done';
        } catch (e) {
            this.reveal.stage = 'bought';
            this.error = txErrorText(e);
        } finally {
            this.busy = '';
        }
    },

    onEscape() { if (this.reveal.open && this.reveal.stage !== 'opening') { this.closeReveal(); } },
}));
