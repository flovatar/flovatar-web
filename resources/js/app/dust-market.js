/**
 * ĐUST marketplace (KB notes/dust-marketplace.md): list / buy / cancel / clean up any Flovatar item for ĐUST on the
 * standard NFTStorefrontV2. Inert unless the layout printed window.DUST_MARKET (DUST_MARKET_ENABLED, testnet only).
 * Chain-first: listings and balances are read with Cadence scripts in the browser; the server index only feeds the
 * marketplace lists. Transactions go through ./flow/fcl-tx (wallets: fcl.mutate, passkey users: sponsored allowlist).
 * Dapper sessions get no ĐUST features at all (Luca 2026-11-25): every entry point refuses them.
 */
import Alpine from "alpinejs";
import * as fcl from "@onflow/fcl";
import {dustSaleCreateTx} from "../flow/dust-sale-create.tx";
import {dustSaleBuyTx} from "../flow/dust-sale-buy.tx";
import {dustSaleCancelTx} from "../flow/dust-sale-cancel.tx";
import {dustSaleCleanupTx} from "../flow/dust-sale-cleanup.tx";
import {getDustListingsScript} from "../flow/get-dust-listings.script";
import {getDustSalePreviewScript} from "../flow/get-dust-sale-preview.script";

const SEGMENTS = {flovatar: 'flovatars', flobit: 'components', flobot: 'flobots', collectible: 'collectibles', accessory: 'dust/accessories'};
const LABELS = {flovatar: 'Flovatar', flobit: 'Flobit', flobot: 'Flobot', collectible: 'Collectible', accessory: 'Accessory'};

window.altCurrencyOn = function(){
    return !!(window.DUST_MARKET && window.DUST_MARKET.enabled);
};

window.isDapperSession = function(){
    return window.currentWallet === 'dapper';
};

