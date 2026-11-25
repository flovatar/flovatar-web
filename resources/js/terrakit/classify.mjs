/**
 * TerraKit wallet check — pure helpers (no DOM, no FCL), shared by the page (resources/js/app/terrakit-check.js),
 * the unit tests (tests/js/terrakit-classify.test.mjs) and the chain verifier (tools/terrakit-check/verify.mjs).
 * KB notes/terrakit-check.md has the rules and the mainnet evidence behind them.
 *
 * Key encodings come from the Cadence script (resources/js/terrakit/check.cdc.mjs):
 *   signAlgo: 1 = ECDSA_P256, 2 = ECDSA_secp256k1 · hashAlgo: 1 = SHA2_256, 3 = SHA3_256 · weight: "1000.00000000"
 */

export const SIGN_P256 = 1;
export const SIGN_SECP256K1 = 2;
export const HASH_SHA2 = 1;
export const HASH_SHA3 = 3;

export const CATEGORY_ORDER = ['land', 'fub', 'flobotkit'];
export const RARITY_ORDER = ['common', 'epic', 'legendary'];

export const CATEGORY_LABELS = {
    land: 'Asteroid Island voucher',
    fub: 'FUB voucher',
    flobotkit: 'Flobot Constructor Kit',
};
export const RARITY_LABELS = {common: 'Common', epic: 'Epic', legendary: 'Legendary'};

const ADDRESS_RE = /^0x[0-9a-f]{16}$/;

/** "0xABC…" / "abc…" (16 hex) → "0xabc…" or null. Surrounding spaces are ignored. */
export function normalizeAddress(input) {
    let s = String(input == null ? '' : input).trim().toLowerCase();
    if (/^[0-9a-f]{16}$/.test(s)) { s = '0x' + s; }
    return ADDRESS_RE.test(s) ? s : null;
}

function num(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
}

function normKey(k) {
    return {
        index: num(k.index),
        signAlgo: num(k.signAlgo),
        hashAlgo: num(k.hashAlgo),
        weight: num(k.weight),
        revoked: k.revoked === true || k.revoked === 'true',
    };
}

/**
 * Which wallet manages an account, from its keys (+ the Dapper receiver flag). Heuristics, verified on mainnet
 * holders (KB notes/terrakit-check.md §3):
 *  - Blocto created secp256k1/SHA3_256 keys in a weight 999 + 1000 + 1 pattern (key rotations repeat the triple).
 *  - Flow Wallet creates secp256k1/SHA2_256 keys (weight 1000) and P256/SHA2_256 weight-500 backup keys. Importing a
 *    Blocto Recovery Kit into Flow Wallet revokes the Blocto keys and adds a secp256k1/SHA2_256 key (same address).
 *  - Dapper accounts are custodial: P256/SHA2_256 keys, a weight-1000 key plus many weight-0 keys, and a DUC/FUT
 *    receiver at /public/dapperUtilityCoinReceiver or /public/flowUtilityTokenReceiver.
 *
 * Returns {class, canSign, formerBlocto, bloctoKeysActive, activeKeys, revokedKeys, totalWeight}
 *   class: 'flowwallet' | 'dapper' | 'blocto' | 'keyless' | 'other' | 'none'
 *   canSign: 'yes' | 'no' | 'unknown'
 */
