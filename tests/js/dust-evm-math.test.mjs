// node --test tests/js/dust-evm-math.test.mjs — vectors are REAL quote-script outputs (testnet pools 2026-11-28 and a
// read-only mainnet run: USDF -> WFLOW on PunchSwap V2 -> Increment ĐUST/FLOW).
import test from 'node:test';
import assert from 'node:assert/strict';
import {big, toUnits, fromUnits, fmtUnits, maxInUnits, spotRate, hopsImpact, incrementImpact, impactLevel, pickRoute, evmPlan,
    evmReason, bridgeAmount, relayQuoteBody, evmHex, evmTxErrorText} from '../../resources/js/evm/dust-evm-math.mjs';

const TESTNET_Q = {
    flowNeeded: '6.65159531', incReserveDust: '1111342.06824128', incReserveFlow: '7363.38944478',
    routes: [
        {path: ['usdc', 'dust'], viaFlow: false, ok: true, amountIn: 97508, hops: [[97117521, '1000000000000000000000000']]},
        {path: ['usdc', 'wflow', 'dust'], viaFlow: false, ok: true, amountIn: 97820, hops: [[500000000, '34081812406710000000000'], ['6619882282850000000000', '1000000000000000000000000']]},
        {path: ['usdc', 'wflow'], viaFlow: true, ok: true, amountIn: 97896, flowNeeded: '6.65159531', hops: [[500000000, '34081812406710000000000']]},
    ],
};
const MAINNET_Q = {
    flowNeeded: '6.64577642', incReserveDust: '1112314.25728807', incReserveFlow: '7363.38944478',
    routes: [
        {path: ['usdf', 'wflow'], viaFlow: true, ok: true, amountIn: 97598, flowNeeded: '6.64577642', hops: [[112474722679, '7681882638682381926932167']]},
        {path: ['stgusdc', 'wflow'], viaFlow: true, ok: true, amountIn: 572374, flowNeeded: '6.64577642', hops: [[123190, '8080427993563488162']]},
        {path: ['usdf', 'wflow', 'dust'], viaFlow: false, ok: false, error: 'execution reverted', hops: [[1, 1], []]},
    ],
};

test('units', () => {
    assert.equal(toUnits('1000', 18), 1000n * 10n ** 18n);
    assert.equal(toUnits('0.097508', 6), 97508n);
    assert.equal(toUnits('1.123456789', 6), 1123456n);
    assert.equal(toUnits('abc', 6), 0n);
    assert.equal(big('97508'), 97508n);
    assert.equal(big(null), 0n);
    assert.equal(fromUnits(97508n, 6), 0.097508);
    assert.equal(fmtUnits(97508n, 6), '0.097508');
    assert.equal(fmtUnits(123456789n, 6), '123.46');
});

test('maxIn rounds up', () => {
    assert.equal(maxInUnits(97508n, 1), 98485n); // 97508 * 1.01 = 98483.08 -> 98484 (+1)
    assert.ok(maxInUnits(1n, 0) >= 1n);
});

test('impact incl. fees: direct testnet pool ~0.4% for Đ1,000', () => {
    const r = TESTNET_Q.routes[0];
    const imp = hopsImpact(r.amountIn, toUnits('1000', 18), r.hops);
    assert.ok(imp > 0.3 && imp < 0.5, String(imp));
    assert.equal(hopsImpact(1, 1, []), Infinity);
    assert.equal(spotRate([[1, 2], [2, 6]]), 6);
});

test('increment leg impact', () => {
    const imp = incrementImpact(6.65159531, 1000, 7363.38944478, 1111342.06824128);
    assert.ok(imp > 0.3 && imp < 0.5, String(imp));
});

test('pickRoute: testnet direct pool is cheapest', () => {
    const {routes, best} = pickRoute(TESTNET_Q, 1000, ['direct', 'hop', 'via'], {blockPct: 10});
    assert.equal(best.name, 'direct');
    assert.equal(best.amountIn, 97508n);
    assert.equal(routes.length, 3);
    assert.ok(routes[2].impact > routes[0].impact); // two pools + two fees
});

