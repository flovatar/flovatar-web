<?php

/*
 * Passkey signing ALLOWLIST: the only Cadence the sponsor co-signs (pays fees for) on behalf of passkey accounts.
 * Key = template id (resources/js/flow/<id>.tx.js), sha256 = SHA-256 of the exact Cadence string the wallet version sends
 * to fcl.mutate(), pinned here. The Cadence itself comes from resources/passkey/templates.json (node bin/passkey-templates.js);
 * App\Services\Passkey\TemplateRegistry refuses any template whose manifest hash differs from the pin, so changing a
 * transaction file needs a conscious update of this list (tests/Unit/PasskeyTemplatesTest checks both).
 *
 * Not listed on purpose: every *-dapper* template and init-account-dapper(-dust), sign-dapper-message (Dapper wallet only: Dapper is
 * a co-signer/payer), claim-child-dust-by-id (HybridCustody parent accounts; a passkey account has no children).
 */
return [
    'builder-checkout' => ['sha256' => '9de905a01ffe9a6b95faa4cf3a74cbdb289b8a8cda4cb87766d787d8127dc0b2', 'label' => 'Buy the missing Flobits (FLOW)'],
    'claim-all' => ['sha256' => 'dd286c96dbed45f6b3908f0f94902da2b50a535bc8d7a02e036529265a338a35', 'label' => 'Claim everything in your inbox'],
    'claim-dust' => ['sha256' => '831e85993e756c8f3d451670329e180f592a246495b9538b2ce9ee0da8f10c0f', 'label' => 'Claim your ĐUST'],
    'claim-dust-by-id' => ['sha256' => '7f6e657f9f442ea327f3e20a2fef60318dba76e4d125ff93e077dbe08456dfc7', 'label' => 'Claim ĐUST'],
    'claim-flobits' => ['sha256' => '487bf6dc0c6ad1adceb8222e7e66405402f61a27656ee00d6c09ddf595327e5d', 'label' => 'Claim Flobits'],
    'claim-flovatar-airdrop' => ['sha256' => '9a8ba1268de16b9f8b17e6f77b947e99e4326d7098d73163f561ef3108520ab2', 'label' => 'Claim the Flovatar airdrop'],
    'claim-flovatar-dust' => ['sha256' => '7658269fb28c952e6c44720ab01b79294af36d225f2e4678aa567dea73accdaa', 'label' => 'Claim the Flovatar ĐUST'],
    'collectible-add-story' => ['sha256' => '27ffcccf43a6938b94fb7ad934971ac559d374623f426d9dc60f8603628ce2f2', 'label' => 'Add a story (ĐUST)'],
    'collectible-create' => ['sha256' => '81f439f0b0f3eb4052ab95414cb467cafdc6229019e66a822892d11c754f7b67', 'label' => 'Create a Psyche Likee (ĐUST)'],
    'collectible-sale-buy' => ['sha256' => 'e92208a071d1d679215c5eeb93084ec3e5eaf07caf6488e46c97f40a670ba902', 'label' => 'Buy (FLOW)'],
    'collectible-sale-cancel' => ['sha256' => '9aa9ba162bded16156dd1b8e855b8780346601fd32324263ea3a7c986bcd0adc', 'label' => 'Cancel the listing'],
    'collectible-sale-create' => ['sha256' => '9a54ea0c246f73841dc3593d049e82f7c13a475bab231ec3823ddd0bd0be6101', 'label' => 'List for sale'],
    'collectible-send-gift' => ['sha256' => '5098f90b5c92e7c7e7612faa14f4cbd55637c85f5ea89da460ad927b67eb1eb8', 'label' => 'Send as a gift'],
    'collectible-set-name' => ['sha256' => '461459c30b85a791190981e8501c03d4086d7170f0aea10d8d1904e21d6c123d', 'label' => 'Set the name (ĐUST)'],
    'collectible-set-position' => ['sha256' => 'f13a2e08dcff481bb6244be0e91528f48809167fb1d89ff0450ae56561027fa4', 'label' => 'Set the position (ĐUST)'],
    'component-sale-buy' => ['sha256' => '8d935b3feae12521993d40db3d9947dc0d2a3bff389dbdd14b7bbd958a255211', 'label' => 'Buy the Flobit (FLOW)'],
    'component-sale-cancel' => ['sha256' => '41be64bee82e93c8f8fb82eb6fd69fe03f44f3e47c9a2cf50e668576425f21d7', 'label' => 'Cancel the Flobit listing'],
    'component-sale-create' => ['sha256' => '3c95c3e31dcecb5b5561d9ff35be0e09f4bd8875a9fbd28447c94bcc6144a3e1', 'label' => 'List the Flobit for sale'],
    'component-sale-update' => ['sha256' => 'c417b6749c0a2b9e2883139e72409d24ccacbe650a7ba1b9102be7b0c98be09f', 'label' => 'Change the Flobit price'],
    'component-send-gift' => ['sha256' => '6dcffa8a8508326045bf1423a15256849f97cc5f989e95d34c6811966048ba06', 'label' => 'Send the Flobit as a gift'],
    'flobot-add-background' => ['sha256' => 'e2fd482361870c932baad43d685d53a20a652f9acb2246d3d4c0233287e85a87', 'label' => 'Add a background'],
    'flobot-create' => ['sha256' => 'c49aeadd476177a88b0527f78968f2ab2eff801e6298ceb3afcdc1dab1c0572f', 'label' => 'Create a Flobot'],
    'flobot-remove-background' => ['sha256' => '8a1e8113ecee5388f94d012d713ad8814b7837d2c2c57ef87d1cd9feb00be5f9', 'label' => 'Remove the background'],
    'flobot-sale-buy' => ['sha256' => '266a8969565a569ffc84f09be7e85ed192329f375bf7fb744a4fe808b9dd92d2', 'label' => 'Buy the Flobot (FLOW)'],
    'flobot-sale-cancel' => ['sha256' => '9aa9ba162bded16156dd1b8e855b8780346601fd32324263ea3a7c986bcd0adc', 'label' => 'Cancel the Flobot listing'],
    'flobot-sale-create' => ['sha256' => '29eb8582ade61f4643cd3c8a1db85d711a99d5f22d3d814e35164bab4baf7a35', 'label' => 'List the Flobot for sale'],
    'flobot-send-gift' => ['sha256' => 'dae89691e55b17fd41596f3b55e87d32afc7f3609c02d322ec89cdf4ff024663', 'label' => 'Send the Flobot as a gift'],
    'flovatar-add-accessory' => ['sha256' => '984e303df528f38b4db2f9caaf34b341851495429fa55d7e3f6fa473ad16e7dd', 'label' => 'Equip an accessory'],
    'flovatar-add-background' => ['sha256' => 'f1068ef93023c9b87fdaf416ed8365795702f55633087f80a6bbcb16023d00aa', 'label' => 'Equip a background'],
    'flovatar-add-eyeglasses' => ['sha256' => '2ced3f265aa28b82d293c564bf1d5056a295e8c4e531e03b2a5ffb4346c9bf1c', 'label' => 'Equip eyeglasses'],
    'flovatar-add-hat' => ['sha256' => '99bf8a97c05474484f2dfc398f62a1803b16ea1bb056571f670d31bfb787c52a', 'label' => 'Equip a hat'],
    'flovatar-add-story' => ['sha256' => 'f8c86e81fb7658655e0a8a0de9eb9e97c7565a3869aa50ab84921d0cec814bbe', 'label' => 'Add a story (ĐUST)'],
    'flovatar-create' => ['sha256' => 'cc48864594ecd6ca9db60da90327126852e335085726f796d89329414d6d4b81', 'label' => 'Create your Flovatar'],
    'flovatar-remove-accessory' => ['sha256' => '02505e953456565f194489baddbcd1b792ab7ac35a1c0817f5d0cf4730e6e59b', 'label' => 'Remove the accessory'],
    'flovatar-remove-background' => ['sha256' => 'ec8b85d3fee7397650e42c6cda9ab7b77ea594871b5b6b08436203d75e372b24', 'label' => 'Remove the background'],
    'flovatar-remove-eyeglasses' => ['sha256' => '9c0556f12dbf6390df4352268cf88a89cc80b7e6b7e8a34a19539e0a9213ebcf', 'label' => 'Remove the eyeglasses'],
    'flovatar-remove-hat' => ['sha256' => 'ff69711b8282d55f3bbedf21c26ab5572102818dc0e220516f136fee1242a04b', 'label' => 'Remove the hat'],
    'flovatar-sale-buy' => ['sha256' => 'f72c04d7f55828322907e51841ffd52706ff837bc87fb827df8dd405164488fe', 'label' => 'Buy the Flovatar (FLOW)'],
    'flovatar-sale-cancel' => ['sha256' => '39641e9dce8e64d21fb1c099a2267ddbbc84d34e5b61a5052dd3067032403329', 'label' => 'Cancel the Flovatar listing'],
    'flovatar-sale-create' => ['sha256' => '16ee3ebd5461e7a574226283142ce5e9020bc2e04cbe5a042d815984776351b2', 'label' => 'List the Flovatar for sale'],
    'flovatar-sale-update' => ['sha256' => '441cf327092d2340d997118d31acc3febd8e82206715cdb521a4adc4a79c2521', 'label' => 'Change the Flovatar price'],
    'flovatar-send-gift' => ['sha256' => 'd26fd8ded6ab3e35a52ee993d6284c930fdecca77a9cced5874e86d3189f1f1b', 'label' => 'Send the Flovatar as a gift'],
    'flovatar-set-name' => ['sha256' => '4a94646df93fe973b28ec7a84a44ea0b166e64bf53143d1259e5fb4f9fea870e', 'label' => 'Set the name (ĐUST)'],
    'flovatar-set-position' => ['sha256' => 'a47090329468fa30150734dfacd93a1e1b2b9644da318b40b00b10aa76fe0eb0', 'label' => 'Set the position (ĐUST)'],
    'flovatar-unlock-3d' => ['sha256' => '3dd75767cf2648a4fb7bf9b42f241ce4a1323e49f407300e55993219dd219f6a', 'label' => 'Unlock 3D (ĐUST)'],
    'island-clear-residents' => ['sha256' => '28637b9cea1f4f34072261ea039a683b03fc15cffbcdeed7926ccc75005043d2', 'label' => 'Remove residents from your island'],
    'island-move-resident' => ['sha256' => '85e1d324a09284724d1c2cba9a5de9a7540e06bc4717fce230bc64d9d160b24c', 'label' => 'Move a resident on your island'],
    'island-open-pack' => ['sha256' => 'ffc5190b4d46f0cb8abbfb3a00fe6e6bbc64bc5d60dea8825f1378c91b520147', 'label' => 'Open a TerraKit pack'],
    'island-set-name-description' => ['sha256' => 'c2ba2948379c2b9243684300c3d443644ce9b27bdefa8b4da2d0bf8ee1e710d1', 'label' => 'Name your island'],
    'island-set-residents' => ['sha256' => '8ef4d0d53f6c9e3faba6d1e0bb58c0433d21cba38f79ce4eda8cba9146f30d61', 'label' => 'Place residents on your island'],
    'island-terraform' => ['sha256' => '416b426583bbb8779b75d4d38c78b24a69d0427c121a65ca17ea68a0b7629885', 'label' => 'Redeem land deeds into an Asteroid Island (burns the deeds)'],
    'init-account' => ['sha256' => '34999fee0803c671fc90fbf3e2638bd69766dcb4d4078138e4d489b643ca2849', 'label' => 'Set up your Flovatar collections'],
    'init-account-dust' => ['sha256' => 'f17116e0f3d072dcb33fc603e11ea7aaf6ec06b94fabf6a07768942b3e678fa0', 'label' => 'Set up your Flovatar collections'],
    'init-metadata-views' => ['sha256' => '9caad143098ae260dcce63205f7f68c26962847827113e9e462ca1d0b89651cd', 'label' => 'Update your collection views'],
    'pack-buy' => ['sha256' => 'f3f28952a61c630655f339f9e65e8d5a168ce2cd6c98772588bc4331c3a86537', 'label' => 'Buy a pack'],
    'pack-buy-v2' => ['sha256' => 'b0699ee81be3c43873ddfc7e88319563fa16ec7b4a08e10f4c8cb8091d9dab65', 'label' => 'Buy a pack'],
    'pack-open' => ['sha256' => 'a7c7e3706eb0f745d259e9e45f36e4ab90c83863e276e12d47a088fd660e456e', 'label' => 'Open a pack'],
    'send-dust-to-address' => ['sha256' => 'd2498de89eff48d6dc23746dceb8ea30c1d4babf4c78aea9d1b56bb2d6ef8ecd', 'label' => 'Send ĐUST'],
    'upgrade-flobits' => ['sha256' => 'f1ec9554a2f654cc0f316b1e8252a6c91ed813d695083ab8d7210b7caea532c4', 'label' => 'Upgrade Flobits (ĐUST)'],
    // ĐUST marketplace (NFTStorefrontV2, KB notes/dust-marketplace.md): sponsor-paid for passkey users
    'dust-sale-create' => ['sha256' => '20c5abf0797c909d7a0f86e17d5314dde8260ce36585081dca6916ceed4fd9c1', 'label' => 'List for ĐUST'],
    'dust-sale-buy' => ['sha256' => '340f3ec7e942ef07b508d45f7b1e9d91c6b907e7d765b93891199bde7e77bb6f', 'label' => 'Buy with ĐUST'],
    'dust-sale-cancel' => ['sha256' => 'dbfd2ce3ea2c22a3579ac392758b581ba98e37a8dd3da8bb2e6e03605c91c6df', 'label' => 'Cancel the ĐUST listing'],
    'dust-sale-cleanup' => ['sha256' => 'c86c1c3121ae14bc3e44053bde306f9e0cba64c1369fea499fce5c46d80051c2', 'label' => 'Clean up an old ĐUST listing'],
];
