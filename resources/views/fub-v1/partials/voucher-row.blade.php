{{-- FUB voucher picker: every voucher of the wallet as a card with its TerraKit illustration, all in one row
     (scrolls sideways when there are many). Needs fubStudio: voucherCards, selVouchers, toggleVoucher. --}}
<div class="fub-vrow" role="group" aria-label="Your FUB vouchers" data-fub="vouchers">
    <template x-for="v in voucherCards" :key="'v' + v.id">
        <button type="button" class="fub-vcard" :class="{on: selVouchers.includes(v.id)}" :aria-pressed="selVouchers.includes(v.id) ? 'true' : 'false'"
                :aria-label="v.rarity + ' FUB voucher #' + v.id" @click="toggleVoucher(v.id)" data-fub="voucher">
            <span class="fub-vimg"><img x-show="v.img" :src="v.img" alt="" loading="lazy"></span>
            <span class="isl-chip" :class="'isl-r-' + v.rarity" x-text="v.rarity"></span>
            <b x-text="'#' + v.id"></b>
        </button>
    </template>
</div>
