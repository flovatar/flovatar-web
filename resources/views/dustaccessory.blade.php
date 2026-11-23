<x-guest-layout>

    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        @if(isset($accessorySales))
            window.accessorySales = @json($accessorySales);
        @endif
        @if(isset($accessorySaleOffers))
            window.accessorySaleOffers = @json($accessorySaleOffers);
        @endif
        @if(isset($accessorySaleOffer))
            window.accessorySaleOffer = @json($accessorySaleOffer);
        @endif
    </script>

    <div
         x-data="accessoryData('{{$address}}', window.templates, window.accessorySaleOffer)"
         x-init="initAccessory($dispatch, '{{$address}}', {{$accessoryId}})"
         @fcomponent-loaded.window="accessory = $event.detail.accessory; isLoading = false;"
         @fcomponent-sale-loaded.window="sale = $event.detail.sale; isLoading = false; if($event.detail.sale) {currentPrice = parseFloat($event.detail.sale.price); currentPriceChange = currentPrice;}"
         @fcomponent-sale-created.window="console.log('sale created!')"
         @fcomponent-sale-deleted.window="console.log('sale deleted!'); currentPrice = null;"
         @price-changed.window="changePriceOpen = false; currentPrice = currentPriceChange;"
         @purchase-completed.window="console.log('purchase completed!')"
    >



        <!-- Flobit Details Page -->
        <div class="flovatar-details-section">
            <div class="container">
                <div class="media">
                    <div class="img-hold">
                        <div class="img-frame">
                            <div x-bind:class="isFlobit() ? 'builder-component-thumb' : ''" x-html="svg()"></div>
                        </div>
                    </div>

                    <div class="info" x-show="!mint()">
                        <dl x-show="!isLoading">
                            <dt>Not Found!</dt>
                            <dd>It's likely that this Collectible Accessory has changed its owner.</dd>
                        </dl>

                        <dl x-show="isLoading">
                            <dt>Loading</dt>
                            <dd>Please wait a few floments...</dd>
                        </dl>
                    </div>

                    <div class="info" x-show="mint()">
                        <h3 x-text="name()"></h3>
                        <dl>
                            <dt>Mint</dt>
                            <dd x-text="'#'+mint()"></dd>
                            <dt>Category</dt>
                            <dd x-text="category() == 'boost' ? 'Booster' : category()"></dd>
                            <dt>Rarity</dt>
                            <dd x-text="rarity()"></dd>
                            <dt>Series</dt>
                            <dd x-text="series()"></dd>
                        </dl>


                        <dl x-show="isSale()" class="details-input-form">
                            <dt>Sale price</dt>
                            <dd class="price-input-detail">
                                <div class="input-panel" x-show="isOwned() && !isDapperSale()">
                                    <div class="input-box">
                                        <div class="input-wrap">
                                            <input x-model.number="currentPriceChange" type="number" name="price" min="0.1" step="0.1">
                                        </div>
                                        <span class="label">Flow</span>
                                    </div>
                                    <button @click.prevent.stop="changePrice" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                </div>
                                <span x-show="!isOwned() || isDapperSale()" x-text="formatPrice(currentPrice)+ 'Flow'"></span>
                            </dd>
                            <dt>Marketplace</dt>
                            <dd>
                                <div class="mp-label" x-show="isDapperSale()">
                                    <span class="ico-wrap">
                                        <img src="/images/ico-dapper.png" width="40" alt="">
                                    </span>
                                    <span>Dapper</span>
                                </div>
                                <div class="mp-label"  x-show="!isDapperSale()">
                                    <div class="icons">
                                        <span class="ico-wrap">
                                            <img src="/images/icon-flow.svg" width="40" alt="">
                                        </span>
                                    </div>
                                    <span>Flow Wallet</span>
                                </div>
                            </dd>
                        </dl>
                        <dl x-show="!isSale() && isOwned() && showSale" class="details-input-form">
                            <dt>Sale price</dt>
                            <dd>
                                <div class="input-panel">
                                    <div class="input-box">
                                        <div class="input-wrap">
                                            <input x-model.number="currentPrice" type="number" name="price" min="0.1" step="0.1">
                                        </div>
                                        <span class="label">Flow</span>
                                    </div>
                                    <button @click.prevent.stop="createSale" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                </div>
                            </dd>
                        </dl>
                        <dl x-show="showGift" class="details-input-form">
                            <dt>Wallet Address</dt>
                            <dd>
                                <div class="input-panel">
                                    <div class="input-box">
                                        <div class="input-wrap">
                                            <input x-model="currentGiftAddress" type="text" placeholder="0x1234567890abcdef">
                                        </div>
                                    </div>
                                    <button @click.prevent.stop="sendGift" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                </div>
                            </dd>
                        </dl>

                        <div class="btns-row">
                            <div class="btn-wrp">
                                <a x-bind:href="getMarketplaceUrl()" class="btn btn-sm btn-lilac"><span class="inner">View in Marketplace</span></a>
                            </div>
                            <div class="btns-right">
                                <div x-show="!isSale() && isOwned() && !showGift" class="btn-wrp">
                                    <button @click.prevent.stop="showGift = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-lilac"><span class="inner">Gift</span></button>
                                </div>
                                <div x-show="!isSale() && isOwned() && !showSale" class="btn-wrp">
                                    <button @click.prevent.stop="showSale = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Sell</span></button>
                                </div>
                                <div x-show="isSale() && !isOwned() && !showSale" class="btn-wrp">
                                    <button @click.prevent.stop="buySale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Buy Now</span></button>
                                </div>
                                <div x-show="isSale() && isOwned() && !showSale" class="btn-wrp">
                                    <button @click.prevent.stop="cancelSale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-red"><span class="inner">Cancel Sale</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>





        <div x-show="!isLoading" class="market-history-chart" x-data="chartData(window.accessorySales, window.accessorySaleOffers)" x-init="renderChart()">
            <div class="container">
                <div class="title-row">
                    <h2>Sales History</h2>
                </div>
                <div class="chart">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- End of flobit Details Page -->


    </div>
    @include('partials.powered-by')


</x-guest-layout>
