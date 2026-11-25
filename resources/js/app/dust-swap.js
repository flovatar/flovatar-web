/**
 * ĐUST ⇄ FLOW auto-swap via IncrementFi (KB notes/dust-swap.md). Inert unless the layout printed window.DUST_SWAP
 * (DUST_SWAP_ENABLED, testnet only for now).
 *
 *  - Rate chip (header): "1 FLOW ≈ Đ301" from the pool reserves (FLOW per ĐUST only, never USD), "low liquidity" below
 *    the configured pool depth.
 *  - "Pay with FLOW" for ĐUST listings (inside partials/dust-market) when the ĐUST balance is short but FLOW suffices.
 *  - "Pay with ĐUST" for FLOW listings (Flovatar / Flobit pages) — sells ĐUST: self-custody Flow Wallet sessions only.
 *  - Quick-swap widget on /dust (FLOW→ĐUST for wallets + passkey users holding FLOW; ĐUST→FLOW wallets only).
 *
 * Chain-first: every quote is a Cadence script run in the browser (get-dust-swap-quote). Fail closed: any script error,
 * an unexpected pair address or an impossible quote hides the swap option. Dapper sessions get no swap UI at all.
 * Slippage default 1% (max 3%), deadline now + 300 s, price impact warn > 2%, block > 10% (config flovatar.dustSwap).
 */
import Alpine from "alpinejs";
import * as fcl from "@onflow/fcl";
import {getDustSwapQuoteScript} from "../flow/get-dust-swap-quote.script";
import {dustSwapFlowToDustTx} from "../flow/dust-swap-flow-to-dust.tx";
import {dustSwapDustToFlowTx} from "../flow/dust-swap-dust-to-flow.tx";
import {dustSwapBuyDustListingTx} from "../flow/dust-swap-buy-dust-listing.tx";
import {dustSwapBuyFlowListingTx} from "../flow/dust-swap-buy-flow-listing.tx";
import {ufix, priceImpact, impactLevel, dustPerFlow, clampSlippage, maxInWithSlippage, minOutWithSlippage, payPlan} from "./dust-swap-math";

const CFG = window.DUST_SWAP && window.DUST_SWAP.enabled ? window.DUST_SWAP : null;

window.dustSwapEnabled = function(){
    return !!CFG;
};

if(CFG){
    // Increment contract aliases for the swap Cadence (per network, from config/flovatar.php dustSwap)
    fcl.config()
        .put('0xSwapRouter', CFG.router)
        .put('0xSwapConfig', CFG.swapConfig)
        .put('0xSwapFactory', CFG.factory);
}

/** 'none' (signed out) | 'dapper' | 'passkey' | 'wallet' (self-custody Flow Wallet). */
window.dustSwapSession = function(){
    const u = window.user;
    if(!u || !u.addr){
        return 'none';
    }
    if(window.currentWallet === 'dapper'){
        return 'dapper';
    }
    if((window.extSession && window.extSession.active) || u.authType === 'passkey'){
        return 'passkey';
    }
    return 'wallet';
};

/** FLOW → ĐUST (adds FLOW to the pool): Flow Wallet, and passkey accounts where the allowlist has it (testnet). */
window.dustSwapCanBuyDust = function(){
    const s = window.dustSwapSession();
    return !!CFG && (s === 'wallet' || (s === 'passkey' && !!CFG.passkeyBuy));
};

/** ĐUST → FLOW (sells ĐUST into the pool): self-custody Flow Wallet only. */
window.dustSwapCanSellDust = function(){
    return !!CFG && window.dustSwapSession() === 'wallet';
};

function num(v){
    const n = parseFloat(v);
    return isFinite(n) ? n : 0;
}

/**
 * Router quote. flowToDust + exactOut + amount (number) + optional user address. Resolves to
 * {reserveIn, reserveOut, reserveDust, reserveFlow, amountIn, amountOut, impact, flowAvailable, dustBalance, feeBps}
 * or throws (fail closed) when the script fails or the pool is not the configured pair.
 */
