/**
 * Passkey sign-in for the whole site (testnet, PASSKEY_LOGIN_ENABLED).
 *
 * Inert unless the layout printed window.PASSKEY_LOGIN (App\Support\PasskeyLogin::enabled()): then
 *  - "Sign in" opens a choice: Email + passkey (no wallet) | Wallet (the unchanged FCL Flow Wallet / Dapper flow);
 *  - a passkey account is fed through the same user pipeline as an FCL wallet (window.applyUser -> user-loaded,
 *    init check, profile/balances, collection reads on chain), with window.currentWallet = 'flow';
 *  - every site transaction goes through resources/js/flow/fcl-tx.js -> window.extMutate(): the server builds the
 *    allowlisted template (hash-pinned), the passkey signs (FLIP-264), the sponsor pays, the tx id comes back;
 *  - login state survives reloads via the server session (/login/nonce auth_type=passkey, fl_auth marker cookie);
 *    pages stay static/edge-cacheable (no user data in HTML), so this also works on the headless build.
 * Server: App\Http\Controllers\PasskeyPocController (/passkey/*), App\Services\Passkey\*.
 */
import Alpine from 'alpinejs';
import * as fcl from '@onflow/fcl';
import * as t from '@onflow/types';

const CFG = window.PASSKEY_LOGIN || null;
const HINT_KEY = 'fl_passkey';

window.extSession = null;

