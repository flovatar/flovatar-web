<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'flow' => [
        'address' => env('FLOW_MARKET_ADDRESS'),
        'storefrontaddress' => env('MIX_CONTRACT_STOREFRONT2', '0x2d55b98eb200daef'),
        // NonFungibleToken contract (standard Deposited events for NFTs whose contract emits no own Deposit, e.g. Flobot)
        'nftaddress' => env('MIX_CONTRACT_NON_FUNGIBLE', env('FLOW_NETWORK') === 'mainnet' ? '0x1d7e57aa55817448' : '0x631e88ae7f1d7c20'),
        'path' => env('FLOW_PATH', '~/.local/bin'),
        'network' => env('FLOW_NETWORK', 'emulator'),
        'minBlockHeight' => env('FLOW_MIN_BLOCK_HEIGHT', 0),

        // Event indexer that replaces the Graffle webhook (flow:catchup / flow:stream / flow:process)
        'rest' => env('FLOW_REST_URL', 'https://rest-mainnet.onflow.org'),
        'ws' => env('FLOW_WS_URL', 'wss://rest-mainnet.onflow.org/v1/ws'),
        'indexer' => [
            'enabled' => env('FLOW_INDEXER_ENABLED', true),
            'start_height' => env('FLOW_INDEXER_START_HEIGHT'),
            'rps' => env('FLOW_INDEXER_RPS', 20),
            'max_blocks_per_run' => env('FLOW_INDEXER_MAX_BLOCKS', 25000),
            'process_batch' => env('FLOW_INDEXER_PROCESS_BATCH', 200),
            'max_attempts' => env('FLOW_INDEXER_MAX_ATTEMPTS', 3),
        ],
    ],
    'sender_mail' => [
        'token' => env('SENDER_MAIL_TOKEN'),
        'url' => env('SENDER_MAIL_URL', 'https://api.sender.net/v2/subscribers')
    ],
    'geo' => [
        // GEO Location editor (Flovatar / Psyche Likee setPosition, Đ10): free OpenStreetMap stack, no API key.
        // Search via our /api/v2/geo proxy (Photon, Nominatim fallback for explicit searches only, cached, 1 req/s to
        // Nominatim), click-on-map picker = Leaflet (public/vendor/leaflet-1.9.4) + the tile URL below.
        // Replaces Google Maps (GOOGLE_MAPS_* are no longer read). App\Services\Geo\Geocoder.
        'enabled' => (bool) env('GEO_LOCATION_ENABLED', true),
        'photon_url' => env('GEO_PHOTON_URL', 'https://photon.komoot.io'),
        'photon_per_minute' => (int) env('GEO_PHOTON_PER_MINUTE', 120),
        'nominatim_url' => env('GEO_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'contact' => env('GEO_CONTACT', ''), // optional e-mail/URL added to the geocoder User-Agent
        'tile_url' => env('GEO_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tile_attribution' => env('GEO_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'),
    ],
    'graffle' => [
        // Shared secret required on POST /graffle (header X-Graffle-Secret, Bearer token or ?secret=)
        'secret' => env('GRAFFLE_WEBHOOK_SECRET'),
        // Graffle stopped delivering events in Feb 2026 and was replaced by the built-in
        // indexer. The /graffle route is only registered when this is true.
        'enabled' => env('GRAFFLE_WEBHOOK_ENABLED', false),
    ]

];