window.dustSwapQuote = async function(flowToDust, exactOut, amount, user){
    if(!CFG){
        throw new Error('ĐUST swap disabled');
    }
    const q = await getDustSwapQuoteScript(!!flowToDust, !!exactOut, amount > 0 ? ufix(amount, exactOut ? 'down' : 'down') : '0.0', user || null);
    if(!q || !q.pairAddress || q.pairAddress.toLowerCase() !== (CFG.pair || '').toLowerCase()){
        throw new Error('Unexpected ĐUST/FLOW pool ' + (q && q.pairAddress));
    }
    const reserveDust = num(q.reserveDust), reserveFlow = num(q.reserveFlow);
    const reserveIn = flowToDust ? reserveFlow : reserveDust;
    const reserveOut = flowToDust ? reserveDust : reserveFlow;
    const amountIn = num(q.amountIn), amountOut = num(q.amountOut);
    return {
        reserveDust, reserveFlow, reserveIn, reserveOut, amountIn, amountOut,
        feeBps: parseInt(q.feeBps, 10) || 30,
        impact: amount > 0 ? priceImpact(amountIn, amountOut, reserveIn, reserveOut) : 0,
        flowAvailable: num(q.flowAvailable),
        dustBalance: num(q.dustBalance),
    };
};

function deadline(){
    return ufix(Math.floor(Date.now() / 1000) + ((CFG && CFG.deadlineSeconds) || 300));
}

function fmtFlow(v){
    const n = num(v);
    const digits = n >= 100 ? 2 : (n >= 1 ? 4 : 6);
    return new Intl.NumberFormat('en-US', {minimumFractionDigits: 0, maximumFractionDigits: digits}).format(n) + ' FLOW';
}

function fmtDust(v){
    return (window.formatDust ? window.formatDust(v) : 'Đ ' + num(v).toFixed(2));
}

function fmtPct(v){
    return isFinite(v) ? (Math.round(v * 100) / 100).toFixed(2) + '%' : '—';
}

window.dustSwapFmt = {flow: fmtFlow, dust: fmtDust, pct: fmtPct};

async function runSwapTx(label, send, onSealed){
    console.log(label + ' start');
    window.dispatch('loading-start');
    try{
        const txId = await send();
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if(fcl.tx.isSealed(transaction)){
                if(!window.transError(transaction)){
                    console.log(label + ' sealed', txId);
                    window.dispatch('loading-completed');
                    window.dispatchEvent(new CustomEvent('dust-swap-done', {detail: {txId: txId}}));
                    window.dispatch('dust-sale-changed', {txId: txId});
                    if(typeof window.refreshProfile === 'function'){
                        window.refreshProfile();
                    }
                    Alpine.store('dustSwap').refresh();
                    if(onSealed){
                        onSealed(txId);
                    }
                } else {
                    window.dispatch('transaction-error', {message: window.transError(transaction)});
                }
                unsub();
            }
        });
    } catch(error){
        window.dispatch('transaction-error', {message: error && error.message ? error.message : error});
    }
}

/** Pool state for the rate chip (shared, loaded once per page + after every swap). */
Alpine.store('dustSwap', {
    ok: false,
    loaded: false,
    rate: 0,
    reserveFlow: 0,
    reserveDust: 0,
    low: false,
    async refresh(){
        if(!CFG){
            return;
        }
        try{
            const q = await window.dustSwapQuote(true, false, 0, null);
            this.reserveFlow = q.reserveFlow;
            this.reserveDust = q.reserveDust;
            this.rate = dustPerFlow(q.reserveDust, q.reserveFlow);
            this.low = q.reserveFlow < (CFG.lowLiquidityFlow || 0);
            this.ok = this.rate > 0;
        } catch(e){
            console.warn('ĐUST swap: pool unavailable', e);
            this.ok = false;
        }
        this.loaded = true;
    },
    rateLabel(){
        return '1 FLOW ≈ Đ' + new Intl.NumberFormat('en-US', {maximumFractionDigits: this.rate >= 100 ? 0 : 2}).format(this.rate);
    },
});

