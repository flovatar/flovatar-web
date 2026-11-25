<x-guest-layout>
    @php($t = $flobitType['template'])
    @push('og')
        @include('partials.og-item', ['ogType' => 'template', 'ogId' => $templateId, 'ogTitle' => 'Flobit - '.$t['name'], 'ogUrl' => url('flobits/'.$templateId),
            'ogDescription' => $t['name'].' ('.ucfirst((string) $t['rarity']).' '.$t['category'].') - '.$flobitType['minted'].' minted. Flovatar Flobit.'])
    @endpush

    <script>
        window.flobitType = @json($flobitType);
    </script>

    {{-- Same layout/classes as the Flobit item page (flovatarcomponent.blade.php); data = template level --}}
    <section class="flovatar-detail flobit-type"
             x-data="flobitTypeData(window.flobitType)"
             x-init="init()">
        <div class="container">
            <div class="row">
                <div class="col-l">
                    <div class="img-holder">
                        <div class="img">
                            <div class="{{ in_array($t['category'], ['spark', 'boost', 'land', 'fub', 'flobotkit'], true) ? '' : 'builder-component-thumb' }}">
                                <img src="/api/image/template/{{ $templateId }}" alt="{{ $t['name'] }}">
                            </div>
                        </div>
                        <div class="info-circles">
                            <div class="circle" x-show="floor('FLOW') !== null" @if(! collect($flobitType['listings'])->firstWhere('currency', 'FLOW')) style="display: none" @endif>
                                <div class="txt-img-t">
                                    <img src="/images/text-on-sale-t.svg" width="58" alt="On Sale">
                                </div>
                                <span class="score text-green" x-html="formatPrice(floor('FLOW'))+'<sub>F</sub>'"></span>
                                <div class="txt-img-b">
                                    <img src="/images/text-on-sale-b.svg" width="58" alt="On Sale">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="btns-container">
                        <div class="btns-row">
                            <div class="btns-list-wrap">
                                <ul class="btns-list">
                                    <li class="grow">
                                        @if(in_array($t['category'], ['boost', 'land', 'flobotkit', 'fub'], true))
                                        <a href="/marketplace#templatecat={{ $t['category'] }}&rarity={{ $t['rarity'] }}" class="btn btn-sm btn-lilac"><span class="inner">View in Marketplace</span></a>
                                        @else
                                        <a href="/marketplace#templatecat=flobits&templateid={{ $templateId }}" class="btn btn-sm btn-lilac"><span class="inner">View in Marketplace</span></a>
                                        @endif
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-r">
                    <div class="content-box">
                        <div class="title-row">
                            <h2>{{ $t['name'] }}</h2>
                        </div>
                        <div class="box">
                            <ul class="stat-list">
                                <li>
                                    <strong>Category</strong>
                                    <span>{{ $t['category'] == 'boost' ? 'Booster' : $t['category'] }}</span>
                                </li>
                                <li>
                                    <strong>Rarity</strong>
                                    <span>{{ $t['rarity'] }}</span>
                                </li>
                                <li>
                                    <strong>Series</strong>
                                    <span>{{ $t['series'] }}</span>
                                </li>
                                <li>
                                    <strong>Minted</strong>
                                    <span x-text="minted()">{{ $flobitType['minted'] }}{{ $t['max_mintable'] ? ' / '.$t['max_mintable'] : '' }}</span>
                                </li>
                                <li>
                                    <strong>Burned</strong>
                                    <span x-text="data.template.burned">{{ $t['burned'] }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="content-box">
                        <div class="title-row">
                            <h2>Marketplace</h2>
                        </div>
                        <div class="box">
                            <ul class="stat-list">
                                <li>
                                    <strong>Listed</strong>
                                    <span x-text="listedText()">{{ collect($flobitType['listings'])->sum('count') }}</span>
                                </li>
                                <li>
                                    <strong>Floor</strong>
                                    <span x-text="floorText()">{{ collect($flobitType['listings'])->map(fn ($l) => number_format($l['floor'], 2).' '.$l['currency'])->implode(' / ') ?: '-' }}</span>
                                </li>
                                <li>
                                    <strong>Sales</strong>
                                    <span x-text="data.sales.count">{{ $flobitType['sales']['count'] }}</span>
                                </li>
                                <li>
                                    <strong>Avg / Max</strong>
                                    <span x-text="salesText()">{{ $flobitType['sales']['count'] ? number_format($flobitType['sales']['avg'], 2).' / '.number_format($flobitType['sales']['max'], 2) : '-' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="content-box" x-show="data.listed.length" @if(! $flobitType['listed']) style="display: none" @endif>
                        <div class="title-row">
                            <h2>Cheapest listings</h2>
                        </div>
                        <div class="box">
                            <ul class="stat-list">
                                <template x-for="l in data.listed" :key="l.id">
                                    <li>
                                        <strong><a x-bind:href="'/components/'+l.id+'/'+l.owner" x-text="l.mint !== null ? 'Mint #'+l.mint : 'Flobit #'+l.id"></a></strong>
                                        <span x-text="formatPrice(l.price)+' '+l.currency"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
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
                                            @include('partials.render-downloads', ['dlType' => 'template', 'dlId' => $templateId, 'dlName' => 'Flobit-'.\Illuminate\Support\Str::slug($t['name'])])
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('partials.powered-by')
</x-guest-layout>
