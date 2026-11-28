{{-- Buy ĐUST widget body (KB notes/flow-evm-program.md EVM-B). Used by /buy-dust (partials/buy-dust-widget) and by the
     on-demand modal (partials/buy-dust-modal, window.FlovatarBuyDust.open). Alpine component buyDust() in
     resources/js/app/buy-dust.js. Chain-first quotes; options without a usable quote stay disabled with the reason. --}}
<p class="bd-warn-box" x-show="session() === 'dapper'">Buying ĐUST needs a Flow Wallet: Dapper wallets don't hold ĐUST.</p>
<div class="bd-amount">
    <label for="bd-amount-{{ $bdId }}">ĐUST you want</label>
    <div class="bd-input">
        <span class="bd-d" aria-hidden="true">Đ</span>
        <input id="bd-amount-{{ $bdId }}" type="text" inputmode="decimal" autocomplete="off" x-model="amount" @input="queue()" :disabled="!!busy" data-bd="amount">
    </div>
    <div class="bd-chips" role="group" aria-label="Quick amounts">
        <template x-for="c in chips()" :key="'c' + c"><button type="button" @click="setAmount(c)" :class="{on: num() === c}" :disabled="!!busy" x-text="fmtDust(c)"></button></template>
    </div>
    <small class="bd-warn" x-show="amountProblem()" x-text="amountProblem()"></small>
</div>
<div class="bd-opts" role="radiogroup" aria-label="Pay with">
    <label class="bd-opt" x-show="swapOn()" :class="{on: method === 'flow', off: !ok('flow')}" data-bd="opt-flow">
        <input type="radio" value="flow" x-model="method" :disabled="!ok('flow') || !!busy">
        <span class="bd-coin is-flow" aria-hidden="true">F</span>
        <span class="bd-txt"><b>FLOW</b><span x-text="quoteText('flow') || (fLoading ? 'Getting the price…' : '')"></span>
            <small>Increment ĐUST/FLOW pool, one transaction.</small>
            <small class="bd-warn" x-show="!ok('flow') && !fLoading" x-text="reasons().flow"></small></span>
    </label>
    <label class="bd-opt" x-show="evmOn()" :class="{on: method === 'usdc', off: !ok('usdc')}" data-bd="opt-usdc">
        <input type="radio" value="usdc" x-model="method" :disabled="!ok('usdc') || !!busy">
        <span class="bd-coin is-usdc" aria-hidden="true">$</span>
        <span class="bd-txt"><b x-text="sym() + ' on Flow EVM'"></b><span x-text="quoteText('usdc') || (eLoading ? 'Getting the price…' : '')" data-bd="usdc-quote"></span>
            <small>From your wallet's Flow EVM account: swap, bridge and deliver in one transaction.</small>
            <small class="bd-warn" x-show="!ok('usdc') && !eLoading && reasons().usdc" x-text="reasons().usdc" data-bd="usdc-reason"></small>
            <button type="button" class="bd-link" x-show="canFaucet() && !ok('usdc') && !eLoading" @click.prevent="faucet()" :disabled="!!busy" data-bd="faucet" x-text="'Testnet: get 25 test ' + sym()"></button></span>
    </label>
    <label class="bd-opt" x-show="evmOn() && xmode() !== 'off'" :class="{on: method === 'xchain', off: !ok('xchain')}" data-bd="opt-xchain">
        <input type="radio" value="xchain" x-model="method" :disabled="!ok('xchain') || !!busy">
        <span class="bd-coin is-xchain" aria-hidden="true">⇄</span>
        <span class="bd-txt"><b>Another chain</b><span x-text="quoteText('xchain') || (eLoading ? 'Getting the price…' : '')"></span>
            <small x-text="xmode() === 'simulate' ? 'USDC from Base, Arbitrum, Ethereum… Testnet: the bridge is simulated (test USDC is minted to your Flow EVM account).' : 'USDC from Base, Arbitrum, Ethereum or Optimism, bridged by Relay to your Flow EVM account, then swapped.'"></small>
            <small class="bd-warn" x-show="!ok('xchain') && !eLoading && reasons().xchain" x-text="reasons().xchain"></small></span>
    </label>
</div>
<div class="bd-quote" x-show="!amountProblem() && (method === 'flow' ? !!fq : !!(eq && eq.plan))" data-bd="quote">
    <dl>
        <div x-show="method !== 'flow'"><dt>Route</dt><dd x-text="routeLabel()" data-bd="route"></dd></div>
        <div><dt>Price impact</dt><dd :class="{warn: level() !== 'ok'}" x-text="fmtPct(impact())" data-bd="impact"></dd></div>
        <div><dt>Max slippage</dt><dd>
            <select x-model.number="slippage" @change="setSlippage($event.target.value)" :disabled="!!busy" aria-label="Maximum slippage">
                <option value="0.5">0.5%</option><option value="1">1%</option><option value="2">2%</option><option value="3">3%</option>
            </select></dd></div>
    </dl>
    <p class="bd-max" x-text="maxText()" data-bd="max"></p>
    <label class="bd-origin" x-show="method === 'xchain' && xmode() === 'relay'"><span>From</span>
        <select x-model.number="origin" :disabled="!!busy"><template x-for="(o, i) in origins()" :key="'o' + o.chainId"><option :value="i" x-text="o.name + ' (USDC)'"></option></template></select>
    </label>
    <p class="bd-warn-box" x-show="level() === 'warn'" role="alert">Thin pool: this purchase moves the price by <b x-text="fmtPct(impact())"></b>. Smaller amounts cost less per ĐUST.</p>
</div>
<dl class="bd-bal" x-show="balances().length">
    <template x-for="b in balances()" :key="b[0]"><div><dt x-text="b[0]"></dt><dd x-text="b[1]"></dd></div></template>
</dl>
<p class="bd-note" x-show="note" x-text="note" role="status" data-bd="note"></p>
<p class="bd-error" x-show="error" x-text="error" role="alert" data-bd="error"></p>
<p class="bd-busy" x-show="busy" role="status"><i class="led" aria-hidden="true"></i><span x-text="busy"></span></p>
<p class="bd-done" x-show="done" role="status" data-bd="done">
    <span x-text="done ? fmtDust(done.amount) + ' added to your wallet.' : ''"></span>
    <a :href="done ? explorerTx(done.txId) : '#'" target="_blank" rel="noopener">View transaction</a>
</p>
<div class="bd-cta">
    <button type="button" class="btn btn-green" data-bd="buy"
            @click.prevent="if(!addr()){ {!! \App\Support\PasskeyLogin::connectAction() !!} } else { buy() }"
            :disabled="addr() && !canBuy()"><span class="inner" x-text="ctaLabel()"></span></button>
</div>
<p class="bd-foot">One transaction per step: if the price moves past your slippage or 5 minutes pass, nothing is spent. Prices are read live on chain.</p>
