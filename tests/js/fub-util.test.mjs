import {test} from 'node:test';
import assert from 'node:assert/strict';
import {precheckSvg, curvePrice, totalOf, selectionProblems, randomPicks, pickIds, capacityOf, fmtDust, txErrorText, fmtDust2, mmss, refillProblem, parseDust, voiceLimit, voiceCost, receiptText, receiptShort, tankPercent, messagesLeft, fmtDustShort, islandLabel, islandBlockReason, refillOptions, fmtFlow, fmtPct, voucherList, islandImg} from '../../resources/js/fub/util.mjs';
import {payPlan} from '../../resources/js/app/dust-swap-math.js';
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
    // the model is never shown (an old API or a stored receipt may still carry it)
    assert.equal(receiptText(rc, 'Sip'), 'Receipt #7: \u011038.46 burned \u00b7 Sip \u00b7 6 steps \u00b7 56,100 tokens (52,000 in, 4,100 out, 40,000 cached) \u00b7 21 s \u00b7 tank \u0110961.54');
    assert.doesNotMatch(receiptText({...rc, model: 'claude-haiku-5-5'}, 'Sip'), /haiku|claude/i);
    assert.equal(receiptShort(rc), '6 steps \u00b7 21 s');
    assert.equal(receiptShort({...rc, status: 'stopped', stopReason: 'tank'}), 'tank ran dry \u00b7 6 steps \u00b7 21 s');
    assert.equal(receiptShort({run: 8, status: 'refunded'}), 'refunded, nothing charged');
    assert.equal(receiptShort(null), '');
    // tank gauge: share of the last fill; a non-empty tank never looks empty
    assert.deepEqual([tankPercent(1234, 1234), tankPercent(617, 1234), tankPercent(1, 100000), tankPercent(0, 500), tankPercent(50, 0), tankPercent(-3, 10)], [100, 50, 4, 0, 100, 0]);
    assert.equal(messagesLeft(1234, 40, 'Sip'), 'About 30 Sip messages in your tank');
    assert.equal(messagesLeft(1300, 1300, 'Big'), 'About 1 Big message in your tank');
    assert.equal(messagesLeft(20, 40, 'Sip'), 'Not enough for a Sip message: refill first');
    assert.equal(messagesLeft(500, 0, 'Sip'), '');
    assert.deepEqual([1000, 10000, 100000, 1500, 2000000, 250].map(fmtDustShort), ['\u01101K', '\u011010K', '\u0110100K', '\u01101.5K', '\u01102M', '\u0110250']);
    assert.match(receiptText({...rc, status: 'stopped', stopReason: 'tank'}, 'Sip'), /tank ran dry/);
    assert.match(receiptText({...rc, status: 'running', charged: 12}, 'Sip'), /^Receipt #7: \u011012.00 burned so far/);
    assert.match(receiptText({...rc, status: 'refunded', charged: 0}, 'Sip'), /refunded, nothing charged/);
    assert.equal(receiptText(null), '');
});

test('island picker labels and reasons (commit step)', () => {
    assert.equal(islandLabel({id: 8, rarity: 'common', name: ''}), 'Island #8 (common)');
    assert.equal(islandLabel({id: 5, rarity: 'common', name: 'Meadow Outpost'}), 'Island #5 \u00b7 Meadow Outpost (common)');
    assert.equal(islandBlockReason({id: 1, free: true}), '');
    assert.equal(islandBlockReason({id: 1, free: false, reason: 'hosts-fub', fubId: 3}), 'Already hosts FUB #3');
    assert.equal(islandBlockReason({id: 1, free: false, reason: 'no-building', rarity: 'legendary'}), 'No FUB building for legendary islands yet');
});

test('refill dialog options: ĐUST balance, FLOW quote, thin pool, FLOW balance', () => {
    // real testnet pool after seeding: 23,695 Đ / 78.89 FLOW; Đ1,234 costs 4.34731518 FLOW
    const plan = (available, amount = 1234, quoteIn = 4.34731518) => payPlan({quoteIn, priceOut: amount, reserveIn: 78.89151114, reserveOut: 23695.00318821, available, slippagePct: 1, warnPct: 5, blockPct: 10});
    const p = plan(15);
    assert.equal(p.level, 'warn');
    assert.ok(p.impact > 5 && p.impact < 6, String(p.impact));
    assert.ok(Math.abs(p.maxIn - 4.39078834) < 1e-8, String(p.maxIn));
    let o = refillOptions({amount: 1234, dust: 2000, plan: p, swapOn: true});
    assert.deepEqual([o.dustOk, o.flowOk], [true, true]);
    o = refillOptions({amount: 1234, dust: 100.5, plan: p, swapOn: true});
    assert.equal(o.dustOk, false);
    assert.match(o.dustReason, /you have \u0110100\.5/);
    o = refillOptions({amount: 1234, dust: 0, plan: plan(1), swapOn: true});
    assert.equal(o.flowOk, false);
    assert.match(o.flowReason, /Not enough FLOW: this needs up to 4\.3908 FLOW/, 'the amount the swap needs, not the (capped) wallet balance');
    o = refillOptions({amount: 5000, dust: 0, plan: plan(100, 5000, 21.1631143), swapOn: true});
    assert.match(o.flowReason, /too thin/);
    assert.match(refillOptions({amount: 1, dust: 0, plan: null, swapOn: false}).flowReason, /not available/);
    assert.match(refillOptions({amount: 1, dust: 0, plan: null, swapOn: true}).flowReason, /Getting/);
    assert.equal(refillOptions({amount: 1, dust: 0, plan: null, swapOn: true, quoteError: 'X'}).flowReason, 'X');
    assert.equal(fmtFlow(4.34731518), '4.3473');
    assert.equal(fmtPct(5.8123), '5.81%');
});

test('generated Cadence: commit takes the island, FLOW refill swaps then burns', () => {
    assert.match(C.COMMIT_TX, /transaction\(voucherIds: \[UInt64\], name: String, islandId: UInt64\)/);
    assert.match(C.REFILL_TANK_FLOW_TX, /swapTokensForExactTokens/);
    assert.match(C.REFILL_TANK_FLOW_TX, /import SwapRouter from 0xSwapRouter/);
    assert.match(C.REFILL_TANK_FLOW_TX, /refillTank\(payment: <-self\.payment, account: self\.account, ref: ref\)/);
    assert.match(C.WALLET_FUB_SCRIPT, /"islands": islandList/);
});

test('swap revert messages are friendly', () => {
    assert.match(txErrorText(new Error('error: assertion failed: [IncSwapErrorMsg:SwapRouter: EXCESSIVE_INPUT_AMOUNT][IncSwapErrorCode:5]')), /moved above your maximum/);
    assert.match(txErrorText(new Error('[IncSwapErrorMsg:SwapRouter: expired][IncSwapErrorCode:2]')), /expired/);
});

test('voucherList: one row, legendary first, illustration from the template id', () => {
    const l = voucherList({common: [175, 171], epic: [172], legendary: [173]}, {171: 72, 172: 73, 173: 74});
    assert.deepEqual(l.map((v) => v.id), [173, 172, 171, 175]);
    assert.equal(l[0].img, '/api/image/template/74');
    assert.equal(l[3].img, ''); // older wallet script without voucherTemplates
    assert.deepEqual(voucherList(null, null), []);
    assert.equal(islandImg(12), '/islands/12/full.svg');
    assert.equal(islandImg(0), '');
});