if(CFG){
    document.addEventListener('alpine:initialized', () => Alpine.store('dustSwap').refresh());
}

/** Header chip: rate + low-liquidity state; hidden for Dapper sessions and when the pool can't be read. */
Alpine.data('dustRateChip', () => ({
    chipVisible(){
        void this.user; // re-evaluate when the signed-in user changes
        const s = Alpine.store('dustSwap');
        return !!CFG && s.ok && window.dustSwapSession() !== 'dapper';
    },
    chipLabel(){
        return Alpine.store('dustSwap').rateLabel();
    },
    chipLow(){
        return Alpine.store('dustSwap').low;
    },
    chipTitle(){
        const s = Alpine.store('dustSwap');
        return 'Increment ĐUST/FLOW pool: ' + fmtDust(s.reserveDust) + ' + ' + fmtFlow(s.reserveFlow) + (s.low ? ' (low liquidity: big trades move the price)' : '');
    },
}));

/**
 * "Pay with FLOW" for a ĐUST listing. Nested inside dustMarket (partials/dust-market): uses the parent's dustListing,
 * dustEnough(), canBuyDust(), dustSignedIn(), kind, itemId.
 */
Alpine.data('dustSwapPay', () => ({
    payQuote: null,
    payPlan: null,
    paySlippage: CFG ? CFG.slippageDefault : 1,
    payError: null,
    payBusy: false,
    payTimer: null,
    payFor: null,
    init(){
        const self = this;
        this.$watch('dustListing', () => self.loadPayQuote());
        this.$watch('profile', () => self.loadPayQuote());
        window.addEventListener('dust-swap-done', () => setTimeout(() => self.loadPayQuote(), 1500));
        this.loadPayQuote();
        this.payTimer = setInterval(() => { if(self.payShouldQuote()) self.loadPayQuote(); }, 30000);
    },
    destroy(){
        clearInterval(this.payTimer);
    },
    payShouldQuote(){
        void this.user;
        return !!CFG && !!this.dustListing && this.canBuyDust() && this.dustSignedIn() && !this.dustEnough() && window.dustSwapCanBuyDust();
    },
    async loadPayQuote(){
        if(!this.payShouldQuote()){
            this.payQuote = null;
            this.payPlan = null;
            return;
        }
        const listing = this.dustListing;
        try{
            const q = await window.dustSwapQuote(true, true, listing.price, window.user.addr);
            if(this.dustListing !== listing){
                return;
            }
            this.payQuote = q;
            this.payFor = listing.listingId;
            this.recomputePay();
        } catch(e){
            console.warn('ĐUST swap: no FLOW quote', e);
            this.payQuote = null;
            this.payPlan = null;
        }
    },
    recomputePay(){
        if(!this.payQuote || !this.dustListing){
            this.payPlan = null;
            return;
        }
        this.paySlippage = clampSlippage(this.paySlippage, CFG.slippageMax);
        const q = this.payQuote;
        this.payPlan = payPlan({
            quoteIn: q.amountIn, priceOut: this.dustListing.price, reserveIn: q.reserveIn, reserveOut: q.reserveOut,
            available: q.flowAvailable, slippagePct: this.paySlippage, warnPct: CFG.impactWarn, blockPct: CFG.impactBlock,
        });
    },
    /** Show the option only when ĐUST is short and the account's spendable FLOW covers the swap. */
    payVisible(){
        return this.payShouldQuote() && !!this.payPlan && this.payPlan.enough;
    },
    payLevel(){
        return this.payPlan ? this.payPlan.level : 'block';
    },
    payButtonLabel(){
        return 'Pay ≈ ' + fmtFlow(this.payQuote ? this.payQuote.amountIn : 0) + ' instead';
    },
    payNote(){
        if(!this.payPlan) return '';
        return 'Includes the ' + ((this.payQuote.feeBps || 30) / 100) + '% swap fee, max ' + fmtFlow(this.payPlan.maxIn) + ' (slippage ' + this.paySlippage + '%). Unused FLOW is refunded.';
    },
    payImpactLabel(){
        return this.payPlan ? 'Price impact ' + fmtPct(this.payPlan.impact) : '';
    },
    async payWithFlow(){
        if(!this.payVisible() || this.payLevel() === 'block' || this.payBusy){
            return;
        }
        const listing = this.dustListing;
        this.payBusy = true;
        try{
            // fresh quote right before signing
            const q = await window.dustSwapQuote(true, true, listing.price, window.user.addr);
            this.payQuote = q;
            this.recomputePay();
            if(!this.payPlan || !this.payPlan.enough || this.payPlan.level === 'block'){
                return;
            }
            const maxIn = ufix(this.payPlan.maxIn, 'down');
            const kind = this.kind, itemId = this.itemId;
            await runSwapTx('ĐUST purchase paid with FLOW',
                () => dustSwapBuyDustListingTx(listing.storefrontAddress, listing.listingId, ufix(listing.price), maxIn, deadline()),
                () => {
                    const seg = {flovatar: 'flovatars', flobit: 'components', flobot: 'flobots', collectible: 'collectibles', accessory: 'dust/accessories'}[kind];
                    if(window.user && window.user.addr && seg && window.itemUrlAfterTx){
                        window.location = window.itemUrlAfterTx(seg, itemId, window.user.addr);
                    }
                });
        } catch(e){
            console.warn('ĐUST swap: pay with FLOW failed', e);
            window.dispatch('transaction-error', {message: 'The FLOW price could not be quoted. Please try again.'});
        } finally {
            this.payBusy = false;
        }
    },
}));

