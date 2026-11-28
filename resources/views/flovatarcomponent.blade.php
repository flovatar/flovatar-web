<x-guest-layout>
    @push('og')
        @php($ogTemplate = ($templates ?? null) ? $templates->first() : null)
        @if($ogTemplate)
        @include('partials.og-item', ['ogType' => 'template', 'ogId' => $ogTemplate->flow_id, 'ogTitle' => 'Flobit #'.$componentId.' - '.$ogTemplate->name, 'ogUrl' => url('components/'.$componentId)])
        @endif
    @endpush
    @if(\App\Support\Blocklist::isBlocked($address ?? null) || \App\Support\Blocklist::isFlagged('App\Models\FlovatarComponent', $componentId ?? null))
        <div class="container">@include('partials.incident-notice')</div>
    @endif

    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        @if(isset($componentSales))
            window.componentSales = @json($componentSales);
        @endif
        @if(isset($componentSaleOffers))
            window.componentSaleOffers = @json($componentSaleOffers);
        @endif
        @if(isset($componentSaleOffer))
            window.componentSaleOffer = @json($componentSaleOffer);
        @endif
    </script>

    <section class="flovatar-detail"
             x-data="componentData('{{$address}}', window.templates, window.componentSaleOffer)"
             x-init="initComponent($dispatch, '{{$address}}', {{$componentId}})"
             @fcomponent-loaded.window="component = $event.detail.component; isLoading = false;"
             @fcomponent-sale-loaded.window="sale = $event.detail.sale; isLoading = false; if($event.detail.sale) {currentPrice = parseFloat($event.detail.sale.price); currentPriceChange = currentPrice;}"
             @fcomponent-sale-created.window="console.log('sale created!')"
             @fcomponent-sale-deleted.window="console.log('sale deleted!'); currentPrice = null;"
             @price-changed.window="changePriceOpen = false; currentPrice = currentPriceChange;"
             @purchase-completed.window="console.log('purchase completed!')"
    >
        <div class="container">
            <div class="row">
                <div class="col-l">
                    <div class="img-holder">
                        <div class="img">
                            <div x-bind:class="isFlobit() ? 'builder-component-thumb' : ''" x-html="svg()"></div>
                        </div>
                        <div class="info-circles">
                            <div class="circle" x-show="isSale()">
                                <div class="txt-img-t">
                                    <img src="/images/text-on-sale-t.svg" width="58" alt="On Sale">
                                </div>
                                <span class="score text-green" x-html="formatPrice(currentPrice)+'<sub>F</sub>'"></span>
                                <div class="txt-img-b">
                                    <img src="/images/text-on-sale-b.svg" width="58" alt="On Sale">
                                </div>
                            </div>
                        </div>
                        <div class="controller" x-show="false">
                            <a x-show="isOwned()" x-bind:href="'/collection/'+address+'?addFlobit={{$componentId}}'" class="controller-item">
                                <i class="icon-upgrade"></i>
                            </a>
                            <button @click.prevent.stop="showZoomModal=true" class="controller-item">
                                <i class="icon-zoom"></i>
                            </button>
                        </div>
                    </div>
                    <div class="info" x-show="!mint()">
                        <dl x-show="!isLoading">
                            <dt>Not Found!</dt>
                            <dd>It's likely that this Flovatar has changed its owner.</dd>
                        </dl>

                        <dl x-show="isLoading">
                            <dt>Loading</dt>
                            <dd>Please wait a few floments...</dd>
                        </dl>
                    </div>
                    <div class="btns-container" x-show="mint()">
                        <div class="btns-row">
                            <div class="btns-list-wrap">
                                <ul class="btns-list">
                                    <li class="grow">
                                        <a x-bind:href="getMarketplaceUrl()" class="btn btn-sm btn-lilac"><span class="inner">View in Marketplace</span></a>
                                    </li>
                                    <li x-show="!isSale() && isOwned() && !showSale{{ \App\Support\DustMarket::enabled() ? ' && !$store.dust.live' : '' }}">
                                        <button @click.prevent.stop="showSale = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Sell</span></button>
                                    </li>
                                    <li x-show="showSaleBtn()">
                                        <button @click.prevent.stop="buySale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Buy Now</span></button>
                                    </li>
                                    <li x-show="isSale() && isOwned() && !showSale">
                                        <button @click.prevent.stop="cancelSale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Cancel Sale</span></button>
                                    </li>
                                    <li x-show="showGift">
                                        <button @click.prevent.stop="showGift = false" x-bind:disabled="!loggedIn()" class="btn btn-icon btn-gray"><span class="inner"><i class="icon-close"></i></span></button>
                                    </li>
                                    <li x-show="!isSale() && isOwned() && !showGift{{ \App\Support\DustMarket::enabled() ? ' && !$store.dust.live' : '' }}">
                                        <button @click.prevent.stop="showGift = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-lilac"><span class="inner">Gift</span></button>
                                    </li>
                                </ul>
                            </div>
                            <div class="foot" x-show="showGift">
                                <div class="input-frame nowrap-frame">
                                    <input x-model="currentGiftAddress" type="text" placeholder="0x1234567890abcdef">
                                    <span class="label">Wallet</span>
                                </div>
                                <button @click.prevent.stop="sendGift" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-r" x-show="mint()">
                    <div class="content-box">
                        <div class="title-row">
                            <h2>Stats</h2>
                        </div>
                        <div class="box">
                            <ul class="stat-list">
                                @if(($templates ?? null) && $templates->first() && $templates->first()->flow_id)
                                <li>
                                    <strong>Type</strong>
                                    <span><a href="/flobits/{{ $templates->first()->flow_id }}">{{ $templates->first()->name }}</a></span>
                                </li>
                                @endif
                                <li>
                                    <strong>Mint</strong>
                                    <span x-text="'#'+mint()"></span>
                                </li>
                                <li>
                                    <strong>Category</strong>
                                    <span x-text="category() == 'boost' ? 'Booster' : category()"></span>
                                </li>
                                <li>
                                    <strong>Rarity</strong>
                                    <span x-text="rarity()"></span>
                                </li>
                                <li>
                                    <strong>Series</strong>
                                    <span x-text="series()"></span>
                                </li>
                                <li>
                                    <strong>Burned</strong>
                                    <span x-text="burned()"></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    @include('partials.evm-bridge', ['evmSlug' => 'flobits', 'evmId' => $componentId])
                    @include('partials.dust-market', ['dustKind' => 'flobit', 'dustId' => $componentId])
                    <div class="content-box" x-show="isSale() || (isOwned() && showSale)">
                        <div class="title-row">
                            <h2>Marketplace</h2>
                        </div>
                        <div class="box">
                            <ul class="content-list">
                                <li x-show="isSale()">
                                    <strong>Sale Price</strong>
                                    <div class="col-r" x-show="isOwned() && !isDapperSale()">
                                        <div class="input-frame nowrap-frame">
                                            <input x-model.number="currentPriceChange" type="number" name="price" min="0.1" step="0.1">
                                            <span class="label">Flow</span>
                                        </div>
                                        <div class="btn-hold">
                                            <button @click.prevent.stop="changePrice" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                            <button @click.prevent.stop="cancelSale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-red"><span class="inner">Cancel</span></button>
                                        </div>
                                    </div>
                                    <div class="col-r" x-show="!isOwned() || isDapperSale()">
                                        <div class="input-frame">
                                            <span class="input-txt" x-text="formatPrice(currentPrice)+ 'Flow'"></span>
                                        </div>
                                    </div>
                                </li>
                                <li x-show="isSale()">
                                    <strong>Marketplace</strong>
                                    <div class="col-r">
                                        <div class="logo-wrap" x-show="isDapperSale()">
                                            <div class="logo">
                                                <img src="/images/ico-dapper.png" width="40" alt="">
                                            </div>
                                            <span>DAPPER</span>
                                        </div>
                                        <div class="logo-wrap" x-show="!isDapperSale()">
                                            <div class="logo">
                                                <img src="/images/icon-flow.svg" width="40" alt="">
                                            </div>
                                            <span>Flow Wallet</span>
                                        </div>
                                    </div>
                                </li>
