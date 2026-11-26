// flovatar-headless — serves the static headless Flovatar site from R2 and proxies the dynamic paths to Laravel.
//
// Architecture: docs/HEADLESS.md (repo) / flovatar-kb notes/headless-architecture.md.
// Objects: R2 bucket flovatar-web under SITE_PREFIX (testnet: "testnet/site/"), written by `php artisan headless:build`
// and by the per-item regeneration job.  Same-origin proxy to ORIGIN for /api/*, wallet login (/login*, /logout,
// /csrf-token), list searches, template data, images endpoints and the few pages that are not static yet, so the
// Laravel session cookie is first-party on the frontend host (no CORS credentials, no third-party cookies).
//
// Bindings / vars (wrangler.toml):
//   SITE        R2 bucket binding -> flovatar-web   (optional: without it objects are fetched from BUCKET_URL)
//   BUCKET_URL  public URL of the bucket (R2 custom domain), e.g. https://headless-reboot.flovatar.com
//   SITE_PREFIX "testnet/site/" (testnet) | "site/" (prod)
//   ORIGIN      Laravel origin, e.g. https://testnet.flovatar.com
//   NOINDEX     "true" on testnet (robots.txt Disallow + X-Robots-Tag)

const TYPES = {
  html: "text/html; charset=utf-8", css: "text/css; charset=utf-8", js: "application/javascript; charset=utf-8",
  mjs: "application/javascript; charset=utf-8", json: "application/json", xml: "application/xml; charset=utf-8",
  svg: "image/svg+xml", png: "image/png", jpg: "image/jpeg", jpeg: "image/jpeg", gif: "image/gif", webp: "image/webp", avif: "image/avif",
  ico: "image/x-icon", woff: "font/woff", woff2: "font/woff2", ttf: "font/ttf", otf: "font/otf",
  eot: "application/vnd.ms-fontobject", mp4: "video/mp4", webm: "video/webm", mp3: "audio/mpeg", wav: "audio/wav",
  pdf: "application/pdf", txt: "text/plain; charset=utf-8", webmanifest: "application/manifest+json",
  glb: "model/gltf-binary", gltf: "model/gltf+json", map: "application/json",
};

// Full browser UA for the Worker's own subrequests: the zone WAF blocks "bot-like" agents on HTML.
const FETCH_UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 flovatar-edge";

const HTML_CACHE = "public, max-age=0, s-maxage=60, stale-while-revalidate=300";
const ASSET_CACHE = "public, max-age=86400";
const IMMUTABLE = "public, max-age=31536000, immutable";

// Paths answered by Laravel (same origin through this Worker).
const PROXY = [
  /^\/api\//, /^\/login(\/|$)/, /^\/logout\/?$/, /^\/csrf-token\/?$/,
  /^\/search\/?$/, /^\/showroom\/(flobot\/|dust-collectible\/)?search\/?$/, /^\/transactions\/search\/?$/,
  /^\/(flobot\/)?builder\/search\/?$/, /^\/builder\/(asteroid|dust\/\d+)\/?$/,
  /^\/templates\//, /^\/storage\//, /^\/(flovatars|flobots|components|collectibles)\/json\//,
  /^\/(flovatars|flobots|collectibles)\/\d+\/0x[a-f0-9]{16}\/.+/, // downloads, twitter, ...
  /^\/components\//, /^\/dust\/accessories\//,                        // Flobit item pages: not static yet
  /^\/pack(\/|$)/, /^\/sportpacks\/?$/, /^\/subscribe\/?$/, /^\/treasure-hunting/,
  /^\/meme-maker\/?$/, /^\/fub(-sale|-test)?\/?$/, /^\/land-sale\/?$/, /^\/twitter\//, /^\/chat(\/|$)/,
  /^\/admin(\/|$)/, /^\/sanctum\//, /^\/livewire\//, /^\/filament\//, /^\/css\/filament\//, /^\/js\/filament\//,
  /^\/transactions\/0x[a-f0-9]{16}\/?$/, /^\/collection\/api\//,
  /^\/passkey(\/|$)/,                                                 // passkey sign-in + sponsored signing (session)
  /^\/quests(\/|$)/,                                                  // ĐUST Quests (session; only where DUST_QUESTS_ENABLED)
  /^\/\.well-known\/webauthn$/,                                       // WebAuthn related origins (rp_id = this host)
  /^\/__stealth\//,                                                  // stealth gate: secret URL sets the signed fl_s cookie
  /^\/fx\//,                                                         // lazy feature bundles (origin decides who gets them)
  /^\/islands(\/|$)/,                                                // Asteroid Islands (ISLANDS_MODE at the origin: belt, builder, island pages)
];