/**
 * "Pay with ĐUST" for a FLOW listing on FlovatarMarketplace (Flovatar / Flobit pages). Nested in the page component:
 * uses isSale(), isOwned(), isDapperSale(), sale (id, price), address (seller). Self-custody Flow Wallet only.
 */
Alpine.data('dustSwapPayDust', (kind) => ({
    pdKind: kind,
    pdQuote: null,
    pdSlippage: CFG ? CFG.slippageDefault : 1,
    pdBusy: false,
    init(){
        const self = this;
        this.$watch('sale', () => self.loadPdQuote());
        this.$watch('user', () => self.loadPdQuote());
        window.addEventListener('dust-swap-done', () => setTimeout(() => self.loadPdQuote(), 1500));
        this.loadPdQuote();
    },
    pdPrice(){
        return this.sale ? num(this.sale.price) : 0;
    },
    pdShouldQuote(){
        void this.user;
        return !!CFG && window.dustSwapCanSellDust() && typeof this.isSale === 'function' && !!this.isSale()
            && !this.isOwned() && !(typeof this.isDapperSale === 'function' && this.isDapperSale()) && this.pdPrice() > 0;
    },
    async loadPdQuote(){
        if(!this.pdShouldQuote()){
            this.pdQuote = null;
            return;
        }
        try{
            this.pdQuote = await window.dustSwapQuote(false, true, this.pdPrice(), window.user.addr);
        } catch(e){
            console.warn('ĐUST swap: no ĐUST quote', e);
            this.pdQuote = null;
        }
    },
    pdMaxIn(){
        if(!this.pdQuote) return 0;
        this.pdSlippage = clampSlippage(this.pdSlippage, CFG.slippageMax);
        return Math.min(maxInWithSlippage(this.pdQuote.amountIn, this.pdSlippage), this.pdQuote.dustBalance);
    },
    pdLevel(){
        return this.pdQuote ? impactLevel(this.pdQuote.impact, CFG.impactWarn, CFG.impactBlock) : 'block';
    },
    /** Offered when the spendable FLOW is short of the price and the ĐUST balance covers the swap. */
    pdVisible(){
        const q = this.pdQuote;
        return this.pdShouldQuote() && !!q && q.flowAvailable - 0.001 < this.pdPrice() && q.dustBalance >= q.amountIn;
    },
    pdButtonLabel(){
        return 'Pay ≈ ' + fmtDust(this.pdQuote ? this.pdQuote.amountIn : 0) + ' instead';
    },
    pdNote(){
        if(!this.pdQuote) return '';
        return 'Not enough FLOW: swap ĐUST for the exact price (includes the ' + ((this.pdQuote.feeBps || 30) / 100) + '% swap fee, max ' + fmtDust(this.pdMaxIn()) + ', slippage ' + this.pdSlippage + '%). Unused ĐUST is refunded.';
    },
    pdImpactLabel(){
        return this.pdQuote ? 'Price impact ' + fmtPct(this.pdQuote.impact) : '';
    },
    async payWithDust(){
        if(!this.pdVisible() || this.pdLevel() === 'block' || this.pdBusy){
            return;
        }
        this.pdBusy = true;
        try{
            this.pdQuote = await window.dustSwapQuote(false, true, this.pdPrice(), window.user.addr);
            if(this.pdLevel() === 'block' || this.pdQuote.dustBalance < this.pdQuote.amountIn){
                return;
            }
            const kind = this.pdKind, tokenId = this.sale.id, seller = this.address, price = this.pdPrice();
            const maxIn = ufix(this.pdMaxIn(), 'down');
            await runSwapTx('FLOW purchase paid with ĐUST',
                () => dustSwapBuyFlowListingTx(kind, seller, tokenId, ufix(price), maxIn, deadline()),
                () => {
                    const seg = kind === 'flovatar' ? 'flovatars' : 'components';
                    if(window.user && window.user.addr && window.itemUrlAfterTx){
                        window.location = window.itemUrlAfterTx(seg, tokenId, window.user.addr);
                    }
                });
        } catch(e){
            console.warn('ĐUST swap: pay with ĐUST failed', e);
            window.dispatch('transaction-error', {message: 'The ĐUST price could not be quoted. Please try again.'});
        } finally {
            this.pdBusy = false;
        }
    },
}));

