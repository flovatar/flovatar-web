/**
 * Buy ĐUST widget (KB notes/flow-evm-program.md EVM-B) — page /buy-dust and an on-demand modal for any page.
 * Part of the ĐUST swap bundle ("s"); inert unless window.DUST_SWAP (FLOW) and/or window.DUST_EVM (stablecoin, other
 * chain) are printed.
 *
 *   Pay with FLOW        Increment ĐUST/FLOW pool (dust-swap-flow-to-dust tx, exact FLOW in, min ĐUST out)
 *   Pay with USDC/USDF   the wallet's Flow EVM account (COA) → one Cadence tx (buy_dust_evm.cdc): EVM DEX swap → bridge
 *                        ĐUST back (or via FLOW + Increment) → ĐUST in the wallet
 *   From another chain   bridge the missing stablecoin into the COA (testnet: simulated with the test faucet;
 *                        mainnet: Relay), then the same tx
 *
 * Integration hook for other pages (Pack Rewind, FUB, …), no extra markup needed besides the modal partial
 * (partials/buy-dust-modal, included by layouts when DustEvm/DustSwap is on):
 *   window.FlovatarBuyDust.open({amount: 3000, reason: 'The ĐUST pack costs Đ3,000'})
 *       → resolves {txId, amount, method} as soon as the purchase is sealed, or null if the user closes without buying
 *   window.addEventListener('buy-dust:done', (e) => e.detail)   // {txId, amount, method}
 */
import Alpine from 'alpinejs';
import {dustSwapFlowToDustTx} from '../flow/dust-swap-flow-to-dust.tx';
import {ufix} from './dust-swap-math';
import {EVMCFG, quoteDustEvm, replan, buyDustEvmTx, bridgeIn, xchainMode, testFaucetTx, sealedTx} from '../evm/dust-evm';
import {fmtUnits, evmReason, evmTxErrorText, bridgeAmount, ROUTE_LABELS, impactLevel, pow10} from '../evm/dust-evm-math.mjs';

const SWAP = window.DUST_SWAP && window.DUST_SWAP.enabled ? window.DUST_SWAP : null;
const CHIPS = [1000, 5000, 10000, 50000];

function txText(e, sym) {
    const s = String((e && (e.message || e.errorMessage)) || e || '');
    const t = evmTxErrorText(s, sym);
    if (t) { return t; }
    if (/Price moved: less DUST|INSUFFICIENT_OUTPUT_AMOUNT/.test(s)) { return 'The FLOW price moved past your slippage. Nothing was spent: try again.'; }
    // a wallet that returns nothing / a JS TypeError is not something to show the user verbatim
    if (/^(TypeError|ReferenceError)|Cannot read properties of|is not a function|undefined/.test(s)) { return 'The wallet did not return a transaction. Nothing was spent: try again.'; }
    const m = /error: (?:assertion failed|panic): ([^\n]+)/i.exec(s);
    return m ? m[1].trim() : (s.length > 240 ? s.slice(0, 240) + '\u2026' : s) || 'The transaction failed.';
}

function fmtD(v) { return 'Đ' + Number(v || 0).toLocaleString('en-US', {maximumFractionDigits: 2}); }
function fmtF(v) { const n = Number(v) || 0; return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: n < 10 ? 4 : 2}); }
function fmtP(v) { return Number.isFinite(v) ? (v < 10 ? v.toFixed(2) : v.toFixed(1)) + '%' : '\u2014'; }

