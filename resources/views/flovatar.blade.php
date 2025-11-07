<x-guest-layout>



    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        @if(isset($flovatarSales))
            window.flovatarSales = @json($flovatarSales);
        @endif
        @if(isset($flovatarSaleOffer))
            window.flovatarSaleOffer = @json($flovatarSaleOffer);
        @endif
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.api_key') }}"></script>

    <section class="flovatar-detail"
             x-data="flovatarData('{{$address}}', {{$flovatarId}}, window.templates, window.flovatarSales, window.flovatarSaleOffer, {{ config('flovatar.dustPerDayScore') }})"
             x-init="initFlovatar($dispatch, '{{$address}}', {{$flovatarId}})"
             @flovatar-loaded.window="loadFlovatar($event.detail.flovatar)"
             @flovatar-sale-loaded.window="loadSale($event.detail.sale)"
             @flovatar-sale-created.window="console.log('sale created!')"
             @flovatar-sale-deleted.window="console.log('sale deleted!'); currentPrice = null;"
             @price-changed.window="console.log('sale price changed!'); changePriceOpen = false; currentPrice = currentPriceChange;"
             @purchase-completed.window="console.log('purchase completed!')"
             @user-loaded.window="getComponents(window.user.addr);"
             @fcomponents-loaded.window="components = $event.detail.components"
             @flovatar-accessory-added.window="refreshAvatar()"
             @flovatar-eyeglasses-added.window="refreshAvatar()"
             @flovatar-hat-added.window="refreshAvatar()"
             @flovatar-background-added.window="refreshAvatar()"
             @address-profile-loaded.window="loadCreatorName($event.detail.profile)"
             @address-profile-owner-loaded.window="loadOwnerName($event.detail.profile)"
             @flovatar-claimables-loaded.window="loadFlovatarClaimables($event.detail.claimables)"
             @flovatar-airdrop-claimed.window="window.getFlovatarClaimables(address, flovatarId)"
             @flovatar-dust-claimed.window="window.getFlovatarClaimables(address, flovatarId)"
    >
        <div class="container">
            <div class="row">
                <div class="col-l">
                    <div class="img-holder">
                        <div class="img">
                            <div x-html="svg()" x-bind:class="hasFlobit('background') ? 'bordered-bg' : ''" x-show="!mode3dEnabled"></div>
                        </div>
                        <div class="flovatar-view-3d" x-show="mode3dEnabled">
                            <iframe
                                id="visualiser"
                                scrolling="no"
                                style="width: 100%; height: 100%; aspect-ratio: 1; overflow: hidden; border: 0"
                                src="https://webgl.flovatar.com/"
                            ></iframe>
                        </div>
                        <div class="info-circles">
                            <div class="circle">
                                <div class="txt-img-t">
                                    <img src="/images/text-rarity-score.svg" width="96" alt="Rarity Score">
                                </div>
                                <span class="score lg" x-bind:class="getRarityClass()" x-text="rarityText()"></span>
                                <div class="txt-img-b">
                                    <img x-bind:src="'/images/text-'+getRarityClass()+'-1.svg'" width="76" alt="">
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
                            <dd>It's likely that this Flovatar has changed its owner.</dd>
                        </dl>

                        <dl x-show="isLoading">
                            <dt>Loading</dt>
                            <dd>Please wait a few floments...</dd>
                        </dl>
                    </div>
                    <div class="btns-container" x-show="mint()">
                        <strong class="title" x-text="name() ? name() : 'Flovatar #'+mint()"></strong>
                        <div class="btns-row">
                            <div class="btns-list-wrap">
                                <ul class="btns-list">

                                    @if(config('flovatar.chatEnabled'))
                                    <li>
                                        <a class="btn btn-sm btn-green" x-bind:href="'/chat/'+(name() ? name() : mint())">
                                            <span class="inner">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6" width="20" height="20" style="margin-right: 3px;">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                                                </svg>
                                                Chat
                                            </span>
                                        </a>
                                    </li>
                                    @endif
                                    <li>
                                        <button x-bind:class="mode3dEnabled ? 'active' : ''" @click.prevent.stop="mode3dEnabled = !mode3dEnabled" class="btn btn-sm btn-lilac"><span class="inner"><span class="short-label">3D</span><span class="long-label">3D VIEW</span></span></button>
                                    </li>
                                    <li>
                                        <a x-bind:href="combinationUrl()" class="btn btn-sm btn-lilac"><span class="inner"><span class="long-label">View in Builder</span> <span class="short-label"><span class="long-label">View in Builder</span> <span class="short-label">Builder</span></span></span></a>
                                    </li>
                                    <li class="grow">
                                        <a target="_blank" x-bind:href="'https://flov.dev/?mint='+mint()" class="btn btn-sm btn-lilac"><span class="inner"><span class="long-label">VIEW IN FLOV.DEV</span><span class="short-label">FLOV.DEV</span></span></a>
                                    </li>
                                    <li x-show="!isSale() && isOwned() && !showSale">
                                        <button @click.prevent.stop="showSale = true" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Sell</span></button>
                                    </li>
                                    <li x-show="showSaleBtn()">
                                        <button @click.prevent.stop="buySale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Buy Now</span></button>
                                    </li>
                                    <li x-show="isSale() && isOwned() && !showSale">
                                        <button @click.prevent.stop="cancelSale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-green"><span class="inner">Cancel Sale</span></button>
                                    </li>
                                    <li x-show="!isSale() && isOwned() && !showGift">
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
                                            <button @click.prevent.stop="cancelSale" x-bind:disabled="!loggedIn()" class="btn btn-sm btn-red"><span class="inner">Cancel Sale</span></button>
                                        </div>
                                    </div>
                                    <div class="col-r" x-show="!isOwned() || isDapperSale()">
                                        <div class="input-frame nowrap-frame">
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
                                                <img src="/images/ico-blocto.png" width="40" alt="">
                                            </div>
                                            <div class="logo">
                                                <img src="/images/ico-lilico.png" width="40" alt="">
                                            </div>
                                            <span>Blocto / Lilico</span>
                                        </div>
                                    </div>
                                </li>
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
                                                <div class="price-tag" x-show="isOwned() && !name()">Ð100</div>
                                                <input x-model="flovatarName" x-show="isOwned() && !name()" type="text" class="input" id="name" placeholder="Give your Flovatar a name" min="3" max="32">
                                                <p class="text-entry" x-show="!isOwned() || name()" x-text="name() ? name() : 'N/A'"></p>
                                            </div>
                                            <div class="r-col" x-show="isOwned() && !name()">
                                                <span class="help">
                                                    <a href="#"><i class="icon-help"></i></a>
                                                    <div class="info-tooltip">
                                                        <strong>Character Name</strong>
                                                        <p>Burn Ð100 to give your Flovatar a name.</p>
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
                                                <div x-show="isOwned()" class="price-tag">Ð50</div>
                                                <textarea x-show="isOwned()" x-model="flovatarStory" min="1" max="300" x-show="isOwned()" class="input" placeholder="Tell us about your Flovatar"></textarea>
                                                <p class="text-entry" x-show="story()" x-text="story()"></p>
                                            </div>
                                            <div class="r-col top-align" x-show="isOwned()">
                                                <span class="help">
                                                    <a href="#"><i class="icon-help"></i></a>
                                                     <div class="info-tooltip">
                                                        <strong>Character Bio</strong>
                                                        <p>Burn Ð50 to add a backstory for your Flovatar.</p>
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
                    <div class="content-box" x-show="isOwned() || position()">
                        <div class="title-row">
                            <h2>GEO Location</h2>
                        </div>
                        <div class="box">
                            <ul class="input-list">
                                <li x-show="isOwned()">
                                    <div class="label-wrap">
                                        <label for="name" class="sm">Location</label>
                                    </div>
                                    <div class="input-col-wrap">
                                        <div class="input-wrap">
                                            <div class="price-tag">Ð10</div>
                                            <input x-model.debounce.500ms="searchAddress" type="text" class="input" id="searchAddress" placeholder="Address -OR- Lat, Long">
                                        </div>
                                        <div class="r-col">
                                            <span class="help">
                                                <a href="#">
                                                    <i class="icon-help"></i>
                                                </a>
                                                <div class="info-tooltip">
                                                    <strong>Location</strong>
                                                    <p>Burn Ð10 to place your Flovatar on the map.</p>
                                                </div>
                                            </span>
                                            <button @click.prevent.stop="setPosition()" x-show="isOwned() && positionFound() && position() != searchAddress"  x-bind:disabled="!loggedIn()" type="submit" class="btn btn-icon btn-green">
                                                <span class="inner"><i class="icon-check"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </li>
                                <li x-show="foundAddress">
                                    <label for="name" class="sm">&nbsp;</label>
                                    <div class="input-wrap">
                                        <span x-show="foundAddress" >
                                            <p class="text-entry" x-text="foundAddress"></p>
                                        </span>
                                    </div>
                                    <div class="r-col">
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-r">
                    <div class="content-box">
                        <div class="title-row">
                            <h2>Ðust & Airdrops</h2>
                        </div>
                        <div class="claim-row-wrap">
                            <div class="claim-row bg-pink">
                                <div class="l-col">
                                    <span class='title'>Daily ÐUST</span>
                                    <span class="count" x-text="'Ð '+dustPerDay()"></span>
                                </div>
                                <div class="r-col">
                                    <div class="wrap">
                                        <span class='title'>Earned ÐUST</span>
                                        <span class="count" x-text="'Ð '+formatPrice(flovatarEarnedDust)"></span>
                                    </div>
                                    <button x-show="isOwned()" @click.prevent.stop="claimDust()" x-bind:disabled="!loggedIn() || flovatarEarnedDust <= 0" class="btn btn-sm btn-pink">
                                        <span class="inner"><span class="long-label">CLAIM</span><span class="short-label"><span class="icon-claim"></span></span></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="claim-row-wrap">
                            <div class="claim-row bg-gray">
                                <div class="l-col">
                                    <div class="item-box">
                                        <div class="img">
                                            <img src="/images/box.svg" width="89" alt="">
                                            <span x-show="flovatarAirdropCount" class="num" x-text="flovatarAirdropCount"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="r-col">
                                    <div class="wrap">
                                        <span class='title'>Airdrop ÐUST</span>
                                        <span class="count" x-text="'Ð '+formatPrice(flovatarAirdroppedDust)"></span>
                                    </div>
                                    <button x-show="isOwned()" @click.prevent.stop="claimAirdrop()" x-bind:disabled="!loggedIn() || (flovatarAirdropCount <= 0 && flovatarAirdroppedDust <= 0)" class="btn btn-sm btn-pink">
                                        <span class="inner"><span class="long-label">CLAIM</span><span class="short-label"><span class="icon-claim"></span></span></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="content-box" x-show="isOwned() && !isSale()">
                        <div class="title-row">
                            <h2>Equipped Flobits</h2>
                        </div>
                        <div class="box">
                            <ul class="input-list">
                                <template x-for="category in optionalCategories">
                                    <li x-show="(filterComp(category) && filterComp(category).length > 0) || hasFlobit(category)">
                                        <label for="name" x-text="category"></label>
                                        <div class="input-col-wrap">
                                            <div class="input-wrap">
                                                <select x-model="optionalIds[category]">
                                                    <option value="" x-text="hasFlobit(category) ? 'Equipped Flobit' : 'No Flobit'"></option>
                                                    <template x-for="comp in filterComp(category)" :key="comp.id">
                                                        <option x-bind:value="comp.id" x-text="comp.name + ' #' + comp.mint"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div class="r-col">
                                                <button x-show="optionalIds[category]" @click.prevent.stop="setOptional(category)" x-bind:disabled="!loggedIn() || !optionalIds[category]"  type="submit" class="btn btn-icon btn-green"><span class="inner"><i class="icon-check"></i></span></button>
                                                <button x-show="!optionalIds[category] && hasFlobit(category)" @click.prevent.stop="removeOptional(category)" x-bind:disabled="!loggedIn() || !hasFlobit(category)" type="submit" class="btn btn-icon btn-red"><span class="inner"><i class="icon-cross02"></i></span></button>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
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
                                <li>
                                    <strong>More Info</strong>
                                    <a class="text-pink" target="_blank" x-bind:href="'https://flov.dev/?mint='+mint()">flov.dev</a>
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
                                    <strong>2d Assets</strong>
                                    <div class="col-r">

                                        <div class="input-panel download-panel">
                                            <button @click.prevent.stop="saveSvg()" class="btn btn-sm btn-lilac"><span class="inner">SVG</span></button>
                                            <a x-bind:download="'Flovatar-'+mint()+'.png'" target="_blank" x-bind:href="'https://images.flovatar.com/flovatar/png/'+mint()+'.png'" class="btn btn-sm btn-lilac"><span class="inner">PNG</span></a>
                                            <a x-bind:download="'Flovatar-'+mint()+'.mp4'"  target="_blank" x-bind:href="'https://images.flovatar.com/flovatar/mp4/'+mint()+'.mp4'" class="btn btn-sm btn-lilac"><span class="inner">MP4</span></a>
                                            <a x-bind:download="'Flovatar-'+mint()+'.gif'"  target="_blank" x-bind:href="'https://images.flovatar.com/flovatar/gif/'+mint()+'.gif'" class="btn btn-sm btn-lilac"><span class="inner">GIF</span></a>
                                        </div>
                                    </div>
                                </li>

                                <li>
                                    <div class="label-wrap">
                                        <strong>3d Assets</strong>
                                    </div>
                                    <div class="col-r grow-mobile">
                                        <div class="input-panel download-panel">
                                            <button x-show="!unlocked3d()" @click.prevent.stop="unlock3d()" x-bind:disabled="!loggedIn()" class="btn btn-icon btn-green">
                                                <span class="inner"><i class="icon-lock"></i></span>
                                                <div class="price-tag button-tag">Ð150</div>
                                            </button>
                                            <a x-show="unlocked3d()" x-bind:download="'Flovatar-'+mint()+'.stl'"  target="_blank" x-bind:href="'https://images.flovatar.com/flovatar/stl/'+mint()+'.stl'" class="btn btn-sm btn-lilac"><span class="inner">STL</span></a>
                                        </div>
                                        <div class="input-hold unlock-3d" x-show="!unlocked3d()">
                                            <span class="help">
                                                <a href="#"><i class="icon-help"></i></a>
                                                <div class="info-tooltip">
                                                    <strong>3D Assets</strong>
                                                    <p>Burn Ð150 to unlock access to 3D models of your Flovatar.</p>
                                                </div>
                                            </span>

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
                <div class="flovatar-traits">
                    <div class="container">
                        <div class="title-row">
                            <h2>Flovatar Traits</h2>
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


                <div class="flovatar-traits" x-show="hasFlobit('hat') || hasFlobit('eyeglasses') || hasFlobit('accessory') || hasFlobit('background')">
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
        </div>

    </section>


    @include('partials.powered-by')

</x-guest-layout>

