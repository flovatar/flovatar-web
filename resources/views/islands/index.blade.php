<x-guest-layout>
{{-- Asteroid Islands belt (ISLANDS_MODE; KB notes/islands-build.md). Server-rendered list of every island (indexer DB);
     the "My islands" tab reads the connected wallet in the browser (resources/js/app/islands.js islandsBelt). --}}
@push('og')
    <meta property="og:title" content="Asteroid Islands · Flovatar" />
    <meta property="og:description" content="Asteroid Islands of the Kaax belt, terraformed from TerraKit land deeds. Every island is home to NFTs that stay in their owner's wallet." />
    <meta property="og:url" content="{{ rtrim(config('islands.site_url'), '/') }}/islands" />
    <meta property="og:site_name" content="Flovatar" />
    <meta property="og:image" content="{{ $islands->count() ? (\App\Support\ItemImages::url('island', $islands->last()->flow_id, 'og.jpg', false) ?? 'https://flovatar.com/images/share.png') : 'https://flovatar.com/images/share.png' }}" />
    <meta name="twitter:card" content="summary_large_image" />
@endpush
<section class="isl isl-belt" x-data="islandsBelt" data-islands="belt">
    <div class="container">
        <header class="isl-hero">
            <span class="isl-eyebrow">Kaax belt · K-334</span>
            <h1>Asteroid Islands</h1>
            <p>Every island here was terraformed from a TerraKit land deed. Flovatars, Flobots, Sportvatars and Psyche Likees live in the scene; any other NFT can move in too.</p>
            <div class="isl-hero-cta">
                <a href="/islands/build" class="btn btn-pink"><span class="inner">Redeem land deeds</span></a>
                <span class="isl-stats">
                    <b>{{ $islands->count() }}</b> islands ·
                    <span class="isl-r-common">{{ $counts['common'] }} common</span> ·
                    <span class="isl-r-epic">{{ $counts['epic'] }} epic</span> ·
                    <span class="isl-r-legendary">{{ $counts['legendary'] }} legendary</span>
                </span>
            </div>
        </header>

        <nav class="isl-tabs" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'belt'" :class="tab === 'belt' ? 'active' : ''" @click="tab = 'belt'; history.replaceState(null, '', '#')">The belt</button>
            <button type="button" role="tab" :aria-selected="tab === 'mine'" :class="tab === 'mine' ? 'active' : ''" @click="showMine()">My islands</button>
        </nav>

        <div x-show="tab === 'belt'" data-islands="belt-list">
            @if($islands->isEmpty())
                <p class="isl-empty">No island has been terraformed yet. Be the first: <a href="/islands/build">redeem a land deed</a>.</p>
            @else
            <div class="isl-belt-track">
                @foreach($islands as $i)
                <a href="/islands/{{ $i->flow_id }}" class="isl-card isl-r-{{ $i->rarity }}" style="--d: {{ ($i->flow_id % 7) * 9 }}px">
                    <span class="isl-card-img">
                        <img loading="lazy" src="{{ \App\Support\ItemImages::url('island', $i->flow_id, 'thumb.jpg', false) ?? '/islands/'.$i->flow_id.'/full.svg' }}" alt="{{ $i->display_name }}">
                    </span>
                    <span class="isl-card-body">
                        <span class="isl-designation">{{ $i->designation }}</span>
                        <span class="isl-card-name">{{ $i->name ?: 'Unnamed' }}</span>
                        <span class="isl-card-meta"><span class="isl-chip isl-r-{{ $i->rarity }}">{{ $i->rarity }}</span>{{ $i->residents_count }}/{{ \App\Support\Islands::capacity($i->rarity) }} residents{{ $i->fused ? ' · fused' : '' }}</span>
                    </span>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        <div x-show="tab === 'mine'" x-cloak data-islands="mine">
            <template x-if="!user">
                <div class="isl-panel">
                    <p>Connect your wallet to see your islands, your land deeds and your unopened TerraKit packs.</p>
                    <button type="button" class="btn btn-pink" @click="connect()"><span class="inner">Connect wallet</span></button>
                </div>
            </template>
            <template x-if="user">
                <div>
                    <p class="isl-for">Wallet <span class="mono" x-text="user.addr"></span></p>
                    <p x-show="mineStatus === 'loading'">Reading your wallet on the Flow blockchain…</p>
                    <p x-show="mineStatus === 'error'" class="isl-error">We couldn't read your wallet. Try again in a moment.</p>
                    <div x-show="mineStatus === 'done'">
                        <div class="isl-panel isl-inventory" x-show="inv">
                            <span>Land deeds: <b x-text="deedCount('common')"></b> common · <b x-text="deedCount('epic')"></b> epic · <b x-text="deedCount('legendary')"></b> legendary</span>
                            <span>Unopened TerraKit packs: <b x-text="packCount()"></b></span>
                            <a href="/islands/build" class="btn btn-sm"><span class="inner">Redeem / open packs</span></a>
                        </div>
                        <div class="isl-belt-track" x-show="mine.length">
                            <template x-for="i in mine" :key="i.id">
                                <a :href="i.url" class="isl-card" :class="'isl-r-' + i.rarity">
                                    <span class="isl-card-img"><img loading="lazy" :src="i.image" :alt="i.name"></span>
                                    <span class="isl-card-body">
                                        <span class="isl-designation" x-text="i.designation"></span>
                                        <span class="isl-card-name" x-text="i.name"></span>
                                        <span class="isl-card-meta"><span class="isl-chip" :class="'isl-r-' + i.rarity" x-text="i.rarity"></span><span x-text="i.residents + '/' + i.capacity + ' residents'"></span></span>
                                    </span>
                                </a>
                            </template>
                        </div>
                        <p x-show="!mine.length" class="isl-empty">No island in this wallet yet.</p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>
</x-guest-layout>
