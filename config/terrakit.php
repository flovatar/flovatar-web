<?php

/*
 * TerraKit wallet check (/terrakit/check, KB notes/terrakit-check.md).
 *
 * A read-only page where a holder pastes an address (or connects Flow Wallet / Dapper) and sees their TerraKit vouchers,
 * unopened TerraKit packs and which kind of wallet manages the account. Everything is read in the browser with one FCL
 * script against the site's access node: no DB reads, no writes, no personal data.
 *
 * OFF by default (TERRAKIT_CHECK_ENABLED=false): no route (404), nothing in the HTML/JS config, no static (headless)
 * page. Enabled on testnet only for now.
 */
return [
    'check_enabled' => (bool) env('TERRAKIT_CHECK_ENABLED', false),

    // FlovatarPack ids that are TerraKit packs (pack names are not readable by script). "a-b,c-d".
    // Mainnet: 10800-15799 (batch 1 10800-13310 + batch 2 13311-15799; Dust Flobit Packs start at 15800).
    // Testnet (wallet-check fixtures 2026-11-25): 13-15.
    'pack_ranges' => env('TERRAKIT_PACK_ID_RANGES', '10800-15799'),

    // FlovatarComponent template categories counted as TerraKit vouchers.
    'categories' => ['land', 'fub', 'flobotkit'],
];
