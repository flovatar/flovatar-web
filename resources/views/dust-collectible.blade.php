<x-guest-layout>
    @push('og')
        @include('partials.og-item', ['ogType' => 'collectible', 'ogId' => $collectibleId, 'ogTitle' => 'Flovatar Collectible #'.$collectibleId, 'ogUrl' => url('collectibles/'.$collectibleId)])
    @endpush
    @if(\App\Support\Blocklist::isBlocked($address ?? null) || \App\Support\Blocklist::isFlagged('App\Models\DustCollectible', $collectibleId ?? null))
        <div class="container">@include('partials.incident-notice')</div>
    @endif

    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
            @if(isset($collectibleSales))
            window.collectibleSales = @json($collectibleSales);
        @endif
        @if(isset($collectibleSaleOffer))
            window.collectibleSaleOffer = @json($collectibleSaleOffer);
        @endif
    </script>


    <section class="flovatar-detail"
             x-data="collectibleData('{{$address}}', {{$collectibleId}}, window.templates, window.collectibleSaleOffer)"
             x-init="initCollectible($dispatch, '{{$address}}', {{$collectibleId}})"
             @item-owner-resolved.window="address = $event.detail.address"
             @fcollectible-loaded.window="loadCollectible($event.detail.collectible)"
             @fcollectible-sale-created.window="console.log('sale created!')"
             @fccollectible-sale-deleted.window="console.log('sale deleted!'); currentPrice = null;"
             @purchase-completed.window="console.log('purchase completed!')"
             @address-profile-loaded.window="loadCreatorName($event.detail.profile)"
             @address-profile-owner-loaded.window="loadOwnerName($event.detail.profile)"
    >
        <div class="container">
            <div class="row">
                <div class="col-l">
                    <div class="img-holder">
                        <div class="img">
                            <div x-html="svg()"></div>
                        </div>
                        <div class="info-circles">
                            <div class="circle">
                                <div class="txt-img-t">
                                    <img src="/images/text-rarity-score.svg" width="96" alt="Rarity Score">
                                </div>
                                <span class="score lg" x-bind:class="getRarityClass()" x-text="rarityText()"></span>
                                <div class="txt-img-b">
                                    <img x-bind:src="'/images/text-'+getRarityClass()+'.svg'" width="76" alt="">
                                </div>
                            </div>
                            <div x-show="isSale()" class="circle">
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
                            <button @click.prevent.stop="showZoomModal=true" class="controller-item">
                                <i class="icon-zoom"></i>
                            </button>
                        </div>
                    </div>
                    <div class="info" x-show="!mint()">
                        <dl x-show="!isLoading">
                            <dt>Not Found!</dt>
                            <dd>It's likely that this Psyche Likee has changed its owner.</dd>
                        </dl>

                        <dl x-show="isLoading">
                            <dt>Loading</dt>
                            <dd>Please wait a few floments...</dd>
                        </dl>
                    </div>
                    <div class="btns-container" x-show="mint()">
                        <strong class="title" x-text="name() ? name() : 'Psyche Likee #'+mint()"></strong>
                        <div class="btns-row" x-show="isOwned()">
                            <div class="btns-list-wrap">
                                <ul class="btns-list">
                                    <li x-show="false">
                                        <a x-bind:href="combinationUrl()" class="btn btn-sm btn-lilac"><span class="inner"><span class="long-label">View in Builder</span> <span class="short-label">View in Builder</span></span></a>
                                    </li>
                                    <li x-show="!isSale() && isOwned() && !showSale{{ \App\Support\DustMarket::enabled() ? ' && !$store.dust.live' : '' }}">
                                        <button @click.prevent.stop="showSale = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Sell</span></button>
                                    </li>
                                    <li x-show="isSale() && isOwned() && !showSale">
                                        <button @click.prevent.stop="cancelSale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Cancel Sale</span></button>
                                    </li>
                                    <li x-show="!isSale() && isOwned() && !showGift{{ \App\Support\DustMarket::enabled() ? ' && !$store.dust.live' : '' }}">
                                        <button @click.prevent.stop="showGift = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-lilac"><span class="inner">Gift</span></button>
                                    </li>
                                    <li x-show="showGift">
                                        <button @click.prevent.stop="showGift = false" x-bind:disabled="!loggedIn()" class="btn btn-icon btn-gray"><span class="inner"><i class="icon-close"></i></span></button>
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

                    @include('partials.evm-bridge', ['evmSlug' => 'collectibles', 'evmId' => $collectibleId])
                    @include('partials.dust-market', ['dustKind' => 'collectible', 'dustId' => $collectibleId])
                    <div class="content-box" x-show="isSale() || (isOwned() && showSale)">
                        <div class="title-row">
                            <h2>Marketplace</h2>
                        </div>
                        <div class="box">
                            <ul class="content-list">
                                <li x-show="isSale()">
                                    <strong>Sale Price</strong>
                                    <div class="col-r">
                                        <div class="input-frame nowrap-frame">
                                            <span class="input-txt" x-text="formatPrice(currentPrice)+ 'Flow'"></span>
                                        </div>
                                    </div>
                                </li>
                                <li x-show="isSale()">
                                    <strong>Marketplace</strong>
                                    <div class="col-r">
                                        <div class="logo-wrap" x-show="isDapperListing()">
                                            <div class="logo">
                                                <img src="/images/ico-dapper.png" width="40" alt="">
                                            </div>
                                            <span>Dapper</span>
                                        </div>
                                        <div class="logo-wrap" x-show="!isDapperListing()">
                                            <div class="logo">
                                                <img src="/images/icon-flow.svg" width="40" alt="">
                                            </div>
                                            <span>Flow Wallet</span>
                                        </div>
                                    </div>
                                </li>
                                <li x-show="!isSale() && isOwned() && showSale">
                                    <strong>Sale Price</strong>
                                    <div class="col-r" x-show="isOwned()">
                                        <div class="input-frame nowrap-frame">
                                            <input x-model.number="currentPrice" type="number" name="price" min="0.1" step="0.1">
                                            <span class="label">Flow</span>
                                        </div>
                                        <div class="btn-hold">
                                            <button @click.prevent.stop="createSale" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                        </div>
                                    </div>
                                </li>
                                <li x-show="showSaleBtn()">
                                    <button @click.prevent.stop="buySale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Buy Now</span></button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="content-box">
                        <div class="title-row">
                            <h2>Profile</h2>
                        </div>
                        <div class="box style-1">
                            <form action="#">
                                <ul class="input-list">
                                    <li>
                                        <div class="label-wrap">
                                            <label for="name" class="sm">Name</label>
                                        </div>
                                        <div class="input-col-wrap">
                                            <div class="input-wrap">
                                                <div class="price-tag" x-show="!name()">Ð100</div>
                                                <input x-model="collectibleName" x-show="isOwned() && !name()" type="text" class="input" id="name" placeholder="Give your Psyche Likee a name" min="3" max="32">
                                                <p class="text-entry" x-show="!isOwned() || name()" x-text="name() ? name() : 'N/A'"></p>
                                            </div>
                                            <div class="r-col" x-show="isOwned() && !name()">
                                            <span class="help">
                                                <a href="#"><i class="icon-help"></i></a>
                                                <div class="info-tooltip">
                                                    <strong>Character Name</strong>
                                                    <p>Burn Ð100 to give your Psyche Likee a name.</p>
                                                </div>
                                            </span>
                                                <button @click.prevent.stop="setName()" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="label-wrap top-align">
                                            <label for="story" class="sm">Story</label>
                                        </div>
                                        <div class="input-col-wrap">
                                            <div class="input-wrap">
                                                <div class="price-tag">Ð50</div>
                                                <textarea x-show="isOwned()" x-model="collectibleStory" min="1" max="300" x-show="isOwned()" class="input" placeholder="Tell us about your Psyche Likee"></textarea>
                                                <p class="text-entry" x-show="story()" x-text="story()"></p>
                                            </div>
                                            <div class="r-col top-align" x-show="isOwned()">
                                            <span class="help">
                                                <a href="#"><i class="icon-help"></i></a>
                                                 <div class="info-tooltip">
                                                    <strong>Character Bio</strong>
                                                    <p>Burn Ð50 to add a backstory for your Psyche Likee.</p>
                                                </div>
                                            </span>
                                                <button @click.prevent.stop="addStory()" x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </form>
                        </div>
                    </div>
                    @if(config('services.geo.enabled'))
                    {{-- GEO Location editor: OpenStreetMap search (Photon via /api/v2/geo, Nominatim fallback on Enter), coordinates
                         or click-on-map (Leaflet). Only lat/lon go on chain (setPosition, Đ10). resources/js/geo.js --}}
                    <div class="content-box" x-show="isOwned() || position()">
                        <div class="title-row">
                            <h2>GEO Location</h2>
                        </div>
                        <div class="box">
                            <ul class="input-list">
                                <li x-show="isOwned()">
                                    <div class="label-wrap">
                                        <label for="searchAddress" class="sm">Location</label>
                                    </div>
                                    <div class="input-col-wrap">
                                        <div class="input-wrap" style="position:relative;">
                                            <div class="price-tag">Ð10</div>
                                            <input x-model="searchAddress" @input.debounce.500ms="geocodeAddress()" @keydown.enter.prevent="geocodeAddress(true)" @keydown.escape="geoSuggestions = []" type="text" class="input" id="searchAddress" autocomplete="off" placeholder="City, address -OR- lat, lon">
                                            <ul class="geo-suggestions" x-show="geoSuggestions.length > 1" x-cloak @click.outside="geoSuggestions = []" style="position:absolute;left:0;right:0;top:100%;z-index:30;margin:4px 0 0;padding:4px 0;list-style:none;background:#fff;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.18);max-height:240px;overflow:auto;">
                                                <template x-for="(s, i) in geoSuggestions" :key="i">
                                                    <li><a href="#" @click.prevent="geoPick(s)" x-text="s.label" style="display:block;padding:8px 14px;font-size:14px;line-height:1.3;color:#222;text-decoration:none;"></a></li>
                                                </template>
                                            </ul>
                                        </div>
                                        <div class="r-col">
                                            <span class="help">
                                                <a href="#">
                                                    <i class="icon-help"></i>
                                                </a>
                                                <div class="info-tooltip">
                                                    <strong>Location</strong>
                                                    <p>Burn Ð10 to place your Psyche Likee on the map. Type an address (Enter searches harder), coordinates (lat, lon) or pick the spot on the map. Only the coordinates are stored on chain.</p>
                                                </div>
                                            </span>
                                            <button @click.prevent.stop="setPosition()" x-show="isOwned() && (collectibleLat != 0 || collectibleLon != 0) && position() != searchAddress"  x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green">
                                                <span class="inner"><i class="icon-check"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </li>
                                <li x-show="foundAddress || geoBusy || geoError">
                                    <label class="sm">&nbsp;</label>
                                    <div class="input-wrap">
                                        <p class="text-entry" x-show="foundAddress" x-text="foundAddress"></p>
                                        <p x-show="geoBusy && !foundAddress" style="font-size:13px;opacity:.7;">Searching…</p>
                                        <p x-show="geoError" x-text="geoError" style="font-size:13px;opacity:.8;margin-top:4px;"></p>
                                    </div>
                                    <div class="r-col">
                                    </div>
                                </li>
                                <li>
                                    <div class="input-wrap" style="width:100%;">
                                        <a href="#" @click.prevent="geoToggleMap()" style="font-size:13px;text-decoration:underline;" x-text="geoMapOpen ? 'Hide map' : (isOwned() ? 'Pick on the map' : 'Show on the map')"></a>
                                        <div x-show="geoMapOpen" x-cloak x-ref="geoMap" style="height:280px;border-radius:12px;margin-top:8px;overflow:hidden;"></div>
                                        <p style="font-size:11px;opacity:.6;margin-top:6px;">Search &amp; map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <script>window.GEO_CONFIG = @json(['tileUrl' => config('services.geo.tile_url'), 'tileAttribution' => config('services.geo.tile_attribution')]);</script>
                    @else
                    {{-- GEO_LOCATION_ENABLED=false: no location editor, only the stored on-chain position. --}}
                    <div class="content-box" x-show="position()">
                        <div class="title-row">
                            <h2>GEO Location</h2>
                        </div>
                        <div class="box">
                            <p class="text-entry" x-text="position()"></p>
                            <p style="font-size:13px;opacity:.75;margin-top:6px;">Setting a location is not available right now.</p>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="col-r">
                    <div class="content-box">
                        <div class="title-row">
                            <h2>Stats</h2>
                        </div>
                        <div class="box">
                            <ul class="stat-list">
                                <li>
                                    <strong>Mint</strong>
                                    <span x-text="'#'+mint()"></span>
                                </li>
                                <li>
                                    <strong>Combination</strong>
                                    <span x-text="combination()"></span>
                                </li>
                                <li>
                                    <strong>Creator Address</strong>
                                    <a x-bind:href="'/collection/'+creatorAddress()" x-text="creatorAddressName()" class="text-pink"></a>
                                </li>
                                <li>
                                    <strong>Owner Address</strong>
                                    <a x-bind:href="'/collection/'+ownerAddress()" x-text="ownerAddressName()" class="text-pink"></a>
                                </li>
                            </ul>
                            <div class="foot">
                                <strong>Traits</strong>
                                <ul class="traits-list">
                                    <li>
                                        <span>Rare</span>
                                        <span class="rarity-count rare"><em x-text="getRarityCountText('rare')"></em></span>
                                    </li>
                                    <li>
                                        <span>Epic</span>
                                        <span class="rarity-count epic"><em x-text="getRarityCountText('epic')"></em></span>

                                    </li>
                                    <li>
                                        <span>Legendary</span>
                                        <span class="rarity-count legendary"><em x-text="getRarityCountText('legendary')"></em></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <template x-if="isOwned()">
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
                                                <button @click.prevent.stop="saveSvg()" class="btn btn-sm btn-lilac"><span class="inner">SVG</span></button>
                                                @include('partials.render-downloads', ['dlType' => 'collectible', 'dlId' => $collectibleId, 'dlName' => 'FlovatarCollectible-'.$collectibleId])
                                            </div>
                                        </div>
                                    </li>

                                </ul>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="row">
                <!--
                                <div class="flovatar-traits">
                                    <div class="container">
                                        <div class="title-row">
                                            <h2>Collectible Traits</h2>
                                        </div>

                                        <div class="thumb-hold">
                                            <template x-for="category in basicCategories">
                                                <div class="thumb" x-bind:class="getComponentBasicClass(category)" x-show="templateBasicSvg(category)">
                                                    <div class="img-hold" x-bind:class="category != 'body' ? 'builder-component-thumb' : ''" x-html="templateBasicSvg(category)">
                                                    </div>
                                                    <dl>
                                                        <dt class="has-mb" x-text="category"></dt>
                                                        <dd class="has-mb" x-text="templateBasicName(category)"></dd>
                                                        <dt>Rarity</dt>
                                                        <dd x-text="templateBasicRarity(category)"></dd>
                                                        <dt>Used by</dt>
                                                        <dd x-text="templateUsedBy(category)"></dd>
                                                    </dl>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                -->

            </div>
        </div>

    </section>
    @include('partials.powered-by')


</x-guest-layout>
