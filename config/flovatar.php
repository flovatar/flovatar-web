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

    /*
    | ĐUST marketplace (KB notes/dust-marketplace.md): buy/sell any Flovatar item for ĐUST through the standard
    | NFTStorefrontV2. Default OFF: with the flag off the indexer ignores ĐUST listings, every public query excludes
    | currency DUST and no ĐUST UI is rendered (prod unchanged). Testnet .env only for now.
    | Creator royalties: paid only when the creator has a ĐUST receiver (Luca 2026-11-25, fixed in the Cadence).
    | Dapper wallets get no ĐUST features at all (Luca 2026-11-25).
    */
    'dustMarket' => [
        'enabled' => (bool) env('DUST_MARKET_ENABLED', false),
        // listing lifetime chosen by the site's sell box (seconds)
        'listingTtl' => (int) env('DUST_MARKET_LISTING_TTL', 60 * 60 * 24 * 365),
        'customId' => 'flovatar-dust',
    ],

    /*
    | ĐUST ⇄ FLOW auto-swap through IncrementFi (KB notes/dust-swap.md): "pay with FLOW" for ĐUST listings, "pay with
    | ĐUST" for FLOW listings (self-custody Flow Wallet only), live rate chip, quick-swap widget on /dust. Default OFF;
    | testnet .env only for now. Increment is a third party: every address is config (per network, env-overridable) and
    | the browser hides the swap whenever a quote script fails. Testnet uses OUR copy of Increment's contracts
    | (Increment's own testnet deployment has no ĐUST pair). Rates are read on chain in the browser (chain-first).
    */
    'dustSwap' => [
        'enabled' => (bool) env('DUST_SWAP_ENABLED', false),
        'router' => env('DUST_SWAP_ROUTER', env('FLOW_NETWORK') === 'mainnet' ? '0xa6850776a94e6551' : '0x84e6590ef1493ae7'),
        // SwapConfig + SwapInterfaces + SwapError
        'swapConfig' => env('DUST_SWAP_CONFIG', env('FLOW_NETWORK') === 'mainnet' ? '0xb78ef7afa52ff906' : '0x84e6590ef1493ae7'),
        'factory' => env('DUST_SWAP_FACTORY', env('FLOW_NETWORK') === 'mainnet' ? '0xb063c16cac85dbd1' : '0x84e6590ef1493ae7'),
        // the ĐUST/FLOW pair: the browser checks that SwapFactory returns exactly this pair, else the swap stays hidden
        'pair' => env('DUST_SWAP_PAIR', env('FLOW_NETWORK') === 'mainnet' ? '0xa06c38beec9cf0e8' : '0x76d585e079b73059'),
        'slippageDefault' => (float) env('DUST_SWAP_SLIPPAGE_DEFAULT', 1.0),
        'slippageMax' => (float) env('DUST_SWAP_SLIPPAGE_MAX', 3.0),
        'impactWarn' => (float) env('DUST_SWAP_IMPACT_WARN', 2.0),
        'impactBlock' => (float) env('DUST_SWAP_IMPACT_BLOCK', 10.0),
        // pool FLOW below this → the rate chip says "low liquidity"
        'lowLiquidityFlow' => (float) env('DUST_SWAP_LOW_LIQUIDITY_FLOW', env('FLOW_NETWORK') === 'mainnet' ? 1000 : 5),
        'deadlineSeconds' => (int) env('DUST_SWAP_DEADLINE_SECONDS', 300),
    ],

    /*
    | "Buy ĐUST from any chain" (KB notes/flow-evm-program.md EVM-B, notes/dust-swap.md): pay with a stablecoin on Flow
    | EVM (the wallet's COA) and, from another chain, bridge it into the COA first. One Cadence tx swaps on the Flow EVM
    | DEX (PunchSwap V2), bridges ĐUST back or goes via FLOW + Increment, then burns/deposits. Needs DUST_SWAP (same
    | feature/bundle) + DUST_EVM_ENABLED. Testnet: test USDC (public faucet) + "simulated" bridge. Mainnet: USDF (deep
    | USDF/WFLOW pool) via FLOW + Increment — no ĐUST pool on EVM needed; the cross-chain step is Relay (relay.link).
    | Every address is env-overridable; the browser fails closed (no quote = option hidden).
    */
    'dustEvm' => [
        'enabled' => (bool) env('DUST_EVM_ENABLED', false),
        'evm' => env('DUST_EVM_CADENCE_EVM', env('FLOW_NETWORK') === 'mainnet' ? '0xe467b9dd11fa00df' : '0x8c5303eaa26202d6'),
        'bridge' => env('DUST_EVM_BRIDGE', env('FLOW_NETWORK') === 'mainnet' ? '0x1e4aa0b87d10b141' : '0xdfc20aee650fcbdf'),
        'chainId' => (int) env('DUST_EVM_CHAIN_ID', env('FLOW_NETWORK') === 'mainnet' ? 747 : 545),
        'explorer' => env('DUST_EVM_EXPLORER', env('FLOW_NETWORK') === 'mainnet' ? 'https://evm.flowscan.io' : 'https://evm-testnet.flowscan.io'),
        // PunchSwap V2 (KittyPunch; UniswapV2 interface, WFLOW() instead of WETH())
        'router' => env('DUST_EVM_ROUTER', env('FLOW_NETWORK') === 'mainnet' ? '0xf45AFe28fd5519d5f8C1d4787a4D5f724C0eFa4d' : '0xeD53235cC3E9d2d464E9c408B95948836648870B'),
        'factory' => env('DUST_EVM_FACTORY', env('FLOW_NETWORK') === 'mainnet' ? '0x29372c22459a4e373851798bFd6808e71EA34A71' : '0x0f6C2EF40FA42B2F0E0a9f5987b2f3F8Af3C173f'),
        'wflow' => env('DUST_EVM_WFLOW', '0xd3bF53DAC106A0290B0483EcBC89d40FcC961f3e'),
        // bridged ĐUST ERC-20 (checked against the bridge association on chain by every tx)
        'dustErc20' => env('DUST_EVM_DUST', env('FLOW_NETWORK') === 'mainnet' ? '0x6f90cef53868878be7a2edbf41d715de09851b54' : '0x783ee59f55cae490ba81335b46cc06c391ac55bd'),
        // the stablecoin users pay with on Flow EVM (testnet: FlovatarTestUSDC with a public faucet; mainnet: USDF)
        'pay' => [
            'symbol' => env('DUST_EVM_PAY_SYMBOL', env('FLOW_NETWORK') === 'mainnet' ? 'USDF' : 'USDC'),
            'name' => env('DUST_EVM_PAY_NAME', env('FLOW_NETWORK') === 'mainnet' ? 'USD Flow (USDF)' : 'Test USDC'),
            'address' => env('DUST_EVM_PAY_TOKEN', env('FLOW_NETWORK') === 'mainnet' ? '0x2aaBea2058b5aC2D339b163C6Ab6f2b6d53aabED' : '0x12baeb407594ded4e5463578a6ce18c9bfdbf543'),
            'decimals' => (int) env('DUST_EVM_PAY_DECIMALS', 6),
        ],
        // candidate routes (the browser quotes all, takes the cheapest that passes the impact guard):
        // "direct" pay→ĐUST pool, "hop" pay→WFLOW→ĐUST, "via" pay→WFLOW then FLOW→ĐUST on Increment (Cadence)
        'routes' => array_values(array_filter(explode(',', (string) env('DUST_EVM_ROUTES', env('FLOW_NETWORK') === 'mainnet' ? 'via' : 'direct,hop,via')))),
        'testFaucet' => env('FLOW_NETWORK') !== 'mainnet' && (bool) env('DUST_EVM_TEST_FAUCET', true),
        // cross-chain step: 'simulate' (testnet: faucet stands in for the bridge), 'relay' (relay.link API), 'off'
        'xchain' => env('DUST_EVM_XCHAIN', env('FLOW_NETWORK') === 'mainnet' ? 'relay' : 'simulate'),
        'relayApi' => env('DUST_EVM_RELAY_API', 'https://api.relay.link'),
        // source chains offered for the cross-chain step (Relay chain ids; USDC addresses on each)
        'origins' => [
            ['chainId' => 8453, 'name' => 'Base', 'usdc' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'],
            ['chainId' => 42161, 'name' => 'Arbitrum', 'usdc' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831'],
            ['chainId' => 1, 'name' => 'Ethereum', 'usdc' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48'],
            ['chainId' => 10, 'name' => 'Optimism', 'usdc' => '0x0b2C639c533813f4Aa9D7837cAf62653d097Ff85'],
        ],
        'slippageDefault' => (float) env('DUST_EVM_SLIPPAGE_DEFAULT', 1.0),
        'slippageMax' => (float) env('DUST_EVM_SLIPPAGE_MAX', 3.0),
        'impactWarn' => (float) env('DUST_EVM_IMPACT_WARN', 5.0),
        'impactBlock' => (float) env('DUST_EVM_IMPACT_BLOCK', 10.0),
        'deadlineSeconds' => (int) env('DUST_EVM_DEADLINE_SECONDS', 300),
    ],
];
