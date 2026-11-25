<?php

/*
 * ĐUST swap templates on the passkey allowlist (KB notes/dust-swap.md): FLOW → ĐUST ONLY (buying ĐUST adds FLOW to the
 * pool). Added to the allowlist at runtime by TemplateRegistry::defaultAllow(), only on TESTNET with DUST_SWAP_ENABLED
 * (App\Support\DustSwap::passkeyBuyAllowed()).
 * The ĐUST → FLOW templates (dust-swap-dust-to-flow, dust-swap-buy-flow-listing) are never allowlisted: Quest ĐUST stays
 * in the closed loop. Same pinning rules as passkey-templates.php (sha256 of the exact Cadence in templates.json).
 */
return [
    'dust-swap-flow-to-dust' => ['sha256' => 'e21348e925be53e04a36aa85efccf41e5506027db0d779045acb2d3d06bf804e', 'label' => 'Swap FLOW for ĐUST'],
    'dust-swap-buy-dust-listing' => ['sha256' => 'ef0e809c03932209621fa892685046b341ca6fc1f081fc382efef3d4522a40be', 'label' => 'Buy with FLOW (swapped to ĐUST)'],
];
