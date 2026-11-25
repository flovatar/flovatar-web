#!/usr/bin/env node
// After `mix`: move the lazy feature bundles (webpack chunks + per-feature CSS) from public/fx to storage/app/fx, so
// nginx and the headless R2 asset mirror never serve them; App\Http\Controllers\FxAssetController hands them out only
// to requests that may see the feature (App\Support\Stealth). Also drops their entries from public/mix-manifest.json.
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const src = path.join(root, 'public', 'fx');
const dst = path.join(root, 'storage', 'app', 'fx');

if (fs.existsSync(src)) {
    fs.mkdirSync(dst, {recursive: true});
    for (const f of fs.readdirSync(dst)) fs.rmSync(path.join(dst, f), {force: true});
    let n = 0;
    for (const f of fs.readdirSync(src)) {
        if (!/^[a-z]{1,8}\.(js|css)$/.test(f)) {
            // never silently drop an unexpected chunk: every other chunk must stay in public/js (webpack.mix.js)
            if (!/\.LICENSE\.txt$/.test(f)) { console.error(`fx-assets: unexpected file public/fx/${f}`); process.exitCode = 1; }
            continue;
        }
        fs.copyFileSync(path.join(src, f), path.join(dst, f));
        n++;
    }
    if (!process.exitCode) fs.rmSync(src, {recursive: true, force: true});
    console.log(`fx-assets: ${n} bundle file(s) -> storage/app/fx`);
}
const manifest = path.join(root, 'public', 'mix-manifest.json');
if (fs.existsSync(manifest)) {
    const m = JSON.parse(fs.readFileSync(manifest, 'utf8'));
    let changed = false;
    for (const k of Object.keys(m)) if (k.startsWith('/fx/')) { delete m[k]; changed = true; }
    if (changed) fs.writeFileSync(manifest, JSON.stringify(m, null, 4));
}
