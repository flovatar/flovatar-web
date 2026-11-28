/**
 * "Buy ĐUST from any chain" — chain client (KB notes/flow-evm-program.md EVM-B). Inert unless the layout printed
 * window.DUST_EVM (DUST_SWAP + DUST_EVM_ENABLED). Used by the FUB refill dialog (fub-v1.js), the Buy ĐUST widget
 * (app/buy-dust.js) and, through window.FlovatarBuyDust, by any other page (Pack Rewind).
 *
 * Chain-first: one Cadence script (DUST_EVM_QUOTE_SCRIPT) returns the wallet's COA + stablecoin balance and router
 * quotes for every configured route; the cheapest route under the impact block wins. Every tx is one Cadence
 * transaction signed in the Flow wallet (generated from the testnet-tested .cdc files: ./cadence.mjs).
 */
import * as fcl from '@onflow/fcl';
import {mutate} from '../flow/fcl-tx';
import * as C from './cadence.mjs';
import {pickRoute, evmPlan, toUnits, evmHex, big, relayQuoteBody} from './dust-evm-math.mjs';

export const EVMCFG = window.DUST_EVM && window.DUST_EVM.enabled ? window.DUST_EVM : null;

if (EVMCFG) {
    fcl.config().put('0xEVM', EVMCFG.evm).put('0xFlowEVMBridge', EVMCFG.bridge);
    const S = window.DUST_SWAP;
    if (S && S.enabled) { fcl.config().put('0xSwapRouter', S.router).put('0xSwapConfig', S.swapConfig).put('0xSwapFactory', S.factory); }
}

/** Candidate routes from config: [{name, path, viaFlow}] (pay token first). */
export function routeDefs() {
    if (!EVMCFG) { return []; }
    const p = EVMCFG.pay.address;
    const defs = {direct: {path: [p, EVMCFG.dust], viaFlow: false}, hop: {path: [p, EVMCFG.wflow, EVMCFG.dust], viaFlow: false}, via: {path: [p, EVMCFG.wflow], viaFlow: true}};
    return (EVMCFG.routes || []).filter((n) => defs[n]).map((n) => ({name: n, ...defs[n]}));
}

/**
 * Quote exactly `amount` ĐUST (number) for `user` (Flow address or null). Resolves to
 * {coa, balance (BigInt pay units), flowAvailable, dustBalance, routes, best, plan(slippage)} — throws on script errors
 * (callers fail closed: the option is hidden / disabled with a reason).
 */
export async function quoteDustEvm(user, amount, slippagePct) {
    if (!EVMCFG) { throw new Error('disabled'); }
    const defs = routeDefs();
    const amt = (Math.floor(Number(amount) * 1e8) / 1e8).toFixed(8);
    const q = await fcl.query({
        cadence: C.DUST_EVM_QUOTE_SCRIPT,
        args: (arg, t) => [
            arg(user || null, t.Optional(t.Address)), arg(amt, t.UFix64), arg(EVMCFG.router, t.String), arg(EVMCFG.factory, t.String),
            arg(defs.map((d) => d.path), t.Array(t.Array(t.String))), arg(defs.map((d) => d.viaFlow), t.Array(t.Bool)),
        ],
    });
    if (EVMCFG.dust && q.dustEvm && evmHex(q.dustEvm) !== EVMCFG.dust.toLowerCase()) { throw new Error('unexpected ĐUST token ' + q.dustEvm); }
    const {routes, best} = pickRoute(q, Number(amt), defs.map((d) => d.name), {blockPct: EVMCFG.impactBlock});
    const balance = big(q.tokenInBalance);
    const plan = best ? evmPlan({amountIn: best.amountIn, balance, slippagePct: slippagePct || EVMCFG.slippageDefault, impact: best.impact,
        warnPct: EVMCFG.impactWarn, blockPct: EVMCFG.impactBlock}) : null;
    return {coa: evmHex(q.coa), balance, flowAvailable: Number(q.flowAvailable || 0), dustBalance: Number(q.dustBalance || 0), routes, best, plan, raw: q};
}

export function replan(q, slippagePct) {
    if (!q || !q.best) { return null; }
    return evmPlan({amountIn: q.best.amountIn, balance: q.balance, slippagePct, impact: q.best.impact, warnPct: EVMCFG.impactWarn, blockPct: EVMCFG.impactBlock});
}

function deadline() { return String(Math.floor(Date.now() / 1000) + (EVMCFG.deadlineSeconds || 300)); }

/** Refill the FUB design-assistant tank paying with the stablecoin (refill_tank_evm.cdc). Returns the tx id. */
export async function refillTankEvmTx({amount, route, maxIn, ref}) {
    return mutate({
        cadence: C.REFILL_TANK_EVM_TX,
        args: (arg, t) => [arg(Number(amount).toFixed(8), t.UFix64), arg(route.path, t.Array(t.String)), arg(big(maxIn).toString(), t.UInt256),
            arg(deadline(), t.UInt256), arg(EVMCFG.router, t.String), arg(!!route.viaFlow, t.Bool), arg(ref, t.String)],
        limit: 9999,
    });
}

