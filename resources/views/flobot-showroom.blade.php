<x-guest-layout>

    <script>
        @if (isset($templates))
            window.templates = @json($templates);
        @endif
    </script>


    <div class="marketplace-section"
         x-data="showroomFlobotData(window.templates)"
         x-init="initShowroomFlobot($dispatch);"
         @hashchange.window="loadSearchParams(); searchResults();"
    >
        <div class="container">

            <div class="flovatar-cards-row">
                <div class="title-row">
                    <h2>Minted Flobots</h2>
                </div>

                @include('partials.filters-flobot-showroom')

                <ul class="flovatar-card-list">
                    <template x-if="flobots">
                        <template x-for="flobot in flobots" :key="flobot.id">

                            <div class="col">
                                <a x-bind:href="'/flobots/'+flobot.flow_id+'/'+flobot.flow_address" class="card" x-bind:class="flobot.flow_metadata.metadata.rarity">
                                    <div class="img-hold">
                                        <img x-bind:src="'/api/image/flobot/'+flobot.flow_id" />
                                    </div>
                                    <div class="wrap">
                                        <dl>
                                            <dt>Mint</dt>
                                            <dd x-text="'#'+flobot.flow_id"></dd>
                                            <dt>Rarity</dt>
                                            <dd x-text="flobot.flow_metadata.metadata.rarity"></dd>
                                            <dt>Sale Price</dt>
                                            <dd x-html="getFlobotPrice(flobot) ? getFlobotPrice(flobot) : 'Not for sale'" x-bind:class="getFlobotPrice(flobot) ? '' : 'text-red'"></dd>
                                        </dl>
                                    </div>
                                    <span x-show="getFlobotPrice(flobot)" class="icon-cart"></span>
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
