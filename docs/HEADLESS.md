# Headless flovatar.com — architecture (v1 built 2026-11-24 on testnet; gaps closed 2026-11-24 night)

> Mirrored in the ops knowledge base (notes/headless-architecture.md). Written so the same pattern can be applied
> to Sportvatar (section 13).

## 1. Shape in one picture

```
browser ──► Cloudflare Worker "flovatar-headless" (host: next-testnet.flovatar.com / *.workers.dev)
              │  GET page/asset ──► R2 flovatar-web/{prefix}site/...   (static HTML/JS/CSS/images, built by Laravel)
              │  /api/*, /login*, /logout, /csrf-token, searches, /templates/*, /storage/*, /components/* ...
              │        └──► same-origin proxy ──► Laravel origin (testnet.flovatar.com)  (JSON API, auth, legacy SSR leftovers)
              │  /flovatars/{id}/{owner} ──► 301 /flovatars/{id}
              └─ og:image in every item page = og.jpg on images.flovatar.com (R2, renderer pipeline)
browser ──► Flow access node (FCL, rest-testnet.onflow.org): wallet contents, balances, owner + listing of an item  (chain-first)
```

Principles (Luca, 2026-11-23): **static first** (HTML from R2, regenerated per item when it changes),
**chain-first** reads (anything a Cadence script can answer is read in the browser), **cached API** only for
cross-wallet questions (search/filter/sort, history, stats, rankings), **visual design unchanged**.

## 2. Decisions (and why)

