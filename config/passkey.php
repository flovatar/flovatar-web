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

    // WebAuthn relying party. rp_id must be the page host (testnet.flovatar.com); null = request host.
    'rp_id' => env('PASSKEY_RP_ID'),
    'rp_name' => env('PASSKEY_RP_NAME', 'Flovatar (testnet)'),

    // Sponsor account: creates the user accounts and pays every fee (envelope signer).
    // Key: 64 hex chars (raw P-256 scalar), ECDSA_P256 + SHA2_256 account key, file chmod 600 outside the app dir.
    'sponsor' => [
        'address' => env('PASSKEY_SPONSOR_ADDRESS'),
        'key_index' => (int) env('PASSKEY_SPONSOR_KEY_INDEX', 0),
        'key_file' => env('PASSKEY_SPONSOR_KEY_FILE'),
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