@include('partials.dust-swap-pay-dust', ['swapKind' => 'flobit'])
                                <li x-show="!isSale() && isOwned() && showSale">
                                    <strong>Sale Price</strong>
                                    <div class="col-r" x-show="isOwned() && !isDapperSale()">
                                        <div class="input-frame nowrap-frame">
                                            <input x-model.number="currentPrice" type="number" name="price" min="0.1" step="0.1">
                                            <span class="label">Flow</span>
                                        </div>
                                        <div class="btn-hold">
                                            <button @click.prevent.stop="createSale" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    @php($dlTemplate = ($templates ?? null) ? $templates->first() : null)
                    @if($dlTemplate && $dlTemplate->flow_id)
                    <div class="content-box downloads-box">
                        <div class="title-row">
                            <h2>Downloads</h2>
                        </div>
                        <div class="box">
                            <ul class="content-list">
                                <li>
                                    <strong>2D Assets</strong>
                                    <div class="col-r">
                                        <div class="input-panel download-panel">
                                            @include('partials.render-downloads', ['dlType' => 'template', 'dlId' => $dlTemplate->flow_id, 'dlName' => 'Flobit-'.$componentId])
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

    </section>



<!--


    <div
         x-data="componentData('{{$address}}', window.templates, window.componentSaleOffer)"
         x-init="initComponent($dispatch, '{{$address}}', {{$componentId}})"
         @fcomponent-loaded.window="component = $event.detail.component; isLoading = false;"
         @fcomponent-sale-loaded.window="sale = $event.detail.sale; isLoading = false; if($event.detail.sale) {currentPrice = parseFloat($event.detail.sale.price); currentPriceChange = currentPrice;}"
         @fcomponent-sale-created.window="console.log('sale created!')"
         @fcomponent-sale-deleted.window="console.log('sale deleted!'); currentPrice = null;"
         @price-changed.window="changePriceOpen = false; currentPrice = currentPriceChange;"
         @purchase-completed.window="console.log('purchase completed!')"
    >



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
                            <dd>It's likely that this Flovatar has changed its owner.</dd>
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
                                <div x-show="!isSale() && isOwned() && !showGift{{ \App\Support\DustMarket::enabled() ? ' && !$store.dust.live' : '' }}" class="btn-wrp">
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





        <div x-show="!isLoading" class="market-history-chart" x-data="chartData(window.componentSales, window.componentSaleOffers)" x-init="renderChart()">
            <div class="container">
                <div class="title-row">
                    <h2>Sales History</h2>
                </div>
                <div class="chart">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>



    </div>
    -->


    @include('partials.powered-by')


</x-guest-layout>
