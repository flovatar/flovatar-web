{{-- Buy ĐUST modal (any page): window.FlovatarBuyDust.open({amount, reason}) → Promise<{txId, amount, method} | null>;
     also dispatches window event 'buy-dust:done'. Included by layouts/guest when the ĐUST swap feature is on. --}}
<div class="bd-modal" x-data="buyDust({modal: true})" x-show="open" x-cloak @keydown.window="keydown($event)" data-bd="modal">
    <div class="bd-modal_back" @click="close()" aria-hidden="true"></div>
    <div class="bd-modal_box bd-box" x-ref="bd" role="dialog" aria-modal="true" aria-labelledby="bd-modal-title">
        <div class="bd-head">
            <h2 id="bd-modal-title">Buy ĐUST</h2>
            <button type="button" class="bd-x" @click="close()" :disabled="!!busy" aria-label="Close">×</button>
        </div>
        <p class="bd-sub" x-show="reason" x-text="reason"></p>
        @include('partials.buy-dust-body', ['bdId' => 'modal'])
    </div>
</div>
