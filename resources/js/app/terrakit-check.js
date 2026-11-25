/**
 * TerraKit wallet check (/terrakit/check, TERRAKIT_CHECK_ENABLED) — KB notes/terrakit-check.md.
 *
 * Chain-first and read-only: one FCL script (resources/js/terrakit/check.cdc.mjs) against the site's access node reads
 * the account keys, the Dapper receiver, TerraKit vouchers (category × rarity) and FlovatarPack ids; the classifier
 * (resources/js/terrakit/classify.mjs) names the wallet. No server calls, no writes, nothing stored.
 * Inert unless the page printed window.TERRAKIT_CHECK.
 */
import Alpine from 'alpinejs';
import * as fcl from '@onflow/fcl';
import {TERRAKIT_CHECK_CADENCE} from '../terrakit/check.cdc.mjs';
import {classifyWallet, inRanges, mergeVouchers, normalizeAddress, scriptErrorKind, summarizeVouchers} from '../terrakit/classify.mjs';

const CFG = window.TERRAKIT_CHECK || null;
const SLICE = 4000; // Flobits per script call (8,000 measured fine on mainnet; slices keep big collections safe)
const NETWORK = process.env.MIX_FLOW_NETWORK || 'mainnet';
const CONTRACT = process.env.MIX_CONTRACT_FLOW;

const WALLET_LABELS = {
    flowwallet: 'Flow Wallet',
    dapper: 'Dapper Wallet',
    blocto: 'Blocto-style keys',
    keyless: 'No active keys',
    other: 'Other wallet',
    passkey: 'Flovatar passkey',
};

export async function readTerrakit(address) {
    let start = 0;
    let acc = null;
    for (let guard = 0; guard < 100; guard++) {
        const r = await fcl.query({
            cadence: TERRAKIT_CHECK_CADENCE,
            args: (arg, t) => [
                arg(address, t.Address),
                arg(CONTRACT, t.Address),
                arg(CFG.categories, t.Array(t.String)),
                arg(String(start), t.Int),
                arg(String(SLICE), t.Int),
            ],
        });
        if (!acc) { acc = r; } else { acc.vouchers = mergeVouchers(acc.vouchers, r.vouchers); }
        start += SLICE;
        if (start >= Number(r.componentTotal || 0)) { break; }
    }
    return acc;
}

if (CFG) {
    Alpine.data('terrakitCheck', () => ({
        input: '',
        address: null,
        status: 'idle', // idle | loading | done | error
        errorKind: '',
        wallet: {class: ''},
        summary: {rows: [], total: 0, listed: 0},
        packCount: 0,
        connected: null,
        connectedWallet: '',
        pendingConnect: false,
        seq: 0,

        init() {
            window.addEventListener('user-loaded', (e) => this.onUser(e.detail && e.detail.user));
            window.addEventListener('logged-out', () => { this.connected = null; this.connectedWallet = ''; });
            if (window.user && window.user.addr) { this.onUser(window.user); }
            const q = new URLSearchParams(window.location.search).get('address');
            if (q) {
                this.input = q;
                this.check();
            }
        },

        onUser(user) {
            if (!user || !user.addr) { return; }
            this.connected = normalizeAddress(user.addr);
            this.connectedWallet = user.authType === 'passkey' ? 'passkey' : (window.detectWallet ? window.detectWallet(user) : 'flow');
            if (this.pendingConnect) {
                this.pendingConnect = false;
                this.useConnected();
            }
        },

        get inputError() {
            if (!this.input || !this.input.trim()) { return ''; }
            return normalizeAddress(this.input) ? '' : 'That isn\u2019t a Flow address. It starts with 0x followed by 16 characters (0\u20139, a\u2013f).';
        },
        get networkLabel() { return NETWORK === 'mainnet' ? 'Flow mainnet' : 'Flow ' + NETWORK; },
        get isConnectedAddress() { return !!this.connected && this.connected === this.address; },
        get walletLabel() { return WALLET_LABELS[this.wallet.class] || ''; },
        get signState() {
            if (this.isConnectedAddress) { return 'yes'; }
            return this.wallet.canSign || 'unknown';
        },
        get signLabel() {
            const s = this.signState;
            if (this.wallet.class === 'blocto') { return 'Can\u2019t sign with Blocto'; }
            return s === 'yes' ? 'Can sign today' : (s === 'no' ? 'Can\u2019t sign' : 'Can sign: not sure');
        },
        get errorTitle() {
            return {invalid: 'Check the address', not_found: 'No account at this address', limit: 'This account is too big to read here', network: 'The blockchain didn\u2019t answer'}[this.errorKind] || 'Something went wrong';
        },
        get errorText() {
            switch (this.errorKind) {
            case 'invalid': return 'A Flow address starts with 0x followed by 16 characters (0\u20139, a\u2013f), for example 0x1234abcd5678ef90.';
            case 'not_found': return 'There is no Flow account at ' + this.address + ' on ' + this.networkLabel + '. Check for typos.';
            case 'limit': return 'This account holds too many items to read in one go. Try again later.';
            default: return 'We couldn\u2019t reach the Flow access node. Check your connection and try again.';
            }
        },
        short(a) { return a ? a.slice(0, 6) + '\u2026' + a.slice(-4) : ''; },

        connect() {
            this.pendingConnect = true;
            fcl.authenticate();
        },
        useConnected() {
            if (!this.connected) { this.connect(); return; }
            this.input = this.connected;
            this.check();
        },

        async check() {
            const address = normalizeAddress(this.input);
            if (!address) {
                this.address = null;
                this.errorKind = 'invalid';
                this.status = 'error';
                return;
            }
            const seq = ++this.seq;
            this.address = address;
            this.status = 'loading';
            this.errorKind = '';
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('address', address);
                window.history.replaceState(null, '', url.toString());
            } catch (e) { /* old browsers */ }
            try {
                const r = await readTerrakit(address);
                if (seq !== this.seq) { return; }
                let wallet = classifyWallet(r);
                if (wallet.class === 'none') {
                    this.errorKind = 'not_found';
                    this.status = 'error';
                    return;
                }
                if (this.connected === address && this.connectedWallet === 'passkey' && (wallet.class === 'other' || wallet.class === 'keyless')) {
                    wallet = {...wallet, class: 'passkey', canSign: 'yes'};
                }
                this.wallet = wallet;
                this.summary = summarizeVouchers(r.vouchers);
                this.packCount = (r.packs || []).filter((id) => inRanges(id, CFG.packRanges)).length;
                this.status = 'done';
            } catch (e) {
                if (seq !== this.seq) { return; }
                console.log(e);
                this.errorKind = scriptErrorKind(e && (e.message || e));
                this.status = 'error';
            }
        },
    }));
}
