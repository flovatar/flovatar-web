/**
 * Flow EVM (EVM-A, 2026-11): bridge Flovatar-family NFTs between Cadence and Flow EVM from item pages, show items a
 * profile holds on Flow EVM, and a read-only EVM wallet (MetaMask) gallery.
 *
 * - Bridging uses the official Flow VM bridge through the user's Cadence-Owned Account (COA) = their Flow EVM address
 *   in Flow Wallet. Works with Flow Wallet (and other non-custodial Flow wallets); Dapper cannot sign these txs.
 * - EVM reads go straight to the public Flow EVM JSON-RPC (ERC-721 balanceOf / tokenOfOwnerByIndex / ownerOf);
 *   names and images come from this site's tokenURI JSON (/flovatars/json/{id}, ...).
 * - Config comes from window.EVM_CFG (printed by resources/views/partials/evm-config.blade.php only when
 *   config('evm.ui') is on); without it nothing here runs.
 */
import * as fcl from "@onflow/fcl"
import {evmBridgeToTx} from "../flow/evm-bridge-to.tx"
import {evmBridgeFromTx} from "../flow/evm-bridge-from.tx"
import {evmBridgeInfoScript} from "../flow/evm-bridge-info.script"

const NET = {
    testnet: {evm: '0x8c5303eaa26202d6', bridge: '0xdfc20aee650fcbdf'},
    mainnet: {evm: '0xe467b9dd11fa00df', bridge: '0x1e4aa0b87d10b141'},
};
const net = process.env.MIX_FLOW_NETWORK === 'mainnet' ? 'mainnet' : 'testnet';
fcl.config()
    .put('0xEVM', process.env.MIX_EVM_CONTRACT || NET[net].evm)
    .put('0xFlowEVMBridge', process.env.MIX_EVM_BRIDGE || NET[net].bridge);

const cfg = () => window.EVM_CFG || null;
const hex = (a) => (a ? (a.startsWith('0x') ? a : '0x' + a).toLowerCase() : null);
const pad = (n) => BigInt(n).toString(16).padStart(64, '0');
const addrArg = (a) => hex(a).slice(2).padStart(64, '0');

async function rpc(method, params) {
    const r = await fetch(cfg().rpc, {method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({jsonrpc: '2.0', id: 1, method, params})});
    const j = await r.json();
    if (j.error) { throw new Error(j.error.message || 'EVM RPC error'); }
    return j.result;
}
const call = (to, data) => rpc('eth_call', [{to, data}, 'latest']);
const num = (h) => (h && h !== '0x' ? BigInt(h) : 0n);

async function erc721Holdings(contract, owner) {
    const bal = Number(num(await call(contract, '0x70a08231' + addrArg(owner))));
    const ids = [];
    for (let i = 0; i < Math.min(bal, 200); i++) {
        ids.push(num(await call(contract, '0x2f745c59' + addrArg(owner) + pad(i))).toString());
    }
    return ids;
}
async function erc721OwnerOf(contract, id) {
    try {
        const r = await call(contract, '0x6352211e' + pad(id));
        return '0x' + r.slice(-40);
    } catch (e) { return null; }
}

let infoCache = {};
async function bridgeInfo(owner) {
    const key = owner || '-';
    if (!infoCache[key]) {
        const ids = Object.values(cfg().collections).map((c) => c.type);
        infoCache[key] = evmBridgeInfoScript(owner || null, ids).catch((e) => { delete infoCache[key]; throw e; });
    }
    return infoCache[key];
}
function contractsFor(info, type) {
    const t = info && info.types && info.types[type];
    if (!t) { return []; }
    return [t.associated, t.legacy].filter(Boolean).map(hex).filter((v, i, a) => a.indexOf(v) === i);
}
async function itemMeta(slug, id) {
    const c = cfg().collections[slug];
    if (!c || !c.json) { return {name: c ? c.name + ' #' + id : '#' + id, image: null}; }
    try {
        const r = await fetch('/' + c.json + '/' + id);
        return r.ok ? await r.json() : {name: c.name + ' #' + id, image: null};
    } catch (e) { return {name: c.name + ' #' + id, image: null}; }
}
// all Flovatar-family ERC-721s held by an EVM address -> [{slug, id, contract, name, image, url}]
async function holdingsOf(evmAddress) {
    const info = await bridgeInfo(null);
    const out = [];
    for (const [slug, c] of Object.entries(cfg().collections)) {
        for (const contract of contractsFor(info, c.type)) {
            let ids = [];
            try { ids = await erc721Holdings(contract, evmAddress); } catch (e) { console.warn('evm holdings', slug, e); }
            for (const id of ids) {
                const legacy = info.types[c.type].legacy && hex(info.types[c.type].legacy) === contract;
                out.push({slug, id, contract, legacy, name: c.name + ' #' + id, image: null, url: c.path ? '/' + c.path + '/' + id : (c.site || null)});
            }
        }
    }
    await Promise.all(out.map(async (it) => {
        const m = await itemMeta(it.slug, it.id);
        it.name = m.name || it.name; it.image = m.image || null;
    }));
    return out;
}