| Topic | Decision | Why / alternatives |
|---|---|---|
| Build tool | **Laravel is the static site generator**: `php artisan headless:build` renders the existing Blade views through the HTTP kernel (same controllers/middleware/partials) into HTML, and ships the existing Mix bundle (`public/js/app.js`, `css/app.css`, chunks, images, fonts) to R2. | Identical design by construction (same templates, same CSS/Alpine/FCL/tx code), zero duplication while the legacy site still runs from the same repo, per-item regeneration from the indexer needs PHP + DB anyway. A Vite multi-page rewrite under `frontend/` was the alternative: weeks of porting 43 Blade views for no visible gain; can still happen page by page later behind the same Worker (the Worker doesn't care who produced an object). Node runs only in the normal Forge deploy (`npm run production`). |
| JS | One bundle for both modes. `window.HEADLESS` (printed by the layout only during a static build) switches the few differences: list searches use `GET /api/v2/...`, canonical owner-less item URLs, owner confirmed on chain, shells read the wallet from the URL. On the server-rendered site `window.HEADLESS` is undefined → legacy behaviour (POST searches, owner URLs). | Prod can merge `develop` without behaviour change; the headless site can be switched on per environment. |
| API host | **Same origin through the Worker** (`/api/*` etc. proxied to Laravel). `api-testnet.flovatar.com` / CORS-with-credentials not needed. | Works on `*.workers.dev` tonight (a cross-site session cookie would be a third-party cookie → blocked by browsers), keeps the Laravel session cookie first-party, no CORS preflights, the existing login/CSRF code works unchanged, one hostname for Luca to configure. Public `/api/v2` GETs additionally send `Access-Control-Allow-Origin: *` so other origins (Sportvatar, mobile, a future api.* host) can read them without credentials. A dedicated `api.flovatar.com` remains possible (Worker `ORIGIN` var) but adds a DNS record + cert + CORS for nothing today. |
| URLs | Canonical item URL `/flovatars/{id}` (`/flobots/{id}`, `/collectibles/{id}`). Old `/flovatars/{id}/{owner}` → **301** by the Worker (query kept). After a tx the page navigates to `/flovatars/{id}?owner=0x…` (hint for the chain check, same object). Flobit items keep `/components/{id}/{owner}` (proxied, section 5). `/collection/{0x…|name}` → collection shell. | Owner in the path went stale on every transfer; one URL per item = one static object + clean share links/SEO. |
| Pages | **Prebuilt per item**: Flovatars, Flobots, DUST collectibles (snapshot of DB data embedded, live chain refresh). **Static shells** (built hourly + on deploy): home, marketplace, showroom ×3, transactions, get-started, explore, story, privacy, dust, builder, builder/flobot, builder/dust, collection shell, 404. **Flobit TYPE pages** `/flobits/{templateFlowId}` prebuilt (one per template with minted Flobits: 406 on prod data, 36 on testnet). **Proxied to Laravel**: Flobit item pages `/components/{id}/{owner}` (decided, see below), `/dust/accessories/*`, packs, treasure hunt, meme maker, fub, admin, `/builder/asteroid`, `/builder/dust/{series}`. | Prebuilt = SEO/share previews + zero origin cost. **Flobit items stay proxied SSR (decision 2026-11-24)**: 133k Flobits × ~60 KB = ~8 GB of objects rewritten on every transfer/pack opening, while the page content is template-level except owner/listing (read on chain anyway); the edge caches each item URL for 60 s and the type page carries the SEO/share value. Revisit only if item pages show up in origin load. |
| Auth | Wallet account-proof login exactly as today: `GET /login/nonce` (nonce in an encrypted `flovatar_login` cookie) → FCL `authenticate` with `appIdentifier = window.location.origin` → `POST /login` (CSRF from `/csrf-token`) → Laravel session cookie (host-only, first-party on the frontend host via the proxy). JSON aliases for API clients: `/api/v2/auth/{nonce,csrf,me,login,logout}`. **The frontend origin must be in `FLOVATAR_ACCOUNT_PROOF_ORIGINS`** (testnet: `https://testnet.flovatar.com,https://next-testnet.flovatar.com`; add the workers.dev origin when used). | Reuses the verified FCLCrypto flow (skill flow-fcl-account-proof-backend). Sanctum SPA/tokens unnecessary while same-origin. |
| Caching | HTML objects: `Cache-Control: public, max-age=0, s-maxage=60, stale-while-revalidate=300` (no purge needed; regeneration replaces the object). Mix bundle with `?id=` → `immutable, 1 y`; other assets 1 day. `/api/v2` public GETs: same 60 s/300 s + CORS `*`, no cookies, 5xx never public; Worker sets `cf.cacheEverything` on user-agnostic GETs so Cloudflare honours those headers; Laravel also memoises each response in Redis (`Cache::flexible`, 10–60 s) for cold/bypassed edges. Personal/auth: `private, no-store`. | Origin ≈ 1 request per unique URL per minute behind the edge. |
| Storage | R2 bucket `flovatar-web`, prefix `{HEADLESS_PREFIX}/site/` → testnet `testnet/site/`, prod `site/`. Off mainnet the code forces a `testnet` prefix (can't overwrite prod objects). Images stay on `images.flovatar.com` (bucket `flovatar`). Legacy static sites keep `static/` (Worker `flovatar-static`, untouched). | One bucket for web output; prefix isolation per environment. |

## 3. Static build (`headless:build`)

- Code: `app/Services/Headless/StaticSiteBuilder.php`, command `app/Console/Commands/HeadlessBuild.php`, context `app/Support/Headless.php`.
- Rendering: `Request::create($path)` with the frontend host → `HttpKernel::handle()` inside `Headless::begin([...])`. Items are rendered from their owner URL (`/flovatars/{id}/{owner}`) with `canonical=/flovatars/{id}`; the layout prints `<link rel=canonical>`, `og:url` on the frontend host and `window.HEADLESS = {mode:'static', page, item:{type,id,owner,snapshot}, apiBase:'', frontendUrl, imagesUrl, blocked}`.
- Object keys: `index.html`, `marketplace/index.html`, `flovatars/{id}/index.html`, `_shells/collection.html`, `404.html`, `sitemap.xml`, assets at their `public/` path (`js/app.js`, `js/940.js` chunks, `css/app.css`, `images/...`, `fonts/...`). Excluded: `index.php`, `storage`, `robots.txt` (Worker answers), `webgl` (Worker falls back to origin).
- Skips unchanged objects (md5 vs R2 ETag; one listing per bulk run, HEAD per single item). Output is deterministic (no timestamps) except the home page (random Flovatars).
- Options: `--assets --shells --items --sitemap` (none = all), `--page=marketplace`, `--type=flovatar --ids=1,5-9 --limit=N`, `--out=/dir` (local files instead of R2, used by tests/previews), `--force`, `--dry-run`, `--quiet-puts`.
- Where it runs: on the web server (PHP + DB + scoped R2 token `~/secure/r2-web.env` → `.env` R2_* keys; disk `r2web` forces IPv4). The Forge deploy script runs `headless:build --assets --shells --sitemap` after the caches (non-fatal) so the R2 bundle always matches the deployed Blade/JS. Node only in `npm run production` of the deploy.
- Flobit TYPE pages: `--items` (or `--items --type=flobit-type`) builds `flobits/{id}/index.html` for every template with ≥1 minted Flobit; sitemap lists them. Prod data: 406 pages in 12 s, ~36 KB each.
- Measured (agent box, prod data copy): all 8,127 item pages (6,720 Flovatars + Flobots + collectibles) in 119 s = 15 ms/page, 63 KB/page (510 MB), 114 MB RSS. Testnet server: shells + 4 items + sitemap 2–4 s; first asset upload (2.3k files, 271 MB) is slow from the server (~minutes) → it runs detached in the deploy and images go last; later runs skip unchanged objects (~10 s).
- Bundle versions: besides `js/app.js` the build stores `js/app.js@{mix id}` / `css/app.css@{mix id}`; the Worker serves `/js/app.js?id=X` from the matching copy with `immutable`, so a page built before a deploy keeps the exact bundle it was rendered with (no HTML/JS mismatch while pages regenerate).

## 4. Regeneration pipeline

1. Indexer applies an event → models change (Flovatar/Flobot/DustCollectible row: mint, owner, metadata, name; SaleOffer: listing create/price/complete/cancel; Sale: sale).
2. Two triggers into `App\Support\HeadlessRegen::touch(type, flowId)`: (a) model observers (registered in AppServiceProvider) on item rows, SaleOffer saves/deletes and new Sales — whoever writes them (indexer, artisan commands); (b) the indexer hook `HeadlessRegen::fromEvent()` at the end of `ProcessGraffleEvents` (event type → item: `Flovatar.*`, `Flobot.*`, `FlovatarDustCollectible.*`, `FlovatarMarketplace.Flovatar*`, `NFTStorefrontV2.*` by `nftType`/`nftID`) — needed because the indexer removes listings with query-builder `->delete()` (no model events). Flobit TYPE pages (`touch('flobit-type', templateFlowId)`): `FlovatarComponent.Created/Destroyed` (event carries `templateId`), FLOW-market component listing events, Storefront `FlovatarComponent.NFT` listings, plus observers on FlovatarComponent create/destroy, component SaleOffers/Sales and FlovatarTemplate (`burned`, name, …); plain Flobit transfers don't touch it. `touch()` = `Cache::add` marker (debounce, per page) + `RegenerateStaticItem` delayed `HEADLESS_DEBOUNCE` (30 s) on queue `default`; no-op unless `HEADLESS_ENABLED=true`.
3. Job clears the marker, renders the page, uploads only if the md5 changed; item gone/ownerless → object deleted (Worker then 404s / origin-falls back).
4. Safety nets (scheduler, only when enabled): hourly `headless:build --shells --sitemap` (:41), nightly full `headless:build` (03:30). Testnet runs the scheduler (queue drained every minute by `queue:work --stop-when-empty`).
5. Not-yet-built item (minted seconds ago): Worker renders it at the origin (`/flovatars/{id}` → owner URL, proxied, `X-Headless: origin-fallback`).
- Collection pages are NOT per wallet: one shell, chain reads in the browser (cheaper and always fresh than 1.7k wallet pages).

## 5. Browser data flow per page

| Page | HTML | Live data |
|---|---|---|
| Home, explore, story, get-started, privacy, dust | static shell | home: Flovatars of the week snapshot (rebuilt hourly) |
| Marketplace | shell (+ lean template list, stats snapshot) | `GET /api/v2/marketplace?…` (same params/shape as `POST /search`), `/api/v2/marketplace/stats` |
| Showroom / Flobot / DUST collectible | shell | `GET /api/v2/showroom/{flovatars,flobots,collectibles}?…` |
| Transactions | shell | `GET /api/v2/transactions?…` (`/transactions/0x…` proxied to Laravel) |
| Flovatar / Flobot / collectible | per item (DB snapshot, sales history, listing, og) | **owner confirmed on chain**: `find-item-owner.cdc` over candidates `?owner=` hint → snapshot owner → connected wallet → `/api/v2/{type}/{id}` owner; then the existing scripts (`get-flovatar`, storefront/marketplace sale, claimables) at that address; tx buttons = existing tx JS (Flow Wallet + Dapper variants) |
| Collection `/collection/{addr|name}` | one shell | everything on chain (existing `get-collections-*` scripts: Flovatars, Flobits, Flobots, collectibles, packs, sales, claimables/DUST); FIND names via `get-collections-by-name-*`. **Big wallets**: when a whole-collection script fails (access-node computation limit) the loader falls back to ID batches (`flow/get-collections-batch.script.js`: IDs script, then 100 Flovatars / 200 Flobots / 200 collectibles / 400 Flobits / 100 FLOW-market sales per script, 4 in flight; by-name via FIND lookup). `?batched=1` forces the batched path. |
| Builders | shell | template SVGs from `/templates/data/{set}.js?v=` (proxied, immutable), checkout via existing tx JS + `POST /builder/search` (proxied) |
| Flobit item `/components/{id}/{owner}` | proxied SSR (edge-cacheable; Worker sets its own canonical) | existing chain scripts; "Type" row links the type page |
| Flobit type `/flobits/{templateFlowId}` | per template (snapshot: supply, floor, cheapest 12 listings, sales) | `GET /api/v2/templates/{id}` refresh on load |
| List images (marketplace, showrooms, transactions) | — | row `list_thumb` (R2 thumb.jpg) when the JPEG looks the same as the SVG (opaque background + not animated), else `/api/image/...` SVG (edge-cached) |

Login state: `/login/nonce` on every page (proxied, no-store) → `authenticated/address`; FCL session in localStorage; silent account-proof login + logout as on the legacy site.

## 6. API v2 contract (Laravel `routes/api.php`, prefix `/api/v2`)

Public (GET, `public, max-age=0, s-maxage=60, stale-while-revalidate=300`, `Access-Control-Allow-Origin: *`, no cookies, JSON errors):

| Endpoint | Returns |
|---|---|
| `meta` | `{api:2, network, contracts.flovatar, frontend_url, images_url, templates:{set:{version,script}}}` |
| `marketplace` | Laravel paginator (20/page) of open listings (`sale_offers` + lean `item`), params as legacy `/search`: `templatecat, flobitcat, templateid, rarity, sortby, pricemin, pricemax, page, dapper`; **v2 list shape (trimmed)**: only the fields the cards read, same JSON paths as the legacy rows (`id, item_type, flow_id, flow_address, price, is_dapper, listing_id, created_at, thumb, list_thumb, item{id, flow_id, flow_address, name, flovatar_template_id, mint, rarity, *_count, flow_metadata{metadata{rareCount, epicCount, legendaryCount, series, mint}}}`); no listing-event metadata/svg. Prod data: 406 KB → 9 KB per page (Flovatar filter 1.1 MB → 10 KB). `thumb` = best R2 render; `list_thumb` = thumb only when visually equal in a list (see §9) |
| `marketplace/stats` | `{total, avg, count, max, dustBurned, dustClaimed}` |
| `transactions` | paginator (50/page) of sales; params as `/transactions/search` (+ `address`) |
| `showroom/flovatars` / `flobots` / `collectibles` | paginators (20/page): `rarity, sortby (datenew/dateold/raritylow/rarityhigh), mintmin, mintmax, category, template` (+ `layer` for collectibles); trimmed rows (`sale_offers` = `[{price, is_dapper}]`, rarity metadata only), `thumb`, `list_thumb`. Prod data: Flovatars 79 → 7.5 KB, collectibles 53 → 7 KB per page |
| `templates?set=all|series1|flobot|dust|dust2` | lean template list (no svg) |
| `templates/{flowId}` | Flobit type: `{template{…, images}, url, minted, listings:[{currency,count,floor}], listed:[{id,mint,owner,price,currency}] (cheapest 12), sales{count,avg,max,last_at}}` (same data as the type page, `ItemSnapshot::flobitType`) |
| `packs` | `[{series,name,opened,unopened,total}]` (never secrets/signatures) |
| `rankings` | `{holders:[top 25 by Flovatars], top_sales:[25], stats}` |
| `{flovatars|flobots|collectibles|components}/{id}` | snapshot `{type,id,name,owner,creator,url,flagged,owner_blocked,images{og,full_png,thumb,downloads},listing{price,currency,is_dapper,seller,listing_id,listed_at}|null,sales[{price,currency,buyer,seller,tx,at}],updated_at, rarity, components, flobits | mint, series | template, destroyed, packed}` |

Auth (web group: session cookie + CSRF, `private, no-store`): `GET auth/nonce` `{nonce,message,authenticated,address}`, `GET auth/csrf` `{token}`, `GET auth/me` `{authenticated,address}`, `POST auth/login` `{address, proofType:'account-proof', nonce, signatures}` → `{result, address}`, `POST auth/logout`. The current frontend still uses the legacy `/login/nonce`, `/csrf-token`, `/login`, `/logout` (identical logic).

Throttle: `/api/v2` public routes skip the api-group 60/min/IP limiter (behind Cloudflare the client IP is an edge IP) and use `throttle:600,1` per route; the edge cache absorbs repeats.

## 7. Worker `flovatar-headless` (`headless/worker/worker.js`, `wrangler.toml`)

- Vars: `SITE` (R2 binding → flovatar-web; optional), `BUCKET_URL` (fallback: public bucket domain `https://headless-reboot.flovatar.com`), `SITE_PREFIX` (`testnet/site/` | `site/`), `ORIGIN` (`https://testnet.flovatar.com` | prod API origin), `NOINDEX` (`true` on testnet → robots `Disallow: /` + `X-Robots-Tag`).
- Order: robots/health → 301 owner URLs → non-GET or PROXY list → same-origin proxy (Location rewritten to the frontend host and owner URLs to canonical, `Set-Cookie` Domain stripped, HTMLRewriter fixes canonical/og:url/absolute links of SSR pages, `cf.cacheEverything` for user-agnostic GETs) → `/collection/*` shell → R2 object (`/x` → `x/index.html`, `x.html`; assets by extension, 304 via ETag) → item not built → origin fallback → `/flobits/{id}` not built → origin → asset not mirrored → origin → `404.html` (status 404). Proxied SSR HTML whose canonical is the legacy site-wide `https://flovatar.com/` gets its own URL as canonical. Content types include `.avif` (home hero).
- Content types by extension; HTML 60 s edge / 0 browser; `?id=` bundles immutable; other assets 1 day; `X-Headless: r2|origin|origin-fallback` for debugging; `/__headless/health`.
- OG: item pages carry og:title/og:url/og:image (og.jpg 1200×630 from images.flovatar.com, fallback legacy PNG/share.png) from the build; SSR fallback pages get og:url rewritten to the frontend host. Crawlers get the real HTML (no JS needed).
- Sitemap: `/sitemap.xml` = R2 object (shells + every item page), rebuilt hourly.
- Deploy: `cd headless/worker && npx wrangler@4 deploy` (token: Workers Scripts Edit + R2 read) or dashboard (paste worker.js, add vars + R2 binding). Custom domain = Worker → Settings → Domains & Routes → Custom domain (creates DNS + cert).
- Proxy headers (prod prep 2026-11-24): the Worker sends the visitor's `CF-Connecting-IP` as `X-Headless-Client-IP` (+ `X-Headless-Proxy: 1`); Laravel `HeadlessProxyRequest` (global middleware) uses it as the client IP only when the connection comes from Cloudflare's Workers range (`HEADLESS_WORKER_IPS`, default `2a06:98c0::/29`). Without it every Worker subrequest reaches the origin as `2a06:98c0:3600::103` and ALL visitors share one per-IP throttle bucket (`/login/nonce` 60/min is called on every page view). Edge-cached proxy responses get `max-age` reset to 0 when they carry `s-maxage` (the zone Browser Cache TTL rewrote it to 4 h on testnet API JSON). Origin `X-Robots-Tag` is dropped unless `NOINDEX`; direct hits on the origin host (`HEADLESS_ORIGIN_HOSTS`, prod `api.flovatar.com`) are noindex.

## 8. Environment keys (Laravel `.env`)

`HEADLESS_ENABLED`, `HEADLESS_FRONTEND_URL`, `HEADLESS_PREFIX` (testnet: `testnet`), `HEADLESS_DISK=r2web`, `R2_WEB_BUCKET=flovatar-web`, `HEADLESS_DEBOUNCE=30`, `HEADLESS_QUEUE=default`; R2 creds = the existing `R2_ACCESS_KEY_ID/R2_SECRET_ACCESS_KEY/R2_ENDPOINT` (scoped server token, IPv4 forced); `FLOVATAR_ACCOUNT_PROOF_ORIGINS` must include the frontend origin(s). Testnet values live in `~/secure/testnet/make-testnet-env.py` (regeneration keeps them).

## 9. Pitfalls found while building (keep for Sportvatar)

- The zones' WAF blocks user agents containing `headless` (and bot-like UAs) on HTML: the Worker's own subrequests use a normal Chrome UA (`FETCH_UA`), never a UA with "headless" in it. Same for test tools (set a Chrome UA in Chromium).
- FLOW listings on the legacy FlovatarMarketplace MOVE the NFT into the seller's sale collection: an owner check must look at `Collection.getIDs()` AND `FlovatarMarketplace.SalePublic.getFlovatarIDs()/getFlovatarComponentIDs()` (Dapper/Storefront listings keep the NFT in the collection).
- Forge kills deployments after ~10 min: long R2 uploads in the deploy script must run detached (`setsid nohup … &`, log to storage/logs).
- Flysystem `writeStream` uses multipart for big files → ETag `md5-N` never equals the file md5 → re-uploaded every build; use a single PUT below 64 MB and compare size for multipart objects.
- A per-IP API throttle at the origin is meaningless behind Cloudflare/the Worker (the origin sees edge IPs): use a high per-route limit and the edge cache.
- Feature tests: Laravel skips CSRF in unit tests; assert the login result instead of a 419.
- R2 `thumb.jpg` is flattened on WHITE: on the dark list cards it only replaces the SVG for opaque items (Flovatar/Flobot with a background, background Flobits) that are not animated (JPEG = frozen frame). Prod: 60% of Flovatars, 96% of Flobots, all DUST collectibles and 87% of Flobit templates are transparent → they keep the SVG. A transparent thumb variant (`thumb.webp` with alpha) in both renderers would let every static item use R2.
- Script computation limits (mainnet, measured 2026-11-24 by looping the contract helpers): ~800 Flovatars, ~2,000 Flobits, ~2,500 DUST collectibles per script; top real holders: 256 Flovatars, 2,495 Flobits (fails as one script), admin wallet 22,683 Flobits (57 batch scripts, ~17 MB, loads but the collection UI then blocks ~2 min rendering every Flobit).
- Model `flow_metadata` is cast `AsCollection`: convert with `->toArray()` before `array_*` helpers (a `json_decode` guard silently returns null).
- browser tooling: `Network.setUserAgentOverride` / emulation do not persist across separate browser_exec calls; a HeadlessChrome UA gets the zone WAF block page on proxied (origin) pages.
- Worker subrequests all come from ONE address at the origin (`2a06:98c0:3600::103`, verified on testnet by hashing the throttle keys): any per-IP limit becomes site-wide behind the Worker unless the visitor IP is passed and trusted (section 7). Port this to Sportvatar before its prod cutover.
- Cache-eligible responses through a proxied hostname without a Cache Rule get the zone Browser Cache TTL (4 h) in `max-age` even when the origin says `max-age=0`; fix in the Worker (or a Cache Rule with Browser TTL "respect origin").

## 10. Security notes

- Static HTML contains nothing user-specific (same rule as the edge-cached SSR pages). Snapshots never include SVG blobs, pack secrets or signatures.
- The Worker proxies only to `ORIGIN`; path traversal rejected; it never caches responses of auth/session paths (only allow-listed user-agnostic GETs get `cacheEverything`, and Laravel marks personal responses `private, no-store`).
- Account-proof `appIdentifier` = frontend origin: exact allowlist only (no wildcards like `*.workers.dev` — any account can create a worker there).
- WalletConnect (Flow Wallet mobile): the Reown project allowlist must include the frontend origin (`https://*.flovatar.com` covers `next-testnet.flovatar.com`, not workers.dev).

## 11. Rollback

- Frontend: point the hostname back (remove the Worker custom domain / route) — the server-rendered site keeps working unchanged on its own host the whole time. R2 objects are just files; `headless:build --force` rewrites everything.
- Code: `HEADLESS_ENABLED=false` + `config:cache` stops jobs/schedules; the JS falls back to legacy behaviour wherever `window.HEADLESS` is absent.

## 12. Testing

- `php artisan test --filter=HeadlessTest` (SQLite, no MySQL): cache headers/CORS/no cookies, JSON 404, paginator shapes, no svg/secrets, auth private, static build output (canonical/og/snapshot/owner) + unchanged skip, debounced job dispatch, off-switch.
- Local Worker: `npx wrangler@4 dev` with `BUCKET_URL` and no R2 binding (real workerd runtime, HTMLRewriter included).
- Mainnet read-only harness for the collection scripts (agent box): `~/work/headless/mainnet_harness.py` (extracts the Cadence from `resources/js/flow/*.script.js`, mainnet aliases, REST `/v1/scripts`) + `verify_batched.py kind:0xaddr …` (batched vs whole script, item-by-item identical except svg).
- List payload/render parity: compare card text/links/classes/images of legacy rows vs v2 rows in Chromium on a local prod-data server (`APP_ENV=hlocal`, `fw_local`).
- Browser E2E (agent box Chromium, normal Chrome UA): pages render, no JS errors, chain data for 0x2538515561c0c431, simulated FCL user sees owner buttons, OG tags, API headers; load check vs origin.

## 13. Porting to Sportvatar (checklist for the next agent)

Sportvatar (`flovatar/sportvatar`, same Laravel 12/Blade/Alpine/FCL structure, Sportvatars + Sportbits + packs):
1. Copy the generic pieces: `config/headless.php`, `app/Support/Headless.php`, `app/Services/Headless/{StaticSiteBuilder,ItemSnapshot}.php`, `app/Console/Commands/HeadlessBuild.php`, `app/Jobs/RegenerateStaticItem.php`, `app/Support/HeadlessRegen.php`, `app/Http/Middleware/PublicApiCache.php`, `app/Http/Controllers/Api/V2/*`, `resources/js/headless.js`, `resources/js/flow/find-item-owner.script.js`, `headless/worker/*`, `tests/Feature/HeadlessTest.php`, `resources/views/headless/404.blade.php`; disk `r2web` with `R2_WEB_BUCKET=sportvatar-web` (the server token already covers sportvatar-web).
2. Adapt the type maps: `ItemSnapshot::TYPES/SEGMENTS` → `sportvatar => Sportvatar::class (sportvatars)`, `sportbit => Sportbit/component class (sportbits)`; `StaticSiteBuilder::SHELLS` to Sportvatar's routes; `ITEM_TYPES`; Cadence in `find-item-owner.script.js` → `Sportvatar.CollectionPublic`, `Sportbit.CollectionPublic` at `0xSportvatar` (check the alias names in bootstrap.js).
3. Layout: same three edits (canonical, og:url, `window.HEADLESS` before app.js); item views: `@item-owner-resolved.window` + `resolveItemOwner()` at the top of the init function; list JS: `axios.post(path)` → `window.searchApi(path)` with a SEARCH_MAP for Sportvatar's search endpoints; card links → `window.itemUrl()`; post-tx redirects → `window.itemUrlAfterTx()`; collection view → `window.HEADLESS_ROUTE` in shell mode.
4. Sportvatar specifics: FLOW + DUC listings (currency column), free-pack claim pages (`/pack/claim`, session-gated → PROXY), fonts provisioned from `/home/forge/shared/sportvatar-fonts` (they're in `public/fonts` after deploy → uploaded to R2 by `--assets`; licensed fonts end up publicly readable either way, same as today on the origin).
5. `.env` (testnet.sportvatar.com): `HEADLESS_*`, `SPORTVATAR_ACCOUNT_PROOF_ORIGINS` += frontend origin; update `make-testnet-env.py`; deploy script step `headless:build --assets --shells --sitemap`.
6. Worker: second deployment `sportvatar-headless` from the same worker.js with its own vars (`ORIGIN=https://testnet.sportvatar.com`, bucket `sportvatar-web`, `SITE_PREFIX=testnet/site/`) — adjust the PROXY list to Sportvatar's dynamic paths.
7. Added on Flovatar after v1 (port the same way): batched collection fallback (`flow/get-collections-batch.script.js` + `collectionLoader()` in actions.js; Sportvatar/Sportbit contracts' helpers), trimmed v2 list rows (`PublicController::trimRows`: whitelist the fields the cards read, keep JSON paths), `list_thumb` rule (opaque + not animated only), Worker `.avif` + SSR canonical fix.

## Production (flovatar.com) — prepared 2026-11-24

- Origin host for the Worker: **`api.flovatar.com`** (existing proxied record already points to 2.29.186.126; Forge alias
  domain 2384396 on site 3514579 with a clone of the zone's Cloudflare Origin CA cert 3986190 → Full (strict) OK).
  `HEADLESS_ORIGIN_HOSTS=api.flovatar.com` → direct hits noindex.
- Frontend attach: Worker **route `flovatar.com/*`** in front of the existing proxied A record (not a Custom Domain):
  failure mode *fail open* serves the classic site if the Worker errors/exceeds limits; rollback = delete the route.
  `www.flovatar.com` is not routed (nginx 301 → apex).
- Prod `.env`: `HEADLESS_ENABLED=true`, `HEADLESS_FRONTEND_URL=https://flovatar.com`, `HEADLESS_PREFIX=` (empty → `site/`),
  `HEADLESS_DISK=r2web`, `R2_WEB_BUCKET=flovatar-web`, `HEADLESS_DEBOUNCE=30`, `HEADLESS_QUEUE=default`,
  `HEADLESS_ORIGIN_HOSTS=api.flovatar.com`. Cutover steps + rollback: KB `notes/headless-prod-cutover.md`.
