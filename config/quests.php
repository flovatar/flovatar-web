<?php

/*
 * ĐUST Quests (KB notes/dust-quests.md) — one-time challenges for NEW passkey users, paid in ĐUST from a separate
 * rewards wallet whose key never lives on the web server (payout worker on another box, HMAC API below).
 *
 * TESTNET ONLY for now: off unless DUST_QUESTS_ENABLED=true AND the passkey sign-in is enabled AND the network is
 * testnet (App\Support\DustQuests::enabled()). Off = no routes, no markup, no JS config: production is unchanged.
 *
 * Amounts below are only the SEED of the catalogue (quest_challenges): rows are inserted once per key and never
 * overwritten, so amounts/titles/active edited in the admin page stay.
 */
return [
    'enabled' => (bool) env('DUST_QUESTS_ENABLED', false),

    'season' => [
        'key' => env('DUST_QUESTS_SEASON', 'season-1'),
        'budget' => (float) env('DUST_QUESTS_BUDGET', 1000000),       // ĐUST for the whole season (Luca 2026-11-25)
        'per_user_cap' => (float) env('DUST_QUESTS_USER_CAP', 1000),   // ĐUST per account (Luca 2026-11-25)
        'daily_cap' => (float) env('DUST_QUESTS_DAILY_CAP', 25000),    // ĐUST paid out per UTC day (worker + server)
    ],

    // Cheap anti-abuse gates (passkey + verified unique email are the main ones)
    'limits' => [
        'claims_per_ip_per_hour' => (int) env('DUST_QUESTS_CLAIMS_PER_IP', 60),
        // more distinct quest accounts than this claiming from one IP (IPv6 /64) in 24 h -> new claims wait for review
        'accounts_per_ip_per_day' => (int) env('DUST_QUESTS_ACCOUNTS_PER_IP', 3),
        'honor_seconds' => (int) env('DUST_QUESTS_HONOR_SECONDS', 10),
        'quiz_attempts_per_day' => (int) env('DUST_QUESTS_QUIZ_ATTEMPTS', 5),
        'streak_days' => 7,
        // on-chain checks look at this many of the account's recent sponsored transactions per kind
        'chain_lookback' => 25,
    ],

    // Admin page (/quests/admin): wallet addresses (same env as the other admin tools)
    'admin_addresses' => array_values(array_filter(array_map(fn ($a) => strtolower(trim($a)), explode(',', (string) env('ADMIN_ADDRESSES', env('STEALTH_ADMIN_ADDRESSES', '')))))),

    // Payout worker API (/quests/payout/*): HMAC-SHA256 with this shared secret; the rewards key stays on the worker box.
    'payout' => [
        'secret' => env('DUST_QUESTS_PAYOUT_SECRET'),
        'rewards_address' => env('DUST_QUESTS_REWARDS_ADDRESS'),
        'max_batch' => (int) env('DUST_QUESTS_MAX_BATCH', 20),
        'clock_skew' => 300,
        'reserve_ttl' => 1800, // a reserved batch without a report is handed back to the worker (same id) for this long
    ],

    // Discord OAuth2 (scopes identify + guilds). Luca creates the application; redirect URI = APP_URL/quests/discord/callback
    'discord' => [
        'client_id' => env('DISCORD_QUESTS_CLIENT_ID'),
        'client_secret' => env('DISCORD_QUESTS_CLIENT_SECRET'),
        'guild_id' => env('DISCORD_QUESTS_GUILD_ID', '895444910075961394'), // Flovatar Discord (KB discord export)
        'invite' => env('DISCORD_QUESTS_INVITE', 'https://discord.gg/flovatar'),
        'authorize_url' => 'https://discord.com/oauth2/authorize',
        'api' => env('DISCORD_QUESTS_API', 'https://discord.com/api/v10'),
    ],

    // "Share your Flovatar card": oEmbed providers (free, no key) and what the post must contain
    'share' => [
        'hashtag' => '#flovatar',
        'mentions' => ['@flovatar', 'flovatar.com'],
        'oembed' => [
            'x' => 'https://publish.twitter.com/oembed',
            'tiktok' => 'https://www.tiktok.com/oembed',
        ],
    ],

    // Seed catalogue (v0.2 rescaled, sum of active amounts Đ 910 <= Đ 1,000 cap). verify:
    //   auto    — account state in our DB (verified email, starter gift sealed, streak)
    //   chain   — a sealed sponsored transaction of this account whose events prove the action (REST check)
    //   market  — indexed ĐUST/FLOW marketplace rows (sales / sale_offers) or a sealed sponsored listing/purchase
    //   link    — wallet link (HybridCustody) recorded by the passkey code and confirmed on chain
    //   discord — Discord OAuth: member of the Flovatar guild, one Discord account per Flovatar account
    //   honor   — open the official profile, claim after honor_seconds (small amounts)
    //   share   — post URL checked via oEmbed (#Flovatar + link/mention), otherwise admin review
    //   quiz    — 5 questions on A Dust Odyssey (the book); all correct
    'catalogue' => [
        ['key' => 'account_email', 'title' => 'Create your account', 'description' => 'Sign up with a passkey and confirm your email.', 'amount' => 50, 'verify' => 'auto', 'sort' => 10],
        ['key' => 'starter_flobit', 'title' => 'Claim your starter Flobit', 'description' => 'Open your passkey account panel and get your free starter Flobit.', 'amount' => 25, 'verify' => 'auto', 'sort' => 20, 'action' => 'panel'],
        ['key' => 'discord', 'title' => 'Join the Flovatar Discord', 'description' => 'Join the Discord server and connect your Discord account (one Discord account per Flovatar account).', 'amount' => 150, 'verify' => 'discord', 'sort' => 30],
        ['key' => 'follow_x', 'title' => 'Follow @flovatar on X', 'description' => 'Open our X profile and follow us.', 'amount' => 25, 'verify' => 'honor', 'sort' => 40, 'url' => 'https://x.com/flovatar'],
        ['key' => 'follow_instagram', 'title' => 'Follow @flovatar_nft on Instagram', 'description' => 'Open our Instagram profile and follow us.', 'amount' => 25, 'verify' => 'honor', 'sort' => 41, 'url' => 'https://www.instagram.com/flovatar_nft/'],
        ['key' => 'follow_youtube', 'title' => 'Subscribe to @flovatar_live on YouTube', 'description' => 'Open our YouTube channel and subscribe.', 'amount' => 25, 'verify' => 'honor', 'sort' => 42, 'url' => 'https://www.youtube.com/@flovatar_live'],
        // TikTok @flovatar is in the contracts' metadata but NOT an active brand channel (brand guide §13.5): off until Luca confirms
        ['key' => 'follow_tiktok', 'title' => 'Follow @flovatar on TikTok', 'description' => 'Open our TikTok profile and follow us.', 'amount' => 25, 'verify' => 'honor', 'sort' => 43, 'url' => 'https://www.tiktok.com/@flovatar', 'active' => false],
        ['key' => 'share_card', 'title' => 'Share your Flovatar card', 'description' => 'Post your Flovatar (or this page) on X or TikTok with #Flovatar and a link to flovatar.com or @flovatar, then paste the post link here.', 'amount' => 75, 'verify' => 'share', 'sort' => 50],
        ['key' => 'equip_flobit', 'title' => 'Equip or change a Flobit', 'description' => 'Add or swap a hat, eyeglasses, accessory or background on your Flovatar (or a Flobot background).', 'amount' => 50, 'verify' => 'chain', 'sort' => 60, 'url' => '/collection'],
        ['key' => 'make_flobot', 'title' => 'Build a Flobot', 'description' => 'Create a Flobot in the Flobot Builder.', 'amount' => 75, 'verify' => 'chain', 'sort' => 70, 'url' => '/flobot/builder'],
        ['key' => 'first_dust_spend', 'title' => 'Spend your first ĐUST', 'description' => 'Buy an item with ĐUST on the marketplace, or create your first Psyche Likee or Flovatar.', 'amount' => 75, 'verify' => 'chain', 'sort' => 80, 'url' => '/marketplace'],
        ['key' => 'first_listing', 'title' => 'List an item for sale', 'description' => 'Put one of your items up for sale (ĐUST or FLOW).', 'amount' => 25, 'verify' => 'market', 'sort' => 90, 'url' => '/collection'],
        ['key' => 'first_sale', 'title' => 'Make your first sale', 'description' => 'Sell one of your items to another collector.', 'amount' => 50, 'verify' => 'market', 'sort' => 100, 'url' => '/collection'],
        ['key' => 'geo', 'title' => 'Put your Flovatar on the map', 'description' => 'Set the location of your Flovatar or Psyche Likee.', 'amount' => 10, 'verify' => 'chain', 'sort' => 110, 'url' => '/collection'],
        ['key' => 'lore_quiz', 'title' => 'A Dust Odyssey quiz', 'description' => 'Answer 5 questions about the book A Dust Odyssey. Get all 5 right.', 'amount' => 75, 'verify' => 'quiz', 'sort' => 120],
        ['key' => 'streak_7', 'title' => '7-day streak', 'description' => 'Visit Flovatar signed in on 7 days in a row.', 'amount' => 50, 'verify' => 'auto', 'sort' => 130],
        ['key' => 'first_daily_claim', 'title' => 'Claim ĐUST from your Flovatar', 'description' => 'Collect the ĐUST your Flovatar generates (claim it on the Flovatar or collection page).', 'amount' => 25, 'verify' => 'chain', 'sort' => 140, 'url' => '/collection'],
        ['key' => 'graduation', 'title' => 'Graduate to your own wallet', 'description' => 'Link your account to Flow Wallet or Dapper Wallet from the passkey account panel.', 'amount' => 100, 'verify' => 'link', 'sort' => 150, 'action' => 'panel'],
    ],

    // On-chain proof per challenge: sponsored transaction kinds (template ids) and an event of ours it must emit.
    // Event names are matched as A.<address>.<Contract>.<Event> with the exact address (NFTStorefrontV2 = the network's
    // storefront contract, everything else = the Flovatar contract account).
    'chain' => [
        'equip_flobit' => [
            'kinds' => ['flovatar-add-hat', 'flovatar-add-eyeglasses', 'flovatar-add-accessory', 'flovatar-add-background', 'flobot-add-background'],
            'events' => ['Flovatar.Updated', 'Flobot.Updated'],
        ],
        'make_flobot' => ['kinds' => ['flobot-create'], 'events' => ['Flobot.Created']],
        'first_dust_spend' => [
            'kinds' => ['dust-sale-buy', 'collectible-create', 'flovatar-create'],
            'events' => ['FlovatarDustCollectible.Created', 'Flovatar.Created', 'NFTStorefrontV2.ListingCompleted'],
        ],
        'geo' => ['kinds' => ['flovatar-set-position', 'collectible-set-position'], 'events' => ['Flovatar.PositionChanged', 'FlovatarDustCollectible.PositionChanged']],
        'first_daily_claim' => [
            'kinds' => ['claim-flovatar-dust', 'claim-dust', 'claim-dust-by-id', 'claim-all'],
            'events' => ['FlovatarInbox.FlovatarWithdrawDust', 'FlovatarInbox.FlovatarClaimedCommunityDust', 'FlovatarInbox.WalletWithdrawDust', 'FlovatarDustToken.TokensDeposited'],
        ],
        'first_listing' => [
            'kinds' => ['dust-sale-create', 'flovatar-sale-create', 'component-sale-create', 'flobot-sale-create', 'collectible-sale-create'],
            'events' => ['FlovatarMarketplace.FlovatarForSale', 'FlovatarMarketplace.FlovatarComponentForSale', 'NFTStorefrontV2.ListingAvailable'],
        ],
    ],

    // Lore quiz — DRAFT, Luca to approve (KB notes/dust-quests.md). Source: the book A Dust Odyssey (canon), facts
    // checked in docs/text/ADO-FINAL-v1.pdf.md. 'answer' is the index of the right option (never sent to the browser).
    'quiz' => [
        ['q' => 'In which sector of the Megaverse is Planet Flova?', 'options' => ['Sector 16', 'Sector 334', 'Sector 1216', 'Sector 42'], 'answer' => 1],
        ['q' => 'Who discovered Dust?', 'options' => ['Larkin Damus', 'Otto Pol', 'Reeno Franklin', 'Rina Fasco'], 'answer' => 2],
        ['q' => "What is the name of Fasco's Psyche Likee?", 'options' => ['Junbee', 'Carlee', 'Florax', 'Gingee'], 'answer' => 0],
        ['q' => 'Which member of the crew can smell Dust?', 'options' => ['Feddo Gellan', 'Rae Franklin', 'FL01D', 'Otto Pol'], 'answer' => 3],
        ['q' => 'Which element hidden inside a Game Boy lets Nostos use the portholes?', 'options' => ['Stimusloosh', 'Portallium', 'Slugweed crystals', 'StarDust'], 'answer' => 1],
    ],
];
