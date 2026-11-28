<?php

/*
 * Pack Rewind (KB notes/pack-rewind.md): TESTNET-ONLY nostalgia page /pack-rewind that re-sells testnet copies of every
 * pack Flovatar ever sold (original contents and prices) plus the Sportvatar 2023 chapter (9 FLOW each).
 * Switch: PACK_REWIND_MODE (off|stealth|on) in config/stealth.php; forced off on every network except testnet
 * (App\Support\Stealth::mode). Stock rows: pack_rewind_packs (php artisan pack-rewind:import <file>).
 */
return [
    // Sportvatar contracts (testnet) + the Sportvatar packs-v2 signer key used for "purchase-flow" authorisations.
    'sportvatar' => [
        'contract' => env('PACK_REWIND_SPORTVATAR', '0xc80df59e5354ca8a'),
        'signer_key_file' => env('PACK_REWIND_SPORTVATAR_SIGNER_KEY_FILE'),
        'epoch' => (int) env('PACK_REWIND_SPORTVATAR_EPOCH', 2),
        'site_url' => env('PACK_REWIND_SPORTVATAR_URL', 'https://testnet.sportvatar.com'),
    ],

    // How long a reserved pack stays held for the buyer (seconds) and how long its authorisation is valid.
    'reservation_ttl' => (int) env('PACK_REWIND_RESERVATION_TTL', 600),
    // Max open reservations per address (a reservation is released when bought or when it expires).
    'max_open_reservations' => (int) env('PACK_REWIND_MAX_OPEN_RESERVATIONS', 3),

    // Timeline: chronological chapters. Keys = series keys in pack_rewind_packs.series. Prices are the ORIGINAL ones
    // (Sportvatar packs: 9 FLOW each, Luca 2026-11-28). art = the original pack artwork (public/images).
    'series' => [
        'genesis-standard' => [
            'collection' => 'flovatar', 'year' => 2021, 'date' => '2021-12-14', 'title' => 'Genesis Standard Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-standard.png',
            'contents' => '1 Spark · 2 Rarity Boosters · 1 Flobit',
            'story' => 'The Genesis drop, December 2021. Whitelisted wallets got the presale on the 14th; two days later the public rushed in and every Standard pack was gone by the 23rd. One Spark was all it took to design your own Flovatar in the brand-new on-chain Builder.',
        ],
        'genesis-deluxe' => [
            'collection' => 'flovatar', 'year' => 2021, 'date' => '2021-12-14', 'title' => 'Genesis Deluxe Pack',
            'price' => '35.0', 'currency' => 'FLOW', 'art' => '/images/pack-deluxe.png',
            'contents' => '1 Spark · 3 Rarity Boosters · 2 Flobits',
            'story' => 'The big brother of the Genesis drop: an extra Booster and an extra Flobit for the collectors who wanted a shot at an Epic or Legendary Flovatar. Series 1 sold out on 28 December 2021.',
        ],
        'flobit-s2' => [
            'collection' => 'flovatar', 'year' => 2022, 'date' => '2022-08-23', 'title' => 'Flobit Series 2 Pack',
            'price' => '39.0', 'currency' => 'FLOW', 'art' => '/images/pack_flobits_loop_square.svg',
            'contents' => '8 Flobits · at least 1 Epic',
            'story' => '66 brand-new Flobits, Rare to Legendary. 420 packs went on public sale on 23 August 2022 and sold out in 11 minutes; the rest became gifts for TerraKit buyers and contest prizes.',
        ],
        'terrakit' => [
            'collection' => 'flovatar', 'year' => 2022, 'date' => '2022-09-26', 'title' => 'TerraKit Pack (pre-sale)',
            'price' => '49.0', 'currency' => 'FLOW', 'art' => '/images/pack-terrakit.svg',
            'contents' => '1 Asteroid TerraKit · 1 FUB TerraKit · 1 Flobot Kit',
            'story' => 'The pre-sale that started the Asteroid Islands dream: half price, 49 FLOW, for a land TerraKit, a Flovatar Universe Builder voucher and a Flobot Constructor Kit, each Common, Epic or Legendary.',
        ],
        'sv-genesis-free' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-05-05', 'title' => 'Sportvatar Genesis Free Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-common.svg',
            'contents' => '1 Sport Flame · 1 Sportbit',
            'story' => 'Sportvatar arrives with RCS MediaGroup (Gazzetta dello Sport, Marca): one free pack for every two Flovatars at the 5 May 2023 snapshot. Free back then, 9 FLOW in the rewind.',
        ],
        'sv-genesis-standard' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-05-05', 'title' => 'Sportvatar Genesis Standard Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-common.svg',
            'contents' => '1 Sport Flame · 1 Sportbit', 'story' => 'A one-of-one: the only Genesis Standard pack ever minted.',
        ],
        'sv-genesis-rare' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-05-05', 'title' => 'Sportvatar Genesis Rare Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-rare.svg',
            'contents' => '2 Sport Flames · 2 Sportbits', 'story' => 'Minted for the Genesis launch and kept in the vault: these 500 packs were never distributed until today.',
        ],
        'sv-genesis-epic' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-05-05', 'title' => 'Sportvatar Genesis Epic Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-epic.svg',
            'contents' => '3 Sport Flames · 4 Sportbits', 'story' => '100 Epic Genesis packs, minted in May 2023 and never released.',
        ],
        'sv-genesis-legendary' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-05-05', 'title' => 'Sportvatar Genesis Legendary Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-legendary.svg',
            'contents' => '4 Sport Flames · 6 Sportbits', 'story' => 'Only 20 ever existed. None left the vault on mainnet.',
        ],
        'sv-s1-standard' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-09-12', 'title' => 'Sportvatar S1 Standard Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-common.svg',
            'contents' => '1 Sport Flame · 1 Sportbit or Number', 'story' => 'The first public Sportvatar sale, 12 September 2023 ($9.99 in DUC back then).',
        ],
        'sv-s1-rare' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-09-12', 'title' => 'Sportvatar S1 Rare Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-rare.svg',
            'contents' => '2 Sport Flames · 2 Sportbits · 1 Number', 'story' => 'Series 1 Rare, $24.99 at launch.',
        ],
        'sv-s1-epic' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-09-12', 'title' => 'Sportvatar S1 Epic Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-epic.svg',
            'contents' => '3 Sport Flames · 4 Sportbits · 2 Numbers', 'story' => 'Series 1 Epic, $69.99 at launch.',
        ],
        'sv-s1-legendary' => [
            'collection' => 'sportvatar', 'year' => 2023, 'date' => '2023-09-12', 'title' => 'Sportvatar S1 Legendary Pack',
            'price' => '9.0', 'currency' => 'FLOW', 'art' => '/images/pack-rewind/sv-legendary.svg',
            'contents' => '4 Sport Flames · 6 Sportbits · 4 Numbers', 'story' => 'The top of the Sportvatar line, $199.99 at launch.',
        ],
        'dust-flobit' => [
            'collection' => 'flovatar', 'year' => 2023, 'date' => '2023-09-20', 'title' => 'ĐUST Flobit Pack',
            'price' => '3000.0', 'currency' => 'DUST', 'art' => '/images/pack-dust.svg',
            'contents' => '5 Flobits · Rare to Legendary',
            'story' => 'NFT Day 2023: 500 packs at 3,000 ĐUST each, every ĐUST burned. Sold out on 28 November 2023 with 1.5M ĐUST gone for good. No ĐUST? Pay with FLOW: the page swaps it for you in the same transaction.',
        ],
    ],

    // ĐUST pack paid with FLOW (one-tx Increment swap): max slippage over the live quote, percent.
    'dust_swap_slippage' => (float) env('PACK_REWIND_DUST_SLIPPAGE', 3),
];