// Stealth launch (App\Support\Stealth): a request carrying the fl_s cookie gets its pages from the origin (which verifies the
// cookie and renders the stealth-only features) and never shares the edge cache. Without the cookie nothing changes: the
// static R2 site has no trace of stealth features. A forged cookie just gets the public page from the origin.
function hasStealthCookie(request) {
  return /(?:^|;\s*)fl_s=[^;]/.test(request.headers.get("cookie") || "");
}

// Anonymous, user-agnostic GETs that may be cached at the edge (honours the origin's Cache-Control).
const EDGE_CACHEABLE = [/^\/\.well-known\/webauthn$/, /^\/api\/islands\/belt(\/|$)/, /^\/api\/v2\/(?!auth\/)/, /^\/api\/image\//, /^\/templates\//, /^\/(flovatars|flobots|components|collectibles)\/json\//, /^\/components\/\d+\/0x[a-f0-9]{16}\/?$/];

const ITEM_OWNER_URL = /^\/(flovatars|flobots|collectibles)\/(\d+)\/(0x[a-f0-9]{16})\/?$/;
const ITEM_URL = /^\/(flovatars|flobots|collectibles)\/(\d+)\/?$/;
const TYPE_URL = /^\/flobits\/\d+\/?$/; // Flobit TYPE pages (prebuilt; origin renders the ones not built yet)

function typeFor(key) {
  const name = key.split("/").pop();
  const ext = name.includes(".") ? name.split(".").pop().toLowerCase() : "";
  return TYPES[ext];
}

function cfg(env) {
  return {
    prefix: env.SITE_PREFIX || "testnet/site/",
    origin: (env.ORIGIN || "https://testnet.flovatar.com").replace(/\/+$/, ""),
    bucketUrl: (env.BUCKET_URL || "").replace(/\/+$/, ""),
    noindex: String(env.NOINDEX || "") === "true",
  };
}

// ---------------------------------------------------------------- R2 access (binding or public bucket URL)

async function getObject(env, c, key, request) {
  if (env.SITE) {
    const opts = {};
    if (request && request.headers.get("if-none-match")) opts.onlyIf = { etagDoesNotMatch: request.headers.get("if-none-match").replace(/^W\//, "").replace(/"/g, "") };
    const obj = await env.SITE.get(c.prefix + key, opts);
    if (!obj) return null;
    return {
      body: "body" in obj ? obj.body : null, // no body = 304 (onlyIf failed)
      etag: obj.httpEtag,
      contentType: obj.httpMetadata && obj.httpMetadata.contentType,
      cacheControl: obj.httpMetadata && obj.httpMetadata.cacheControl,
      size: obj.size,
    };
  }
  if (!c.bucketUrl) return null;
  const r = await fetch(c.bucketUrl + "/" + c.prefix + key, {
    headers: { "User-Agent": FETCH_UA },
    cf: { cacheTtl: 30, cacheEverything: true },
  });
  if (r.status !== 200) return null;
  return {
    body: r.body,
    etag: r.headers.get("etag"),
    contentType: r.headers.get("content-type"),
    cacheControl: r.headers.get("cache-control"),
    size: Number(r.headers.get("content-length") || 0),
  };
}

function objectResponse(c, request, key, obj, status = 200, cacheOverride = null, typeKey = null) {
  const h = new Headers();
  const type = typeFor(typeKey || key) || obj.contentType || "application/octet-stream";
  h.set("Content-Type", type);
  let cache = cacheOverride;
  if (!cache) {
    if (type.startsWith("text/html")) cache = HTML_CACHE;
    else if (/@[a-f0-9]+$/.test(key)) cache = IMMUTABLE; // versioned bundle copy (js/app.js@{mix id})
    else if (/\.(js|css)$/.test(key)) cache = "public, max-age=300"; // unversioned bundle: may change on deploy
    else cache = obj.cacheControl && !/text\/html/.test(type) ? obj.cacheControl : ASSET_CACHE;
  }
  h.set("Cache-Control", cache);
  if (obj.etag) h.set("ETag", obj.etag);
  h.set("X-Content-Type-Options", "nosniff");
  h.set("X-Headless", "r2");
  if (c.noindex) h.set("X-Robots-Tag", "noindex, nofollow, noarchive");
  if (obj.body === null && status === 200) return new Response(null, { status: 304, headers: h });
  return new Response(request.method === "HEAD" ? null : obj.body, { status, headers: h });
}

// ---------------------------------------------------------------- origin proxy

function rewriteLocation(loc, c, frontendOrigin) {
  if (!loc) return loc;
  try {
    const u = new URL(loc, c.origin);
    if (u.origin === c.origin) {
      const m = u.pathname.match(ITEM_OWNER_URL); // origin redirects to owner URLs -> canonical item URL
      return frontendOrigin + (m ? `/${m[1]}/${m[2]}` : u.pathname) + u.search + u.hash;
    }
  } catch (e) { /* keep */ }
  return loc;
}

async function proxy(request, env, c, url, opts = {}) {
  const upstream = c.origin + url.pathname + url.search;
  const headers = new Headers(request.headers);
  // the origin sees every Worker subrequest from Cloudflare's Workers address: pass the visitor's IP so per-IP
  // limits (login nonce, POST /login, api group) stay per visitor (Laravel HeadlessProxyRequest trusts it only
  // from the Workers range)
  const clientIp = request.headers.get("cf-connecting-ip") || "";
  for (const k of ["host", "cf-connecting-ip", "cf-ipcountry", "cf-ray", "cf-visitor", "x-real-ip", "content-length"]) headers.delete(k);
  headers.set("X-Forwarded-Host", url.host);
  headers.set("X-Forwarded-Proto", "https");
  headers.set("X-Headless-Proxy", "1");
  if (clientIp) headers.set("X-Headless-Client-IP", clientIp); else headers.delete("X-Headless-Client-IP");
  if (!headers.get("user-agent")) headers.set("User-Agent", FETCH_UA);
  const init = { method: request.method, headers, redirect: "manual" };
  if (!["GET", "HEAD"].includes(request.method)) init.body = request.body;
  const cacheable = request.method === "GET" && !hasStealthCookie(request) && EDGE_CACHEABLE.some((re) => re.test(url.pathname));
  if (cacheable) init.cf = { cacheEverything: true };
  let r = await fetch(upstream, init);
  const out = new Headers(r.headers);
  if (out.has("location")) out.set("location", rewriteLocation(out.get("location"), c, url.origin));
  // Laravel cookies are host-only; drop a Domain attribute if one ever appears so they stick to this host
  const cookies = typeof r.headers.getSetCookie === "function" ? r.headers.getSetCookie() : [];
  if (cookies.length) {
    out.delete("set-cookie");
    for (const ck of cookies) out.append("set-cookie", ck.replace(/;\s*domain=[^;]*/i, ""));
  }
  // edge-cached proxy responses come back with the zone's Browser Cache TTL (4 h) in max-age: restore the origin's
  // browser TTL (0) for shared-cache responses so browsers don't keep marketplace/API JSON for hours
  const cc = out.get("cache-control");
  if (cc && /s-maxage=/.test(cc)) out.set("cache-control", cc.replace(/(^|,\s*)max-age=\d+/, "$1max-age=0"));
  out.set("X-Headless", opts.fallback ? "origin-fallback" : "origin");
  // the origin host itself is noindex; pages served here are indexable unless NOINDEX
  if (c.noindex) out.set("X-Robots-Tag", "noindex, nofollow, noarchive"); else out.delete("X-Robots-Tag");
  let resp = new Response(r.body, { status: r.status, statusText: r.statusText, headers: out });
  if ((out.get("content-type") || "").includes("text/html") && typeof HTMLRewriter !== "undefined") {
    resp = rewriteOriginHtml(resp, c, url.origin, url.pathname);
  }
  return resp;
}

// Server-rendered HTML coming through the proxy: canonical/og:url and absolute origin links -> this host.
function rewriteOriginHtml(resp, c, frontendOrigin, pathname = "/") {
  const fix = (v) => (v && v.startsWith(c.origin) ? frontendOrigin + v.slice(c.origin.length) : v);
  // legacy layout prints a site-wide canonical (https://flovatar.com/) on every SSR page -> this page's own URL;
  // item pages rendered at the origin (not built yet) use the owner-less canonical item URL
  const own = pathname.replace(ITEM_OWNER_URL, "/$1/$2");
  const canonical = (v) => (!v || /^https?:\/\/[^\/]+\/?$/.test(v) ? frontendOrigin + own : fix(v));
  const ogUrl = (v) => { const f = fix(v); return f && f.startsWith(frontendOrigin) ? frontendOrigin + f.slice(frontendOrigin.length).replace(ITEM_OWNER_URL, "/$1/$2") : f; };
  return new HTMLRewriter()
    .on('link[rel="canonical"]', { element(e) { e.setAttribute("href", canonical(e.getAttribute("href"))); } })
    .on('meta[property="og:url"]', { element(e) { e.setAttribute("content", ogUrl(e.getAttribute("content"))); } })
    .on("a[href]", { element(e) { const h = e.getAttribute("href"); if (h && h.startsWith(c.origin)) e.setAttribute("href", fix(h)); } })
    .transform(resp);
}

// Item page not built yet (minted seconds ago): render it at the origin (owner URL), cache briefly.
async function itemFallback(request, env, c, url, seg, id) {
  const r = await fetch(`${c.origin}/${seg}/${id}`, { redirect: "manual", headers: { "User-Agent": FETCH_UA } });
  const loc = r.headers.get("location");
  if (r.status >= 300 && r.status < 400 && loc) {
    const target = new URL(loc, c.origin);
    if (ITEM_OWNER_URL.test(target.pathname)) {
      const u = new URL(url);
      u.pathname = target.pathname;
      return proxy(new Request(u.toString(), request), env, c, u, { fallback: true });
    }
  }
  return null;
}

function withNoStore(resp) {
  const r = new Response(resp.body, resp);
  r.headers.set("Cache-Control", "private, no-store");
  return r;
}

async function notFound(request, env, c) {
  const obj = await getObject(env, c, "404.html", null);
  if (obj) return objectResponse(c, request, "404.html", obj, 404, "public, max-age=0, s-maxage=60");
  return new Response("Not found", { status: 404, headers: { "Content-Type": "text/plain" } });
}

function robots(c, url) {
  const body = c.noindex
    ? "User-agent: *\nDisallow: /\n"
    : `User-agent: *\nAllow: /\nSitemap: ${url.origin}/sitemap.xml\n`;
  return new Response(body, { headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=3600" } });
}

// ---------------------------------------------------------------- router

async function handle(request, env) {
  const c = cfg(env);
  const url = new URL(request.url);
  let path;
  try { path = decodeURIComponent(url.pathname); } catch { path = url.pathname; }
  if (path.includes("..") || path.includes("\0")) return new Response("Bad request", { status: 400 });

  if (path === "/robots.txt") return robots(c, url);
  if (path === "/__headless/health") return new Response(JSON.stringify({ ok: true, prefix: c.prefix, origin: c.origin, r2: !!env.SITE }), { headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });

  // canonical item URLs: /flovatars/{id}/{owner} -> /flovatars/{id}
  const owner = path.match(ITEM_OWNER_URL);
  if (owner && ["GET", "HEAD"].includes(request.method)) {
    return Response.redirect(`${url.origin}/${owner[1]}/${owner[2]}${url.search}`, 301);
  }

  if (!["GET", "HEAD"].includes(request.method) || PROXY.some((re) => re.test(path))) {
    return proxy(request, env, c, url);
  }

  // stealth (admin) visitors: every page from the origin, item pages via their owner URL (same as the not-built fallback)
  if (hasStealthCookie(request) && !typeFor(path.replace(/\/+$/, ""))) {
    const it = path.match(ITEM_URL);
    if (it) {
      const fb = await itemFallback(request, env, c, url, it[1], it[2]);
      if (fb) return withNoStore(fb);
      return notFound(request, env, c);
    }
    return withNoStore(await proxy(request, env, c, url));
  }

  // shells: wallet comes from the URL at runtime
  if (/^\/collection\/[^\/]+\/?$/.test(path)) {
    const obj = await getObject(env, c, "_shells/collection.html", request);
    if (obj) {
      const resp = objectResponse(c, request, "_shells/collection.html", obj);
      // one shell for every wallet: canonical/og:url = this wallet's URL (the build can only print /collection)
      if (resp.status !== 200 || typeof HTMLRewriter === "undefined") return resp;
      const self = url.origin + url.pathname.replace(/\/+$/, "");
      return new HTMLRewriter()
        .on('link[rel="canonical"]', { element(e) { e.setAttribute("href", self); } })
        .on('meta[property="og:url"]', { element(e) { e.setAttribute("content", self); } })
        .transform(resp);
    }
    return proxy(request, env, c, url, { fallback: true });
  }
  if (path === "/collection" || path === "/collection/") return Response.redirect(`${url.origin}/`, 302);

  // static objects
  let p = path.replace(/^\/+/, "");
  // mix-versioned bundle: /js/app.js?id=X -> the copy of exactly that build (pages built before a deploy keep theirs)
  const vid = url.searchParams.get("id");
  if (vid && /^[a-f0-9]{8,64}$/.test(vid) && /^(js|css)\/[^\/]+\.(js|css)$/.test(p)) {
    const obj = await getObject(env, c, `${p}@${vid}`, request);
    if (obj) return objectResponse(c, request, `${p}@${vid}`, obj, 200, null, p);
  }
  const candidates = p === "" ? ["index.html"] : p.endsWith("/") ? [p + "index.html"] : typeFor(p) ? [p] : [p + "/index.html", p + ".html"];
  for (const key of candidates) {
    const obj = await getObject(env, c, key, request);
    if (obj) return objectResponse(c, request, key, obj);
  }

  const item = path.match(ITEM_URL);
  if (item) {
    const fb = await itemFallback(request, env, c, url, item[1], item[2]);
    if (fb && fb.status === 200) return fb;
    return notFound(request, env, c);
  }
  if (TYPE_URL.test(path)) {
    const r = await proxy(request, env, c, url, { fallback: true });
    if (r.status === 200) return r;
    return notFound(request, env, c);
  }
  // assets not mirrored on R2 (e.g. /webgl/*): try the origin, otherwise 404
  if (typeFor(p)) {
    const r = await proxy(request, env, c, url, { fallback: true });
    if (r.status !== 404) return r;
  }
  return notFound(request, env, c);
}

export default {
  async fetch(request, env, ctx) {
    try {
      return await handle(request, env, ctx);
    } catch (e) {
      return new Response("Worker error: " + (e && e.message ? e.message : String(e)), { status: 502, headers: { "Content-Type": "text/plain", "Cache-Control": "no-store" } });
    }
  },
};
