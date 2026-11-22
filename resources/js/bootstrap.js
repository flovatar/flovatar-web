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
        discoveryAuthInclude: ["0x82ec283f88a62e65"]
    },
    mainnet: {
        accessNode: 'https://rest-mainnet.onflow.org',
        discoveryWallet: 'https://fcl-discovery.onflow.org/authn',
        discoveryAuthnEndpoint: 'https://fcl-discovery.onflow.org/api/authn',
        // Adds in Dapper
        discoveryAuthInclude: ["0xead892083b3e2c6c"]
    }
};

const network = process.env.MIX_FLOW_NETWORK;

/**
 * FCL account-proof: the server issues a nonce (bound to the session) that the wallet signs
 * during authentication; /login then verifies the proof on chain.
 * The nonce is prefetched on page load because Dapper Wallet requires the resolver to
 * resolve immediately (otherwise Safari/Firefox block the wallet popup).
 */
window.accountProofNonce = null;
window.loginMessage = null;
window.loadLoginNonce = function(){
    return window.axios.get('/login/nonce').then(function(response){
        if(response.data && response.data.nonce){
            window.accountProofNonce = response.data.nonce;
            window.loginMessage = response.data.message;
        }
        return response.data;
    }).catch(function(error){
        console.log(error);
        return null;
    });
};
window.loadLoginNonce();

const accountProofResolver = async () => {
    if(!window.accountProofNonce){
        await window.loadLoginNonce();
    }
    return window.accountProofNonce ? {nonce: window.accountProofNonce} : null;
};

config()
    .put("app.detail.icon", "https://flovatar.com/images/logo-rounded.png") // Configure FCL's Access Node
    .put("app.detail.title", "Flovatar") // Configure FCL's Access Node
    .put("flow.network", network)
    .put("accessNode.api", fclConfigInfo[network].accessNode) // Configure FCL's Access Node
    .put("discovery.wallet", fclConfigInfo[network].discoveryWallet) // Configure FCL's Wallet Discovery mechanism
    .put("discovery.authn.endpoint", fclConfigInfo[network].discoveryAuthnEndpoint) // Configure FCL's Wallet Discovery mechanism
    .put("discovery.authn.include", fclConfigInfo[network].discoveryAuthInclude) // Configure FCL's Wallet Discovery mechanism
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


