<x-guest-layout>
    @if(\App\Support\Blocklist::isBlocked($address ?? null))
        <div class="container">@include('partials.incident-notice')</div>
    @endif
    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        @if(isset($templatesDust))
            window.templatesDust = @json($templatesDust);
        @endif
    </script>


    <div
        x-data="collectionData('{{$address}}', '{{$addressName}}', window.templates, {{ config('flovatar.dustEnabled') ? 'true' : 'false' }}, {{ config('flovatar.dustPerDayScore') }})"
        x-init="initCollection($dispatch, '{{$address}}', '{{$addressName}}', {{ config('flovatar.dustEnabled') }})"
        @server-auth.window="serverAuthenticated = !!$event.detail.authenticated"
        @collections-loaded.window="loadCollections($event.detail.collections)"
        @collections-pack-loaded.window="loadCollectionsPack($event.detail.collections)"
        @collections-flovatar-loaded.window="loadCollectionsFlovatar($event.detail.collections)"
        @collections-flovatar-sale-loaded.window="loadCollectionsFlovatarSale($event.detail.collections)"
        @collections-flobot-loaded.window="loadCollectionsFlobot($event.detail.collections)"
        @collections-flobot-sale-loaded.window="loadCollectionsFlobotSale($event.detail.collections)"
        @collections-component-loaded.window="loadCollectionsComponent($event.detail.collections)"
        @collections-component-sale-loaded.window="loadCollectionsComponentSale($event.detail.collections)"
        @collections-claimables-loaded.window="loadClaimables($event.detail.claimables)"
        @collections-collectible-loaded.window="loadCollectionsCollectible($event.detail.collections)"
        @pack-opening-started.window="startPackOpening()"
        @pack-opened.window="handlePackOpening()"
        @inbox-claimed.window="handleInboxClaimed()"
        @inbox-flobit-claiming.window="showPackAnimation = true;"
        @inbox-dust-claimed.window="handleInboxClaimed()"
        @inbox-flobit-claimed.window="handleInboxClaimed()"
        @collections-flovatar-failed.window="collectionFlovatarFailed = true; loadFlovatarFromIds();"
        @collections-flovatar-ids-loaded.window="loadCollectionsFlovatarIds($event.detail.collections)"
        @flovatar-loaded.window="loadSingleFlovatar($event.detail)"
        @flobits-upgraded.window="flobitUpgraded()"
        @sportack-loaded.window="loadSportpacks($event.detail.packs)"
    >
        <!-- My collection Page -->
        <div class="my-collection">
            <div class="container">

                @if(config('flovatar.dustEnabled'))
                <div class="info-box-hold">
                    <div class="info-box">
                        <div class="left-infos">
                            <div class="daily-dust">
                                <strong class="title">Daily ÐUST</strong>
                                <strong class="count" x-text="'Ð '+dailyDustFormatted()"></strong>
                            </div>
                            <div class="earned-dust">
                                <strong class="title">Earned ÐUST</strong>
                                <strong class="count" x-text="'Ð '+totalDustFormatted()"></strong>
                            </div>
                        </div>

                        <div class="current-balance">
                            <div class="left">
                                <button x-show="isCollectionOwner() && !isDapperWallet()" @click.prevent.stop="claimAllDustWithIds()" class="btn pink-alt"><span class="inner"><span class="short-label">CLAIM</span><span class="long-label">CLAIM ÐUST</span><span class="icon-claim"></span></span></button>
                            </div>
                            <div class="content-r">
                                <strong class="title">Current balance</strong>
                                <strong class="count" x-text="'Ð '+formatPrice(dustBalance())"></strong>
                            </div>
                        </div>
                    </div>

                    <div class="airdrop-panel">
                        <div class="item-box">
                            <div class="item"><img src="/images/airdrop.svg" alt="image description"></div>
                            <span class="count" x-text="airdropFlobits()" x-show="airdropFlobits() > 0"></span>
                        </div>

                        <div class="btn-wrap">

                            <button x-show="isCollectionOwner()" @click.prevent.stop="showAirdropModal = true" class="btn pink-alt" x-bind:disabled="airdropFlobits() <= 0"><span class="inner" x-text="airdropFlobits() > 0 ? 'Claim all' : 'No airdrops'"><span class="long-label">Claim all Airdrops</span><span class="short-label">Claim</span></span></button>
                        </div>
                    </div>
                </div>

                <div class="info-box-hold" x-show="isCollectionOwner() && !isDapperWallet()">
                    <div class="info-box no-wrap">
                        <div class="panel-left">
                            <div>
                                <strong class="title">ÐUST</strong>
                                <div class="input-wrap md">
                                    <div>
                                        <input type="number" min="0.0" step="0.1" placeholder="0.00" x-model="sendDustAmount">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <strong class="title">Wallet Address</strong>
                                <div class="input-wrap">
                                    <div>
                                        <input type="text" placeholder="0x0000000000000000" x-model="sendDustAddress">
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="panel-end">
                            <div class="panel-button">
                                <button @click.prevent.stop="sendDust()" class="btn grey"><span class="inner"><span class="short-label">SEND</span><span class="long-label">SEND ÐUST</span></span></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-box-hold" x-show="isDapperWallet()">
                    <div class="info-box no-wrap">
                        <div class="panel-left">
                            <div>
                                <strong class="title">Account Linking</strong>
                                <div>
                                    <p x-show="!hasParentAccount()">You can request to Dapper to enable Account Linking on your wallet to connect a non-custodial one and be able to claim ÐUST there.</p>
                                    <p x-show="hasParentAccount()">Congratulations! You have a parent account linked to your Dapper wallet. Log in with that account to claim ÐUST also for the Flovatar in this wallet.</p>
                                </div>
                            </div>
                        </div>

                        <div class="panel-end">
                            <div class="panel-button">
                                <a x-show="!hasParentAccount()" x-bind:href="'mailto:developers@dapperlabs.com?subject=Account Linking&body=Dapper Wallet Address - '+currentAddressCollection()+'%0D%0A%0D%0AI am requesting access to Dapper\'s Account Linking portal for the above Dapper Wallet.%0D%0A%0D%0AThank you'" class="btn pink-alt"><span class="inner"><span class="short-label">LINK ACC.</span><span class="long-label">LINK ACCOUNT</span></span></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-box-hold" x-show="!isDapperWallet() && hasChildAccounts()">
                    <div class="info-box no-wrap">
                        <div class="panel-left">
                            <div>
                                <strong class="title">Account Linking</strong>
                                <div>
                                    <p>You have the following wallets linked as children account: <span x-text="childAddresses().join(' ')"></span>. Please click the button on the right to claim the $DUST for all the Flovatars in those wallets.</p>
                                </div>
                            </div>
                        </div>

                        <div class="panel-end">
                            <div class="panel-button">
                                <button @click.prevent.stop="claimAllChildDustWithIds()" class="btn pink-alt"><span class="inner"><span class="short-label">CLAIM</span><span class="long-label">CLAIM ÐUST</span></span></button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="collection">
                    <div class="title-row">
                        <h2>Your Collection</h2>
                    </div>
                    <div class="collection-select">
                        <select x-model="activeTab">
                            <option value="flovatar" x-text="'Flovatar ('+flovatarCountTotal()+')'"></option>
                            <option value="flobot" x-text="'Flobot ('+flobotCountTotal()+')'"></option>
                            <option value="collectible" x-text="'Psyche Likee ('+collectibleCountTotal()+')'"></option>
                            <option value="spark" x-text="'Sparks & Boosters ('+sparkCountTotal()+')'"></option>
                            <option value="flobit" x-text="'Flobits ('+flobitCountTotal()+')'"></option>
                            <option value="terrakit" x-text="'Terrakits ('+terrakitCountTotal()+')'"></option>
                            <option value="pack" x-text="'Packs ('+packCountTotal()+')'"></option>
                            <option value="sportvatar" x-text="'Sportvatar'"></option>
                        </select>
                        <ul class="collection-list">
                            <li x-bind:class="activeTab == 'flovatar' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'flovatar'">Flovatars  <span class="count" x-text="flovatarCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'flobot' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'flobot'">Flobots  <span class="count" x-text="flobotCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'collectible' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'collectible'">Psyche Likee  <span class="count" x-text="collectibleCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'spark' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'spark'">Sparks & Boosters  <span class="count" x-text="sparkCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'flobit' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'flobit'">Flobits  <span class="count" x-text="flobitCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'terrakit' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'terrakit'">Terrakits  <span class="count" x-text="terrakitCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'pack' ? 'active' : ''"><button @click.prevent.stop="activeTab = 'pack'">Packs  <span class="count" x-text="packCountTotal()"></span></button></li>
                            <li x-bind:class="activeTab == 'sportvatar' ? 'active' : ''"  x-show="loggedIn()"><button @click.prevent.stop="activeTab = 'sportvatar'">Sportvatars </button></li>
                        </ul>
                    </div>
                </div>

                @if(config('flovatar.dustEnabled'))
                <div class="upgrade-bar" x-show="activeTab == 'flobit'">
                    <div class="files">
                        <div class="custom-scroll">
                            <div class="inner">
                                <template x-for="burnable in burnList" :key="burnable.id">
                                    <div class="file">
                                        <div class="card" x-bind:class="burnable.metadata ? burnable.metadata.rarity : burnable.rarity">
                                            <span class="close" @click.prevent.stop="removeFlobitToUpgrade(burnable)"><i class="icon-close"></i></span>
                                            <div class="img-hold builder-component-thumb">
                                                <img x-bind:src="burnable.metadata ? '/api/image/template/'+burnable.metadata.templateId : '/api/image/template/'+burnable.templateId">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template x-for="burnable in emptyBurnListItems()" :key="burnable">
                                    <div class="file"></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="info">
                        <button @click.prevent.stop="showUpgradeFlobitModal = true" class="btn pink-alt" x-bind:disabled="burnList.length < 10"><span class="inner"><span class="upgradeBtnText" x-text="(burnList.length == 10) ? 'Upgrade Flobits' : 'Upgrade '+burnList.length+' / 10'"></span>  <span class="icon-upgrade"></span></span></button>
                        <a href="#" class="help" x-show="false"><span class="icon-help"></span></a>
                    </div>
                </div>
                @endif

                <div x-show="activeTab == 'flovatar'">
                    @include('partials.filters-collection-flovatar')
                    <div class="flovatar-card-list">
                        <template x-for="flovatar in filteredFlovatars()" :key="flovatar.id">
                            @include('partials.flovatar')
                        </template>
                    </div>
                    <div x-show="flovatars.length == 0 && flovatarSales.length == 0">
                        <span class="no-results-msg">No Flovatars yet. <a href="/builder" class="underline">Go create some!</a></span>
                    </div>
                </div>


                <div x-show="activeTab == 'flobot'">
                    @include('partials.filters-collection-flobot')
                    <div class="flovatar-card-list">
                        <template x-for="flobot in filteredFlobots()" :key="flobot.id">
                            @include('partials.flobot')
                        </template>
                    </div>
                    <div x-show="flobots.length == 0">
                        <span class="no-results-msg">No Flobots yet. <a href="/builder/flobot" class="underline">Go create some!</a></span>
                    </div>
                </div>


                <div x-show="activeTab == 'collectible'">
                    @include('partials.filters-collection-collectible')
                    <div class="flovatar-card-list">
                        <template x-for="collectible in filteredCollectibles()" :key="collectible.id">
                            @include('partials.collectible')
                        </template>
                    </div>
                    <div x-show="collectibles.length == 0">
                        <span class="no-results-msg">No Psyche Likee yet. <a href="/builder/dust" class="underline">Go create some!</a></span>
                    </div>
                </div>


                <div x-show="activeTab == 'spark'">
                    @include('partials.filters-collection-booster')
                    <div class="flovatar-card-list">
                        <div class="col" x-show="groupCategory('spark', 'common') && sparkCount>0">
                            <a href="#" @click.prevent.stop="groupSpark = false" class="card stack">
                                <div class="img-hold">
                                    <img src="/api/image/template/458" alt="spark" />
                                </div>
                                <div class="wrap">
                                    <dl>
                                        <dt>Type</dt>
                                        <dd>Spark</dd>
                                        <dt>Rarity</dt>
                                        <dd>Common</dd>
                                        <dt>Count</dt>
                                        <dd x-text="sparkCount"></dd>
                                        <dt>On sale</dt>
                                        <dd x-text="sparkSaleCount"></dd>
                                    </dl>
                                </div>
                                <span x-show="sparkSaleCount>0" class="icon-cart"></span>
                            </a>
                        </div>
                        <div class="col" x-show="groupCategory('boost', 'rare') && rareCount>0">
                            <a href="#" @click.prevent.stop="groupBoosterRare = false" class="card stack rare">
                                <div class="img-hold">
                                    <img src="/api/image/template/75" alt="rare booster"  />
                                </div>
                                <div class="wrap">
                                    <dl>
                                        <dt>Type</dt>
                                        <dd>Booster</dd>
                                        <dt>Rarity</dt>
                                        <dd>Rare</dd>
                                        <dt>Count</dt>
                                        <dd x-text="rareCount"></dd>
                                        <dt>On sale</dt>
                                        <dd x-text="rareSaleCount"></dd>
                                    </dl>
                                </div>
                                <span x-show="rareSaleCount>0" class="icon-cart"></span>
                            </a>
                        </div>
                        <div class="col" x-show="groupCategory('boost', 'epic') && epicCount>0">
                            <a href="#" @click.prevent.stop="groupBoosterEpic = false" class="card stack epic">
                                <div class="img-hold">
                                    <img src="/api/image/template/73" alt="epic booster" />
                                </div>
                                <div class="wrap">
                                    <dl>
                                        <dt>Type</dt>
                                        <dd>Booster</dd>
                                        <dt>Rarity</dt>
                                        <dd>Epic</dd>
                                        <dt>Count</dt>
                                        <dd x-text="epicCount"></dd>
                                        <dt>On sale</dt>
                                        <dd x-text="epicSaleCount"></dd>
                                    </dl>
                                </div>
                                <span x-show="epicSaleCount>0" class="icon-cart"></span>
                            </a>
                        </div>
                        <div class="col" x-show="groupCategory('boost', 'legendary') && legendaryCount>0">
                            <a href="#" @click.prevent.stop="groupBoosterLegendary = false" class="card stack legendary">
                                <div class="img-hold">
                                    <img src="/api/image/template/74" alt="legendary booster" />
                                </div>
                                <div class="wrap">
                                    <dl>
                                        <dt>Type</dt>
                                        <dd>Booster</dd>
                                        <dt>Rarity</dt>
                                        <dd>Legendary</dd>
                                        <dt>Count</dt>
                                        <dd x-text="legendaryCount"></dd>
                                        <dt>On sale</dt>
                                        <dd x-text="legendarySaleCount"></dd>
                                    </dl>
                                </div>
                                <span x-show="legendarySaleCount>0" class="icon-cart"></span>
                            </a>
                        </div>

                        <template x-for="component in filteredSparkBooster()" :key="component.id">
                            @include('partials.component-collection')
                        </template>
                    </div>
                    <div x-show="componentsSparkBooster().length == 0">
                        <span class="no-results-msg">No Spark or Boosters here. <a href="/marketplace" class="underline">Go get some!</a></span>
                    </div>
                </div>


                <div x-show="activeTab == 'flobit'">
                    @include('partials.filters-collection-flobit')
                    <div class="flovatar-card-list">


                        <template x-for="component in filteredFlobits()" :key="(component && component.id) ? component.id : ((component && component.templateId && component.name) ? component.name+component.templateId : component)">
                            @include('partials.component-collection')
                        </template>
                    </div>
                    <div x-show="componentsFlobit().length == 0">
                        <span class="no-results-msg">No Flobits here. <a href="/marketplace" class="underline">Go get some!</a></span>
                    </div>
                </div>


                <div x-show="activeTab == 'terrakit'">
                    @include('partials.filters-collection-terrakit')
                    <div class="flovatar-card-list">
                        <template x-for="flobit in componentsStackedTerraKit()" :key="flobit.templateId">
                            <div class="col" x-show="groupTerraKit[flobit.templateId]">
                                <a href="#" @click.prevent.stop="groupTerraKit[flobit.templateId] = false" class="card stack" x-bind:class="flobit.rarity">
                                    <div class="img-hold">
                                        <img x-bind:src="'/api/image/template/'+flobit.templateId" alt="flobit" />
                                    </div>
                                    <div class="wrap">
                                        <dl>
                                            <dt x-text="flobit.category"></dt>
                                            <dd x-text="flobit.name"></dd>
                                            <dt>Rarity</dt>
                                            <dd x-text="flobit.rarity"></dd>
                                            <dt>Count</dt>
                                            <dd x-text="flobit.count"></dd>
                                            <dt>On sale</dt>
                                            <dd x-text="flobit.salesCount"></dd>
                                        </dl>
                                    </div>
                                    <span x-show="flobit.salesCount>0" class="icon-cart"></span>
                                </a>
                            </div>
                        </template>
                        <template x-for="component in filteredTerrakit()" :key="component.id">
                            @include('partials.component-collection')
                        </template>
                    </div>

                    <div x-show="componentsTerraKit().length == 0">
                        <span class="no-results-msg">No TerraKit NFTs here. <a href="/marketplace" class="underline">Go get some!</a></span>
                    </div>
                </div>


                <div x-show="activeTab == 'pack'">
                    <div class="flovatar-card-list">
                        <template x-for="pack in packs" :key="pack">
                            @include('partials.pack')
                        </template>
                    </div>
                    <div x-show="packs.length == 0">
                        <span class="no-results-msg">No Packs yet. Please wait for our next drop to buy some!</span>
                    </div>
                </div>

                <div x-show="activeTab == 'sportvatar'" x-show="loggedIn()">
                    <div class="flovatar-card-list">

                        {{-- Server login state comes from /login/nonce (serverAuthenticated): this page is edge-cached. --}}
                        <button x-show="loggedIn() && !serverAuthenticated" @click.prevent.stop="if(loggedIn()){window.createSignature(window.loginMessage)}" class="btn-o">Verify Account to Redeem the Packs</button>
                        <button @click.prevent.stop="getSportvatarCodes()" x-show="serverAuthenticated && (!sportpacks || !sportpacks.length)" class="btn-o btn-show-pack-code">Show my Sportvatar packs</button>
                        <div x-show="sportpacks && sportpacks.length" class="pack-code">
                            <p x-text="'You have ' + sportpacks.length + ' free Sportvatar pack' + (sportpacks.length == 1 ? '' : 's') + ' to claim. Old pack codes no longer work: connect this same wallet on Sportvatar to claim, no code needed.'"></p>
                            <a target="_blank" rel="noopener" href="{{ rtrim(config('flovatar.sportvatarUrl'), '/') }}/pack/claim" class="btn-o">Claim on Sportvatar</a>
                        </div>
                    </div>
                </div>


            </div>
        </div>

        @include('partials.powered-by')









        <div class="modal-wrapper pack-modal-wrapper jshidden" x-show="showMixedRarityModal">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box error">
                        <h2>MIXED RARITY</h2>
                        <p class="text-lg">You can only upgrade Flobits of the same rarity. <br> e.g. x10 Common Flobits or 10x Rare Flobits and so on.</p>
                        <div class="btn-hold">
                            <button @click.prevent.stop="showMixedRarityModal = false" class="btn btn-sm btn-red"><span class="inner">ok</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper pack-modal-wrapper jshidden" x-show="showAirdropModal">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="modal-pack-box">
                        <button x-show="!fadePackOpening" @click.prevent.stop="showAirdropModal = false" class="close"><i class="icon-cross02"></i></button>
                        <div class="open-pack">
                            <div class="img-pack" x-bind:class="fadePackOpening ? 'fade-out' : ''" x-show="!fadePackCompleted">
                                @include('partials.pack-airdrop-svg')
                            </div>

                            <div class="flovatar-card-list pack-content" x-show="fadePackCompleted" x-bind:class="fadePackCompleted ? 'fade-in' : ''">
                                <template x-for="component in packNewComponents" :key="component.id">
                                    <div class="col">
                                        <a x-bind:href="'/components/'+component.id+'/'+address" class="card" x-bind:class="getRarityColorComponent(component)">
                                            <div class="img-hold component" x-bind:class="showTerraKit ? '' : 'builder-component-thumb'">
                                                <img x-bind:src="component.metadata ? '/api/image/template/'+component.metadata.templateId : '/api/image/template/'+component.templateId" />
                                            </div>
                                            <div class="wrap">
                                                <dl>
                                                    <dt x-text="getComponentCategory(component)"></dt>
                                                    <dd x-text="getComponentName(component)"></dd>
                                                    <dt>Rarity</dt>
                                                    <dd x-text="component.metadata ? component.metadata.rarity : component.rarity"></dd>
                                                    <dt>Mint</dt>
                                                    <dd x-text="getComponentMint(component)"></dd>
                                                    <dt>Sale Price</dt>
                                                    <dd x-html="component.price ? formatPrice(component.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="component.price ? '' : 'text-red'"></dd>
                                                </dl>
                                            </div>
                                            <span x-show="component.price" class="icon-cart"></span>
                                        </a>
                                    </div>
                                </template>
                            </div>


                            <button x-show="!hidePackOpenBtn && !showPackAnimation && !showPackOpening && !fadePackOpening" @click.prevent.stop="claimAirdropFlobits()" class="btn btn-pink btn-lg"><span class="inner">Open Airdrop Pack</span></button>
                            <button x-show="fadePackCompleted" @click.prevent.stop="showAirdropModal = false" class="btn btn-pink btn-lg"><span class="inner">Back to your Collection</span></button>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper pack-modal-wrapper jshidden"  x-show="showUpgradeFlobitModal">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="msg-box" x-show="showUpgradeFlobitModalComplete">
                        <h2>Flobits Upgraded</h2>
                        <p class="text-lg">
                            Congratulations! <br>
                            You successfully upgraded 10 Flobits to 1 of a higher rarity tier. <br>
                            Now check in your Inbox to see what you received!
                        </p>
                        <div class="btn-hold">
                            <button @click.prevent.stop="showUpgradeFlobitModal = false; showUpgradeFlobitModalComplete = false;" class="btn btn-pink btn-md"><span class="inner">Close</span></button>
                        </div>
                    </div>

                    <div class="modal-pack-box modal-airdrop" x-show="!showUpgradeFlobitModalComplete">
                        <button @click.prevent.stop="showUpgradeFlobitModal = false" class="close"><i class="icon-cross02"></i></button>

                        <div>
                            <div class="flovatar-card-list" x-show="!showUpgradeFlobitModalComplete">

                                <template x-for="component in burnList" :key="component.id">
                                    @include('partials.component')
                                </template>

                            </div>

                        </div>

                        <button @click.prevent.stop="upgradeFlobits" x-show="!upgradeFlobitsStarted" class="btn btn-pink btn-md"><span class="inner">Burn 10 Flobits + 20Ð to Upgrade</span></button>
                    </div>
                </div>
            </div>
        </div>





        <div class="modal-wrapper pack-modal-wrapper jshidden" x-show="showPackModal">
            <div class="modal-content">
                <div class="modal-inner">
                    <div class="modal-pack-box">
                        <button x-show="!fadePackOpening" @click.prevent.stop="showPackModal = false" class="close"><i class="icon-cross02"></i></button>
                        <div class="open-pack">
                            <div class="img-pack" x-show="!fadePackCompleted" x-bind:class="fadePackOpening ? 'fade-out' : ''">
                                <template x-if="showTerraKit">
                                    @include('partials.pack-terrakit-svg')
                                </template>
                                <template x-if="!showTerraKit && showDustPack">
                                    @include('partials.pack-dust-svg')
                                </template>
                                <template x-if="!showTerraKit && !showDustPack">
                                    @include('partials.pack-svg')
                                </template>
                            </div>
                            <div class="flovatar-card-list pack-content" x-show="fadePackCompleted" x-bind:class="fadePackCompleted ? 'fade-in' : ''">

                                <template x-for="component in packNewComponents" :key="component.id">
                                    <div class="col">
                                        <a x-bind:href="'/components/'+component.id+'/'+address" class="card" x-bind:class="getRarityColorComponent(component)">
                                            <div class="img-hold component" x-bind:class="showTerraKit ? '' : 'builder-component-thumb'">
                                                <img x-bind:src="component.metadata ? '/api/image/template/'+component.metadata.templateId : '/api/image/template/'+component.templateId" />
                                            </div>
                                            <div class="wrap">
                                                <dl>
                                                    <dt x-text="getComponentCategory(component)"></dt>
                                                    <dd x-text="getComponentName(component)"></dd>
                                                    <dt>Rarity</dt>
                                                    <dd x-text="component.metadata ? component.metadata.rarity : component.rarity"></dd>
                                                    <dt>Mint</dt>
                                                    <dd x-text="getComponentMint(component)"></dd>
                                                    <dt>Sale Price</dt>
                                                    <dd x-html="component.price ? formatPrice(component.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="component.price ? '' : 'text-red'"></dd>
                                                </dl>
                                            </div>
                                            <span x-show="component.price" class="icon-cart"></span>
                                        </a>
                                    </div>
                                </template>

                            </div>

                            <button x-show="!hidePackOpenBtn && !showPackAnimation && !showPackOpening && !fadePackOpening" @click.prevent.stop="window.openPack(selectedPackToOpen); hidePackOpenBtn = true;" class="btn btn-pink btn-lg"><span class="inner">Open pack</span></button>
                            <a x-show="fadePackCompleted" x-bind:href="addressName ? '/collection/'+addressName : '/collection/'+address" class="btn btn-pink btn-lg"><span class="inner">Back to your Collection</span></a>
                        </div>
                    </div>
                </div>
            </div>


        </div>

    </div>


</x-guest-layout>