/** Quick-swap widget (/dust). */
Alpine.data('dustSwapWidget', () => ({
    wFlowToDust: true,
    wAmount: '',
    wQuote: null,
    wError: '',
    wSlippage: CFG ? CFG.slippageDefault : 1,
    wTimer: null,
    wBusy: false,
    wBalances: null,
    init(){
        const self = this;
        this.$watch('user', () => { self.wLoadBalances(); self.wQueue(); });
        window.addEventListener('dust-swap-done', () => { self.wAmount = ''; self.wQuote = null; setTimeout(() => self.wLoadBalances(), 1500); });
        this.wLoadBalances();
    },
    wSession(){
        void this.user; // reactive: appData.user
        return window.dustSwapSession();
    },
    wVisible(){
        return !!CFG && this.wSession() !== 'dapper';
    },
    wCanSell(){
        void this.user;
        return window.dustSwapCanSellDust();
    },
    wAllowed(){
        void this.user;
        return this.wFlowToDust ? window.dustSwapCanBuyDust() : window.dustSwapCanSellDust();
    },
    wFlip(){
        if(!this.wCanSell() && this.wFlowToDust){
            return;
        }
        this.wFlowToDust = !this.wFlowToDust;
        this.wAmount = '';
        this.wQuote = null;
        this.wError = '';
    },
    wInLabel(){ return this.wFlowToDust ? 'FLOW' : 'ĐUST'; },
    wOutLabel(){ return this.wFlowToDust ? 'ĐUST' : 'FLOW'; },
    async wLoadBalances(){
        if(!CFG || !window.user || !window.user.addr){
            this.wBalances = null;
            return;
        }
        try{
            const q = await window.dustSwapQuote(true, false, 0, window.user.addr);
            this.wBalances = {flow: q.flowAvailable, dust: q.dustBalance};
        } catch(e){
            this.wBalances = null;
        }
    },
    wBalanceIn(){
        if(!this.wBalances) return null;
        return this.wFlowToDust ? Math.max(0, this.wBalances.flow - 0.001) : this.wBalances.dust;
    },
    wMax(){
        const b = this.wBalanceIn();
        if(b !== null){
            this.wAmount = ufix(b, 'down').replace(/\.?0+$/, '');
            this.wQueue();
        }
    },
    wQueue(){
        const self = this;
        clearTimeout(this.wTimer);
        this.wError = '';
        this.wTimer = setTimeout(() => self.wLoadQuote(), 350);
    },
    async wLoadQuote(){
        const amount = num(this.wAmount);
        if(!(amount > 0)){
            this.wQuote = null;
            return;
        }
        try{
            const q = await window.dustSwapQuote(this.wFlowToDust, false, amount, window.user && window.user.addr);
            if(num(this.wAmount) === amount){
                this.wQuote = q;
                this.wError = '';
            }
        } catch(e){
            console.warn('ĐUST swap: quote failed', e);
            this.wQuote = null;
            this.wError = 'No quote for this amount right now.';
        }
    },
    wOutText(){
        if(!this.wQuote) return '';
        return this.wFlowToDust ? fmtDust(this.wQuote.amountOut) : fmtFlow(this.wQuote.amountOut);
    },
    wMinOut(){
        if(!this.wQuote) return 0;
        this.wSlippage = clampSlippage(this.wSlippage, CFG.slippageMax);
        return minOutWithSlippage(this.wQuote.amountOut, this.wSlippage);
    },
    wMinText(){
        return this.wFlowToDust ? fmtDust(this.wMinOut()) : fmtFlow(this.wMinOut());
    },
    wLevel(){
        return this.wQuote ? impactLevel(this.wQuote.impact, CFG.impactWarn, CFG.impactBlock) : 'ok';
    },
    wShort(){
        const b = this.wBalanceIn();
        return b !== null && num(this.wAmount) > b;
    },
    wCanSwap(){
        return this.wAllowed() && !!this.wQuote && this.wLevel() !== 'block' && !this.wShort() && !this.wBusy && this.wMinOut() > 0;
    },
    wButtonLabel(){
        if(this.wSession() === 'none') return 'Sign in to swap';
        if(!this.wAllowed()) return 'Not available for this account';
        if(this.wShort()) return 'Not enough ' + this.wInLabel();
        if(this.wQuote && this.wLevel() === 'block') return 'Price impact too high';
        return 'Swap';
    },
    async wSwap(){
        if(!this.wCanSwap()){
            return;
        }
        this.wBusy = true;
        try{
            const amount = num(this.wAmount);
            const q = await window.dustSwapQuote(this.wFlowToDust, false, amount, window.user.addr);
            this.wQuote = q;
            if(this.wLevel() === 'block'){
                return;
            }
            const minOut = ufix(this.wMinOut(), 'down');
            const amountIn = ufix(amount, 'down');
            if(this.wFlowToDust){
                await runSwapTx('Swap FLOW → ĐUST', () => dustSwapFlowToDustTx(amountIn, minOut, deadline()));
            } else {
                await runSwapTx('Swap ĐUST → FLOW', () => dustSwapDustToFlowTx(amountIn, minOut, deadline()));
            }
        } catch(e){
            console.warn('ĐUST swap failed', e);
            window.dispatch('transaction-error', {message: 'The swap could not be quoted. Please try again.'});
        } finally {
            this.wBusy = false;
        }
    },
}));
