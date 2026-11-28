import test from 'node:test';
import assert from 'node:assert/strict';
import {fmtPrice, chapters, buyPlan, reasonText, itemImage, revealOrder, progress, fmtDate} from '../../resources/js/app/pack-rewind-util.mjs';

test('prices', () => {
    assert.equal(fmtPrice('9.0', 'FLOW'), '9 FLOW');
    assert.equal(fmtPrice('3000.0', 'DUST'), '\u01103,000');
    assert.equal(fmtDate('2021-12-14'), '14 Dec 2021');
});

test('chapters keep chronological order and group by year', () => {
    const c = chapters([{key: 'a', year: 2021}, {key: 'b', year: 2021}, {key: 'c', year: 2022}, {key: 'd', year: 2023}]);
    assert.deepEqual(c.map((x) => [x.year, x.items.length]), [[2021, 2], [2022, 1], [2023, 1]]);
});

test('buy plan: FLOW packs', () => {
    const s = {price: '9.0', currency: 'FLOW', remaining: 3};
    assert.equal(buyPlan({series: s, wallet: '', flow: 100}).reason, 'connect');
    assert.equal(buyPlan({series: s, wallet: 'dapper', flow: 100}).reason, 'dapper');
    assert.equal(buyPlan({series: s, wallet: 'flow', flow: 100}).mode, 'flow');
    assert.equal(buyPlan({series: s, wallet: 'flow', flow: 9}).mode, 'none');
    assert.equal(buyPlan({series: {...s, remaining: 0}, wallet: 'flow', flow: 100}).reason, 'soldout');
    assert.match(reasonText(buyPlan({series: s, wallet: 'flow', flow: 5}), s), /Not enough FLOW \(about 4\.01 more\)/);
});

test('buy plan: DUST pack pays DUST first, FLOW swap as fallback / alternative', () => {
    const s = {price: '3000.0', currency: 'DUST', remaining: 10};
    assert.equal(buyPlan({series: s, wallet: 'flow', flow: 0, dust: 5000, quoteIn: null}).mode, 'dust');
    const both = buyPlan({series: s, wallet: 'flow', flow: 50, dust: 5000, quoteIn: 10});
    assert.equal(both.mode, 'dust'); assert.equal(both.alt, 'swap');
    const swap = buyPlan({series: s, wallet: 'flow', flow: 50, dust: 0, quoteIn: 10, slippagePct: 3});
    assert.equal(swap.mode, 'swap'); assert.equal(swap.maxIn, 10.3);
    assert.equal(buyPlan({series: s, wallet: 'flow', flow: 50, dust: 0, quoteIn: null}).reason, 'quote');
    assert.equal(buyPlan({series: s, wallet: 'flow', flow: 5, dust: 0, quoteIn: 10}).reason, 'flow');
});

test('item images and reveal order', () => {
    assert.equal(itemImage({t: 120}, 'flovatar'), '/api/image/template/120');
    assert.equal(itemImage({t: 60}, 'sportvatar', 'https://testnet.sportvatar.com/'), 'https://testnet.sportvatar.com/api/image/template/60');
    const o = revealOrder([{n: 'L', r: 'legendary'}, {n: 'C', r: 'common'}, {n: 'R', r: 'rare'}]);
    assert.deepEqual(o.map((x) => x.n), ['C', 'R', 'L']);
});

test('progress', () => {
    assert.deepEqual(progress([{key: 'a'}, {key: 'b'}], {a: 2}), {done: 1, total: 2, pct: 50});
});
