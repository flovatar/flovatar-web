<?php

/*
 * FUB v1 (FlovatarUniverseBuilder contract; KB notes/fub/FUB-BUILD.md).
 *
 * Switch: FUB_MODE = off | stealth | on (App\Support\Stealth feature "fub"; default off = production today).
 *   off     = no routes, no markup, no indexing (the 2022 /fub page stays as it was).
 *   stealth = routes + indexing, pages only for stealth requests.
 *   on      = public (testnet).
 * Fails closed without a contract address for the network (mainnet has none yet).
 *
 * Public-repo rule: this repo is public. The design assistant's name, its effort levels and prices are NOT in this repo:
 * the studio reads them at runtime from the assistant API (FUB_ASSISTANT_URL + /api/v1/meta). Tier limits (layers,
 * sizes) are read from the contract settings, never hard-coded for display.
 */
$network = env('FLOW_NETWORK', 'emulator');
$testnet = $network === 'testnet';

return [
    'contract' => env('FUB_CONTRACT_ADDRESS', $testnet ? '0x2c6a4c4342aeca55' : ''),
    'start_height' => (int) env('FUB_START_HEIGHT', $testnet ? 355514945 : 0),
    'site_url' => rtrim((string) env('FUB_SITE_URL', env('APP_URL', 'https://flovatar.com')), '/'),

    // Scoped operator (FlovatarUniverseBuilder.Uploader capability holder). The key file holds 64 hex chars (P-256),
    // chmod 600, outside the repo (~/secure). Key index = the key registered with SHA2_256 on the operator account.
    'operator' => [
        'address' => env('FUB_OPERATOR_ADDRESS', ''),
        'key_file' => env('FUB_OPERATOR_KEY_FILE', ''),
        'key_index' => (int) env('FUB_OPERATOR_KEY_INDEX', 1),
        'compute_limit' => (int) env('FUB_OPERATOR_COMPUTE_LIMIT', 9999),
    ],

    // Draft-tool API for the design assistant server (server-to-server bearer token; never in the browser).
    'tool_token' => env('FUB_TOOL_TOKEN', ''),
    // Public base URL of the assistant API (the studio's side panel talks to it directly with the user's own token).
    'assistant_url' => rtrim((string) env('FUB_ASSISTANT_URL', ''), '/'),

    // Optional separate cookieless origin for user SVG (e.g. https://fub-content.flovatar.com). Empty = same host under
    // /fub-content/* with a sandbox CSP (testnet). Pages only ever reference user SVG through <img>.
    'content_origin' => rtrim((string) env('FUB_CONTENT_ORIGIN', ''), '/'),

    // Directory rule (Luca Q12 b): listed after automatic checks pass and a report-free period.
    'directory_delay_hours' => (int) env('FUB_DIRECTORY_DELAY_HOURS', 48),

    // Admins (hide / suspend requests): wallet addresses; defaults to the stealth admin list.
    'admins' => array_values(array_filter(array_map(fn ($a) => strtolower(trim($a)),
        explode(',', (string) env('FUB_ADMIN_ADDRESSES', env('STEALTH_ADMIN_ADDRESSES', env('ADMIN_ADDRESSES', ''))))))),

    'chain_cache_seconds' => (int) env('FUB_CHAIN_CACHE_SECONDS', 60),

    // Studio input caps (the contract enforces its own; these keep requests small)
    'max_upload_bytes' => 524288,
    'max_items_per_draft' => 750,
];
