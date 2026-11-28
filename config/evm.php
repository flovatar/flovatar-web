<?php

/*
 * Flow EVM / cross-VM bridge settings (EVM-A, 2026-11). Everything is derived from FLOW_NETWORK so the same code
 * runs on testnet.flovatar.com and flovatar.com; EVM_BRIDGE_UI=false hides the bridge buttons + gallery.
 */
$network = env('FLOW_NETWORK', 'emulator');
$mainnet = $network === 'mainnet';
$flv = env('MIX_CONTRACT_FLOW', $mainnet ? '0x921ea449dffec68a' : '0x2c6a4c4342aeca55');
$spv = env('EVM_SPORTVATAR_ADDRESS', $mainnet ? '0xca5c31c0c03e11be' : '0xc80df59e5354ca8a');
$id = fn ($addr, $name) => 'A.'.preg_replace('/^0x/', '', $addr).'.'.$name.'.NFT';

return [
    'ui' => (bool) env('EVM_BRIDGE_UI', ! $mainnet),
    'network' => $network,
    'chain_id' => $mainnet ? 747 : 545,
    'chain_name' => $mainnet ? 'Flow EVM' : 'Flow EVM Testnet',
    'rpc' => env('EVM_RPC_URL', $mainnet ? 'https://mainnet.evm.nodes.onflow.org' : 'https://testnet.evm.nodes.onflow.org'),
    'explorer' => $mainnet ? 'https://evm.flowscan.io' : 'https://evm-testnet.flowscan.io',
    'bridge_address' => $mainnet ? '0x1e4aa0b87d10b141' : '0xdfc20aee650fcbdf',
    'evm_contract' => $mainnet ? '0xe467b9dd11fa00df' : '0x8c5303eaa26202d6',
    // image for wallets: CDN PNG renders exist on mainnet; testnet uses the live SVG endpoints of this site
    'image_cdn' => (bool) env('EVM_IMAGE_CDN', $mainnet),
    'royalty_bps' => 500,
    // collections shown in the EVM gallery / bridge UI, keyed by the slug used in /evm/collections/{slug}.json
    'collections' => [
        'flovatar' => ['type' => $id($flv, 'Flovatar'), 'name' => 'Flovatar', 'symbol' => 'FLVT', 'path' => 'flovatars', 'json' => 'flovatars/json'],
        'flobits' => ['type' => $id($flv, 'FlovatarComponent'), 'name' => 'Flovatar Component (Flobits)', 'symbol' => 'FLVC', 'path' => 'components', 'json' => 'components/json'],
        'flobots' => ['type' => $id($flv, 'Flobot'), 'name' => 'Flobot', 'symbol' => 'FLBT', 'path' => 'flobots', 'json' => 'flobots/json'],
        'collectibles' => ['type' => $id($flv, 'FlovatarDustCollectible'), 'name' => 'Flovatar Dust Collectible (Likee)', 'symbol' => 'FLDC', 'path' => 'collectibles', 'json' => 'collectibles/json'],
        'accessories' => ['type' => $id($flv, 'FlovatarDustCollectibleAccessory'), 'name' => 'Flovatar Dust Collectible Accessory', 'symbol' => 'FLDA', 'path' => null, 'json' => 'accessories/json'],
        'sportvatars' => ['type' => $id($spv, 'Sportvatar'), 'name' => 'Sportvatar', 'symbol' => 'SPTV', 'path' => null, 'json' => null, 'site' => $mainnet ? 'https://sportvatar.com' : 'https://testnet.sportvatar.com'],
        'sportbits' => ['type' => $id($spv, 'Sportbit'), 'name' => 'Sportbit', 'symbol' => 'SPTB', 'path' => null, 'json' => null, 'site' => $mainnet ? 'https://sportvatar.com' : 'https://testnet.sportvatar.com'],
    ],
];
