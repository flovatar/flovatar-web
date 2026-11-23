<x-guest-layout>
    @push('og')
        @include('partials.og-item', ['ogType' => 'flobot', 'ogId' => $flobotId, 'ogTitle' => 'Flobot #'.$flobotId, 'ogUrl' => url('flobots/'.$flobotId)])
    @endpush
    @if(\App\Support\Blocklist::isBlocked($address ?? null) || \App\Support\Blocklist::isFlagged('App\Models\Flobot', $flobotId ?? null))
        <div class="container">@include('partials.incident-notice')</div>
    @endif

    @isset($templatesScript)
    {{-- window.templates (with SVGs) from a versioned, long-cacheable script --}}
    <script src="{{ $templatesScript }}"></script>
    @endisset
    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        @if(isset($flobotSales))
            window.flobotSales = @json($flobotSales);
        @endif
        @if(isset($flobotSaleOffer))
            window.flobotSaleOffer = @json($flobotSaleOffer);
        @endif
    </script>
    <div
        x-data="flobotData('{{$address}}', window.templates, window.flobotSales, window.flobotSaleOffer)"
        x-init="initFlobot($dispatch, '{{$address}}', {{$flobotId}})"
        @flobot-loaded.window="loadFlobot($event.detail.flobot)"
        @flobot-sale-loaded.window="loadSale($event.detail.sale)"
        @flobot-sale-created.window="console.log('sale created!')"
        @flobot-sale-deleted.window="console.log('sale deleted!'); currentPrice = null;"
        @purchase-completed.window="console.log('purchase completed!')"
        @user-loaded.window="getComponents(window.user.addr);"
        @fcomponents-loaded.window="components = $event.detail.components"
        @flobot-background-added.window="refreshAvatar()"
        @address-profile-loaded.window="loadCreatorName($event.detail.profile)"
        @address-profile-owner-loaded.window="loadOwnerName($event.detail.profile)"
    >


        <!-- Flobot Details Page -->
        <div class="flovatar-details-section">
            <div class="container">
                <div class="media">
                    <div class="img-hold">
                        <div class="img-frame">
                            <img x-show="curBackgroundId()" x-bind:src="(curBackgroundId()) ? '/api/image/template/'+curBackgroundId() : ''" class="flobot-background" />
                            <img x-show="flobot" x-bind:src="(flobot) ? '/api/image/flobot/'+mint() : ''" />
                        </div>
                    </div>

                    <div class="info" x-show="!mint()">
                        <dl x-show="!isLoading">
                            <dt>Not Found!</dt>
                            <dd>It's likely that this Flobot has changed its owner.</dd>
                        </dl>

                        <dl x-show="isLoading">
                            <dt>Loading</dt>
                            <dd>Please wait a few floments...</dd>
                        </dl>
                    </div>
                    <div class="info" x-show="mint()">
                        <dl>
                            <dt>Mint</dt>
                            <dd x-text="'#'+mint()"></dd>
                            <dt>Combination</dt>
                            <dd x-text="combination()"></dd>
                            <dt>Owner address</dt>
                            <dd>
                                <a x-bind:href="'/collection/'+ownerAddress()" x-text="ownerAddressName()"></a>
                            </dd>
                            <dt>Creator address</dt>
                            <dd>
                                <a x-bind:href="'/collection/'+creatorAddress()" x-text="creatorAddressName()"></a>
                            </dd>
                            <dt>Rarity</dt>
                            <dd x-text="getRarityClass()"></dd>
                        </dl>
                        <template x-if="isOwned() && mint()">
                            <dl class="details-input-form">
                                <dt>Assets Download</dt>
                                <dd>
                                    <div class="input-panel">
                                        <a target="_blank" x-bind:download="'Flobot-'+mint()+'.svg'" x-bind:href="'/api/image/flobot/'+mint()" class="btn btn-sm btn-lilac"><span class="inner">SVG</span></a>
                                    </div>
                                </dd>
                            </dl>
                        </template>
                        <dl x-show="isSale()" class="details-input-form">
                            <dt>Sale price</dt>
                            <dd class="price-input-detail">
                                <span x-text="formatPrice(currentPrice)+ 'Flow'"></span>
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
                                <a x-bind:href="combinationUrl()" class="btn btn-sm btn-lilac"><span class="inner">View in Builder</span></a>
                            </div>
                            <div class="btns-right">
                                <div x-show="!isSale() && isOwned() && !showGift && !isDapperWallet()" class="btn-wrp">
                                    <button @click.prevent.stop="showGift = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-lilac"><span class="inner">Gift</span></button>
                                </div>
                                <div x-show="!isSale() && isOwned() && !showSale" class="btn-wrp">
                                    <button @click.prevent.stop="showSale = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Sell</span></button>
                                </div>
                                <div x-show="showSaleBtn()" class="btn-wrp">
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




        <!-- Customize Flobits -->
        <div class="customize-flobits" x-show="isOwned() && !isSale()">
            <div class="container">
                <div class="title-row">
                    <h2>Customize flobits</h2>
                </div>
                <div class="customize-flobit-panel">
                    <ul class="option-list">
                        <li x-show="(filterComp('background') && filterComp('background').length > 0) || hasFlobit('background')">
                            <h3>Background</h3>
                            <div class="select-wrp">
                                <select x-model="optionalIds['background']">
                                    <option value="" x-text="hasFlobit('background') ? 'Equipped Flobit' : 'No Flobit'"></option>
                                    <template x-for="comp in filterComp('background')" :key="comp.id">
                                        <option x-bind:value="comp.id" x-text="comp.name + ' #' + comp.mint"></option>
                                    </template>
                                </select>
                                <button x-show="optionalIds['background']" @click.prevent.stop="setOptional('background')" x-bind:disabled="!loggedIn() || !optionalIds['background']"  type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                <button x-show="!optionalIds['background'] && hasFlobit('background')" @click.prevent.stop="removeOptional('background')" x-bind:disabled="!loggedIn() || !hasFlobit('background')" type="submit" class="btn btn-icon btn-red"><span class="inner"><i class="icon-cross02"></i></span></button>
                            </div>
                        </li>

                    </ul>
                </div>

            </div>
        </div>
        <!-- end Customize Flobits -->


        <div class="flovatar-traits">
            <div class="container">
                <div class="title-row">
                    <h2>Flobot Traits</h2>
                </div>

                <div class="thumb-hold">
                    <template x-for="category in basicFlobotCategories">
                        <div class="thumb" x-bind:class="getComponentBasicClass(category)" x-show="templateBasicSvg(category)">
                            <div class="img-hold" x-html="templateBasicSvg(category)">
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


        <div class="flovatar-traits" x-show="false && hasFlobit('background')">
            <div class="container">
                <div class="title-row">
                    <h2>Equipped Flobits</h2>
                </div>

                <div class="thumb-hold">
                    <template x-for="category in optionalCategories">
                        <div class="thumb" x-bind:class="getComponentOptionalClass(category)" x-show="hasFlobit(category)">
                            <div class="img-hold" x-bind:class="category != 'background' ? 'builder-component-thumb' : ''" x-html="templateOptionalSvg(category)">
                            </div>
                            <dl>
                                <dt class="has-mb">Name</dt>
                                <dd class="has-mb" x-text="templateOptionalName(category)"></dd>
                                <dt>Category</dt>
                                <dd x-text="category"></dd>
                                <dt>Rarity</dt>
                                <dd x-text="templateOptionalRarity(category)"></dd>
                            </dl>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>


    @include('partials.powered-by')

</x-guest-layout>

