#!/usr/bin/env node
/*
 * Builds resources/passkey/templates.json from resources/js/flow/*.tx.js — the server-side copy of every wallet
 * transaction's Cadence, keyed by the SHA-256 of the exact string the browser hands to fcl.mutate().
 *
 * The passkey signing path (resources/js/flow/fcl-tx.js) sends only {sha256, args}; the server looks the Cadence up
 * here, checks it is on the allowlist (config/passkey-templates.php, hash-pinned) and builds the transaction itself,
 * so the sponsor never co-signs code it does not know.
 *
 *   node bin/passkey-templates.js          # (re)write the manifest
 *   node bin/passkey-templates.js --check  # exit 1 if the manifest is stale (CI / tests)
 *
 * Each module is evaluated with stub `fcl` / `t` objects: the function is called with dummy arguments, the options
 * passed to fcl.mutate are captured (cadence + the FCL argument types).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const root = path.resolve(__dirname, '..');
const flowDir = path.join(root, 'resources/js/flow');
const outFile = path.join(root, 'resources/passkey/templates.json');

function typeProxy() {
    // t.UInt64 -> 'UInt64'; t.Optional(t.UInt64) -> 'Optional(UInt64)'; t.Array(t.UInt64) -> 'Array(UInt64)';
    // t.Dictionary({key: t.UInt64, value: t.UFix64}) -> 'Dictionary(UInt64,UFix64)'
    return new Proxy({}, {
        get(_, name) {
            const label = String(name);
            const fn = function (inner) {
                if (inner && typeof inner === 'object' && !Array.isArray(inner) && inner.key) {
                    return label + '(' + inner.key + ',' + inner.value + ')';
                }
                if (Array.isArray(inner)) {
                    return label + '(' + inner.join(',') + ')';
                }
                return label + '(' + inner + ')';
            };
            fn.toString = () => label;
            fn[Symbol.toPrimitive] = () => label;
            return fn;
        },
    });
}

function evaluate(file) {
    let src = fs.readFileSync(file, 'utf8');
    const names = [];
    src = src.replace(/^\s*import[^\n]*from\s+["'][^"']+["'];?\s*$/mg, '');
    src = src.replace(/export\s+async\s+function\s+(\w+)/g, (m, n) => { names.push(n); return 'async function ' + n; });
    src = src.replace(/export\s+function\s+(\w+)/g, (m, n) => { names.push(n); return 'function ' + n; });
    if (names.length !== 1) {
        throw new Error(path.basename(file) + ': expected exactly one exported function, found ' + names.length);
    }
    const captured = [];
    const fcl = {
        mutate: async (opts) => { captured.push(opts); return 'tx'; },
        authz: 'AUTHZ',
        currentUser: { authorization: 'AUTHZ' },
    };
    // eslint-disable-next-line no-new-func
    // floatArg / floatArgFull are window globals from resources/js/utils.js (number -> UFix64/Fix64 string)
    const factory = new Function('fcl', 't', 'process', 'floatArg', 'floatArgFull', src + '\nreturn ' + names[0] + ';');
    const fn = factory(fcl, typeProxy(), { env: new Proxy({}, { get: (_, k) => 'ENV_' + String(k) }) }, String, String);
    return { fn, captured, name: names[0] };
}

async function build() {
    const files = fs.readdirSync(flowDir).filter((f) => f.endsWith('.tx.js')).sort();
    const templates = {};
    for (const f of files) {
        const { fn, captured, name } = evaluate(path.join(flowDir, f));
        await fn('1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', [], [], [], [], []);
        if (captured.length !== 1) {
            throw new Error(f + ': expected one fcl.mutate call, got ' + captured.length);
        }
        const opts = captured[0];
        if (typeof opts.cadence !== 'string') {
            throw new Error(f + ': cadence is not a string');
        }
        const extra = Object.keys(opts).filter((k) => ['cadence', 'args', 'limit'].indexOf(k) < 0);
        let argTypes = [];
        if (typeof opts.args === 'function') {
            argTypes = opts.args((value, type) => String(type), typeProxy()).map(String);
        }
        const id = f.replace(/\.tx\.js$/, '');
        templates[id] = {
            id,
            file: 'resources/js/flow/' + f,
            function: name,
            sha256: crypto.createHash('sha256').update(opts.cadence, 'utf8').digest('hex'),
            argTypes,
            limit: opts.limit || null,
            customSigners: extra.length > 0,
            cadence: opts.cadence,
        };
    }
    return { generated_by: 'bin/passkey-templates.js', templates };
}

build().then((manifest) => {
    const json = JSON.stringify(manifest, null, 2) + '\n';
    if (process.argv.includes('--check')) {
        const current = fs.existsSync(outFile) ? fs.readFileSync(outFile, 'utf8') : '';
        if (current !== json) {
            console.error('resources/passkey/templates.json is stale: run node bin/passkey-templates.js');
            process.exit(1);
        }
        console.log('passkey templates manifest up to date (' + Object.keys(manifest.templates).length + ' templates)');
        return;
    }
    fs.mkdirSync(path.dirname(outFile), { recursive: true });
    fs.writeFileSync(outFile, json);
    console.log('wrote ' + path.relative(root, outFile) + ' (' + Object.keys(manifest.templates).length + ' templates)');
}).catch((e) => { console.error(e.message || e); process.exit(1); });
