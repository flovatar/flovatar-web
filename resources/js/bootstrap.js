window._ = require('lodash');

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.signalR = require("@microsoft/signalr");
window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * CSRF for edge-cached pages: the HTML carries no token and no session. Before the first
 * state-changing request (login, subscribe, pack reserve, ...) we fetch one from
 * /csrf-token (this starts the session) and send it as X-CSRF-TOKEN. On 419 (expired or
 * rotated after login) the token is refetched and the request retried once.
 * Read-only search POSTs are CSRF-exempt server side (App\Support\PublicCache) and skip
 * this, so browsing never creates a session.
 */
const csrfExemptPaths = [
    /^\/api\/(?!fub\/)/, // FUB studio API (/api/fub/*) is session-authenticated: it needs the CSRF token
    /^\/(search|showroom\/search|showroom\/flobot\/search|showroom\/dust-collectible\/search|transactions\/search|builder\/search|flobot\/builder\/search)\/?$/
];
let csrfTokenPromise = null;
window.fetchCsrfToken = function(force){
    if(force || !csrfTokenPromise){
        csrfTokenPromise = window.axios.get('/csrf-token', {params: {t: Date.now()}}).then(function(response){
            return response.data.token;
        }).catch(function(error){
            csrfTokenPromise = null;
            throw error;
        });
    }
    return csrfTokenPromise;
};
function needsCsrfToken(config){
    const method = (config.method || 'get').toLowerCase();
    if(['get', 'head', 'options'].indexOf(method) >= 0){
        return false;
    }
    let url;
    try {
        url = new URL(config.url, window.location.origin);
    } catch(e){
        return false;
    }
    if(url.origin !== window.location.origin){
        return false;
    }
    return !csrfExemptPaths.some(function(re){ return re.test(url.pathname); });
}
window.axios.interceptors.request.use(async function(config){
    if(needsCsrfToken(config)){
        const token = await window.fetchCsrfToken(false);
        if(token){
            config.headers['X-CSRF-TOKEN'] = token;
        }
    }
    return config;
});
window.axios.interceptors.response.use(function(response){ return response; }, async function(error){
    const config = error.config;
    if(error.response && error.response.status === 419 && config && !config.__csrfRetried && needsCsrfToken(config)){
        config.__csrfRetried = true;
        await window.fetchCsrfToken(true);
        return window.axios(config);
    }
    return Promise.reject(error);
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */



import {config} from "@onflow/fcl"

const fclConfigInfo = {
    emulator: {
        accessNode: 'http://127.0.0.1:8888',
        discoveryWallet: 'http://localhost:8701/fcl/authn',
        discoveryAuthInclude: []
    },
    testnet: {
        accessNode: 'https://rest-testnet.onflow.org',
        discoveryWallet: 'https://fcl-discovery.onflow.org/testnet/authn',
        discoveryAuthnEndpoint: 'https://fcl-discovery.onflow.org/api/testnet/authn',
        // Adds in Dapper
        discoveryAuthInclude: ["0x82ec283f88a62e65"],
        discoveryAuthExclude: ["0x55ad22f01ef568a1"]
    },
    mainnet: {
        accessNode: 'https://rest-mainnet.onflow.org',
        discoveryWallet: 'https://fcl-discovery.onflow.org/authn',
        discoveryAuthnEndpoint: 'https://fcl-discovery.onflow.org/api/authn',
        // Flow Wallet + Dapper only (Blocto shut down)
        discoveryAuthInclude: ["0x33f75ff0b830dcec", "0xead892083b3e2c6c"],
        discoveryAuthExclude: ["0x55ad22f01ef568a1"]
    }
};

const network = process.env.MIX_FLOW_NETWORK;

/**
 * FCL account-proof: the server issues a nonce (bound to this browser) that the wallet signs
 * during authentication; /login then verifies the proof on chain.
 *
 * Nonce ON DEMAND (2026-11-24): pages are static/edge-cached, so /login/nonce (uncached, origin) is NOT called on
 * every page view any more. It is loaded only when a login is involved:
 *  - the server login marker cookie (fl_auth=1, non-HttpOnly, set by the server on login and on /login/nonce while
 *    logged in, cleared on logout) exists -> load at startup (login state, admin flags);
 *  - an FCL user is connected (stored FCL session or fresh connect) -> updateUser() calls ensureLoginNonce();
 *  - intent: pointer/focus on a connect/login button prefetches it, so the account-proof resolver (awaited by FCL
 *    before it opens the wallet discovery; Dapper needs it to resolve fast) normally finds it ready;
 *  - the resolver / createSignature() await it in any case.
 * ensureLoginNonce() = one request per page (the nonce is stable for 30 min); loadLoginNonce() forces a refresh.
 */
window.accountProofNonce = null;
window.loginMessage = null;
// Server-side (Laravel session) login state: {authenticated, address}. Pages are edge-cached / static,
// so it is read from /login/nonce instead of being rendered into the HTML.
window.serverAuth = {authenticated: false, address: null};
window.loginNoncePromise = null;
window.LOGIN_MARKER_COOKIE = 'fl_auth';
window.hasServerLoginHint = function(){
    return document.cookie.split(';').some(function(c){ return c.trim().indexOf(window.LOGIN_MARKER_COOKIE + '=') === 0; });
};
window.clearServerLoginHint = function(){
    document.cookie = window.LOGIN_MARKER_COOKIE + '=; Max-Age=0; path=/; SameSite=Lax';
};
window.loadLoginNonce = function(){
    window.loginNoncePromise = window.axios.get('/login/nonce').then(function(response){
        if(response.data && response.data.nonce){
            window.accountProofNonce = response.data.nonce;
            window.loginMessage = response.data.message;
        }
        if(response.data){
            window.serverAuth = {authenticated: !!response.data.authenticated, address: response.data.address || null};
            if(response.data.auth_type){
                // only sent when the testnet passkey sign-in is enabled: 'passkey' | 'wallet'
                window.serverAuth.authType = response.data.auth_type;
            }
            window.dispatchEvent(new CustomEvent('server-auth', {detail: window.serverAuth}));
        }
        return response.data;
    }).catch(function(error){
        console.log(error);
        window.loginNoncePromise = null;
        return null;
    });
    return window.loginNoncePromise;
};
window.ensureLoginNonce = function(){
    return window.loginNoncePromise || window.loadLoginNonce();
};
// Forget the client-side login state (wallet logout); the next login fetches a fresh nonce.
window.resetServerAuth = function(){
    window.accountProofNonce = null;
    window.loginMessage = null;
    window.loginNoncePromise = null;
    window.clearServerLoginHint();
    window.serverAuth = {authenticated: false, address: null};
    window.dispatchEvent(new CustomEvent('server-auth', {detail: window.serverAuth}));
};
if(window.hasServerLoginHint()){
    window.ensureLoginNonce();
}
// Prefetch on login intent (hover/touch/focus on a connect or login button), never on page load for anonymous visitors.
(function(){
    const INTENT = /authenticate|connectWallet|chooseWallet|createSignature|walletLogin|signMessage|reservePack|claimWithVoucher/;
    const isLoginControl = function(el){
        for(let i = 0; el && el.nodeType === 1 && i < 6; i++, el = el.parentElement){
            const attrs = el.attributes;
            for(let j = 0; j < attrs.length; j++){
                const name = attrs[j].name;
                if((name.indexOf('@click') === 0 || name.indexOf('x-on:click') === 0) && INTENT.test(attrs[j].value)){
                    return true;
                }
            }
        }
        return false;
    };
    const onIntent = function(event){
        if(window.loginNoncePromise || !event.target){ return; }
        if(isLoginControl(event.target)){ window.ensureLoginNonce(); }
    };
    ['pointerover', 'pointerdown', 'touchstart', 'focusin'].forEach(function(type){
        document.addEventListener(type, onIntent, {capture: true, passive: true});
    });
})();

// WalletConnect (Flow Wallet mobile app and wallets without a browser extension). Free project id from cloud.reown.com.
if (process.env.MIX_WALLETCONNECT_PROJECT_ID) {
    config().put("walletconnect.projectId", process.env.MIX_WALLETCONNECT_PROJECT_ID);
}

const accountProofResolver = async () => {
    if(!window.accountProofNonce){
        await window.ensureLoginNonce();
    }
    return window.accountProofNonce ? {appIdentifier: window.location.origin, nonce: window.accountProofNonce} : null;
};

config()
    .put("app.detail.icon", "https://flovatar.com/images/logo-rounded.png") // Configure FCL's Access Node
    .put("app.detail.title", "Flovatar") // Configure FCL's Access Node
    .put("flow.network", network)
    .put("accessNode.api", fclConfigInfo[network].accessNode) // Configure FCL's Access Node
    .put("discovery.wallet", fclConfigInfo[network].discoveryWallet) // Configure FCL's Wallet Discovery mechanism
    .put("discovery.authn.endpoint", fclConfigInfo[network].discoveryAuthnEndpoint) // Configure FCL's Wallet Discovery mechanism
    .put("discovery.authn.include", fclConfigInfo[network].discoveryAuthInclude) // Configure FCL's Wallet Discovery mechanism
    .put("discovery.authn.exclude", fclConfigInfo[network].discoveryAuthExclude || [])
    .put("fcl.limit", 9999) // Configure gas limit
    .put("fcl.accountProof.resolver", accountProofResolver) // Server-issued nonce for wallet login (account-proof)
    .put("0xProfile", process.env.MIX_CONTRACT_PROFILE) // Will let us use `0xProfile` in our Cadence
    .put("0xFungible", process.env.MIX_CONTRACT_FUNGIBLE) // Will let us use `0xFungible` in our Cadence
    .put("0xNonFungible", process.env.MIX_CONTRACT_NON_FUNGIBLE) // Will let us use `0xFungible` in our Cadence
    .put("0xFlowToken", process.env.MIX_CONTRACT_FLOW_TOKEN) // Will let us use `0xFlowToken` in our Cadence
    .put("0xFlovatar", process.env.MIX_CONTRACT_FLOW) // Will let us use `0xFlovatar` in our Cadence
    .put("0xComponent", process.env.MIX_CONTRACT_FLOW) // Will let us use `0xFlovatarComponent` in our Cadence
    .put("0xTemplate", process.env.MIX_CONTRACT_FLOW) // Will let us use `0xFlovatarComponentTemplate` in our Cadence
    .put("0xPack", process.env.MIX_CONTRACT_FLOW) // Will let us use `0xFlovatarPack` in our Cadence
    .put("0xMarketplace", process.env.MIX_CONTRACT_FLOW) // Will let us use `0xMarketplace` in our Cadence
    .put("0xFindProfile", process.env.MIX_CONTRACT_FIND) // Will let us use `0xFindProfile` in our Cadence
    .put("0xFind", process.env.MIX_CONTRACT_FIND) // Will let us use `0xFind` in our Cadence
    .put("0xMetadataViews", process.env.MIX_METADATA_VIEWS) // Will let us use `0xMetadataViews` in our Cadence
    .put("0xStorefront2", process.env.MIX_CONTRACT_STOREFRONT2) // Will let us use `0xStorefront` in our Cadence
    .put("0xDuc", process.env.MIX_CONTRACT_DUC) // Will let us use `0xDuc` in our Cadence
    .put("0xFut", process.env.MIX_CONTRACT_DUC) // Will let us use `0xFut` in our Cadence
    .put("0xTokenForwarding", process.env.MIX_TOKEN_FORWARDING) // Will let us use `0xFut` in our Cadence
    .put("0xDapperMerchant", process.env.MIX_DAPPER_MERCHANT) // Will let us use `0xFut` in our Cadence
    .put("0xHybridCustody", process.env.MIX_HYBRID_CUSTODY) // Will let us use `0xFut` in our Cadence


