@if(\App\Support\DustSwap::enabled())
{{-- ĐUST swap (KB notes/dust-swap.md): quick-swap widget on /dust. Rendered only when DUST_SWAP_ENABLED. Chain-first:
     quotes come from the Increment router through a Cadence script in the browser; no quote = no swap. FLOW → ĐUST for
     Flow Wallet users and passkey users holding FLOW; ĐUST → FLOW for self-custody Flow Wallet sessions only; hidden
     for Dapper. --}}
<section class="dust-swap-section" id="swap" x-data="dustSwapWidget()" x-show="wVisible()" x-cloak>
    <div class="container">
        <div class="dust-swap-widget">
            <div class="dust-swap-head">
                <h2>Swap ĐUST</h2>
                <span class="dust-rate-chip" x-show="$store.dustSwap.ok" x-bind:class="{'is-low': $store.dustSwap.low}">
                    <span x-text="$store.dustSwap.rateLabel()"></span>
                    <em x-show="$store.dustSwap.low">low liquidity</em>
                </span>
            </div>
            <p class="dust-swap-sub" x-show="$store.dustSwap.loaded && !$store.dustSwap.ok">The ĐUST/FLOW pool can't be read right now, so swapping is paused.</p>
            <div x-show="$store.dustSwap.ok">
                <div class="dust-swap-row">
                    <label>You pay</label>
                    <div class="dust-swap-input">
                        <input type="number" min="0" step="any" inputmode="decimal" placeholder="0.0" x-model="wAmount" @input="wQueue()" name="dust_swap_in">
                        <span class="unit" x-text="wInLabel()"></span>
                    </div>
                    <div class="dust-swap-bal" x-show="wBalanceIn() !== null">
                        <span x-text="'Available: ' + (wFlowToDust ? dustSwapFmt.flow(wBalanceIn()) : dustSwapFmt.dust(wBalanceIn()))"></span>
                        <a href="#" @click.prevent="wMax()">Max</a>
                    </div>
                </div>
                <div class="dust-swap-flip">
                    <button type="button" class="btn btn-icon btn-sm" @click.prevent="wFlip()" x-bind:disabled="!wCanSell()" x-bind:title="wCanSell() ? 'Switch direction' : 'Selling ĐUST for FLOW is available for Flow Wallet accounts only'"><span class="inner">⇅</span></button>
                </div>
                <div class="dust-swap-row">
                    <label>You receive (estimate)</label>
                    <div class="dust-swap-input is-out">
                        <span class="out" x-text="wOutText() || '—'"></span>
                        <span class="unit" x-text="wOutLabel()"></span>
                    </div>
                </div>
                <ul class="dust-swap-details" x-show="wQuote">
                    <li><span>Minimum received</span><strong x-text="wMinText()"></strong></li>
                    <li><span>Price impact (incl. 0.3% fee)</span><strong x-bind:class="'impact-' + wLevel()" x-text="dustSwapFmt.pct(wQuote ? wQuote.impact : 0)"></strong></li>
                    <li>
                        <span>Max slippage</span>
                        <select x-model.number="wSlippage">
                            <option value="0.5">0.5%</option>
                            <option value="1">1%</option>
                            <option value="2">2%</option>
                            <option value="3">3%</option>
                        </select>
                    </li>
                </ul>
                <p class="dust-note impact-warn" x-show="wQuote && wLevel() === 'warn'">High price impact: the pool is thin, consider a smaller amount.</p>
                <p class="dust-note impact-block" x-show="wQuote && wLevel() === 'block'">Price impact above 10%: swap a smaller amount.</p>
                <p class="dust-note" x-show="wError" x-text="wError"></p>
                <div class="dust-swap-cta">
                    <button class="btn btn-green" @click.prevent="if(wSession() === 'none'){ {!! \App\Support\PasskeyLogin::connectAction() !!} } else { wSwap() }" x-bind:disabled="wSession() !== 'none' && !wCanSwap()"><span class="inner" x-text="wButtonLabel()"></span></button>
                </div>
                <p class="dust-swap-foot">Swaps run on the IncrementFi ĐUST/FLOW pool in one transaction: if the price moves past your slippage or 5 minutes pass, nothing happens. <span x-show="wSession() === 'passkey' && !wCanSell()">Passkey accounts can buy ĐUST with FLOW; selling ĐUST needs a Flow Wallet.</span></p>
            </div>
        </div>
    </div>
</section>
@endif