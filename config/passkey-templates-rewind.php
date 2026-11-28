<?php

/*
 * Pack Rewind templates on the passkey allowlist (KB notes/pack-rewind.md). Added at RUNTIME by
 * TemplateRegistry::defaultAllow() only while App\Support\PackRewind::active() (PACK_REWIND_MODE + TESTNET).
 * Pins = sha256 of the exact Cadence in resources/passkey/templates.json (node bin/passkey-templates.js).
 * The ĐUST pack paid in ĐUST uses the already-allowlisted pack-buy-v2; opening a Flovatar pack uses island-open-pack.
 */
return [
    'rewind-buy-flow' => ['sha256' => '5386576b5442e4cf11cd60f77f5c9e486fb89fd91c44539c87e33d27cfd30f98', 'label' => 'Buy a Pack Rewind pack'],
    'rewind-buy-dust-with-flow' => ['sha256' => '47149235ca6b43e6417e6a95c565a556cc4ffb5467412b63d40ad1b905c16d54', 'label' => 'Buy the ĐUST pack with FLOW (swapped to ĐUST)'],
    'rewind-sv-buy-flow' => ['sha256' => '26eba0aa9edadc9428c88587e10f002f23b575e866ed30bec452a94324aaba96', 'label' => 'Buy a Sportvatar pack'],
    'rewind-sv-open' => ['sha256' => '74f3858a62a0595914df1268564ce741e08f79b6a33be88a59b7a44395e54c2e', 'label' => 'Open a Sportvatar pack'],
];
