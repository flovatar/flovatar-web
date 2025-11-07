<x-guest-layout>
    <script>
        @if(isset($templates))
            window.templates = @json($templates);
        @endif
        @if(isset($stats))
            window.stats = @json($stats);
        @endif
    </script>
    <div class="marketplace-section"
         x-data="marketplaceData(window.templates, window.stats)"
         x-init="initMarketplace($dispatch);"
         @hashchange.window="loadSearchParams(); searchResults();"
    >
        <div class="container">
            @include('partials.marketplace-stats')

            <div class="flovatar-cards-row">
                <div class="title-row alt-sm">
                    <div class="title-wrap">
                        <h2>For sale</h2>
                        @if(config('flovatar.dapperEnabled') === true)
                        <div class="icons">
                            <span class="ico-wrap" x-show="!showDapper">
                                <img src="/images/ico-blocto.png" width="36" alt="">
                            </span>
                            <span class="ico-wrap" x-show="!showDapper">
                                <img src="/images/ico-lilico.png" width="36" alt="">
                            </span>
                            <span class="ico-wrap" x-show="showDapper">
                                <img src="/images/ico-dapper.png" width="36" alt="">
                            </span>
                        </div>
                        <select class="select-outline" x-model="selectedMarketplace" value="showDapper ? 'dapper' : 'blocto'">
                            <option value="blocto">Blocto / Lilico</option>
                            <option value="dapper">Dapper</option>
                        </select>
                        @endif
                    </div>
                </div>

                @include('partials.filters')

                <div class="flovatar-card-list">
                    <template x-if="sales">
                        <template x-for="sale in sales" :key="sale.id">
                            @include('partials.sale')
                        </template>
                    </template>
                </div>
            </div>

            @include('partials.pagination')

            <ul x-show="totalResults == 0" class="no-results-msg">
                <li>
                    <span class="transition-colors block text-nowrap" x-text="isDapperWallet() ? 'Please note that Dapper Marketplace will be enabled as soon as the FLOW token will be supported by the Dapper Wallet' : 'No Results'"></span>
                </li>
            </ul>

            <div x-show="!sales" class="loading-msg">
                <div class="w-3/4 m-auto">
                    <div class="text-3xl font-smile">Loading</div>
                    <div class="text-xl">Please wait a few floments</div>
                </div>
            </div>

        </div>
    </div>

    @include('partials/powered-by')


</x-guest-layout>
