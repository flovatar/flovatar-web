# Stealth launch gate

Features can be live on production but visible only to admins/testers. Code: `App\Support\Stealth`, `config/stealth.php`.

## Modes (per feature, `.env`)

| Variable | Feature | Legacy flag used when unset |
|---|---|---|
| `PASSKEY_MODE` | passkey sign-in + sponsored transactions | `PASSKEY_POC_ENABLED` |
| `DUST_MARKET_MODE` | ĐUST marketplace | `DUST_MARKET_ENABLED` |
| `DUST_QUESTS_MODE` | ĐUST Quests | `DUST_QUESTS_ENABLED` |
| `DUST_SWAP_MODE` | ĐUST ⇄ FLOW swap | `DUST_SWAP_ENABLED` |
| `TERRAKIT_CHECK_MODE` | TerraKit wallet check | `TERRAKIT_CHECK_ENABLED` |

- `off`: no routes, no markup, no JS config, no data (as before the feature existed).
- `stealth`: routes and background jobs run, but only **stealth requests** see the feature; everyone else, every
  static/headless build, the sitemap and the edge cache get exactly the `off` output. Stealth routes answer a plain 404.
- `on`: public. Invalid values fail closed (`off`).

Mainnet also fails closed: passkey needs the AWS KMS sponsor (`PasskeyPoc::mainnetSponsorReady()`), Quests need the
rewards server (`DUST_QUESTS_REWARDS_ADDRESS` + `DUST_QUESTS_PAYOUT_SECRET`).

## Who is a stealth request

1. A valid signed `fl_s` cookie (HMAC with `STEALTH_SECRET`, HttpOnly, Secure, SameSite=Lax, 30 days), set by
   `GET /__stealth/{STEALTH_SECRET}` (`?off=1` clears it). Wrong secret = the normal 404 (rate limited, no 403/429).
2. An account-proof **wallet** session (Flow Wallet / Dapper, never passkey) of an address in `STEALTH_ADMIN_ADDRESSES`
   (default `ADMIN_ADDRESSES`); the login also sets `fl_s` for that address (dropped if the address leaves the list).

Responses rendered for a stealth request are `private, no-store`; shared Redis cache keys get a `:stealth` variant.

## Assets

Feature JS/CSS are webpack chunks `p` (passkey), `m` (market), `s` (swap), `q` (quests), `t` (terrakit). After `mix`,
`bin/fx-assets.mjs` moves them from `public/fx` to `storage/app/fx`, so neither nginx nor the headless R2 mirror can
serve them; `GET /fx/{code}.js|css` (`FxAssetController`) returns them only where the feature is visible. The layout
prints `window.__FX` (visible bundle codes) and `app.js` imports those chunks.

## Headless Worker

`headless/worker/worker.js`: requests carrying `fl_s` get every page from the origin (no edge cache); `/__stealth/*` and
`/fx/*` are proxied. **The Worker must be redeployed for stealth visitors to see features on the headless host.**
Without the cookie nothing changes (static R2 pages have no trace of stealth features).

## Checks

- `php artisan test --filter=Stealth` (gate, parity, routes, bundles, cache keys, static builds).
- `bin/stealth-parity.py snapshot|diff|check` — anonymous parity before/after and the hard "no trace" check against a
  live host (see its `--help`).