Alpine.data('buyDust', (opts) => ({
    modal: !!(opts && opts.modal),
    amount: String((opts && opts.amount) || 1000),
    reason: '',
    method: 'flow',
    slippage: (EVMCFG && EVMCFG.slippageDefault) || (SWAP && SWAP.slippageDefault) || 1,
    fq: null, fErr: '', fLoading: false,        // FLOW quote (Increment)
    eq: null, eErr: '', eLoading: false, eDebug: '', // stablecoin quote (Flow EVM)
    origin: 0,
    busy: '', error: '', done: null, note: '',
    open: false, resolver: null, timer: null, seq: 0,
    init() {
        this.$watch('user', () => this.requote());
        if (this.modal) {
            window.FlovatarBuyDust = {
                available: () => !!(SWAP || EVMCFG),
                open: (o) => new Promise((resolve) => {
                    this.amount = String(Math.ceil(Number((o && o.amount) || 1000)));
                    this.reason = (o && o.reason) || '';
                    this.done = null; this.error = ''; this.note = '';
                    this.resolver = resolve;
                    this.open = true;
                    this.requote();
                    this.$nextTick(() => { const el = this.$refs.bd && this.$refs.bd.querySelector('input'); if (el) { el.focus(); } });
                }),
            };
        } else {
            const amt = new URLSearchParams(window.location.search).get('amount');
            if (amt && Number(amt) > 0) { this.amount = String(Math.ceil(Number(amt))); }
            this.requote();
        }
        this.timer = setInterval(() => { if ((!this.modal || this.open) && !this.busy) { this.requote(); } }, 15000);
    },
    // ---- session / availability
    session() { void this.user; return window.dustSwapSession ? window.dustSwapSession() : 'none'; },
    addr() { const u = this.user || window.user; return u && u.addr ? u.addr : (window.user && window.user.addr) || null; },
    visible() { return !!SWAP || !!EVMCFG; },
    /** EVM routes need a Flow Wallet COA: not Dapper, not passkey accounts (their templates are not allowlisted). */
    evmWallet() { const u = this.user || window.user; return !!(u && u.addr) && window.currentWallet !== 'dapper' && u.authType !== 'passkey'; },
    evmOn() { return !!EVMCFG; },
    swapOn() { return !!SWAP; },
    sym() { return EVMCFG ? EVMCFG.pay.symbol : 'USDC'; },
    xmode() { return xchainMode(); },
    origins() { return (EVMCFG && EVMCFG.origins) || []; },
    chips() { return CHIPS; },
    num() { const n = Number(String(this.amount).replace(/[^0-9.]/g, '')); return Number.isFinite(n) && n > 0 ? Math.floor(n * 1e8) / 1e8 : 0; },
    amountProblem() {
        const n = this.num();
        if (!n) { return 'Enter how much ĐUST you want.'; }
        if (n > 10000000) { return 'At most Đ10,000,000 per purchase.'; }
        return '';
    },
    setAmount(v) { this.amount = String(v); this.requote(); },
    // ---- quotes
    queue() { clearTimeout(this.qt); this.qt = setTimeout(() => this.requote(), 400); },
    async requote() {
        if (this.amountProblem() || !this.visible()) { this.fq = null; this.eq = null; return; }
        const n = this.num();
        const my = ++this.seq;
        const user = this.addr();
        const jobs = [];
        if (SWAP && window.dustSwapQuote) {
            this.fLoading = true;
            jobs.push(window.dustSwapQuote(true, true, n, user).then((q) => {
                if (my !== this.seq) { return; }
                this.fq = q; this.fErr = '';
            }).catch(() => { if (my === this.seq) { this.fq = null; this.fErr = 'The FLOW price is not available right now.'; } }).finally(() => { if (my === this.seq) { this.fLoading = false; } }));
        }
        if (EVMCFG) {
            this.eLoading = true;
            jobs.push(quoteDustEvm(user, n, this.slippage).then((q) => {
                if (my !== this.seq) { return; }
                this.eq = q; this.eErr = q.best ? '' : 'No ' + this.sym() + ' route for this amount right now.';
            }).catch((e) => { if (my === this.seq) { this.eq = null; this.eDebug = String((e && e.message) || e).slice(0, 300); this.eErr = 'The ' + this.sym() + ' price is not available right now.'; } }).finally(() => { if (my === this.seq) { this.eLoading = false; } }));
        }
        await Promise.all(jobs);
        if (my === this.seq) { this.pick(); }
    },
    setSlippage(v) { this.slippage = Number(v); if (this.eq) { this.eq.plan = replan(this.eq, this.slippage); } },
    // ---- per-method state
    flowPlan() {
        const q = this.fq;
        if (!q) { return null; }
        const pay = Number(ufix(q.amountIn, 'up'));
        const avail = Math.max(0, (q.flowAvailable || 0) - 0.001);
        return {pay, impact: q.impact, level: impactLevel(q.impact, (SWAP && SWAP.impactWarn) || 2, (SWAP && SWAP.impactBlock) || 10), enough: avail >= pay, minOut: this.num() * (1 - this.slippage / 100)};
    },
    flowReason() {
        if (!SWAP) { return 'Not available on this site.'; }
        if (this.fErr) { return this.fErr; }
        const p = this.flowPlan();
        if (!p) { return this.fLoading ? 'Getting the FLOW price\u2026' : 'No FLOW price right now.'; }
        if (p.level === 'block') { return 'The ĐUST/FLOW pool is too thin for this amount (impact ' + fmtP(p.impact) + '). Try less.'; }
        if (this.addr() && !(window.dustSwapCanBuyDust && window.dustSwapCanBuyDust())) { return 'Not available for this kind of account.'; }
        if (this.addr() && !p.enough) { return 'Not enough FLOW: this needs ' + fmtF(p.pay) + ' FLOW.'; }
        return '';
    },
    evmReasonText() {
        if (!EVMCFG) { return 'Not available on this site.'; }
        if (this.addr() && !this.evmWallet()) { return 'Needs a Flow Wallet (with its EVM account).'; }
        const plan = this.eq && this.eq.plan;
        if (!this.addr()) { return plan && plan.level === 'block' ? evmReason({symbol: this.sym(), plan}) : (this.eErr || ''); }
        return evmReason({symbol: this.sym(), plan, quoteError: this.eErr, coa: this.eq && this.eq.coa, loading: this.eLoading});
    },
    xchainReasonText() {
        if (!EVMCFG || this.xmode() === 'off') { return 'Not available on this site.'; }
        if (this.addr() && !this.evmWallet()) { return 'Needs a Flow Wallet (with its EVM account).'; }
        const plan = this.eq && this.eq.plan;
        if (this.eErr) { return this.eErr; }
        if (!plan) { return this.eLoading ? 'Getting the price\u2026' : 'No price right now.'; }
        if (plan.level === 'block') { return evmReason({symbol: this.sym(), plan}); }
        if (this.addr() && this.xmode() === 'relay' && !(this.eq && this.eq.coa)) { return 'Your wallet needs a Flow EVM account first (Flow Wallet: enable EVM).'; }
        return '';
    },
    reasons() { return {flow: this.flowReason(), usdc: this.evmReasonText(), xchain: this.xchainReasonText()}; },
    ok(m) { return this.reasons()[m] === ''; },
    pick() {
        if (this.ok(this.method)) { return; }
        const m = ['flow', 'usdc', 'xchain'].find((x) => this.ok(x));
        if (m) { this.method = m; }
    },
    bridgeUnits() { return this.eq && this.eq.plan ? bridgeAmount(this.eq.plan.short, EVMCFG.pay.decimals) : 0n; },
    fmtPay(u) { return EVMCFG ? fmtUnits(u, EVMCFG.pay.decimals) : ''; },
    routeLabel() { return this.eq && this.eq.best ? (ROUTE_LABELS[this.eq.best.name] || this.eq.best.name) : ''; },
    quoteText(m) {
        if (m === 'flow') { const p = this.flowPlan(); return p ? '\u2248 ' + fmtF(p.pay) + ' FLOW' : ''; }
        if (!this.eq || !this.eq.best) { return ''; }
        if (m === 'usdc') { return '\u2248 ' + this.fmtPay(this.eq.best.amountIn) + ' ' + this.sym(); }
        const b = this.bridgeUnits();
        return b > 0n ? 'bridge ' + this.fmtPay(b) + ' ' + this.sym() + ', then pay \u2248 ' + this.fmtPay(this.eq.best.amountIn) : '\u2248 ' + this.fmtPay(this.eq.best.amountIn) + ' ' + this.sym() + ' (no bridge needed)';
    },
    impact() { if (this.method === 'flow') { const p = this.flowPlan(); return p ? p.impact : NaN; } return this.eq && this.eq.plan ? this.eq.plan.impact : NaN; },
    level() { if (this.method === 'flow') { const p = this.flowPlan(); return p ? p.level : 'ok'; } return this.eq && this.eq.plan ? this.eq.plan.level : 'ok'; },
    maxText() {
        if (this.method === 'flow') { const p = this.flowPlan(); return p ? 'You pay ' + fmtF(p.pay) + ' FLOW, receive at least ' + fmtD(Math.floor(p.minOut * 100) / 100) : ''; }
        return this.eq && this.eq.plan ? 'You receive exactly ' + fmtD(this.num()) + ', pay at most ' + this.fmtPay(this.eq.plan.maxIn) + ' ' + this.sym() : '';
    },
    balances() {
        const out = [];
        if (this.fq && this.addr()) { out.push(['FLOW (spendable)', fmtF(this.fq.flowAvailable)]); out.push(['ĐUST', fmtD(this.fq.dustBalance)]); }
        if (this.eq && this.addr()) { out.push([this.sym() + ' (Flow EVM)', this.eq.coa ? this.fmtPay(this.eq.balance) : 'no EVM account']); }
        return out;
    },
    canFaucet() { return !!(EVMCFG && EVMCFG.testFaucet) && this.evmWallet(); },
    fmtPct: fmtP,
    fmtDust: fmtD,
    canBuy() { return !this.busy && !this.amountProblem() && this.ok(this.method); },
    ctaLabel() {
        if (!this.addr()) { return 'Connect wallet'; }
        if (this.busy) { return 'Working\u2026'; }
        if (this.method === 'xchain' && this.bridgeUnits() > 0n) { return 'Bridge & buy ' + fmtD(this.num()); }
        return 'Buy ' + fmtD(this.num());
    },
    // ---- actions
    async faucet() {
        if (this.busy || !this.canFaucet()) { return; }
        this.error = ''; this.busy = 'Getting test ' + this.sym() + '\u2026';
        try {
            await sealedTx(await testFaucetTx(25n * pow10(EVMCFG.pay.decimals)));
            this.note = '25 test ' + this.sym() + ' added to your Flow EVM account.';
        } catch (e) { this.error = txText(e, this.sym()); }
        this.busy = '';
        await this.requote();
    },
    async buy() {
        if (!this.canBuy()) { return; }
        this.error = ''; this.done = null; this.note = '';
        const n = this.num();
        const method = this.method;
        try {
            let txId;
            if (method === 'flow') {
                const p = this.flowPlan();
                this.busy = 'Waiting for your wallet\u2026';
                const dl = ufix(Math.floor(Date.now() / 1000) + ((SWAP && SWAP.deadlineSeconds) || 300));
                txId = await dustSwapFlowToDustTx(ufix(p.pay, 'up'), ufix(p.minOut, 'down'), dl);
                this.busy = 'Swapping FLOW for ĐUST on chain\u2026';
            } else {
                if (method === 'xchain' && this.bridgeUnits() > 0n) {
                    const units = this.bridgeUnits();
                    const origin = this.origins()[this.origin] || this.origins()[0] || {};
                    await bridgeIn({units, coa: this.eq.coa, origin, onStatus: (s) => { this.busy = s; }});
                    this.note = this.fmtPay(units) + ' ' + this.sym() + (this.xmode() === 'simulate' ? ' arrived (simulated bridge, testnet).' : ' arrived on Flow EVM.');
                }
                this.busy = 'Updating the price\u2026';
                const q = await quoteDustEvm(this.addr(), n, this.slippage);
                this.eq = q;
                if (this.evmReasonText()) { throw new Error(this.evmReasonText()); }
                this.busy = 'Waiting for your wallet\u2026';
                txId = await buyDustEvmTx({amount: n, route: q.best, maxIn: q.plan.maxIn});
                this.busy = 'Swapping ' + this.sym() + ' on Flow EVM and bridging ĐUST\u2026';
            }
            await sealedTx(txId);
            this.done = {txId, amount: n, method};
            this.busy = '';
            window.dispatchEvent(new CustomEvent('buy-dust:done', {detail: this.done}));
            window.dispatchEvent(new CustomEvent('dust-swap-done', {detail: {txId}}));
            // modal: the caller (e.g. Pack Rewind) continues as soon as the ĐUST is in the wallet; the modal keeps the
            // success line until the user closes it
            if (this.modal && this.resolver) { const r = this.resolver; this.resolver = null; r(this.done); }
            if (typeof window.refreshProfile === 'function') { window.refreshProfile(); }
            await this.requote();
        } catch (e) {
            this.error = txText(e, this.sym());
            this.busy = '';
        }
    },
    close() {
        if (this.busy) { return; }
        this.open = false;
        if (this.resolver) { this.resolver(this.done); this.resolver = null; }
    },
    keydown(e) { if (this.modal && this.open && e.key === 'Escape') { e.preventDefault(); this.close(); } },
    explorerTx(txId) { return (window.DUST_SWAP && window.DUST_SWAP.network === 'mainnet' ? 'https://www.flowscan.io/tx/' : 'https://testnet.flowscan.io/tx/') + txId; },
}));

export default {};
