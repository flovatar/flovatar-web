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
    'login_enabled' => (bool) env('PASSKEY_LOGIN_ENABLED', in_array(strtolower((string) env('PASSKEY_MODE', '')), ['stealth', 'on'], true)),

    // WebAuthn relying party. rp_id must be the page host or a registrable suffix of it; null = request host.
    // Behind the headless Worker the request host is the API origin (api.flovatar.com), so set it explicitly:
    //   prod:    PASSKEY_RP_ID=flovatar.com           PASSKEY_ORIGINS=https://flovatar.com
    //   testnet: PASSKEY_RP_ID=testnet.flovatar.com   PASSKEY_ORIGINS=https://testnet.flovatar.com,https://next-testnet.flovatar.com
    'rp_id' => env('PASSKEY_RP_ID'),
    'rp_name' => env('PASSKEY_RP_NAME', 'Flovatar (testnet)'),

    // Exact origins (scheme://host[:port]) whose WebAuthn responses we accept (clientDataJSON.origin). Empty =
    // https://{rp_id} only. Origins that are not the rp_id host or a subdomain of it (next-testnet.flovatar.com for
    // rp_id testnet.flovatar.com, sportvatar.com for flovatar.com) only work in browsers with WebAuthn Related Origin
    // Requests (Chrome/Edge 128+, Safari 18+, Firefox 152+) and must be listed in https://{rp_id}/.well-known/webauthn,
    // which this app serves from the same list (App\Services\Passkey\WebAuthnOrigins).
    'origins' => array_values(array_filter(array_map(fn ($o) => rtrim(strtolower(trim($o)), '/'), explode(',', (string) env('PASSKEY_ORIGINS', ''))), 'strlen')),

    // Email one-time codes (sign-up + "verify your email"). Codes are 6 digits, stored as HMAC-SHA256, single use.
    'email' => [
        'code_ttl' => (int) env('PASSKEY_EMAIL_CODE_TTL', 600),          // seconds
        'max_attempts' => (int) env('PASSKEY_EMAIL_MAX_ATTEMPTS', 5),    // wrong codes before the code is burnt
        'resend_cooldown' => (int) env('PASSKEY_EMAIL_COOLDOWN', 60),    // seconds between two sends to one address
        'sends_per_email_per_hour' => (int) env('PASSKEY_EMAIL_SENDS_PER_EMAIL', 5),
        'sends_per_ip_per_hour' => (int) env('PASSKEY_EMAIL_SENDS_PER_IP', 20), // IPv6 bucketed by /64
        'verifies_per_ip_per_hour' => (int) env('PASSKEY_EMAIL_VERIFIES_PER_IP', 60),
        'verified_ttl' => (int) env('PASSKEY_EMAIL_VERIFIED_TTL', 1800), // a verified email is valid for sign-up this long
        // TESTNET AUTOMATION ONLY: keep the last code for @resend.dev sink addresses readable via
        // `php artisan passkey:test-otp <email>` (never on mainnet, never for other domains).
        'test_read' => (bool) env('PASSKEY_TEST_OTP_READ', false),
    ],

    // DB-loss recovery: an assertion from an unknown passkey -> recover the P-256 public key from the signature ->
    // Flow key indexer -> on-chain key check -> signature check -> the account/credential rows are re-created.
    'recovery' => [
        'enabled' => (bool) env('PASSKEY_RECOVERY_ENABLED', true),
        // null = by network: testnet https://staging.key-indexer.flow.com, mainnet https://production.key-indexer.flow.com
        'key_indexer' => env('PASSKEY_KEY_INDEXER_URL'),
        'attempts_per_ip_per_hour' => (int) env('PASSKEY_RECOVERY_PER_IP', 20),
    ],

    // Link to Flow Wallet / Dapper (HybridCustody child account; the passkey account publishes, the wallet claims).
    'link' => [
        'enabled' => (bool) env('PASSKEY_LINK_ENABLED', true),
        'display_name' => env('PASSKEY_LINK_NAME', 'Flovatar account'),
        'display_description' => env('PASSKEY_LINK_DESCRIPTION', 'Your Flovatar passkey account (email + passkey sign-in on flovatar.com)'),
        'thumbnail' => env('PASSKEY_LINK_THUMBNAIL', 'https://brand.flovatar.com/assets/logos/flovatar/png/flovatar-social-grad.png'),
        // FCLCrypto (account-proof verification of the parent wallet): testnet 0x74daa6f9c7ef24b1, mainnet 0xb4b82a1c9d21d284
        'fcl_crypto' => env('PASSKEY_FCL_CRYPTO', '0x74daa6f9c7ef24b1'),
    ],

    // Sponsor account: creates the user accounts and pays every fee (envelope signer).
    // Key: 64 hex chars (raw P-256 scalar), ECDSA_P256 + SHA2_256 account key, file chmod 600 outside the app dir.
    // driver: 'file' (key file, testnet only) | 'kms' (AWS KMS ECC_NIST_P256 / SIGN_VERIFY key, required on mainnet; see
    // App\Services\Passkey\KmsSponsorSigner, `php artisan passkey:kms-check` and flovatar-kb notes/mainnet-launch-stealth.md).
    'sponsor' => [
        'driver' => env('PASSKEY_SPONSOR_DRIVER', 'file'),
        'address' => env('PASSKEY_SPONSOR_ADDRESS'),
        'key_index' => (int) env('PASSKEY_SPONSOR_KEY_INDEX', 0),
        'key_file' => env('PASSKEY_SPONSOR_KEY_FILE'),
        'kms' => [
            'provider' => env('PASSKEY_SPONSOR_KMS_PROVIDER'),   // aws (mainnet) | mock (testnet end-to-end check only)
            'key' => env('PASSKEY_SPONSOR_KMS_KEY'),             // AWS key ARN (arn:aws:kms:<region>:<account>:key/<id>)
            'public_key' => env('PASSKEY_SPONSOR_KMS_PUBLIC_KEY'), // X||Y hex of the KMS key (= account key on chain)
            'region' => env('PASSKEY_SPONSOR_KMS_REGION', env('AWS_DEFAULT_REGION')),
            // label line → value line file OUTSIDE the app dir, chmod 600 ("access key id" / value / "secret access key" / value)
            'credentials_file' => env('PASSKEY_SPONSOR_KMS_CREDENTIALS_FILE'),
            // provider=mock (testnet only): local 64-hex key file answered in KMS wire format (end-to-end tests)
            'mock_key_file' => env('PASSKEY_SPONSOR_KMS_MOCK_KEY_FILE'),
        ],
        // Balance watchdog (passkey:sponsor-watch, scheduled hourly + Hermes cron): warn below this many FLOW.
        'min_balance' => (float) env('PASSKEY_SPONSOR_MIN_BALANCE', 50),
        // Proposal keys used for sponsor-only transactions (account creation, gifts). Every listed index must hold the
        // same public key as key_index (add them with `php artisan passkey:sponsor-keys N`). Each key has its own
        // sequence number, so N keys = N sponsor transactions in flight. Empty = key_index only.
        'proposal_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('PASSKEY_SPONSOR_PROPOSAL_KEYS', ''))), 'strlen')),
    ],

    // FLOW deposited into each new account on top of the account-creation minimum (storage headroom).
    // The site builders ask for >= 0.05 FLOW before minting (storage), so new accounts start above that.
    'account_fund' => env('PASSKEY_ACCOUNT_FUND', '0.06'),

    // Storage top-up for existing passkey accounts below the builders' minimum: the sponsor sends
    // (target - balance) when balance < min, at most `topups_max` times per account.
    'storage' => [
        'min' => (float) env('PASSKEY_STORAGE_MIN', 0.05),
        'target' => (float) env('PASSKEY_STORAGE_TARGET', 0.061),
        'topups_max' => (int) env('PASSKEY_STORAGE_TOPUPS_MAX', 3),
    ],

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
        // Asteroid Islands contract (config/islands.php; set at runtime in the browser from window.ISLANDS.contract)
        '0xIslands' => env('ISLANDS_CONTRACT_ADDRESS', env('FLOW_NETWORK') === 'testnet' ? '0x2c6a4c4342aeca55' : null),
        // ĐUST swap (IncrementFi; same per-network defaults as config/flovatar.php dustSwap, set at runtime in the browser)
        '0xSwapRouter' => env('DUST_SWAP_ROUTER', env('FLOW_NETWORK') === 'mainnet' ? '0xa6850776a94e6551' : '0x84e6590ef1493ae7'),
        '0xSwapConfig' => env('DUST_SWAP_CONFIG', env('FLOW_NETWORK') === 'mainnet' ? '0xb78ef7afa52ff906' : '0x84e6590ef1493ae7'),
        '0xSwapFactory' => env('DUST_SWAP_FACTORY', env('FLOW_NETWORK') === 'mainnet' ? '0xb063c16cac85dbd1' : '0x84e6590ef1493ae7'),
        // Flow EVM + VM bridge (EVM-A; same per-network defaults as resources/js/app/evm.js)
        '0xEVM' => env('MIX_EVM_CONTRACT', env('FLOW_NETWORK') === 'mainnet' ? '0xe467b9dd11fa00df' : '0x8c5303eaa26202d6'),
        '0xFlowEVMBridge' => env('MIX_EVM_BRIDGE', env('FLOW_NETWORK') === 'mainnet' ? '0x1e4aa0b87d10b141' : '0xdfc20aee650fcbdf'),
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
        'ViewResolver' => env('PASSKEY_VIEW_RESOLVER', '0x631e88ae7f1d7c20'),
        // HybridCustody + CapabilityFactory/Filter + the NFT/FT factories (same account on testnet and mainnet)
        'HybridCustody' => env('MIX_HYBRID_CUSTODY', '0x294e44e1ec6993c6'),
    ],

    'flowscan' => env('PASSKEY_FLOWSCAN', 'https://testnet.flowscan.io'),
];
