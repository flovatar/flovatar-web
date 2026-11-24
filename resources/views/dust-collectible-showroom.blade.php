<x-guest-layout>

    <script>
        @if (isset($templates))
            window.templates = @json($templates);
        @endif
    </script>


    <div class="marketplace-section"
         x-data="showroomCollectibleData(window.templates)"
         x-init="initShowroomCollectible($dispatch);"
         @hashchange.window="loadSearchParams(); searchResults();"
    >
        <div class="container">

            <div class="flovatar-cards-row">
                <div class="title-row">
                    <h2>Minted Collectibles</h2>
                </div>

                @include('partials.filters-dust-showroom')

                <ul class="flovatar-card-list">
                    <template x-if="collectibles">
                        <template x-for="collectible in collectibles" :key="collectible.id">

                            <div class="col">
                                <a x-bind:href="window.itemUrl('collectibles', collectible.flow_id, collectible.flow_address)" class="card" x-bind:class="getRarityColor(collectible)">
                                    <div class="img-hold">
                                        <img x-bind:src="'/api/image/collectible/'+collectible.flow_id" />
                                    </div>
                                    <div class="wrap">
                                        <dl>
                                            <dt>Mint</dt>
                                            <dd x-text="'#'+collectible.flow_id"></dd>
                                            <dt>Rarity</dt>
                                            <dd x-text="getRarityText(collectible)"></dd>
                                            <dt>Rarity Score</dt>
                                            <dd x-text="getRarityScore(collectible)"></dd>
                                            <dt>Sale Price</dt>
                                            <dd x-html="collectible.price ? formatPrice(collectible.price)+' <span>Flow</span>' : 'Not for sale'" x-bind:class="collectible.price ? '' : 'text-red'"></dd>
                                        </dl>
                                    </div>
                                    <span x-show="collectible.price" class="icon-cart"></span>
                                </a>
                            </div>

                        </template>
                    </template>
                </ul>
            </div>
            @include('partials.pagination')
        </div>
    </div>

    @include('partials/powered-by')

</x-guest-layout>
