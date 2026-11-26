<?php

/*
 * Asteroid Islands (FlovatarAsteroidIsland contract; KB notes/islands-build.md, notes/islands-contract.md).
 *
 * Switch: ISLANDS_MODE = off | stealth | on (App\Support\Stealth feature "islands"; default off).
 *   off     = no routes, no markup, no JS config, no indexing, no renders (production today).
 *   stealth = routes + indexing run, pages visible only to stealth requests (config/stealth.php).
 *   on      = public.
 * The feature also fails closed when no island contract address is known for the network (mainnet has none yet).
 *
 * Mint = redeem only: 1 TerraKit land deed -> island of the same rarity, 5 same-rarity deeds -> next tier. No fees,
 * no payment UI, no re-terraform UI.
 */
$network = env('FLOW_NETWORK', 'emulator');
$testnet = $network === 'testnet';

return [
    // Address of the FlovatarAsteroidIsland contract ('' = unknown on this network -> feature off).
    'contract' => env('ISLANDS_CONTRACT_ADDRESS', $testnet ? '0x2c6a4c4342aeca55' : ''),

    // First block height to scan for island events (contract deployment; testnet block 355263568).
    'start_height' => (int) env('ISLANDS_START_HEIGHT', $testnet ? 355263568 : 0),

    // Public site base for island URLs (OG tags, share links).
    'site_url' => rtrim((string) env('ISLANDS_SITE_URL', env('APP_URL', 'https://flovatar.com')), '/'),

    // Premium (in-scene, integrated SVG) resident families = the Flovatar family. Contract name => address.
    // The contract's typeRules list "premium" is merged in at runtime (App\Services\Islands\IslandChain::config()).
    'premium_contracts' => [
        'Flovatar' => env('FLOW_MARKET_ADDRESS', $testnet ? '0x2c6a4c4342aeca55' : '0x921ea449dffec68a'),
        'Flobot' => env('FLOW_MARKET_ADDRESS', $testnet ? '0x2c6a4c4342aeca55' : '0x921ea449dffec68a'),
        'FlovatarDustCollectible' => env('FLOW_MARKET_ADDRESS', $testnet ? '0x2c6a4c4342aeca55' : '0x921ea449dffec68a'),
        'Sportvatar' => env('ISLANDS_SPORTVATAR_ADDRESS', $testnet ? '0xc80df59e5354ca8a' : '0xca5c31c0c03e11be'),
    ],

    // Family display names + in-world scale (af-design §3.4: Flovatar 1, Flobot 0.85, Psyche Likee 0.62, Sportvatar 1).
    'families' => [
        'Flovatar' => ['label' => 'Flovatar', 'scale' => 1.0],
        'Flobot' => ['label' => 'Flobot', 'scale' => 0.85],
        'FlovatarDustCollectible' => ['label' => 'Psyche Likee', 'scale' => 0.62],
        'Sportvatar' => ['label' => 'Sportvatar', 'scale' => 1.0],
    ],

    // Defaults while the contract has no setting (contract settings premiumSlots.<rarity> win).
    'capacity' => ['common' => 5, 'epic' => 25, 'legendary' => 125],
    'premium_default' => ['common' => 3, 'epic' => 6, 'legendary' => 10],
    'budget_default' => ['common' => 120000, 'epic' => 200000, 'legendary' => 300000],
    // Resident scale per tier when the art has no anchor metadata (af-design §3.4 tier scales).
    'tier_scale' => ['common' => 0.16, 'epic' => 0.11, 'legendary' => 0.075],

    // Sportvatar resident art (SVG) for in-scene placement: {id} is replaced.
    'sportvatar_svg_url' => env('ISLANDS_SPORTVATAR_SVG_URL', rtrim((string) env('SPORTVATAR_URL', 'https://sportvatar.com'), '/').'/api/image/{id}'),

    // TerraKit pack ids (FlovatarPack) shown with an "Open pack" button in the redeem flow (same ranges as /terrakit/check).
    'pack_ranges' => env('ISLANDS_PACK_ID_RANGES', env('TERRAKIT_PACK_ID_RANGES', '10800-15799')),

    // Chain reads (templates, config) are cached this many seconds.
    'chain_cache_seconds' => (int) env('ISLANDS_CHAIN_CACHE', 300),
];
