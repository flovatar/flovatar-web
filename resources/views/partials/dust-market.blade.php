{{-- ĐUST marketplace (KB notes/dust-marketplace.md): rendered only when DUST_MARKET_ENABLED. Nested inside the item
     page's x-data (address / user / profile / isSale() / isDapperWallet() from the parent scopes). Chain-first: the
     listing comes from the seller's NFTStorefrontV2 in the browser. No ĐUST features for Dapper wallets (Luca). --}}
@if(\App\Support\DustMarket::enabled())
<div class="content-box dust-market-box" x-data="dustMarket('{{ $dustKind }}', {{ (int) $dustId }})"
     x-show="dustListing || dustDead || canListDust()" x-cloak>
    <div class="title-row">
        <h2>ĐUST Marketplace</h2>
    </div>
    <div class="box">
        <ul class="content-list">
            <template x-if="dustListing">
                <li>
                    <strong>Price</strong>
                    <div class="col-r">
                        <div class="input-frame nowrap-frame">
                            <span class="input-txt dust-price" x-text="dustFmt(dustListing.price)"></span>
                        </div>
                        <div class="btn-hold" x-show="dustMine() && !dustDapper()">
                            <button @click.prevent.stop="cancelDust" x-bind:disabled="!dustSignedIn()" class="btn btn-sm btn-red"><span class="inner">Cancel ĐUST listing</span></button>
                        </div>
                        <div class="btn-hold" x-show="canBuyDust()">
                            <button @click.prevent.stop="buyDust" x-bind:disabled="!dustSignedIn() || !dustEnough()" class="btn btn-sm btn-green"><span class="inner" x-text="'Buy for ' + dustFmt(dustListing.price)"></span></button>
                        </div>
                    </div>
                </li>
            </template>
            <template x-if="dustListing && canBuyDust() && dustSignedIn()">
                <li>
                    <strong>Your ĐUST</strong>
                    <div class="col-r">
                        <span class="input-txt" x-text="dustFmt(dustBalance())"></span>
                        <span class="dust-note" x-show="!dustEnough()" x-text="'You need ' + dustFmt(dustListing.price - dustBalance()) + ' more ĐUST'"></span>
                    </div>
                </li>
            </template>
            <template x-if="dustListing && !dustSignedIn() && !dustDapper()">
                <li><span class="dust-note">Sign in to buy with ĐUST.</span></li>
            </template>
            <template x-if="dustListing && dustMine()">
                <li><span class="dust-note">Listed for ĐUST on the Flow NFTStorefront. To change the price, cancel and list again.</span></li>
            </template>
            <template x-if="!dustListing && dustDead">
                <li>
                    <strong>Old ĐUST listing</strong>
                    <div class="col-r">
                        <span class="dust-note" x-text="dustDead.purchased ? 'Sold, waiting for clean-up' : (dustDead.expired ? 'Expired' : 'The seller no longer holds this item')"></span>
                        <div class="btn-hold" x-show="dustSignedIn() && !dustDapper()">
                            <button @click.prevent.stop="cleanupDust" class="btn btn-sm btn-lilac"><span class="inner">Clean up</span></button>
                        </div>
                    </div>
                </li>
            </template>
            <template x-if="canListDust()">
                <li>
                    <strong>List for ĐUST</strong>
                    <div class="col-r">
                        <div class="input-frame nowrap-frame">
                            <input x-model.number="dustPrice" @input="queuePreview()" type="number" name="dust_price" min="1" step="1" placeholder="0">
                            <span class="label">ĐUST</span>
                        </div>
                        <div class="btn-hold">
                            <button @click.prevent.stop="listDust" x-bind:disabled="!dustSignedIn() || !(dustPrice > 0)" type="submit" class="btn btn-sm btn-green"><span class="inner">List for ĐUST</span></button>
                        </div>
                    </div>
                </li>
            </template>
            <template x-if="canListDust() && dustPreview">
                <li>
                    <strong>You receive</strong>
                    <div class="col-r">
                        <span class="input-txt" x-text="dustFmt(dustPreview.seller)"></span>
                        <span class="dust-note" x-text="'Marketplace fee ' + Math.round(dustPreview.feeRate * 100) + '% (' + dustFmt(dustPreview.fee) + ')' + (dustPreview.creator > 0 ? ', creator royalty ' + dustFmt(dustPreview.creator) : '')"></span>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
@endif
