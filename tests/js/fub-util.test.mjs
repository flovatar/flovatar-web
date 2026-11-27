import {test} from 'node:test';
import assert from 'node:assert/strict';
import {precheckSvg, curvePrice, totalOf, selectionProblems, randomPicks, pickIds, capacityOf, fmtDust, txErrorText, fmtDust2, mmss, refillProblem, parseDust, voiceLimit, voiceCost, receiptText} from '../../resources/js/fub/util.mjs';
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

test('token-metered assistant helpers: Đ with 2 decimals, timer, refill range, voice limit and cost, receipts', () => {
    assert.equal(fmtDust2(12.3), '\u011012.30');
    assert.equal(fmtDust2(1234.567), '\u01101,234.57');
    assert.equal(fmtDust2(0), '\u01100.00');
    assert.equal(mmss(0), '0:00');
    assert.equal(mmss(65.9), '1:05');
    assert.equal(mmss(600), '10:00');
    // refill: whole ĐUST in [min, max] (contract tankMin..tankMax)
    assert.equal(refillProblem('1234', 100, 10000000), '');
    assert.equal(refillProblem('10,000', 100, 10000000), '');
    assert.equal(parseDust('100,000'), 100000);
    assert.match(refillProblem('99', 100, 10000000), /minimum refill is \u0110100/);
    assert.match(refillProblem(10000001, 100, 10000000), /maximum refill is \u011010,000,000/);
    assert.match(refillProblem('12.5', 100, 1000), /whole number/);
    assert.match(refillProblem('', 100, 1000), /Enter how much/);
    assert.match(refillProblem('abc', 100, 1000), /whole number/);
    // voice: what the tank affords, capped at 10 minutes
    assert.equal(voiceLimit({affordableSeconds: 45}, {maxSeconds: 600}), 45);
    assert.equal(voiceLimit({affordableSeconds: 5000}, {maxSeconds: 600}), 600);
    assert.equal(voiceLimit(null, {maxSeconds: 600}), 600);
    assert.equal(voiceLimit({affordableSeconds: 0}, {maxSeconds: 600}), 0);
    assert.ok(Math.abs(voiceCost(36, 1.111111) - 40) < 0.001);
    assert.equal(voiceCost(-3, 1.1), 0);
    // receipts
    const rc = {run: 7, level: 'sip', status: 'done', charged: 38.456, modelLabel: 'Claude Haiku 5.5', steps: 6, tokens: {input: 52000, output: 4100, cached: 40000}, seconds: 21.4, balanceAfter: 961.544};
    assert.equal(receiptText(rc, 'Sip'), 'Receipt #7: \u011038.46 burned \u00b7 Sip \u00b7 Claude Haiku 5.5 \u00b7 6 steps \u00b7 56,100 tokens (52,000 in, 4,100 out, 40,000 cached) \u00b7 21 s \u00b7 tank \u0110961.54');
    assert.match(receiptText({...rc, status: 'stopped', stopReason: 'tank'}, 'Sip'), /tank ran dry/);
    assert.match(receiptText({...rc, status: 'running', charged: 12}, 'Sip'), /^Receipt #7: \u011012.00 burned so far/);
    assert.match(receiptText({...rc, status: 'refunded', charged: 0}, 'Sip'), /refunded, nothing charged/);
    assert.equal(receiptText(null), '');
});
