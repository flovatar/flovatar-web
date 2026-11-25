#!/usr/bin/env python3
"""Stealth launch parity check (flovatar-web; KB notes/prod-release-stealth.md, notes/mainnet-launch-stealth.md).

Proves that anonymous visitors and crawlers see NO trace of features in stealth mode, and that a stealth visitor
(secret-URL cookie) does. Stdlib only, Python 3.8+. Never prints secrets (the stealth URL is read from a file).

  snapshot BASE DIR   fetch the sample pages anonymously (Chrome UA, no cookies) and store status, key headers and the
                      normalised body (CSRF tokens, nonces, ?id= asset hashes, Cloudflare tokens removed) in DIR.
  diff DIR_A DIR_B    compare two snapshots (e.g. before/after a release, or mode=off vs mode=stealth). Exit 1 when a
                      page differs; prints a short unified diff per page so legitimate release changes can be reviewed.
  check BASE          hard gate, exit 1 on any failure:
                      - no stealth marker (window.DUST_SWAP, window.__FX, /fx/ bundles, passkey modal, ĐUST market box,
                        quests nav, …) in any anonymous page or in the sitemap;
                      - stealth-only routes (/passkey, /quests, /terrakit/check, /.well-known/webauthn, /fx/*.js) answer 404
                        without a cookie; /__stealth/<wrong> is a plain 404 without Set-Cookie;
                      - with --stealth-url-file FILE (a file holding https://host/__stealth/<secret>, chmod 600): the
                        secret URL sets fl_s, the features listed in --expect are visible with it, those responses are
                        private/no-store, and the same pages fetched anonymously right after are still clean (no edge
                        cache poisoning).

Examples
  bin/stealth-parity.py snapshot https://flovatar.com ~/parity/before
  bin/stealth-parity.py snapshot https://flovatar.com ~/parity/after
  bin/stealth-parity.py diff ~/parity/before ~/parity/after
  bin/stealth-parity.py check https://flovatar.com --stealth-url-file ~/secure/prod-stealth-url.txt \
      --expect passkey,dust_market,quests,swap,terrakit
Global options (before the command): --paths-file FILE (one path per line; default list below), --keep-asset-ids.
check options: --features a,b (only these must be invisible; default all five: passkey,dust_market,quests,swap,terrakit),
               --expect a,b (must be visible with the stealth cookie), --stealth-url-file FILE.
"""
import argparse
import difflib
import hashlib
import http.cookiejar
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request

UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'

# Public pages that render the shared layout/navigation and the item/list partials where stealth features hook in.
DEFAULT_PATHS = [
    '/', '/marketplace',
    '/showroom', '/showroom/flobot', '/showroom/dust-collectible', '/transactions',
    '/builder', '/builder/flobot', '/builder/dust', '/dust', '/flovatars/1', '/flovatars/2', '/flobots/1',
    '/flobits/1', '/flobits/41', '/collectibles/1', '/sitemap.xml', '/robots.txt',
    '/api/v2/templates?set=dust', '/api/v2/rankings', '/api/v2/flovatars/1',
]

# Routes that exist only for stealth/on features: must be a plain 404 for anonymous requests in stealth/off mode.
FEATURE_ROUTES = {
    'passkey': ['/passkey', '/passkey/session', '/.well-known/webauthn', '/fx/p.js', '/fx/p.css'],
    'dust_market': ['/fx/m.js', '/fx/m.css'],
    'quests': ['/quests', '/fx/q.js', '/fx/q.css'],
    'swap': ['/fx/s.js', '/fx/s.css'],
    'terrakit': ['/terrakit/check', '/fx/t.js', '/fx/t.css'],
}
FEATURES = list(FEATURE_ROUTES)

