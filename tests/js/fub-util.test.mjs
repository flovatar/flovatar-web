import {test} from 'node:test';
import assert from 'node:assert/strict';
import {precheckSvg, curvePrice, totalOf, selectionProblems, randomPicks, pickIds, capacityOf, fmtDust, txErrorText} from '../../resources/js/fub/util.mjs';
import * as C from '../../resources/js/fub/cadence.mjs';

test('precheck flags dangerous SVG and accepts clean art', () => {
    assert.equal(precheckSvg('<g><rect width="1" height="1"/></g>').warnings.length, 0);
    const bad = precheckSvg('<svg><script>alert(1)</script><rect onload="x()"/><text>hi</text><image href="https://x/y.png"/></svg>');
    assert.ok(bad.warnings.length >= 4);
    assert.equal(precheckSvg('').ok, false);
    assert.equal(precheckSvg('hello').ok, false);
});

test('price curve matches the contract rule', () => {
    assert.equal(curvePrice(10, 0, 0, 50), 10);
    assert.ok(Math.abs(curvePrice(10, 0.1, 100, 2) - 12.1) < 1e-9);
    assert.equal(curvePrice(10, 0.1, 15, 30), 15);
});

test('selection helpers', () => {
    const layers = [{key: 'bg', name: 'Sky', required: true}, {key: 'hat', name: 'Hat', required: false}];
    const items = [{id: 1, layer: 'bg', live: true, price: 5, currentPrice: 5, name: 'a'}, {id: 2, layer: 'hat', live: true, price: 7, currentPrice: 8, maxMint: 1, minted: 1, name: 'b'}];
    assert.deepEqual(selectionProblems(layers, {}, items), ['Pick a Sky.']);
    assert.deepEqual(selectionProblems(layers, {bg: 1, hat: 2}, items), ['Hat: b is sold out.']);
    assert.equal(totalOf({bg: 1, hat: 2}, items), 13);
    assert.deepEqual(pickIds(layers, {hat: 2, bg: 1}), [1, 2]);
    const r = randomPicks(layers, items, () => 0);
    assert.equal(r.bg, 1);
    assert.equal(r.hat, undefined); // sold out never picked
});

test('voucher capacity uses the public numbers', () => {
    assert.deepEqual(capacityOf({common: 1}), {slots: 25, mints: 500, tier: 'common'});
    assert.deepEqual(capacityOf({common: 5}), {slots: 125, mints: 2500, tier: 'epic'});
    assert.deepEqual(capacityOf({epic: 5}), {slots: 625, mints: 12500, tier: 'legendary'});
});

test('formatting + tx errors', () => {
    assert.equal(fmtDust(2000), '\u01102,000');
    assert.equal(txErrorText(new Error('error: assertion failed: Sold out\n  --> x')), 'Sold out');
    assert.equal(txErrorText('User rejected'), 'You cancelled the transaction in your wallet.');
});

test('generated Cadence uses site aliases only', () => {
    for (const [k, v] of Object.entries(C)) {
        assert.ok(!/import "/.test(v), k);
        if (k !== 'DUST_BALANCE_SCRIPT') { assert.match(v, /import FlovatarUniverseBuilder from 0xFUB/, k); }
    }
    assert.match(C.MINT_TX, /maxPrice: UFix64/);
    assert.match(C.PUBLISH_TX, /expectedManifest/);
});
