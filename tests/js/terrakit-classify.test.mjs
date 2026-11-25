// Unit tests for the TerraKit wallet-check classifier: `node --test tests/js/*.test.mjs`
// Key patterns are copied from real mainnet accounts (keys only, no addresses; KB notes/terrakit-check.md §3).
import test from 'node:test';
import assert from 'node:assert/strict';
import {classifyWallet, inRanges, mergeVouchers, normalizeAddress, scriptErrorKind, summarizeVouchers} from '../../resources/js/terrakit/classify.mjs';

// [signAlgo, hashAlgo, weight, revoked] -> script shape (strings, like FCL decodes them)
const K = (rows) => rows.map(([s, h, w, r], i) => ({index: String(i), signAlgo: String(s), hashAlgo: String(h), weight: w.toFixed(8), revoked: !!r}));
const acct = (keys, extra = {}) => ({exists: true, keys: K(keys), dapperReceiver: false, ...extra});

const BLOCTO = [[2, 3, 999], [2, 3, 1000], [2, 3, 1]];
const BLOCTO_REVOKED = [[2, 3, 999, 1], [2, 3, 1000, 1], [2, 3, 1, 1]];

test('address normalisation', () => {
    assert.equal(normalizeAddress(' 0x2A0ECCAE942667BE '), '0x2a0eccae942667be');
    assert.equal(normalizeAddress('2a0eccae942667be'), '0x2a0eccae942667be');
    for (const bad of ['', '0x123', '0x2a0eccae942667bez', '0x2a0eccae942667be0', 'hello', null, undefined]) {
        assert.equal(normalizeAddress(bad), null, String(bad));
    }
});

test('Blocto: secp256k1/SHA3 999 + 1000 + 1, still active', () => {
    const r = classifyWallet(acct(BLOCTO));
    assert.equal(r.class, 'blocto');
    assert.equal(r.canSign, 'no');
    assert.equal(r.bloctoKeysActive, true);
});

test('Blocto with a key rotation (old triple revoked, new triple active)', () => {
    assert.equal(classifyWallet(acct([...BLOCTO_REVOKED, ...BLOCTO])).class, 'blocto');
});

test('Recovery Kit imported into Flow Wallet: Blocto keys revoked + secp256k1/SHA2 key', () => {
    const r = classifyWallet(acct([...BLOCTO_REVOKED, [2, 3, 1000, 1], [2, 3, 1, 1], [2, 3, 1, 1], [2, 1, 1000]]));
    assert.equal(r.class, 'flowwallet');
    assert.equal(r.canSign, 'yes');
    assert.equal(r.formerBlocto, true);
    assert.equal(r.bloctoKeysActive, false);
});

test('Blocto keys still active + a Flow Wallet key added: Flow Wallet can sign', () => {
    const r = classifyWallet(acct([[2, 3, 999], [2, 3, 1000, 1], [2, 3, 1, 1], [2, 3, 1000], [2, 3, 1], [2, 1, 1000]]));
    assert.equal(r.class, 'flowwallet');
    assert.equal(r.bloctoKeysActive, true);
});

test('Flow Wallet: single secp256k1/SHA2 key', () => {
    const r = classifyWallet(acct([[2, 1, 1000]]));
    assert.equal(r.class, 'flowwallet');
    assert.equal(r.formerBlocto, false);
});

test('Flow Wallet multi-backup: P256/SHA2 weight-500 backup keys', () => {
    assert.equal(classifyWallet(acct([...BLOCTO_REVOKED, [1, 1, 500], [1, 1, 500], [2, 1, 1000], [1, 1, 1000]])).class, 'flowwallet');
    assert.equal(classifyWallet(acct([[1, 1, 500], [1, 1, 500]])).class, 'flowwallet');
});

test('Dapper: P256/SHA2 1000 + many weight-0 keys (+ DUC receiver)', () => {
    const keys = [[1, 1, 1000], ...Array.from({length: 10}, () => [1, 1, 0]), [1, 1, 1000], ...Array.from({length: 10}, () => [1, 1, 0])];
    assert.equal(classifyWallet(acct(keys, {dapperReceiver: true})).class, 'dapper');
    assert.equal(classifyWallet(acct(keys)).class, 'dapper', 'key pattern alone');
    const r = classifyWallet(acct([[1, 1, 1000]], {dapperReceiver: 'true'}));
    assert.equal(r.class, 'dapper', 'receiver alone');
    assert.equal(r.canSign, 'yes');
});

test('Other: single P256 key (Flow CLI, passkey, other wallets)', () => {
    assert.equal(classifyWallet(acct([[1, 3, 1000]])).class, 'other');
    assert.equal(classifyWallet(acct([[1, 1, 1000]])).class, 'other');
    assert.equal(classifyWallet(acct([[1, 3, 1000]])).canSign, 'unknown');
});

test('Keyless: no keys, or every key revoked / weight 0', () => {
    assert.equal(classifyWallet(acct([])).class, 'keyless');
    assert.equal(classifyWallet(acct(BLOCTO_REVOKED)).class, 'keyless');
    assert.equal(classifyWallet(acct([[1, 1, 0]])).class, 'keyless');
});

test('No account', () => {
    assert.equal(classifyWallet({exists: false, keys: []}).class, 'none');
    assert.equal(classifyWallet(null).class, 'none');
});

test('voucher grid: every category x rarity, totals, listed', () => {
    const s = summarizeVouchers([
        {templateId: '592', category: 'land', rarity: 'common', count: '8', listed: '1'},
        {templateId: '593', category: 'land', rarity: 'epic', count: '4', listed: '0'},
        {templateId: '590', category: 'fub', rarity: 'epic', count: '4', listed: '0'},
        {templateId: '586', category: 'flobotkit', rarity: 'common', count: '8', listed: '0'},
        {templateId: '9', category: 'hat', rarity: 'common', count: '3', listed: '0'},
    ]);
    assert.equal(s.total, 24);
    assert.equal(s.listed, 1);
    assert.deepEqual(s.rows.map((r) => r.category), ['land', 'fub', 'flobotkit']);
    assert.deepEqual(s.rows[0].cells.map((c) => c.count), [8, 4, 0]);
    assert.equal(s.rows[0].cells[0].templateId, '592');
    assert.equal(s.rows[2].total, 8);
});

test('slices merge by template', () => {
    const m = mergeVouchers([{templateId: '69', count: '2', listed: '0', category: 'land', rarity: 'common'}],
        [{templateId: '69', count: '3', listed: '1'}, {templateId: '72', count: '1', listed: '0'}]);
    assert.equal(m.find((v) => v.templateId === '69').count, 5);
    assert.equal(m.find((v) => v.templateId === '69').listed, 1);
    assert.equal(m.length, 2);
});

test('TerraKit pack ranges', () => {
    const mainnet = [[10800, 15799]];
    assert.equal(inRanges('10800', mainnet), true);
    assert.equal(inRanges(15799, mainnet), true);
    assert.equal(inRanges(15800, mainnet), false); // Dust Flobit Pack
    assert.equal(inRanges(10236, mainnet), false); // Flobit Pack
    assert.equal(inRanges(14, [[13, 15]]), true);
});

test('script error kinds', () => {
    assert.equal(scriptErrorKind('error: failed to get public key count: [Error Code: 1201] account not found for address 0000000000000001'), 'not_found');
    assert.equal(scriptErrorKind('[Error Code: 1110] computation exceeds limit'), 'limit');
    assert.equal(scriptErrorKind('Failed to fetch'), 'network');
});
