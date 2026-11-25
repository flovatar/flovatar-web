<?php

/*
 * Passkey (WebAuthn / FLIP-264) walletless-onboarding proof of concept — TESTNET ONLY.
 *
 * Everything is off unless PASSKEY_POC_ENABLED=true: no routes are registered and nothing runs.
 * The PoC also refuses to run when services.flow.network is 'mainnet' (see PasskeyPoc::assertTestnet()).
 * Docs: flovatar-kb notes/passkey-poc.md.
 */
return [
    'enabled' => (bool) env('PASSKEY_POC_ENABLED', false),

    // Site-wide integration ("Sign in" -> Email + passkey | Wallet, sponsored signing of the site's own transactions).
    // Needs the PoC flag too and a testnet network (App\Support\PasskeyLogin::enabled()). Off = the site is unchanged.
    'login_enabled' => (bool) env('PASSKEY_LOGIN_ENABLED', false),

    // WebAuthn relying party. rp_id must be the page host (testnet.flovatar.com); null = request host.
    'rp_id' => env('PASSKEY_RP_ID'),
    'rp_name' => env('PASSKEY_RP_NAME', 'Flovatar (testnet)'),

    // Sponsor account: creates the user accounts and pays every fee (envelope signer).
    // Key: 64 hex chars (raw P-256 scalar), ECDSA_P256 + SHA2_256 account key, file chmod 600 outside the app dir.
    'sponsor' => [
        'address' => env('PASSKEY_SPONSOR_ADDRESS'),
        'key_index' => (int) env('PASSKEY_SPONSOR_KEY_INDEX', 0),
        'key_file' => env('PASSKEY_SPONSOR_KEY_FILE'),
        // Proposal keys used for sponsor-only transactions (account creation, gifts). Every listed index must hold the
        // same public key as key_index (add them with `php artisan passkey:sponsor-keys N`). Each key has its own
        // sequence number, so N keys = N sponsor transactions in flight. Empty = key_index only.
        'proposal_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('PASSKEY_SPONSOR_PROPOSAL_KEYS', ''))), 'strlen')),
    ],

    // FLOW deposited into each new account on top of the account-creation minimum (storage headroom).
    'account_fund' => env('PASSKEY_ACCOUNT_FUND', '0.01'),

    // Abuse limits (the sponsor pays): accounts per IP per day, accounts per day in total, starter gifts per account.
    'limits' => [
        'accounts_per_ip_per_day' => (int) env('PASSKEY_ACCOUNTS_PER_IP_PER_DAY', 10),
        'accounts_per_day' => (int) env('PASSKEY_ACCOUNTS_PER_DAY', 100),
        'txs_per_account_per_hour' => (int) env('PASSKEY_TXS_PER_ACCOUNT_PER_HOUR', 30),
    ],

    'compute_limit' => (int) env('PASSKEY_COMPUTE_LIMIT', 9999),

    // FCL address aliases used by the site's transactions (resources/js/bootstrap.js config()->put('0x...')).
    // Same MIX_* values the JS bundle is built with, so a sponsored transaction runs exactly the wallet Cadence.
    'aliases' => [
        '0xProfile' => env('MIX_CONTRACT_PROFILE'),
        '0xFungible' => env('MIX_CONTRACT_FUNGIBLE'),
        '0xNonFungible' => env('MIX_CONTRACT_NON_FUNGIBLE'),
        '0xFlowToken' => env('MIX_CONTRACT_FLOW_TOKEN'),
        '0xFlovatar' => env('MIX_CONTRACT_FLOW'),
        '0xComponent' => env('MIX_CONTRACT_FLOW'),
        '0xTemplate' => env('MIX_CONTRACT_FLOW'),
        '0xPack' => env('MIX_CONTRACT_FLOW'),
        '0xMarketplace' => env('MIX_CONTRACT_FLOW'),
        '0xFindProfile' => env('MIX_CONTRACT_FIND'),
        '0xFind' => env('MIX_CONTRACT_FIND'),
        '0xMetadataViews' => env('MIX_METADATA_VIEWS'),
        '0xStorefront2' => env('MIX_CONTRACT_STOREFRONT2'),
        '0xDuc' => env('MIX_CONTRACT_DUC'),
        '0xFut' => env('MIX_CONTRACT_DUC'),
        '0xTokenForwarding' => env('MIX_TOKEN_FORWARDING'),
        '0xDapperMerchant' => env('MIX_DAPPER_MERCHANT'),
        '0xHybridCustody' => env('MIX_HYBRID_CUSTODY'),
    ],

    // Testnet faucet shown in the passkey account panel (deposit address).
    'faucet' => env('PASSKEY_FAUCET', 'https://testnet-faucet.onflow.org/fund-account'),

    // Contract addresses (testnet defaults).
    'contracts' => [
        'Flovatar' => env('FLOW_MARKET_ADDRESS', '0x2c6a4c4342aeca55'),
        'FungibleToken' => env('PASSKEY_FUNGIBLE_TOKEN', '0x9a0766d93b6608b7'),
        'NonFungibleToken' => env('PASSKEY_NON_FUNGIBLE_TOKEN', '0x631e88ae7f1d7c20'),
        'MetadataViews' => env('PASSKEY_METADATA_VIEWS', '0x631e88ae7f1d7c20'),
        'FlowToken' => env('PASSKEY_FLOW_TOKEN', '0x7e60df042a9c0868'),
    ],

    'flowscan' => env('PASSKEY_FLOWSCAN', 'https://testnet.flowscan.io'),
];
