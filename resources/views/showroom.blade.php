<x-guest-layout>

    <script>
        @if (isset($templates))
            window.templates = @json($templates);
        @endif
    </script>


    <div class="marketplace-section"
         x-data="showroomData(window.templates)"
         x-init="initShowroom($dispatch);"
         @hashchange.window="loadSearchParams(); searchResults();"
    >
        <div class="container">

            <div class="flovatar-cards-row">
                <div class="title-row">
                    <h2>Minted flovatars</h2>
                </div>

                @include('partials.filters-showroom')

                <ul class="flovatar-card-list">
                    <template x-if="flovatars">
                        <template x-for="flovatar in flovatars" :key="flovatar.id">

                            <div class="col">
                                <a x-bind:href="window.itemUrl('flovatars', flovatar.flow_id, flovatar.flow_address)" class="card" x-bind:class="getRarityColor(flovatar)">
                                    <div class="img-hold">
                                        <img x-bind:src="flovatar.list_thumb || '/api/image/'+flovatar.flow_id" />
                                        <div x-html="getFlovatarRarityImages(flovatar.flow_metadata)" class="rarity-score-boosters"></div>
                                    </div>
                                    <div class="wrap">
                                        <dl>
                                            <dt>Mint</dt>
                                            <dd x-text="'#'+flovatar.flow_id"></dd>
                                            <dt>Rarity</dt>
                                            <dd x-text="getRarityText(flovatar)"></dd>
                                            <dt>Rarity Score</dt>
                                            <dd x-text="getRarityScore(flovatar.flow_metadata)"></dd>
                                            <dt>Sale Price</dt>
                                            <dd x-html="getFlovatarPrice(flovatar) ? getFlovatarPrice(flovatar) : 'Not for sale'" x-bind:class="getFlovatarPrice(flovatar) ? '' : 'text-red'"></dd>
                                        </dl>
                                    </div>
                                    <span x-show="getFlovatarPrice(flovatar)" class="icon-cart"></span>
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
