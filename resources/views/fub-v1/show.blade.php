<x-guest-layout>
{{-- F.U.N. builder of one published FUB (FUB_MODE). Server renders the frame; picks/quote/mint run in the browser (fubBuilder). --}}
@push('og')
    <meta property="og:title" content="{{ $fub->name }} · FUB #{{ $fub->flow_id }} · Flovatar" />
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($fub->description ?: 'Build and mint your own F.U.N. on Flow.', 200) }}" />
@endpush
<section class="isl fub" x-data="fubBuilder({{ (int) $fub->flow_id }})" data-fub="builder">
    <div class="container">
        <p class="isl-crumbs"><a href="/fub">FUB</a> › <span>#{{ $fub->flow_id }}</span> · built on <a href="/islands/{{ $fub->island_id }}">Island #{{ $fub->island_id }}</a></p>
        <h1>{{ $fub->name }}</h1>
        <p class="muted">by <span class="mono">{{ $fub->creator }}</span> · {{ ucfirst($fub->tier) }} FUB · <span x-text="fub ? fub.minted : {{ (int) $fub->minted }}"></span> / {{ number_format($fub->max_supply) }} minted · royalty {{ rtrim(rtrim(number_format($fub->royalty * 100, 2), '0'), '.') }}%</p>
        @if($fub->description)<p>{{ $fub->description }}</p>@endif
        @if(request()->query('published'))<p class="isl-notice isl-n-ok">Published! Your FUB building now stands on Island #{{ $fub->island_id }}.</p>@endif
        @if($fub->suspended)<div class="isl-panel isl-warn"><p>This FUB is suspended.</p></div>@endif
        @if($fub->status !== 'open')<div class="isl-panel isl-warn"><p>Minting is {{ $fub->status }}.</p></div>@endif

        <div class="fub-grid" x-show="fub" x-cloak>
            <div class="fub-stage">
                <div class="fub-canvas"><img :src="previewUrl" alt="Your F.U.N. preview" data-fub="preview"></div>
                <div class="fub-row">
                    <button class="btn" @click="dice()">🎲 Random</button>
                    <b x-text="fmtDust(total)"></b>
                    <button class="btn btn-yellow" @click="mint()" :disabled="!!busy || problems.length > 0 || (quote && !quote.available)" data-fub="mint">Mint F.U.N.</button>
                </div>
                <template x-for="p in problems"><p class="isl-error" x-text="p"></p></template>
                <p class="isl-error" x-show="quote && !quote.available" x-text="quote ? reasonText(quote.reason) : ''"></p>
                <p class="muted" x-show="quote && quote.available" x-text="quote ? ('Creator gets ' + fmtDust(quote.creatorAmount) + ' · ' + fmtDust(quote.burnedAmount) + ' burned') : ''"></p>
                <p class="muted" x-show="wallet" x-text="'Your ĐUST: ' + fmtDust(dust)"></p>
                <p class="isl-error" x-show="error" x-text="error"></p>
                <p class="isl-notice isl-n-ok" x-show="minted" data-fub="minted">Minted! <a :href="'/fub/fun/' + minted" x-show="minted > 0" x-text="'See F.U.N. #' + minted"></a></p>
            </div>
            <div>
                <div class="fub-tabs">
                    <template x-for="l in (fub ? fub.layers : [])" :key="l.key">
                        <button :class="{on: layer === l.key}" @click="layer = l.key" x-text="l.name + (l.required ? '' : ' (optional)')"></button>
                    </template>
                </div>
                <template x-for="l in (fub ? fub.layers : [])" :key="'g' + l.key">
                    <div class="fub-items" x-show="layer === l.key">
                        <template x-for="it in itemsOf(l.key)" :key="it.id">
                            <button class="fub-item" :class="{on: picks[l.key] === it.id, off: it.maxMint > 0 && it.minted >= it.maxMint}" @click="pick(l, it)" :title="it.description">
                                <div class="fub-thumb"><img :src="it.image" :alt="it.name" loading="lazy"></div>
                                <b x-text="it.name"></b>
                                <small x-text="fmtDust(it.currentPrice) + (it.maxMint > 0 ? ' · ' + it.minted + '/' + it.maxMint : '')"></small>
                                <small x-show="it.aiAssisted">AI-assisted</small>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>
        @include('fub-v1.partials.report', ['fubId' => $fub->flow_id])
    </div>
    <div class="fub-busy" x-show="busy" x-text="busy" x-cloak></div>
</section>
</x-guest-layout>