/** 'Đ 1,234' / 'Đ 12.5' */
window.formatDust = function(amount){
    return 'Đ ' + new Intl.NumberFormat('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2}).format(parseFloat(amount || 0));
};

window.isDustRow = function(row){
    return !!(row && row.currency === 'DUST');
};

/** Price of a marketplace/transaction/showroom row in its currency; flowSuffix keeps each list's FLOW format. */
window.formatMarketPrice = function(row, flowSuffix){
    if(window.isDustRow(row)){
        return window.formatDust(row.price);
    }
    return window.formatPrice(row ? row.price : 0) + (flowSuffix === undefined ? ' FLOW' : flowSuffix);
};

function normalize(l){
    return {
        storefrontAddress: l.storefrontAddress,
        listingId: '' + l.listingId,
        kind: l.kind,
        nftID: parseInt(l.nftID, 10),
        price: parseFloat(l.price),
        expiry: parseInt(l.expiry, 10),
        purchased: !!l.purchased,
        holds: !!l.holds,
        expired: !!l.expired,
        valid: !!l.valid,
        cuts: (l.cuts || []).map(c => ({address: c.address, amount: parseFloat(c.amount)})),
        url: '/' + SEGMENTS[l.kind] + '/' + l.nftID + (l.kind === 'flobit' ? '/' + l.storefrontAddress : ''),
        label: (LABELS[l.kind] || l.kind) + ' #' + l.nftID,
    };
}

/** ĐUST listings of a wallet (kind+id = one item, kind '' = all). Never throws (empty list on errors). */
window.getDustListings = async function(address, kind, id){
    if(!address || !window.altCurrencyOn()){
        return [];
    }
    try{
        const rows = await getDustListingsScript(address, kind || '', id || 0);
        return (rows || []).map(normalize);
    } catch(e){
        console.warn('ĐUST listings unavailable', e);
        return [];
    }
};

function refuseDapper(){
    if(window.isDapperSession()){
        window.dispatch('transaction-error', {message: 'ĐUST marketplace features are not available for Dapper wallets.'});
        return true;
    }
    return false;
}

async function runDustTx(label, send, onSealed){
    console.log(label + ' start');
    window.dispatch('loading-start');
    try{
        const txId = await send();
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe(transaction => {
            if(fcl.tx.isSealed(transaction)){
                if(!window.transError(transaction)){
                    console.log(label + ' sealed');
                    window.dispatch('loading-completed');
                    window.dispatch('dust-sale-changed', {txId: txId});
                    if(typeof window.refreshProfile === 'function'){
                        window.refreshProfile();
                    }
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
        window.dispatch('transaction-error', {message: error});
    }
}

window.createDustSale = async function(kind, itemId, price){
    if(refuseDapper()) return;
    const ttl = (window.DUST_MARKET && window.DUST_MARKET.listingTtl) || 31536000;
    const expiry = Math.floor(Date.now() / 1000) + ttl;
    return runDustTx('ĐUST listing', () => dustSaleCreateTx(kind, itemId, price, expiry));
};

window.cancelDustSale = async function(listingId){
    if(refuseDapper()) return;
    return runDustTx('ĐUST listing cancel', () => dustSaleCancelTx(listingId));
};

window.buyDustSale = async function(seller, listingId, price, kind, itemId){
    if(refuseDapper()) return;
    return runDustTx('ĐUST purchase', () => dustSaleBuyTx(seller, listingId, price), () => {
        if(window.user && window.user.addr && SEGMENTS[kind]){
            window.location = window.itemUrlAfterTx(SEGMENTS[kind], itemId, window.user.addr);
        }
    });
};

window.cleanupDustSale = async function(seller, listingId){
    if(refuseDapper()) return;
    return runDustTx('ĐUST listing clean-up', () => dustSaleCleanupTx(seller, listingId));
};

Alpine.store('dust', {live: null});

/**
 * Item pages (partials/dust-market): nested inside the page component, so `address` (the confirmed owner), `user`,
 * `profile`, `isSale()` and `isDapperWallet()` come from the parent scopes.
 */
Alpine.data('dustMarket', (kind, itemId) => ({
    kind: kind,
    itemId: itemId,
    dustListing: null,
    dustDead: null,
    dustLoaded: false,
    dustPrice: null,
    dustPreview: null,
    dustShowForm: false,
    previewTimer: null,
    init(){
        const self = this;
        this.loadDust();
        this.$watch('address', () => self.loadDust());
        window.addEventListener('dust-sale-changed', () => setTimeout(() => self.loadDust(), 1500));
    },
    dustOwner(){
        return this.address || null;
    },
    async loadDust(){
        const owner = this.dustOwner();
        if(!owner){
            return;
        }
        const rows = await window.getDustListings(owner, this.kind, this.itemId);
        this.dustListing = rows.find(l => l.valid) || null;
        this.dustDead = rows.find(l => !l.valid) || null;
        Alpine.store('dust').live = this.dustListing;
        this.dustLoaded = true;
    },
    dustDapper(){
        return typeof this.isDapperWallet === 'function' ? this.isDapperWallet() : window.isDapperSession();
    },
    dustMine(){
        return !!(this.user && this.user.addr && this.dustOwner() && this.user.addr.toLowerCase() === this.dustOwner().toLowerCase());
    },
    dustSignedIn(){
        return !!(this.user && this.user.addr);
    },
    flowSaleLive(){
        return typeof this.isSale === 'function' ? !!this.isSale() : false;
    },
    dustBalance(){
        return this.profile && this.profile.dustBalance ? parseFloat(this.profile.dustBalance) : 0;
    },
    dustEnough(){
        return !!this.dustListing && this.dustBalance() >= this.dustListing.price;
    },
    canListDust(){
        return this.dustLoaded && this.dustMine() && !this.dustDapper() && !this.dustListing && !this.flowSaleLive();
    },
    canBuyDust(){
        return !!this.dustListing && !this.dustMine() && !this.dustDapper();
    },
    dustFmt(v){
        return window.formatDust(v);
    },
    queuePreview(){
        const self = this;
        clearTimeout(this.previewTimer);
        this.dustPreview = null;
        const price = parseFloat(this.dustPrice);
        if(!(price > 0)){
            return;
        }
        this.previewTimer = setTimeout(async () => {
            try{
                const p = await getDustSalePreviewScript(self.dustOwner(), self.kind, self.itemId, price);
                if(p && parseFloat(self.dustPrice) === price){
                    self.dustPreview = {fee: parseFloat(p.fee), feeRate: parseFloat(p.feeRate), creator: parseFloat(p.creator), seller: parseFloat(p.seller)};
                }
            } catch(e){
                console.warn('ĐUST preview unavailable', e);
            }
        }, 400);
    },
    listDust(){
        const price = parseFloat(this.dustPrice);
        if(this.canListDust() && price > 0 && price <= 999999){
            window.createDustSale(this.kind, this.itemId, price);
        }
    },
    cancelDust(){
        if(this.dustListing && this.dustMine() && !this.dustDapper()){
            window.cancelDustSale(this.dustListing.listingId);
        }
    },
    buyDust(){
        if(this.canBuyDust() && this.dustEnough()){
            window.buyDustSale(this.dustListing.storefrontAddress, this.dustListing.listingId, this.dustListing.price, this.kind, this.itemId);
        }
    },
    cleanupDust(){
        if(this.dustDead && this.dustSignedIn() && !this.dustDapper()){
            window.cleanupDustSale(this.dustDead.storefrontAddress, this.dustDead.listingId);
        }
    },
}));

/** Collection page: every ĐUST listing of the wallet (chain), with Cancel for the owner and Clean up for dead ones. */
Alpine.data('dustListings', (address) => ({
    dustRows: [],
    dustLoaded: false,
    init(){
        const self = this;
        this.load();
        if(typeof this.address !== 'undefined'){
            this.$watch('address', () => self.load());
        }
        window.addEventListener('dust-sale-changed', () => setTimeout(() => self.load(), 1500));
    },
    wallet(){
        return (typeof this.address === 'string' && this.address) ? this.address : address;
    },
    async load(){
        if(!this.wallet()){
            return;
        }
        this.dustRows = await window.getDustListings(this.wallet(), '', 0);
        this.dustLoaded = true;
    },
    mine(){
        return !!(this.user && this.user.addr && this.wallet() && this.user.addr.toLowerCase() === ('' + this.wallet()).toLowerCase());
    },
    dapper(){
        return typeof this.isDapperWallet === 'function' ? this.isDapperWallet() : window.isDapperSession();
    },
    fmt(v){
        return window.formatDust(v);
    },
    cancel(row){
        if(this.mine() && !this.dapper()){
            window.cancelDustSale(row.listingId);
        }
    },
    cleanup(row){
        if(this.user && this.user.addr && !this.dapper()){
            window.cleanupDustSale(row.storefrontAddress, row.listingId);
        }
    },
}));