async function runTx(label, fn, onSealed) {
    window.dispatch('loading-start');
    try {
        const txId = await fn();
        window.dispatch('waiting-seal');
        const unsub = await fcl.tx(txId).subscribe((transaction) => {
            if (fcl.tx.isSealed(transaction)) {
                unsub();
                const err = window.transError ? window.transError(transaction) : transaction.errorMessage;
                if (!err) {
                    infoCache = {};
                    window.dispatch('loading-completed');
                    window.dispatch('evm-bridged', {label, txId});
                    if (onSealed) { onSealed(txId); }
                } else {
                    window.dispatch('transaction-error', {message: err});
                }
            }
        });
        return txId;
    } catch (error) {
        window.dispatch('transaction-error', {message: (error && error.message) || error});
        return null;
    }
}

window.evmApi = {rpc, erc721Holdings, erc721OwnerOf, bridgeInfo, holdingsOf, itemMeta, contractsFor, hex,
    bridgeTo: (slug, ids, done) => runTx('to-evm', () => evmBridgeToTx(cfg().collections[slug].type, ids), done),
    bridgeFrom: (slug, ids, done) => runTx('from-evm', () => evmBridgeFromTx(cfg().collections[slug].type, ids), done)};

const fmt = (v) => {
    const n = parseFloat(v);
    return isNaN(n) ? '?' : (n < 0.01 ? n.toFixed(5) : n.toFixed(3)).replace(/0+$/, '').replace(/\.$/, '');
};
const short = (a) => (a ? hex(a).slice(0, 6) + '…' + hex(a).slice(-4) : '');

