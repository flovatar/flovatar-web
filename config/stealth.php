<?php

/*
 * Stealth launch switches (flovatar-kb notes/mainnet-launch-stealth.md, App\Support\Stealth).
 *
 * Each feature has a mode: off | stealth | on.
 *   off     = as before the feature existed: no routes, no markup, no JS config, no data shown.
 *   stealth = live on the server (routes registered, background jobs such as the ĐUST storefront indexer run), but
 *             visible ONLY to stealth requests: a valid signed `fl_s` cookie (set by /__stealth/{STEALTH_SECRET} or by an
 *             account-proof wallet login of an address in STEALTH_ADMIN_ADDRESSES) or a wallet session of such an
 *             address. Everyone else (and every static/headless build) gets exactly the "off" output; stealth routes 404.
 *   on      = public.
 * Unset *_MODE = legacy flag (*_ENABLED=true → on, else off), so testnet keeps working unchanged.
 * Invalid values fail closed (off).
 */
return [
    // Raw env values (null = use the legacy *_ENABLED flag). Read through App\Support\Stealth::mode().
    'features' => [
        'passkey' => env('PASSKEY_MODE'),          // legacy: PASSKEY_POC_ENABLED
        'dust_market' => env('DUST_MARKET_MODE'),  // legacy: DUST_MARKET_ENABLED
        'quests' => env('DUST_QUESTS_MODE'),       // legacy: DUST_QUESTS_ENABLED
        'swap' => env('DUST_SWAP_MODE'),           // legacy: DUST_SWAP_ENABLED
        'terrakit' => env('TERRAKIT_CHECK_MODE'),  // legacy: TERRAKIT_CHECK_ENABLED
        'islands' => env('ISLANDS_MODE'),          // Asteroid Islands (no legacy flag: unset = off)
        'fub' => env('FUB_MODE'),                  // FUB studio + F.U.N. builder (no legacy flag: unset = off)
        'rewind' => env('PACK_REWIND_MODE'),       // Pack Rewind nostalgia page (TESTNET ONLY; no legacy flag: unset = off)
    ],

    // Secret for /__stealth/{secret} (>= 32 chars; empty = the secret URL is disabled). Also signs the fl_s cookie.
    'secret' => env('STEALTH_SECRET'),

    // Wallet addresses that see stealth features after an account-proof login (Flow Wallet / Dapper; never passkey).
    'admin_addresses' => array_values(array_filter(array_map(
        fn ($a) => strtolower(trim($a)),
        explode(',', (string) env('STEALTH_ADMIN_ADDRESSES', env('ADMIN_ADDRESSES', '')))
    ))),

    'cookie' => 'fl_s',
    'secret_cookie_days' => (int) env('STEALTH_COOKIE_DAYS', 30),
];