/** Buy exactly `amount` ĐUST into the wallet paying with the stablecoin (buy_dust_evm.cdc). Returns the tx id. */
export async function buyDustEvmTx({amount, route, maxIn}) {
    return mutate({
        cadence: C.BUY_DUST_EVM_TX,
        args: (arg, t) => [arg(Number(amount).toFixed(8), t.UFix64), arg(route.path, t.Array(t.String)), arg(big(maxIn).toString(), t.UInt256),
            arg(deadline(), t.UInt256), arg(EVMCFG.router, t.String), arg(!!route.viaFlow, t.Bool)],
        limit: 9999,
    });
}

/** TESTNET: test USDC faucet into the wallet's COA (creates the COA when missing). units = BigInt base units. */
export async function testFaucetTx(units) {
    if (!EVMCFG || !EVMCFG.testFaucet) { throw new Error('No test faucet on this network'); }
    return mutate({cadence: C.TEST_USDC_FAUCET_TX, args: (arg, t) => [arg(EVMCFG.pay.address, t.String), arg(big(units).toString(), t.UInt256)], limit: 9999});
}

export async function sealedTx(txId) {
    const tx = await fcl.tx(txId).onceSealed();
    if (tx.statusCode !== 0 || tx.errorMessage) { throw new Error(tx.errorMessage || 'Transaction failed'); }
    return tx;
}

// ------------------------------------------------------------------------------------------------ cross-chain step

/** Human label of the cross-chain mode: 'simulate' (testnet) | 'relay' | 'off'. */
export function xchainMode() { return EVMCFG ? EVMCFG.xchain : 'off'; }

/**
 * Deliver `units` of the pay token to the wallet's COA from another chain.
 *  - simulate (testnet: no bridge serves Flow EVM testnet): the test-USDC faucet tx stands in for the arrival.
 *  - relay (mainnet): connect the browser's EVM wallet (window.ethereum), Relay quote EXACT_OUTPUT to the COA, send the
 *    returned transaction steps on the origin chain, poll Relay until the funds arrived. onStatus(text) reports progress.
 */
export async function bridgeIn({units, coa, origin, onStatus}) {
    const say = (s) => { if (onStatus) { onStatus(s); } };
    if (xchainMode() === 'simulate') {
        say('Simulating the bridge (testnet): minting test USDC into your Flow EVM account\u2026');
        const txId = await testFaucetTx(units);
        await sealedTx(txId);
        return {simulated: true, txId};
    }
    if (xchainMode() !== 'relay') { throw new Error('Cross-chain payments are not available here.'); }
    const eth = window.ethereum;
    if (!eth || !eth.request) { throw new Error('No EVM wallet found in this browser (MetaMask, Coinbase Wallet, Rabby\u2026).'); }
    say('Connect the wallet that holds your USDC on ' + origin.name + '\u2026');
    const [from] = await eth.request({method: 'eth_requestAccounts'});
    const body = relayQuoteBody({user: from, recipient: coa, originChainId: origin.chainId, originCurrency: origin.usdc,
        destinationChainId: EVMCFG.chainId, destinationCurrency: EVMCFG.pay.address, amountOut: units});
    say('Getting a bridge quote from Relay\u2026');
    const r = await fetch(EVMCFG.relayApi + '/quote', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
    const quote = await r.json();
    if (!r.ok || !quote.steps) { throw new Error((quote && quote.message) || 'No bridge route right now.'); }
    for (const step of quote.steps) {
        for (const item of (step.items || [])) {
            if (item.status === 'complete') { continue; }
            if (step.kind !== 'transaction') { throw new Error('This bridge route needs a signature step that is not supported yet.'); }
            const d = item.data;
            const hexChain = '0x' + Number(d.chainId).toString(16);
            if ((await eth.request({method: 'eth_chainId'})) !== hexChain) {
                await eth.request({method: 'wallet_switchEthereumChain', params: [{chainId: hexChain}]});
            }
            say((step.description || step.action || 'Confirm in your EVM wallet') + '\u2026');
            await eth.request({method: 'eth_sendTransaction', params: [{from: d.from, to: d.to, data: d.data, value: d.value ? '0x' + BigInt(d.value).toString(16) : '0x0'}]});
            if (item.check && item.check.endpoint) {
                say('Waiting for the funds to arrive on Flow EVM\u2026');
                for (let i = 0; i < 120; i++) {
                    await new Promise((res) => setTimeout(res, 3000));
                    const s = await (await fetch(EVMCFG.relayApi + item.check.endpoint)).json();
                    if (s.status === 'success') { break; }
                    if (s.status === 'failure' || s.status === 'refund') { throw new Error('The bridge transfer failed (' + s.status + '): your funds are refunded on ' + origin.name + '.'); }
                    if (i === 119) { throw new Error('The bridge is taking longer than usual. Check again in a few minutes.'); }
                }
            }
        }
    }
    return {simulated: false};
}

/** Pay-token units the user still needs for a quote (0n when the COA already holds enough). */
export function shortfall(q) { return q && q.plan ? q.plan.short : 0n; }

export {toUnits};