document.addEventListener('alpine:init', () => {
    // Item page panel: partials/evm-bridge.blade.php
    window.Alpine.data('evmBridge', (slug, id) => ({
        slug, id: '' + id, enabled: !!cfg(), loading: true, error: null, user: null, info: null,
        coa: null, ownedInCadence: false, evmOwner: null, evmContract: null, isLegacy: false, confirm: null,
        explorer: cfg() ? cfg().explorer : '',
        async init() {
            if (!this.enabled) { return; }
            this.user = (window.user && window.user.addr) ? window.user : null;
            window.addEventListener('user-loaded', (e) => { this.user = (e.detail && e.detail.user) || window.user; this.refresh(); });
            window.addEventListener('evm-bridged', () => this.refresh());
            await this.refresh();
        },
        type() { return cfg().collections[this.slug].type; },
        async refresh() {
            this.loading = true; this.error = null;
            try {
                const owner = this.user && this.user.addr ? this.user.addr : null;
                infoCache = {};
                this.info = await bridgeInfo(owner);
                this.coa = this.info.coa ? hex(this.info.coa) : null;
                const t = this.info.types[this.type()] || {};
                this.ownedInCadence = !!(t.owned || []).map(String).includes(this.id);
                this.evmOwner = null; this.evmContract = null; this.isLegacy = false;
                const bridgeCoa = hex(this.info.bridgeCOA);
                for (const c of contractsFor(this.info, this.type())) {
                    const o = await erc721OwnerOf(c, this.id);
                    if (o && !/^0x0{40}$/.test(o) && o !== bridgeCoa) {
                        this.evmOwner = o; this.evmContract = c; this.isLegacy = !!(t.legacy && hex(t.legacy) === c);
                        break;
                    }
                }
            } catch (e) {
                console.warn('evm refresh', e); this.error = 'Could not read the bridge state right now.';
            }
            this.loading = false;
        },
        t() { return (this.info && this.info.types[this.type()]) || {}; },
        dapper() { return window.currentWallet === 'dapper'; },
        canBridgeTo() { return !!this.user && this.ownedInCadence && !this.dapper() && !this.t().paused; },
        canBridgeBack() { return !!this.user && !!this.coa && this.evmOwner === this.coa && !this.dapper(); },
        feeText() {
            if (!this.info) { return ''; }
            const f = this.info.fees;
            let s = '≈ ' + fmt(f.estimate) + ' FLOW bridge fee (capped at ' + fmt(f.max) + ')';
            if (!this.t().onboarded) { s += ' + ' + fmt(f.onboard) + ' FLOW one-time onboarding of this collection'; }
            return s;
        },
        destText() { return this.coa ? 'your Flow EVM address ' + short(this.coa) : 'a new Flow EVM address (COA) created in your Flow account by this transaction'; },
        short, ask(kind) { this.confirm = kind; },
        go() {
            const k = this.confirm; this.confirm = null;
            if (k === 'to') { window.evmApi.bridgeTo(this.slug, [this.id], () => this.refresh()); }
            if (k === 'from') { window.evmApi.bridgeFrom(this.slug, [this.id], () => this.refresh()); }
        },
    }));

    // Profile section (collection page) and the /evm gallery: items held on Flow EVM by an address
    window.Alpine.data('evmHoldings', (flowAddress, evmAddress) => ({
        enabled: !!cfg(), flowAddress: flowAddress || (window.HEADLESS_ROUTE && window.HEADLESS_ROUTE.address) || null, evmAddress: evmAddress ? hex(evmAddress) : null,
        items: [], loading: false, error: null, input: '', mm: null, chainOk: null, explorer: cfg() ? cfg().explorer : '',
        mine: false, user: null,
        async init() {
            if (!this.enabled) { return; }
            this.user = (window.user && window.user.addr) ? window.user : null;
            window.addEventListener('user-loaded', (e) => { this.user = (e.detail && e.detail.user) || window.user; this.checkMine(); });
            window.addEventListener('evm-bridged', () => this.load());
            if (this.flowAddress && !this.evmAddress) {
                try { const info = await bridgeInfo(this.flowAddress); this.evmAddress = info.coa ? hex(info.coa) : null; } catch (e) { this.error = 'Could not read the Flow EVM address.'; }
            }
            this.checkMine();
            if (this.evmAddress) { await this.load(); }
        },
        checkMine() { this.mine = !!(this.user && this.flowAddress && this.user.addr === this.flowAddress); },
        async load() {
            if (!this.evmAddress) { return; }
            this.loading = true; this.error = null;
            try { this.items = await holdingsOf(this.evmAddress); } catch (e) { this.error = 'Could not read Flow EVM right now.'; }
            this.loading = false;
        },
        async lookup() {
            const v = (this.input || '').trim();
            if (/^0x[0-9a-fA-F]{40}$/.test(v)) { this.evmAddress = hex(v); this.flowAddress = null; await this.load(); return; }
            if (/^0x[0-9a-fA-F]{16}$/.test(v)) {
                this.flowAddress = v.toLowerCase();
                const info = await bridgeInfo(this.flowAddress);
                this.evmAddress = info.coa ? hex(info.coa) : null;
                if (!this.evmAddress) { this.items = []; this.error = 'This Flow account has no Flow EVM address yet.'; return; }
                await this.load(); return;
            }
            this.error = 'Enter a Flow EVM address (0x + 40 hex) or a Flow address (0x + 16 hex).';
        },
        hasMetaMask() { return typeof window.ethereum !== 'undefined'; },
        async connectMetaMask() {
            if (!this.hasMetaMask()) { this.error = 'No EVM wallet found in this browser. Install MetaMask (or open this page in your wallet browser).'; return; }
            try {
                const acc = await window.ethereum.request({method: 'eth_requestAccounts'});
                this.mm = acc && acc[0] ? hex(acc[0]) : null;
                const chain = await window.ethereum.request({method: 'eth_chainId'});
                this.chainOk = parseInt(chain, 16) === cfg().chainId;
                if (this.mm) { this.evmAddress = this.mm; this.flowAddress = null; await this.load(); }
            } catch (e) { this.error = (e && e.message) || 'Wallet connection refused.'; }
        },
        async addNetwork() {
            try {
                await window.ethereum.request({method: 'wallet_addEthereumChain', params: [{
                    chainId: '0x' + cfg().chainId.toString(16), chainName: cfg().chainName,
                    nativeCurrency: {name: 'FLOW', symbol: 'FLOW', decimals: 18}, rpcUrls: [cfg().rpc], blockExplorerUrls: [cfg().explorer]}]});
                this.chainOk = true;
            } catch (e) { this.error = (e && e.message) || 'Could not add the network.'; }
        },
        async watch(it) {
            try { await window.ethereum.request({method: 'wallet_watchAsset', params: {type: 'ERC721', options: {address: it.contract, tokenId: '' + it.id}}}); }
            catch (e) { this.error = (e && e.message) || 'The wallet did not add the NFT.'; }
        },
        bridgeBack(it) { window.evmApi.bridgeFrom(it.slug, [it.id], () => this.load()); },
        short,
    }));
});
