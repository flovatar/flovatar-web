@if(\App\Support\DustSwap::enabled())
{{-- Buy ĐUST widget, page version (/buy-dust). See partials/buy-dust-body + resources/js/app/buy-dust.js. --}}
<section class="bd-section" x-data="buyDust({})" x-show="swapOn() || evmOn()" x-cloak data-bd="widget">
    <div class="container">
        <div class="bd-box">
            <div class="bd-head">
                <h2>Buy ĐUST</h2>
                <span class="dust-rate-chip" x-show="$store.dustSwap && $store.dustSwap.ok"><span x-text="$store.dustSwap.rateLabel()"></span></span>
            </div>
            <p class="bd-sub">Pay with FLOW, with {{ \App\Support\DustEvm::enabled() ? config('flovatar.dustEvm.pay.symbol', 'USDC') . ' on Flow EVM' : 'FLOW' }}{{ \App\Support\DustEvm::enabled() ? ', or bring USDC from another chain' : '' }}. ĐUST lands in your Flow wallet.</p>
            @include('partials.buy-dust-body', ['bdId' => 'page'])
        </div>
    </div>
</section>
@endif
