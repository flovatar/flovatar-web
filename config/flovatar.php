<?php

return [

    'isTest' => env('FLOVATAR_TEST', false),
    'isLive' => env('FLOVATAR_LIVE', false),
    'isWhitelist' => env('FLOVATAR_WHITELIST', false),
    'isLaunched' => env('FLOVATAR_LAUNCHED', false),
    'dustPackLive' => env('FLOVATAR_DUST_PACK_LIVE', false),
    'price' => env('FLOVATAR_PRICE', 9.00),
    'price2' => env('FLOVATAR_PRICE_2', 35.00),


    'signer' => env('FLOW_SIGNER', 'testnet-account'),

    'dustPerDayScore' => env('DUST_PER_DAY_SCORE', 0.91447),

    'base3dHomePath' => env('BASE_3D_HOME_PATH', '/home/forge/'),
    'combinerFolder' => env('COMBINER_FOLDER', '3d.flovatar.com'),

    'categories' => [
        'body' => 'Body',
        'hair' => 'Hair',
        'eyes' => 'Eyes',
        'nose' => 'Nose',
        'mouth' => 'Mouth',
        'clothing' => 'Clothing',
        'facialHair' => 'Facial Hair',
        'eyeglasses' => 'Eyeglasses',
        'hat' => 'Hat',
        'accessory' => 'Accessory',
        'background' => 'Background'
        ],
    'marketplace_categories' => [
        'flobot' => 'Flobot',
        'collectible' => 'Psyche Likee',
        'spark' => 'Spark',
        'boost' => 'Booster',
        'flobits' => 'Flobits',
        'flobotkit' => 'Flobot Kit',
        'land' => 'Island',
        'fub' => 'FUB'
    ],
    //'svgOpen' => '<svg width="100%" height="100%" viewBox="0 0 1100 1600" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:1.5;"><rect x="0" y="0" width="1100" height="1600" style="fill:#f4f4f4;fill-opacity:0;"/>',
    'svgOpen' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 3000 3000" width="100%" height="100%">',
    'svgOpenFlobot' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 3000 3000" width="100%" height="100%" fill="#000">',
    'svgOpenCollectible' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 3000 3000" width="100%" height="100%">',
    'svgClose' => '</svg>',
    'svgCloseCollectible' => '</svg>',
    'svgOpenAsteroid' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 3025.27 4033.12">',

    'discordUrl' => env('DISCORD_URL', 'https://discord.gg/flovatar'),
    'twitterUrl' => env('TWITTER_URL', 'https://twitter.com/flovatar'),
    'mediumUrl' => env('MEDIUM_URL', 'https://blog.flovatar.com/'),
    'githubUrl' => env('GITHUB_URL', 'https://github.com/crash13override/flovatar'),

    'treasureEnabled' => env('FLOVATAR_TREASURE_ENABLED', false),
    'contractAddress' => env('MIX_CONTRACT_FLOW', ''),

    // Comma separated origins accepted as FCL account-proof appIdentifier (defaults to APP_URL's origin)
    'accountProofOrigins' => env('FLOVATAR_ACCOUNT_PROOF_ORIGINS', ''),
    'dapperEnabled' => env('DAPPER_ENABLED', false),
    'flobitSaleEnabled' => env('FLOBIT_SALE_ENABLED', false),
    'dustEnabled' => env('DUST_ENABLED', false),
    // 'daemon' (default): flow:stream + queue workers run under Supervisor.
    // 'scheduled': no daemons; the scheduler polls the chain and drains the queues every minute (testnet / low-RAM).
    'indexerMode' => env('FLOW_INDEXER_MODE', 'daemon'),

    /*
    | Relaunch feature flags (2026-11).
    | Pack sales and reservations stay disabled (FLOVATAR_PACK_SALES_ENABLED=false); routes answer
    | with `packSalesNotice` instead. The Go pack-signing tool (/go) was replaced by the pure-PHP
    | App\Services\Flow\UserMessageSigner (see docs/PACK-SIGNING.md).
    */
    // Exported images (gif/png/mp4/svg) live on the CDN bucket behind images.flovatar.com.
    'imagesUrl' => env('FLOVATAR_IMAGES_URL', 'https://images.flovatar.com'),
    'imageProxy' => env('FLOVATAR_IMAGE_PROXY', 'redirect'),
    // Re-rendering images needs webgif + headless Chromium + ffmpeg and writes to the s3 disk.
    // Off until that pipeline is restored on the new server (jobs return immediately).
    'imageExportEnabled' => env('FLOVATAR_IMAGE_EXPORT_ENABLED', false),

    'packSalesEnabled' => env('FLOVATAR_PACK_SALES_ENABLED', false),
    'packSalesNotice' => env('FLOVATAR_PACK_SALES_NOTICE', 'Pack sales are paused while the shop is being moved to a new server. Packs you already own are safe on the blockchain.'),

    /*
    | Pack signing (docs/PACK-SIGNING.md).
    | packSignerKeyFile: dedicated signer private key (64 hex chars, `flow keys generate` format),
    |   outside the repository, chmod 600. Never an account key of the mainnet contract account.
    | packV1SigningEnabled: legacy v1 signing (signature over the pack randomString checked against
    |   ACCOUNT KEY 0 of the pack owner). v1 needs the contract account's own key on the server, so it
    |   must stay false on mainnet; it exists for testnet only.
    | packsV2*: v2 authorisations checked by the pack verifier inside FlovatarPack (recipient-bound,
    |   expiring, epoch-revocable). packsV2Epoch must equal FlovatarPack.getVerifierEpoch().
    */
    'packSignerKeyFile' => env('PACK_SIGNER_KEY_FILE'),
    'packV1SigningEnabled' => (bool) env('FLOVATAR_PACK_V1_SIGNING', false),
    'packsV2Enabled' => (bool) env('FLOVATAR_PACKS_V2', false),
    // Where Flovatar holders claim their free Sportvatar packs (reboot.sportvatar.com until the DNS switch)
    'sportvatarUrl' => env('SPORTVATAR_URL', 'https://sportvatar.com'),
    'packsV2Epoch' => (int) env('FLOVATAR_PACK_EPOCH', 1),
    'packsV2Ttl' => (int) env('FLOVATAR_PACK_AUTH_TTL', 900),

    /*
    | Addresses involved in the 2026-11-16 incident. Listings and sales of these
    | addresses (and NFTs recorded in flagged_items) are hidden from marketplace views,
    | stats and search, and their pages show a warning. Comma separated.
    */
    'blockedAddresses' => array_values(array_filter(array_map(
        fn ($a) => strtolower(trim($a)),
        explode(',', (string) env('FLOVATAR_BLOCKED_ADDRESSES', '0x0c7deb7a0c9cb424'))
    ))),
    'blockedNotice' => env('FLOVATAR_BLOCKED_NOTICE', 'This wallet or item is linked to the 2026-11-16 security incident. It is hidden from the marketplace while the team reviews it.'),
];
