@if(\App\Support\DustSwap::enabled())
{{-- ĐUST swap (KB notes/dust-swap.md): "Pay with ĐUST" for a FLOW listing on FlovatarMarketplace (Flovatar / Flobit
     pages, inside the Marketplace box <ul>; page scope: isSale(), isOwned(), isDapperSale(), sale, address).
     SELLS ĐUST into the pool → self-custody Flow Wallet sessions only (never passkey, never Dapper). Rendered only when
     DUST_SWAP_ENABLED; shown when the spendable FLOW is short of the price and the ĐUST balance covers the swap. --}}
<li class="dust-swap-pay" x-data="dustSwapPayDust('{{ $swapKind }}')" x-show="pdVisible()" x-cloak>
    <strong>Pay with ĐUST</strong>
    <div class="col-r">
        <div class="btn-hold">
            <button @click.prevent.stop="payWithDust" x-bind:disabled="pdBusy || pdLevel() === 'block'" class="btn btn-sm btn-lilac"><span class="inner" x-text="pdButtonLabel()"></span></button>
        </div>
        <span class="dust-note" x-text="pdNote()"></span>
        <span class="dust-note dust-swap-impact" x-bind:class="'impact-' + pdLevel()" x-text="pdImpactLabel() + (pdLevel() === 'warn' ? ' — high, the pool is thin' : (pdLevel() === 'block' ? ' — too high for this pool' : ''))"></span>
        <label class="dust-swap-slippage">Max slippage
            <select x-model.number="pdSlippage">
                <option value="0.5">0.5%</option>
                <option value="1">1%</option>
                <option value="2">2%</option>
                <option value="3">3%</option>
            </select>
        </label>
    </div>
</li>
@endif