# Markup/config only rendered when a feature is visible (resources/views: layouts/guest, partials/navigation,
# partials/passkey-auth, partials/dust-market, partials/dust-swap-*, terrakit/check). Case-sensitive substrings.
MARKERS = {
    'any': ['window.__FX'],
    'passkey': ['/fx/p.', 'signin-modal', 'passkey-modal', 'passkey-email', 'Signed in with', "open-passkey-account"],
    'dust_market': ['/fx/m.', 'window.DUST_MARKET', 'dust-market-box', '$store.dust.live'],
    'quests': ['/fx/q.', 'window.DUST_QUESTS', 'data-quests=', 'href="/quests"'],
    'swap': ['/fx/s.', 'window.DUST_SWAP', 'dust-rate-chip', 'href="/dust#swap"', 'dust-swap-widget'],
    'terrakit': ['/fx/t.', 'window.TERRAKIT_CHECK', 'href="/terrakit/check"'],
}
SITEMAP_FORBIDDEN = ['/passkey', '/quests', '/terrakit/check', '/__stealth', '/fx/']

NORMALISE = [
    (re.compile(r'(<meta name="csrf-token" content=")[^"]*'), r'\1X'),
    (re.compile(r'(name="_token" value=")[^"]*'), r'\1X'),
    (re.compile(r'(nonce=")[^"]*'), r'\1X'),
    (re.compile(r'(/cdn-cgi/l/email-protection#)[0-9a-f]+'), r'\1X'),
    (re.compile(r'(data-cfemail=")[0-9a-f]+'), r'\1X'),
    (re.compile(r'(cf_chl_[a-z_]*[=:]\s*")[^"]*'), r'\1X'),
    (re.compile(r'"(csrf|nonce|token)"\s*:\s*"[^"]*"'), r'"\1":"X"'),
]
ASSET_ID = re.compile(r'\?id=[0-9a-f]{6,}')
HEADERS = ['content-type', 'cache-control', 'x-headless', 'x-robots-tag']


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def opener(jar=None, follow=True):
    handlers: list = [urllib.request.HTTPCookieProcessor(jar if jar is not None else http.cookiejar.CookieJar())]
    if not follow:
        handlers.append(NoRedirect())
    return urllib.request.build_opener(*handlers)


def fetch(op, url):
    req = urllib.request.Request(url, headers={'User-Agent': UA, 'Accept': 'text/html,application/json;q=0.9,*/*;q=0.8',
                                               'Accept-Language': 'en'})
    try:
        r = op.open(req, timeout=60)
        status, hdrs, body = r.status, r.headers, r.read()
    except urllib.error.HTTPError as e:
        status, hdrs, body = e.code, e.headers, e.read()
    return status, hdrs, body.decode('utf-8', 'replace')


def normalise(body, keep_ids=False):
    for rx, rep in NORMALISE:
        body = rx.sub(rep, body)
    if not keep_ids:
        body = ASSET_ID.sub('?id=X', body)
    return body


def read_paths(args):
    if args.paths_file:
        return [l.strip() for l in open(os.path.expanduser(args.paths_file)) if l.strip() and not l.startswith('#')]
    return DEFAULT_PATHS


def slug(path):
    return re.sub(r'[^A-Za-z0-9._-]+', '_', path.strip('/') or 'index')[:120] + '-' + hashlib.sha1(path.encode()).hexdigest()[:6]


def markers_in(body, features=None):
    found = []
    for f, ms in MARKERS.items():
        if features is not None and f not in features:
            continue
        for m in ms:
            if m in body:
                found.append(f + ':' + m)
    return found


# ------------------------------------------------------------------ commands

