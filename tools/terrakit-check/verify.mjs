#!/usr/bin/env node
// TerraKit wallet check — run the page's Cadence script + classifier against an access node (read-only, no signing).
//
//   node tools/terrakit-check/verify.mjs mainnet 0x2a0eccae942667be 0x989eaeed9ce94cd2
//   node tools/terrakit-check/verify.mjs testnet --ranges 13-15 0x7dffb779b5324e70
//
// Prints one JSON line per address: wallet class, sign state, voucher grid (category/rarity counts) and TerraKit packs.
// Same Cadence (resources/js/terrakit/check.cdc.mjs) and same classifier (classify.mjs) as /terrakit/check.
import {TERRAKIT_CHECK_CADENCE} from '../../resources/js/terrakit/check.cdc.mjs';
import {classifyWallet, inRanges, mergeVouchers, normalizeAddress, summarizeVouchers} from '../../resources/js/terrakit/classify.mjs';

const NETS = {
    mainnet: {rest: 'https://rest-mainnet.onflow.org', contract: '0x921ea449dffec68a', ranges: [[10800, 15799]]},
    testnet: {rest: 'https://rest-testnet.onflow.org', contract: '0x2c6a4c4342aeca55', ranges: [[13, 15]]},
};
const SLICE = 4000;

function decode(v) {
    if (v === null || v === undefined) { return v; }
    switch (v.type) {
    case 'Optional': return v.value === null ? null : decode(v.value);
    case 'Array': return v.value.map(decode);
    case 'Dictionary': return Object.fromEntries(v.value.map((kv) => [decode(kv.key), decode(kv.value)]));
    case 'Struct': case 'Resource': case 'Event':
        return Object.fromEntries(v.value.fields.map((f) => [f.name, decode(f.value)]));
    default: return v.value;
    }
}

async function script(net, address, start) {
    const cfg = NETS[net];
    const code = TERRAKIT_CHECK_CADENCE.replace(/from 0xFlovatar/g, 'from ' + cfg.contract);
    const args = [
        {type: 'Address', value: address},
        {type: 'Address', value: cfg.contract},
        {type: 'Array', value: ['land', 'fub', 'flobotkit'].map((c) => ({type: 'String', value: c}))},
        {type: 'Int', value: String(start)},
        {type: 'Int', value: String(SLICE)},
    ].map((a) => Buffer.from(JSON.stringify(a)).toString('base64'));
    const r = await fetch(cfg.rest + '/v1/scripts?block_height=sealed', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({script: Buffer.from(code).toString('base64'), arguments: args}),
    });
    const text = await r.text();
    if (!r.ok) { throw new Error(text.slice(0, 400)); }
    return decode(JSON.parse(Buffer.from(JSON.parse(text), 'base64').toString()));
}

async function check(net, address, ranges) {
    let start = 0;
    let acc = null;
    for (;;) {
        const r = await script(net, address, start);
        if (!acc) { acc = r; } else { acc.vouchers = mergeVouchers(acc.vouchers, r.vouchers); }
        start += SLICE;
        if (start >= Number(r.componentTotal || 0)) { break; }
    }
    const w = classifyWallet(acc);
    const s = summarizeVouchers(acc.vouchers);
    const grid = Object.fromEntries(s.rows.map((row) => [row.category, row.cells.map((c) => c.count).join('/')]));
    return {
        address, network: net, class: w.class, canSign: w.canSign, formerBlocto: w.formerBlocto, bloctoKeysActive: w.bloctoKeysActive,
        activeKeys: w.activeKeys, revokedKeys: w.revokedKeys, dapperReceiver: acc.dapperReceiver,
        vouchers: s.total, grid, listed: s.listed, terrakitPacks: (acc.packs || []).filter((id) => inRanges(id, ranges)).length,
        flobitsScanned: Number(acc.componentTotal || 0),
    };
}

const argv = process.argv.slice(2);
const net = argv.shift();
if (!NETS[net]) { console.error('usage: verify.mjs mainnet|testnet [--ranges a-b,c-d] 0x...'); process.exit(2); }
let ranges = NETS[net].ranges;
const ri = argv.indexOf('--ranges');
if (ri >= 0) {
    ranges = argv[ri + 1].split(',').map((p) => p.split('-').map(Number)).map(([a, b]) => [a, b ?? a]);
    argv.splice(ri, 2);
}
let failed = 0;
for (const raw of argv) {
    const address = normalizeAddress(raw);
    if (!address) { console.log(JSON.stringify({address: raw, error: 'invalid address'})); failed++; continue; }
    try {
        console.log(JSON.stringify(await check(net, address, ranges)));
    } catch (e) {
        console.log(JSON.stringify({address, error: String(e.message || e).slice(0, 300)}));
        failed++;
    }
}
process.exit(failed ? 1 : 0);