// ------------------------------------------------------------------ helpers
function b64uToBuf(s) {
    s = String(s).replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) { s += '='; }
    const bin = atob(s);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) { out[i] = bin.charCodeAt(i); }
    return out.buffer;
}
function bufToB64u(buf) {
    const bytes = new Uint8Array(buf);
    let bin = '';
    for (let i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
async function sha256hex(text) {
    const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
}
function errorText(e) {
    if (e && e.response && e.response.data) {
        const d = e.response.data;
        if (d.error) { return d.error; }
        if (d.message) { return d.message; }
    }
    const n = e && e.name;
    if (n === 'NotAllowedError') { return 'The passkey prompt was cancelled or timed out. Nothing was signed.'; }
    if (n === 'InvalidStateError') { return 'This device already has a passkey for your account (synced via iCloud Keychain / Google Password Manager). You can just sign in here.'; }
    if (n === 'NotSupportedError') { return "This device can't create the kind of passkey we need (P-256). Try another browser or device."; }
    if (n === 'SecurityError') { return 'The browser refused the passkey for this address.'; }
    return (e && e.message) || String(e);
}
async function api(method, url, data) {
    const r = await window.axios({method, url, data, headers: {Accept: 'application/json'}});
    return r.data;
}
function readHint() {
    try { return JSON.parse(window.localStorage.getItem(HINT_KEY) || 'null'); } catch (e) { return null; }
}
function writeHint(v) {
    try { v ? window.localStorage.setItem(HINT_KEY, JSON.stringify(v)) : window.localStorage.removeItem(HINT_KEY); } catch (e) { /* private mode */ }
}
function dispatch(name, detail) {
    window.dispatchEvent(new CustomEvent(name, {detail: detail || {}}));
}

// ------------------------------------------------------------------ WebAuthn ceremonies
function createCredential(options) {
    const pk = options.publicKey;
    pk.challenge = b64uToBuf(pk.challenge);
    pk.user.id = b64uToBuf(pk.user.id);
    pk.excludeCredentials = (pk.excludeCredentials || []).map((c) => ({type: 'public-key', id: b64uToBuf(c.id)}));
    return navigator.credentials.create({publicKey: pk}).then((cred) => ({
        id: cred.id,
        clientDataJSON: bufToB64u(cred.response.clientDataJSON),
        attestationObject: bufToB64u(cred.response.attestationObject),
    }));
}
function getAssertion(challenge, allowIds) {
    const pk = {challenge: b64uToBuf(challenge), rpId: CFG.rpId, userVerification: 'required', timeout: 120000};
    if (allowIds && allowIds.length) {
        pk.allowCredentials = allowIds.map((id) => ({type: 'public-key', id: b64uToBuf(id)}));
    }
    return navigator.credentials.get({publicKey: pk}).then((a) => ({
        id: a.id,
        clientDataJSON: bufToB64u(a.response.clientDataJSON),
        authenticatorData: bufToB64u(a.response.authenticatorData),
        signature: bufToB64u(a.response.signature),
        userHandle: a.response.userHandle ? bufToB64u(a.response.userHandle) : null,
    }));
}

/**
 * The page origin can use the rpId directly (same host or a subdomain), or only through WebAuthn Related Origin
 * Requests (/.well-known/webauthn on the rpId host, e.g. next-testnet.flovatar.com -> testnet.flovatar.com).
 * Returns a message when this browser can't do the latter, else null.
 */
async function relatedOriginProblem() {
    if (!CFG || !CFG.rpId) { return null; }
    const host = window.location.hostname;
    if (host === CFG.rpId || host.endsWith('.' + CFG.rpId)) { return null; }
    try {
        if (window.PublicKeyCredential && PublicKeyCredential.getClientCapabilities) {
            const caps = await PublicKeyCredential.getClientCapabilities();
            if (caps && caps.relatedOrigins === false) {
                return 'This browser can only use your passkey on https://' + CFG.rpId + ' — please open the site there, or update your browser.';
            }
        }
    } catch (e) { /* unknown: just try */ }
    return null;
}

// ------------------------------------------------------------------ link to a wallet (HybridCustody, graduation)
/**
 * Connect the user's wallet with FCL WITHOUT switching the site session to it: window.extLinking makes the site's
 * user pipeline (actions.js updateUser) ignore the wallet; the wallet proves the address with an FCL account-proof
 * over our link nonce. Returns {address, wallet, proof: {nonce, signatures}}.
 */
async function connectParentWallet(nonce) {
    const saved = window.accountProofNonce;
    window.accountProofNonce = nonce; // the site's account-proof resolver (bootstrap.js) sends this nonce
    try {
        try { await fcl.unauthenticate(); } catch (e) { /* none */ }
        await fcl.authenticate();
        const user = await fcl.currentUser.snapshot();
        if (!user || !user.addr) { throw new Error('No wallet was connected.'); }
        const svc = (user.services || []).find((x) => x.type === 'account-proof');
        if (!svc || !svc.data || svc.data.nonce !== nonce || !svc.data.signatures) {
            throw new Error('This wallet did not prove the address (account proof). Please use Flow Wallet or Dapper.');
        }
        const wallet = window.detectWallet ? window.detectWallet(user) : 'other';
        return {address: user.addr, wallet, proof: {nonce: svc.data.nonce, signatures: svc.data.signatures}};
    } finally {
        window.accountProofNonce = saved;
    }
}
/** The wallet (parent) claims the published link; it signs and pays its own small fee. Returns the tx id. */
async function claimInWallet() {
    const c = await api('get', '/passkey/link/claim');
    const txId = await fcl.mutate({cadence: c.cadence, args: (arg, tt) => [arg(c.child, tt.Address)], limit: 9999});
    const res = await fcl.tx(txId).onceSealed();
    if (res && res.errorMessage) { throw new Error('Your wallet could not accept the link: ' + res.errorMessage); }
    return txId;
}

/**
 * Safari only opens the passkey sheet straight from a tap; our calls follow a server round trip. On NotAllowedError
 * we keep the prepared request and show "Continue with passkey" (passkey-confirm event -> passkeyAuth component),
 * which calls WebAuthn from the tap. Same button = retry after a real cancel; Cancel rejects (nothing signed).
 */
let pendingConfirm = null;
function ceremony(label, call) {
    return call().catch((e) => {
        if (!e || e.name !== 'NotAllowedError') { throw e; }
        return new Promise((resolve, reject) => {
            pendingConfirm = {
                go() {
                    call().then((v) => { pendingConfirm = null; dispatch('passkey-confirm-close'); resolve(v); }, (e2) => {
                        if (e2 && e2.name === 'NotAllowedError') { dispatch('passkey-confirm', {label, retry: true}); return; }
                        pendingConfirm = null; dispatch('passkey-confirm-close'); reject(e2);
                    });
                },
                cancel() { pendingConfirm = null; dispatch('passkey-confirm-close'); reject(new Error('Cancelled. Nothing was signed.')); },
            };
            dispatch('passkey-confirm', {label, retry: false});
        });
    });
}
window.passkeyConfirmGo = function () { if (pendingConfirm) { pendingConfirm.go(); } };
window.passkeyConfirmCancel = function () { if (pendingConfirm) { pendingConfirm.cancel(); } };

// ------------------------------------------------------------------ signing (called by flow/fcl-tx.js)
function argsToJson(opts) {
    if (typeof opts.args !== 'function') { return []; }
    return opts.args(fcl.arg, t).map((a) => a.xform.asArgument(a.value));
}
// Builders need >= 0.05 FLOW for storage before minting: the sponsor tops up passkey accounts (server-capped).
window.extEnsureStorage = async function () {
    if (!(window.extSession && window.extSession.active)) { return null; }
    return api('post', '/passkey/storage/ensure', {});
};

// Testnet starting FLOW for new passkey accounts (App\Services\Passkey\FlowGrant). Emits `passkey-flow-grant`
// on window with {state, txId, amount} or {error}; pages (e.g. /pack-rewind) listen and refresh balances.
window.extFlowGrant = async function () {
    if (!(window.extSession && window.extSession.active)) { return null; }
    window.dispatchEvent(new CustomEvent('passkey-flow-grant', {detail: {state: 'pending'}}));
    try {
        const out = await api('post', '/passkey/flow-grant', {});
        window.dispatchEvent(new CustomEvent('passkey-flow-grant', {detail: out}));
        return out;
    } catch (e) {
        const detail = {state: 'failed', error: errorText(e)};
        window.dispatchEvent(new CustomEvent('passkey-flow-grant', {detail}));
        throw new Error(detail.error);
    }
};

window.extMutate = async function (opts) {
    const template = await sha256hex(opts.cadence);
    const prep = await api('post', '/passkey/tx/template', {template, args: argsToJson(opts)}).catch((e) => { throw new Error(errorText(e)); });
    const assertion = await ceremony(prep.label || 'approve this transaction', () => getAssertion(prep.challenge, [prep.credentialId]))
        .catch((e) => { throw new Error(errorText(e)); });
    const out = await api('post', '/passkey/tx/submit', {token: prep.token, assertion}).catch((e) => { throw new Error(errorText(e)); });
    dispatch('passkey-tx', {txId: out.txId, template: prep.template});
    return out.txId;
};

async function waitPasskeyTx(txId) {
    const started = Date.now();
    for (;;) {
        const r = await api('get', '/passkey/tx/' + txId).catch(() => null);
        if (r && (r.status === 'Sealed' || r.status === 'Failed' || r.status === 'Expired')) { return r; }
        if (Date.now() - started > 90000) { throw new Error('Still not sealed after 90 s; check Flowscan.'); }
        await new Promise((res) => setTimeout(res, 1500));
    }
}

// ------------------------------------------------------------------ session
function passkeyUser(address) {
    return {f_type: 'User', f_vsn: '1.0.0', addr: address, loggedIn: true, authType: 'passkey', services: []};
}
function activate(address) {
    window.extSession = {active: true, address};
    writeHint({address});
    window.currentWallet = 'flow';
}
function deactivate() {
    window.extSession = null;
    writeHint(null);
}
let applied = null;
function applyPasskeyUser(address) {
    if (applied === address || !window.dispatch || !window.applyUser) { return; }
    applied = address;
    window.applyUser(passkeyUser(address));
}

async function startSession(address) {
    // a wallet connected in this browser would fight over window.user: disconnect FCL (local only)
    try { await fcl.unauthenticate(); } catch (e) { /* none */ }
    activate(address);
    if (window.fetchCsrfToken) { window.fetchCsrfToken(true).catch(() => {}); } // session regenerated -> new token
    applied = null;
    applyPasskeyUser(address);
    if (window.loadLoginNonce) { window.loadLoginNonce(); }
    dispatch('passkey-signed-in', {address});
}

if (CFG && CFG.enabled) {
    // Restore after a reload: optimistic from the local hint while the server confirms (/login/nonce).
    const hint = readHint();
    if (hint && hint.address && window.hasServerLoginHint && window.hasServerLoginHint()) {
        activate(hint.address);
    } else if (hint) {
        writeHint(null);
    }
    const origInitApp = window.initApp;
    window.initApp = function (d) {
        origInitApp(d);
        if (window.extSession && window.extSession.active) {
            setTimeout(() => applyPasskeyUser(window.extSession.address), 0);
        }
    };
    const onServerAuth = (ev) => {
        const s = ev.detail || {};
        if (s.authenticated && s.authType === 'passkey' && s.address) {
            if (!window.extSession || window.extSession.address !== s.address) {
                activate(s.address);
                applied = null;
            }
            applyPasskeyUser(s.address);
        } else if (window.extSession && window.extSession.active && (!s.authenticated || s.authType === 'wallet')) {
            // passkey session expired / replaced by a wallet login
            deactivate();
            if (applied) {
                applied = null;
                window.user = null;
                window.profile = null;
                if (window.dispatch) { window.dispatch('logged-out'); }
            }
        }
    };
    window.addEventListener('server-auth', onServerAuth);
    // this file is a lazy bundle now: the /login/nonce answer may have arrived before it loaded
    if (window.loginNoncePromise) {
        window.loginNoncePromise.then((data) => { if (data) { onServerAuth({detail: window.serverAuth}); } });
    }
    window.addEventListener('logged-out', () => { deactivate(); applied = null; });
    // Pages that ask for a signed login message (legacy "Verify" buttons): a passkey sign-in is already a server login.
    const origCreateSignature = window.createSignature;
    window.createSignature = async function (message) {
        if (window.extSession && window.extSession.active) {
            const data = await window.loadLoginNonce();
            if (data && data.authenticated) { window.dispatch('logged-in'); return; }
            dispatch('open-signin');
            return;
        }
        return origCreateSignature(message);
    };
}

// ------------------------------------------------------------------ Alpine component (modals + account panel)
Alpine.data('passkeyAuth', () => ({
    signinOpen: false,
    step: 'choice',
    email: '',
    emailError: '',
    code: '',
    codeSentTo: '',
    resendIn: 0,
    resendTimer: null,
    existingEmail: false,
    verifyEmail: '',
    verifyCode: '',
    verifySentTo: '',
    busy: false,
    error: '',
    info: '',
    confirmOpen: false,
    confirmLabel: '',
    confirmRetry: false,
    panelOpen: false,
    account: null,
    accountError: '',
    dust: null,
    supported: !!(window.PublicKeyCredential && navigator.credentials),

    openSignin() {
        this.step = 'choice';
        this.error = '';
        this.emailError = '';
        this.info = '';
        this.existingEmail = false;
        this.signinOpen = true;
        relatedOriginProblem().then((m) => { if (m) { this.error = m; } });
    },
    startCooldown(seconds) {
        this.resendIn = seconds || 60;
        if (this.resendTimer) { clearInterval(this.resendTimer); }
        this.resendTimer = setInterval(() => { this.resendIn = Math.max(0, this.resendIn - 1); if (!this.resendIn) { clearInterval(this.resendTimer); } }, 1000);
    },
    changeEmail() {
        this.codeSentTo = '';
        this.code = '';
        this.existingEmail = false;
        this.info = '';
        this.error = '';
        this.$nextTick(() => this.$refs.passkeyEmail && this.$refs.passkeyEmail.focus());
    },
    closeSignin() {
        if (!this.busy) { this.signinOpen = false; }
    },
    useWallet() {
        this.signinOpen = false;
        fcl.authenticate();
    },
    async run(fn) {
        if (this.busy) { return; }
        this.busy = true;
        this.error = '';
        try { await fn(); } catch (e) { this.error = errorText(e); console.log(e); } finally { this.busy = false; }
    },
    validateEmail() {
        const v = (this.email || '').trim();
        if (!v) { this.emailError = 'Please enter your email address.'; }
        else if (v.length > 190 || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { this.emailError = 'That doesn\'t look like a valid email address.'; }
        else { this.emailError = ''; }
        if (this.emailError && this.$refs.passkeyEmail) { this.$nextTick(() => this.$refs.passkeyEmail.focus()); }
        return !this.emailError;
    },
    /** Sign-up step 1: email -> we send a 6-digit code (generic answer, cooldown 60 s). */
    sendCode() {
        this.error = '';
        if (!this.validateEmail()) { return Promise.resolve(); }
        return this.run(async () => {
            if (!this.supported) { throw new Error('This browser does not support passkeys.'); }
            const em = this.email.trim().toLowerCase();
            const out = await api('post', '/passkey/email/start', {email: em, purpose: 'signup'});
            this.codeSentTo = em;
            this.code = '';
            this.existingEmail = false;
            this.startCooldown(out.cooldown);
            this.info = 'We sent a 6-digit code to ' + em + '. It works for 10 minutes.';
            this.$nextTick(() => this.$refs.passkeyCode && this.$refs.passkeyCode.focus());
        });
    },
    /** Sign-up step 2: code -> (existing account? -> sign in) -> step 3: passkey + Flow account. */
    signUp() {
        this.error = '';
        if (!this.codeSentTo || this.codeSentTo !== (this.email || '').trim().toLowerCase()) { return this.sendCode(); }
        const code = (this.code || '').replace(/\D/g, '');
        if (code.length !== 6) {
            this.error = 'Enter the 6-digit code from the email.';
            this.$nextTick(() => this.$refs.passkeyCode && this.$refs.passkeyCode.focus());
            return Promise.resolve();
        }
        return this.run(async () => {
            if (!this.supported) { throw new Error('This browser does not support passkeys.'); }
            this.info = 'Checking your code…';
            const v = await api('post', '/passkey/email/verify', {email: this.codeSentTo, code, purpose: 'signup'});
            if (v.existing) {
                this.existingEmail = true;
                this.info = v.message || 'You already have an account with this email. Sign in with your passkey.';
                return;
            }
            this.info = 'Creating your passkey…';
            const options = await api('post', '/passkey/register/options', {email: this.codeSentTo});
            const cred = await ceremony('create your passkey', () => createCredential(JSON.parse(JSON.stringify(options))));
            this.info = 'Creating your Flow account (≈15 s)…';
            const out = await api('post', '/passkey/register/verify', cred);
            this.info = '';
            this.codeSentTo = '';
            this.code = '';
            this.signinOpen = false;
            await startSession(out.address);
            // Testnet: new accounts get their starting FLOW from the sponsor (server-guarded, once per account).
            if (out.flowGrant && out.flowGrant.enabled && out.flowGrant.state === 'none') {
                window.extFlowGrant().catch(() => null);
            }
        }).finally(() => { if (this.error && !this.codeSentTo) { this.info = ''; } });
    },
    signIn() {
        return this.run(async () => {
            if (!this.supported) { throw new Error('This browser does not support passkeys.'); }
            const options = await api('post', '/passkey/login/options', {});
            const a = await ceremony('sign in', () => getAssertion(options.publicKey.challenge, []));
            const out = await api('post', '/passkey/login/verify', a);
            this.signinOpen = false;
            await startSession(out.address);
            if (out.recovered) {
                // DB-loss recovery: the account was found on chain; the email is unknown -> ask to verify it
                await this.openAccount();
                this.info = 'Welcome back! We restored your account from the blockchain. Please confirm your email (form above).';
            } else if (out.email_verified === false) {
                // accounts created before email codes existed: nudge once per sign-in (the panel shows the form on top)
                await this.openAccount();
                this.info = 'One quick thing: confirm your email with a 6-digit code (form above). It keeps your account recoverable and unlocks ĐUST quests.';
            }
        });
    },

    // ---- tx confirm (Safari fresh-gesture fallback)
    showConfirm(detail) {
        this.confirmLabel = detail.label || '';
        this.confirmRetry = !!detail.retry;
        this.confirmOpen = true;
    },
    confirmGo() { window.passkeyConfirmGo(); },
    confirmCancel() { this.confirmOpen = false; window.passkeyConfirmCancel(); },

    // ---- account panel
    isPasskey() { return !!(window.extSession && window.extSession.active); },
    async openAccount() {
        this.panelOpen = true;
        this.error = '';
        this.info = '';
        this.dust = window.profile ? window.profile.dustBalance : null;
        await this.loadAccount();
    },
    async loadAccount() {
        try {
            this.account = await api('get', '/passkey/account');
            this.accountError = '';
            if (this.account && !this.verifyEmail) { this.verifyEmail = this.account.email || ''; }
        } catch (e) {
            this.accountError = errorText(e);
        }
    },
    fmt(v, d) {
        if (v === null || v === undefined || v === '') { return '–'; }
        return Intl.NumberFormat('en-US', {minimumFractionDigits: d || 2, maximumFractionDigits: d || 2}).format(parseFloat(v));
    },
    copy(text) { if (navigator.clipboard) { navigator.clipboard.writeText(text); this.info = 'Copied'; setTimeout(() => { this.info = ''; }, 1500); } },
    txUrl(id) { return (CFG ? CFG.flowscan : '') + '/tx/' + id; },
    accountUrl() { return (CFG ? CFG.flowscan : '') + '/account/' + (this.account ? this.account.address : ''); },
    faucetUrl() { return CFG ? CFG.faucet : '#'; },
    pendingDevices() { return this.account ? this.account.devices.filter((d) => d.status === 'pending') : []; },
    addThisDevice() {
        return this.run(async () => {
            const options = await api('post', '/passkey/device/options', {});
            const cred = await ceremony('save a passkey on this device', () => createCredential(JSON.parse(JSON.stringify(options))));
            const pending = await api('post', '/passkey/device/verify', cred);
            this.info = 'Passkey saved on this device. Now approve it with the passkey you signed in with.';
            await this.loadAccount();
            await this.approveDevice(pending.pending, true);
        });
    },
    approveDevice(id, inner) {
        const go = async () => {
            const prep = await api('post', '/passkey/tx/prepare', {action: 'add_key', params: {credential: id}});
            const a = await ceremony('approve the new device', () => getAssertion(prep.challenge, [prep.credentialId]));
            const out = await api('post', '/passkey/tx/submit', {token: prep.token, assertion: a});
            this.info = 'Adding the device key on chain…';
            const r = await waitPasskeyTx(out.txId);
            if (r.status !== 'Sealed') { throw new Error('Adding the device failed: ' + (r.error || r.status)); }
            this.info = 'Device added.';
            await this.loadAccount();
        };
        return inner ? go() : this.run(go);
    },
    // ---- verify the email of an existing account (needed for ĐUST quests)
    sendVerifyCode() {
        return this.run(async () => {
            const em = (this.verifyEmail || '').trim().toLowerCase();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(em)) { throw new Error('Please enter a valid email address.'); }
            const out = await api('post', '/passkey/email/start', {email: em, purpose: 'verify'});
            this.verifySentTo = em;
            this.verifyCode = '';
            this.startCooldown(out.cooldown);
            this.info = 'We sent a 6-digit code to ' + em + '.';
        });
    },
    confirmVerifyCode() {
        return this.run(async () => {
            await api('post', '/passkey/email/verify', {email: this.verifySentTo, code: (this.verifyCode || '').replace(/\D/g, ''), purpose: 'verify'});
            this.verifySentTo = '';
            this.verifyCode = '';
            this.info = 'Email confirmed. Thank you!';
            await this.loadAccount();
        });
    },

    // ---- link to Flow Wallet / Dapper (HybridCustody child account)
    linkedWallets() { return this.account && this.account.links ? this.account.links.filter((l) => l.status === 'linked') : []; },
    pendingLinks() { return this.account && this.account.links ? this.account.links.filter((l) => l.status === 'published') : []; },
    walletName(w) { return w === 'dapper' ? 'Dapper' : (w === 'flow' ? 'Flow Wallet' : 'wallet'); },
    linkWallet() {
        const hooks = window.passkeyLinkHooks || {}; // test harness only: {connect(nonce), claim(parent, child)}
        return this.run(async () => {
            window.extLinking = true;
            try {
                this.info = 'Connect the wallet you want to link (Flow Wallet or Dapper)…';
                const n = await api('get', '/passkey/link/nonce');
                const parent = await (hooks.connect ? hooks.connect(n.nonce) : connectParentWallet(n.nonce));
                const prep = await api('post', '/passkey/link/prepare', {parent: parent.address, wallet: parent.wallet, proof: parent.proof});
                if (prep.token) {
                    this.info = 'Approve with your passkey to link your account to ' + parent.address + '…';
                    const a = await ceremony(prep.label, () => getAssertion(prep.challenge, [prep.credentialId]));
                    const sub = await api('post', '/passkey/tx/submit', {token: prep.token, assertion: a});
                    this.info = 'Publishing the link on chain…';
                    const r = await waitPasskeyTx(sub.txId);
                    if (r.status !== 'Sealed') { throw new Error('Linking failed: ' + (r.error || r.status)); }
                }
                if (!prep.linked) {
                    this.info = 'Last step: confirm in your wallet (it pays a tiny network fee)…';
                    const claimTx = await (hooks.claim ? hooks.claim(parent.address, this.account.address) : claimInWallet());
                    const c = await api('post', '/passkey/link/confirm', {parent: parent.address, claimTx});
                    if (!c.linked) { throw new Error('Your wallet has not accepted the link yet. Try "Link" again to finish.'); }
                }
                this.info = 'Linked! Your wallet now shows this account under linked accounts. You can still sign in here with your passkey.';
            } catch (e) {
                this.info = ''; // don't leave "Connect the wallet…" next to the error
                throw e;
            } finally {
                // disconnect the wallet again (FCL keeps its session in storage: it would replace the passkey user on
                // the next page load), then let the pipeline listen again
                if (!hooks.connect) { try { await fcl.unauthenticate(); } catch (e) { /* none */ } }
                await new Promise((res) => setTimeout(res, 300));
                window.extLinking = false;
                await this.loadAccount();
            }
        });
    },

    claimGift() {
        return this.run(async () => {
            this.info = 'Sending your starter Flobit…';
            const r = await api('post', '/passkey/gift', {});
            this.info = 'Starter ' + ((r.item && r.item.category) || 'Flobit') + ' sent to your collection.';
            await this.loadAccount();
            if (window.refreshProfile) { window.refreshProfile(); }
        });
    },
}));
