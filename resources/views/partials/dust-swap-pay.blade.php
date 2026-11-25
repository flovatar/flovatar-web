@if(\App\Support\DustSwap::enabled())
{{-- ĐUST swap (KB notes/dust-swap.md): "Pay with FLOW" for a ĐUST listing. Included inside partials/dust-market's <ul>
     (dustMarket scope: dustListing, dustEnough(), canBuyDust(), dustSignedIn()). Rendered only when DUST_SWAP_ENABLED;
     shown when the ĐUST balance is short and the account's spendable FLOW covers the swap. Fails closed (no quote = hidden). --}}
<template x-if="dustListing && canBuyDust()">
    <li class="dust-swap-pay" x-data="dustSwapPay()" x-show="payVisible()" x-cloak>
        <strong>Pay with FLOW</strong>
        <div class="col-r">
            <div class="btn-hold">
                <button @click.prevent.stop="payWithFlow" x-bind:disabled="payBusy || payLevel() === 'block'" class="btn btn-sm btn-lilac"><span class="inner" x-text="payButtonLabel()"></span></button>
            </div>
            <span class="dust-note" x-text="payNote()"></span>
            <span class="dust-note dust-swap-impact" x-bind:class="'impact-' + payLevel()" x-text="payImpactLabel() + (payLevel() === 'warn' ? ' — high, the pool is thin' : (payLevel() === 'block' ? ' — too high, buy ĐUST in smaller steps' : ''))"></span>
            <label class="dust-swap-slippage">Max slippage
                <select x-model.number="paySlippage" @change="recomputePay()">
                    <option value="0.5">0.5%</option>
                    <option value="1">1%</option>
                    <option value="2">2%</option>
                    <option value="3">3%</option>
                </select>
            </label>
        </div>
    </li>
</template>
@endif