export function classifyWallet(check) {
    const base = {formerBlocto: false, bloctoKeysActive: false, activeKeys: 0, revokedKeys: 0, totalWeight: 0};
    if (!check || check.exists === false || check.exists === 'false') {
        return {...base, class: 'none', canSign: 'no'};
    }
    const keys = (check.keys || []).map(normKey);
    const live = keys.filter((k) => !k.revoked);
    const signing = live.filter((k) => k.weight > 0);
    const totalWeight = signing.reduce((s, k) => s + k.weight, 0);
    const isBloctoKey = (k) => k.signAlgo === SIGN_SECP256K1 && k.hashAlgo === HASH_SHA3 && k.weight === 999;
    const isFlowWalletKey = (k) => k.hashAlgo === HASH_SHA2
        && ((k.signAlgo === SIGN_SECP256K1 && k.weight > 0) || (k.signAlgo === SIGN_P256 && k.weight === 500));
    const dapperReceiver = check.dapperReceiver === true || check.dapperReceiver === 'true';
    const zeroWeightP256 = live.filter((k) => k.signAlgo === SIGN_P256 && k.hashAlgo === HASH_SHA2 && k.weight === 0).length;

    const info = {
        ...base,
        formerBlocto: keys.some(isBloctoKey),
        bloctoKeysActive: signing.some(isBloctoKey),
        activeKeys: signing.length,
        revokedKeys: keys.length - live.length,
        totalWeight,
    };

    if (signing.length === 0) {
        return {...info, class: 'keyless', canSign: 'no'};
    }
    const fwWeight = signing.filter(isFlowWalletKey).reduce((s, k) => s + k.weight, 0);
    if (fwWeight > 0) {
        return {...info, class: 'flowwallet', canSign: fwWeight >= 1000 ? 'yes' : 'unknown'};
    }
    if (info.bloctoKeysActive) {
        return {...info, class: 'blocto', canSign: 'no'};
    }
    if (dapperReceiver || zeroWeightP256 >= 3) {
        return {...info, class: 'dapper', canSign: 'yes'};
    }
    return {...info, class: 'other', canSign: totalWeight >= 1000 ? 'unknown' : 'no'};
}

/** "10800-15799" style ranges (already parsed: [[lo, hi], ...]) → predicate. */
export function inRanges(id, ranges) {
    const n = Number(id);
    return (ranges || []).some((r) => n >= Number(r[0]) && n <= Number(r[1]));
}

/**
 * Voucher rows from the script → a fixed grid (every category × rarity, zero counts included) + totals.
 * Unknown categories/rarities are ignored (the script already filters by category).
 */
export function summarizeVouchers(vouchers) {
    const grid = {};
    for (const c of CATEGORY_ORDER) {
        grid[c] = {};
        for (const r of RARITY_ORDER) { grid[c][r] = {count: 0, listed: 0, templateId: null}; }
    }
    let total = 0;
    let listed = 0;
    for (const v of vouchers || []) {
        const c = String(v.category || '');
        const r = String(v.rarity || '').toLowerCase();
        if (!grid[c] || !grid[c][r]) { continue; }
        const n = num(v.count);
        grid[c][r].count += n;
        grid[c][r].listed += num(v.listed);
        if (grid[c][r].templateId === null) { grid[c][r].templateId = String(v.templateId); }
        total += n;
        listed += num(v.listed);
    }
    const rows = CATEGORY_ORDER.map((c) => ({
        category: c,
        label: CATEGORY_LABELS[c],
        total: RARITY_ORDER.reduce((s, r) => s + grid[c][r].count, 0),
        cells: RARITY_ORDER.map((r) => ({rarity: r, label: RARITY_LABELS[r], ...grid[c][r]})),
    }));
    return {rows, total, listed};
}

/** Merge voucher rows of several script slices (same templateId → counts added). */
export function mergeVouchers(a, b) {
    const map = new Map();
    for (const v of [...(a || []), ...(b || [])]) {
        const id = String(v.templateId);
        const prev = map.get(id);
        if (prev) {
            prev.count = num(prev.count) + num(v.count);
            prev.listed = num(prev.listed) + num(v.listed);
        } else {
            map.set(id, {...v, count: num(v.count), listed: num(v.listed)});
        }
    }
    return [...map.values()];
}

/** Cadence error text → 'not_found' | 'limit' | 'network' */
export function scriptErrorKind(message) {
    const m = String(message || '');
    if (/account not found|could not find account|failed to get account/i.test(m)) { return 'not_found'; }
    if (/computation|limit exceeded|1110/i.test(m)) { return 'limit'; }
    return 'network';
}