test('pickRoute: mainnet USDF via FLOW beats thin stgUSDC, missing ĐUST pool is skipped', () => {
    const {routes, best} = pickRoute(MAINNET_Q, 1000, ['via', 'via', 'hop'], {blockPct: 10});
    assert.equal(best.path[0], 'usdf');
    assert.equal(best.amountIn, 97598n);
    assert.ok(best.impact > 0.55 && best.impact < 0.8, String(best.impact));
    assert.equal(routes[2].ok, false);
});

test('pickRoute: everything blocked -> still reports cheapest', () => {
    const q = {...TESTNET_Q, routes: [TESTNET_Q.routes[0]]};
    const {best} = pickRoute(q, 1000, ['direct'], {blockPct: 0.1});
    assert.equal(best.name, 'direct');
    assert.equal(impactLevel(best.impact, 5, 0.1), 'block');
});

test('evmPlan + reasons', () => {
    let p = evmPlan({amountIn: 97508n, balance: 200000000n, slippagePct: 1, impact: 0.4, warnPct: 5, blockPct: 10});
    assert.equal(p.enough, true); assert.equal(p.level, 'ok'); assert.equal(p.maxIn, 98485n);
    assert.equal(evmReason({symbol: 'USDC', plan: p, coa: '0xabc'}), '');
    p = evmPlan({amountIn: 97508n, balance: 98000n, slippagePct: 1, impact: 0.4});
    assert.equal(p.maxIn, 98000n); // capped at balance, still >= quote
    p = evmPlan({amountIn: 97508n, balance: 2000n, slippagePct: 1, impact: 0.4});
    assert.equal(p.enough, false); assert.equal(p.short, 95508n);
    assert.match(evmReason({symbol: 'USDC', plan: p, coa: '0xabc'}), /Not enough USDC/);
    assert.match(evmReason({symbol: 'USDC', plan: p, coa: ''}), /no Flow EVM account/);
    assert.match(evmReason({symbol: 'USDC', plan: {...p, level: 'block', impact: 43}}), /too thin.*43\.0%/);
    assert.match(evmReason({symbol: 'USDC', quoteError: 'x'}), /^x$/);
});

test('bridgeAmount: shortfall + 2% (min 0.01)', () => {
    assert.equal(bridgeAmount(95508n, 6), 95508n + 10000n);
    assert.equal(bridgeAmount(10_000_000n, 6), 10_200_000n);
    assert.equal(bridgeAmount(0n, 6), 0n);
});

test('relay quote body', () => {
    const b = relayQuoteBody({user: '0x' + '1'.repeat(40), recipient: '0x000000000000000000000002d5af56848445d74e', originChainId: 8453,
        originCurrency: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', destinationChainId: 747, destinationCurrency: '0x2aaBea2058b5aC2D339b163C6Ab6f2b6d53aabED', amountOut: 105508n});
    assert.equal(b.tradeType, 'EXACT_OUTPUT');
    assert.equal(b.amount, '105508');
    assert.equal(b.destinationChainId, 747);
    assert.throws(() => relayQuoteBody({user: 'x', recipient: 'y', amountOut: 1n}));
    assert.throws(() => relayQuoteBody({user: '0x' + '1'.repeat(40), recipient: '0x' + '2'.repeat(40), amountOut: 0n}));
});

test('evmHex', () => {
    assert.equal(evmHex('000000000000000000000002d5Af56848445d74E'), '0x000000000000000000000002d5af56848445d74e');
    assert.equal(evmHex('nope'), '');
});

test('evmTxErrorText: real sealed errors from testnet', () => {
    assert.match(evmTxErrorText(new Error('error: panic: EVM swap failed: PunchSwapV2Router: EXCESSIVE_INPUT_AMOUNT'), 'USDC'), /USDC price moved/);
    assert.match(evmTxErrorText('panic: EVM swap failed: PunchSwapV2Router: EXPIRED'), /expired/);
    assert.match(evmTxErrorText('EVM swap failed: TransferHelper::transferFrom: transferFrom failed', 'USDF'), /Not enough USDF/);
    assert.match(evmTxErrorText('User rejected'), /cancelled/);
    assert.equal(evmTxErrorText('something else'), '');
});
