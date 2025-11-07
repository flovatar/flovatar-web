<x-guest-layout>
    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        window.watermarkSvg = '';
        window.childUnsafeIds = [];
    </script>

    <div class="flovatar-builder psyche-builder asteroid-builder"
         x-data="asteroidBuilderData(window.templates)"
         x-init="initAsteroidBuilder($dispatch);"
         @user-loaded="window.getCollectionsFlovatar(window.user.addr); window.getCollectionsFlobot(window.user.addr); window.getCollectionsCollectible(window.user.addr);"
         @asteroid-created.window="handleAsteroidCollectibleCreated()"
         @builder-checkout-completed.window="processAsteroidBuilderCheckoutCompleted()"
         @collections-flovatar-loaded.window="collectionsFlovatarLoaded($event.detail.collections)"
         @collections-collectible-loaded.window="loadCollectionsCollectible($event.detail.collections)"
         @collections-flobot-loaded.window="loadCollectionsFlobot($event.detail.collections)"
         @hashchange.window="loadHashParams(); loadCombination();"
    >
        <div class="flovatar-builder_bg"></div>
        <div class="flovatar-builder_frame asteroid-builder_frame">
            <div class="flovatar-builder_content">
                <div class="core-parts">
                    <div class="widget-frame">
                        <span class="triangle-icon scrl-up"></span>
                        <div class="widget-content scrollable-frame">
                            <div class="scroll-y">
                                <div class="scroll-content">
                                    <ul class="core-parts-list">
                                        <template x-for="layer in traitsLayers()" :key="layer.id">
                                            <li>
                                                <button class="btn part-btn" @click.prevent.stop="selectActiveLayer(layer)" x-bind:class="getLayerClass(layer)">
                                                    <span class="inner">
                                                        <img x-bind:src="'/images/dust-part-'+layer.name+'.png'" alt="">
                                                        <span class="txt" x-text="layer.name"></span>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>

                                    <strong x-show="loggedIn()" class="title">Residents</strong>
                                    <ul x-show="loggedIn()" class="core-parts-list">
                                        <template x-for="layer in flobitLayers()" :key="layer.id">
                                            <li>
                                                <button class="btn part-btn" @click.prevent.stop="selectActiveLayer(layer)" x-bind:class="getLayerClass(layer)">
                                                    <span class="inner">
                                                        <img x-bind:src="'/images/dust-part-'+layer.name+'.png'" alt="">
                                                        <span class="txt" x-text="layer.name"></span>
                                                    </span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <span class="triangle-icon scrl-down"></span>
                    </div>
                    <span class="connector"></span>
                </div>
                <div class="flovatar-info">
                    <div class="widget-frame">
                        <div class="widget-content scroll-y">
                            <div class="flovatar-info-row" x-bind:class="infoBoxClass()">
                                <strong class="title" x-text="getLayerName(activeLayer)"></strong>
                                <div class="flovatar">
                                    <button class="icon-cross-circle" x-show="showUnavailable()"></button>
                                    <div class="inner-wrap">
                                        <img x-bind:src="getTempUrl(activeLayer)" x-show="getTempUrl(activeLayer)" />
                                    </div>
                                </div>
                                <div class="desc">
                                    <dl>
                                        <dt>Trait</dt>
                                        <dd x-text="getTempName(activeLayer)"></dd>
                                    </dl>
                                    <dl class="two-cols">
                                        <dt>Rarity</dt>
                                        <dd x-html="getTempRarity(activeLayer)"></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="connector"></span>
                </div>
                <div class="flovatar-parts">
                    <div class="widget-frame">
                        <div class="widget-content scrollable-frame">
                            <div class="scroll-y">
                                <div class="scroll-content">
                                    <ul class="parts-list psyche-parts-list">
                                        <template x-for="template in filterTemp(activeLayer)" :key="template.id">
                                            <li x-bind:class="getComponentParentClass(template)">
                                                <button class="part-card" @click.prevent.stop="selectComponent(template.flow_id)" x-bind:class="getComponentClass(template)">
                                                    <div class="builder-component-thumb">
                                                        <img x-bind:src="getTemplateUrl(template.flow_id)" alt="">
                                                    </div>
                                                </button>
                                            </li>
                                        </template>

                                        <template x-if="activeLayer == 6">
                                            <template x-for="flovatar in flovatars" :key="flovatar.id">
                                                <li x-bind:class="getFlovatarRarityText(flovatar)">
                                                    <button class="part-card" @click.prevent.stop="setSlot(flovatar.id, 'flovatar')" x-bind:class="getFlovatarRarityText(flovatar)">
                                                        <div class="builder-component-thumb">
                                                            <img x-bind:src="'/api/image/'+flovatar.id" alt="">
                                                        </div>
                                                    </button>
                                                </li>
                                            </template>
                                        </template>
                                        <template x-if="activeLayer == 7">
                                            <template x-for="collectible in collectibles" :key="collectible.id">
                                                <li x-bind:class="collectible.rarity">
                                                    <button class="part-card" @click.prevent.stop="setSlot(collectible.id, 'collectible')" x-bind:class="collectible.rarity">
                                                        <div class="builder-component-thumb">
                                                            <img x-bind:src="'/api/image/collectible/'+collectible.id" alt="">
                                                        </div>
                                                    </button>
                                                </li>
                                            </template>
                                        </template>
                                        <template x-if="activeLayer == 8">
                                            <template x-for="flobot in flobots" :key="flobot.id">
                                                <li x-bind:class="flobot.rarity">
                                                    <button class="part-card" @click.prevent.stop="setSlot(flobot.id, 'flobot')" x-bind:class="flobot.rarity">
                                                        <div class="builder-component-thumb">
                                                            <img x-bind:src="'/api/image/flobot/'+flobot.id" alt="">
                                                        </div>
                                                    </button>
                                                </li>
                                            </template>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="connector"></span>
                </div>
                <div class="flovatar-machine asteroid-builder-machine">
                    <div class="widget-frame machine-frame no-bg">
                        <div class="widget-content machine-box no-bg">
                            <div class="machine psyche-machine asteroid-machine">
                                <div class="flovatar-img">
                                    <div class="builder-svg" x-html="getFullSvg()" id="flovatar-builder-box"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="foot-row">
                        <ul class="btns-list">
                            <li>
                                <button @click.prevent.stop="saveSvg()" class="btn btn-lilac btn-icon"><span class="inner"><i class="icon-camera"></i></span></button>
                            </li>
                            <li>
                                <button @click.prevent.stop="showZoomModal = true;" class="btn btn-green btn-tall btn-zoom"><span class="inner">

                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                                        </svg>
                                        Zoom In</span>
                                </button>
                            </li>
                        </ul>
                        <span class="divider-line"></span>
                        <ul class="info-list">
                            <li>
                                <div class="dl">
                                    <span class="dt">Rarity</span>
                                    <span class="dd" x-text="getCurrentRarity()" x-bind:class="getCurrentRarityClass()"></span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-wrapper jshidden" x-show="showZoomModal">
            <div class="modal-content modal-zoom">
                <div class="modal-inner">
                    <div class="msg-box zoom-box">
                        <button @click.prevent.stop="showZoomModal = false;" class="close"><i class="icon-cross02"></i></button>
                        <div class="flovatar-modal-img">
                            <div class="modal-zoom-svg" x-html="getFullSvg()"></div>
                        </div>
                        <div class="btn-hold">
                            <button @click.prevent.stop="showZoomModal = false;" class="btn btn-sm btn-green"><span class="inner">Close</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</x-guest-layout>