def cmd_snapshot(args):
    base = args.base.rstrip('/')
    out = os.path.expanduser(args.dir)
    os.makedirs(out, exist_ok=True)
    op = opener()
    index = {'base': base, 'at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()), 'pages': {}}
    for p in read_paths(args):
        op = opener()  # fresh, cookie-less client per page (an anonymous first visit)
        st, h, body = fetch(op, base + p)
        nb = normalise(body, args.keep_asset_ids)
        f = slug(p) + '.txt'
        with open(os.path.join(out, f), 'w') as fh:
            fh.write(nb)
        index['pages'][p] = {'status': st, 'file': f, 'sha256': hashlib.sha256(nb.encode()).hexdigest(),
                             'headers': {k: h.get(k, '') for k in HEADERS},
                             'set_cookie': sorted({c.split('=', 1)[0] for c in (h.get_all('Set-Cookie') or [])}),
                             'markers': markers_in(body)}
        print('%3d %s %s%s' % (st, p, index['pages'][p]['sha256'][:12], ('  MARKERS ' + ','.join(index['pages'][p]['markers'])) if index['pages'][p]['markers'] else ''))
    with open(os.path.join(out, 'index.json'), 'w') as fh:
        json.dump(index, fh, indent=1)
    print('snapshot -> %s (%d pages)' % (out, len(index['pages'])))
    return 0


def cmd_diff(args):
    a_dir, b_dir = os.path.expanduser(args.a), os.path.expanduser(args.b)
    a = json.load(open(os.path.join(a_dir, 'index.json')))['pages']
    b = json.load(open(os.path.join(b_dir, 'index.json')))['pages']
    bad = 0
    for p in sorted(set(a) | set(b)):
        if p not in a or p not in b:
            print('ONLY IN %s: %s' % ('A' if p in a else 'B', p))
            bad += 1
            continue
        pa, pb = a[p], b[p]
        diffs = []
        if pa['status'] != pb['status']:
            diffs.append('status %s -> %s' % (pa['status'], pb['status']))
        for k in HEADERS:
            if pa['headers'].get(k) != pb['headers'].get(k):
                diffs.append('%s: %r -> %r' % (k, pa['headers'].get(k), pb['headers'].get(k)))
        if pa['set_cookie'] != pb['set_cookie']:
            diffs.append('set-cookie %s -> %s' % (pa['set_cookie'], pb['set_cookie']))
        new_markers = sorted(set(pb['markers']) - set(pa['markers']))
        if new_markers:
            diffs.append('NEW STEALTH MARKERS in B: ' + ', '.join(new_markers))
        if pa['sha256'] != pb['sha256']:
            ta = open(os.path.join(a_dir, pa['file'])).read().splitlines()
            tb = open(os.path.join(b_dir, pb['file'])).read().splitlines()
            ud = list(difflib.unified_diff(ta, tb, 'A' + p, 'B' + p, n=1, lineterm=''))
            diffs.append('body differs (%d diff lines):\n    ' % len(ud) + '\n    '.join(l[:220] for l in ud[:args.max_lines]))
        if diffs:
            bad += 1
            print('DIFF %s\n  %s' % (p, '\n  '.join(diffs)))
        else:
            print('same %s' % p)
    print('%d page(s) differ' % bad if bad else 'PARITY OK: all %d pages identical' % len(a))
    return 1 if bad else 0


def cmd_check(args):
    base = args.base.rstrip('/')
    fails = []
    paths = read_paths(args)
    # features that must be invisible to anonymous visitors (all by default; on testnet only the ones in stealth mode)
    feats = [f for f in (args.features or ','.join(FEATURES)).split(',') if f]
    unknown = [f for f in feats if f not in FEATURES]
    if unknown:
        raise SystemExit('unknown feature(s): %s (known: %s)' % (unknown, FEATURES))
    scan = feats + ['any'] if set(feats) == set(FEATURES) else feats
    routes = [r for f in feats for r in FEATURE_ROUTES[f]]
    bundles = [r for r in routes if r.startswith('/fx/') and r.endswith('.js')]

    def anon_scan(label):
        for p in paths:
            st, h, body = fetch(opener(), base + p)
            m = markers_in(body, scan)
            if p == '/sitemap.xml':
                m += ['sitemap:' + s for s in SITEMAP_FORBIDDEN if s in body]
            if m:
                fails.append('%s %s: %s' % (label, p, ', '.join(m)))
            print('%s %3d %s %s' % (label, st, p, 'MARKERS ' + ','.join(m) if m else 'clean'))

    anon_scan('anon')
    for p in routes:
        st, h, body = fetch(opener(follow=False), base + p)
        ok = st == 404
        print('route %3d %s %s' % (st, p, 'ok' if ok else 'EXPOSED'))
        if not ok:
            fails.append('stealth route %s answered %s (expected 404)' % (p, st))
    st, h, body = fetch(opener(follow=False), base + '/__stealth/' + 'x' * 43)
    sc = [c.split('=', 1)[0] for c in (h.get_all('Set-Cookie') or [])]
    print('wrong secret %d set-cookie=%s' % (st, sc))
    if st != 404 or 'fl_s' in sc:
        fails.append('/__stealth/<wrong> answered %s set-cookie %s' % (st, sc))

    if args.stealth_url_file:
        url = open(os.path.expanduser(args.stealth_url_file)).read().strip()
        if not re.match(r'^https://[^/]+/__stealth/[A-Za-z0-9_-]{32,200}$', url):
            fails.append('stealth url file: unexpected format')
        else:
            url = base + url[url.index('/__stealth/'):]  # same host as BASE (frontend), never printed
            jar = http.cookiejar.CookieJar()
            st, h, _ = fetch(opener(jar, follow=False), url)
            names = [c.name for c in jar]
            print('secret url %d cookie set=%s' % (st, 'fl_s' in names))
            if 'fl_s' not in names:
                fails.append('secret URL did not set fl_s (status %s)' % st)
            expect = [f for f in (args.expect or '').split(',') if f]
            seen = set()
            for p in paths:
                st, h, body = fetch(opener(jar), base + p)
                m = markers_in(body)
                vis = sorted({x.split(':', 1)[0] for x in m} - {'any'})
                seen.update(vis)
                cc = h.get('cache-control', '')
                if m and p not in ('/sitemap.xml', '/robots.txt') and 'no-store' not in cc and 'private' not in cc:
                    fails.append('stealth page %s is shareable: Cache-Control %r' % (p, cc))
                print('stealth %3d %s %s cc=%s' % (st, p, ','.join(vis) or '-', cc))
            for f in expect:
                if f not in seen:
                    fails.append('feature %s not visible with the stealth cookie' % f)
            for p in bundles:
                st, h, body = fetch(opener(jar, follow=False), base + p)
                print('stealth bundle %3d %s cc=%s' % (st, p, h.get('cache-control', '')))
                f = [k for k, v in FEATURE_ROUTES.items() if p in v][0]
                if f in expect and (st != 200 or 'no-store' not in h.get('cache-control', '')):
                    fails.append('bundle %s for a stealth visitor: %s %r' % (p, st, h.get('cache-control', '')))
            anon_scan('anon-after')  # the stealth visit must not have poisoned any shared cache

    print('\n' + ('FAIL\n  ' + '\n  '.join(fails) if fails else 'STEALTH CHECK OK'))
    return 1 if fails else 0


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--paths-file')
    ap.add_argument('--keep-asset-ids', action='store_true')
    sp = ap.add_subparsers(dest='cmd', required=True)
    s = sp.add_parser('snapshot')
    s.add_argument('base')
    s.add_argument('dir')
    d = sp.add_parser('diff')
    d.add_argument('a')
    d.add_argument('b')
    d.add_argument('--max-lines', type=int, default=30)
    c = sp.add_parser('check')
    c.add_argument('base')
    c.add_argument('--stealth-url-file')
    c.add_argument('--features', help='features that must be invisible anonymously (default: all = %s)' % ','.join(FEATURES))
    c.add_argument('--expect', help='features that must be visible with the stealth cookie, e.g. swap,dust_market')
    args = ap.parse_args()
    return {'snapshot': cmd_snapshot, 'diff': cmd_diff, 'check': cmd_check}[args.cmd](args)


if __name__ == '__main__':
    sys.exit(main())
