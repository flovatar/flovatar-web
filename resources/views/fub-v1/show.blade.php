<x-guest-layout>
{{-- F.U.N. builder of one published FUB (FUB_MODE). Server renders the frame; picks/quote/mint run in the browser (fubBuilder).
     Layout = the Flovatar builder shell (.fub-shell in layouts/_fub-v1.scss): options LEFT, big preview RIGHT. --}}
@push('og')
    <meta property="og:title" content="{{ $fub->name }} · FUB #{{ $fub->flow_id }} · Flovatar" />
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($fub->description ?: 'Build and mint your own F.U.N. on Flow.', 200) }}" />
@endpush
<section class="isl fub flovatar-builder fub-shell" x-data="fubBuilder({{ (int) $fub->flow_id }})" data-fub="builder">
    <div class="flovatar-builder_bg"></div>
    <div class="flovatar-builder_frame">
        <div class="flovatar-builder_content">
            <div class="fub-shell_wrap">
                {{-- ================= LEFT: options ================= --}}
                <div class="fub-shell_options">
                    <div class="fub-shell_info">
                        <div class="widget-frame"><div class="widget-content">
                            <div class="fub-info-head">
                                <p class="isl-crumbs"><a href="/fub">FUB</a> › <span>#{{ $fub->flow_id }}</span> · built on <a href="/islands/{{ $fub->island_id }}">Island #{{ $fub->island_id }}</a></p>
                                <h1>{{ $fub->name }}</h1>
                                <p class="fub-info-state">
                                    <span class="fub-badge">{{ ucfirst($fub->tier) }} FUB</span>
                                    <span class="fub-badge"><span x-text="fub ? fub.minted : {{ (int) $fub->minted }}">{{ (int) $fub->minted }}</span> / {{ number_format($fub->max_supply) }} minted</span>
                                    <span class="fub-badge">royalty {{ rtrim(rtrim(number_format($fub->royalty * 100, 2), '0'), '.') }}%</span>
                                </p>
                            </div>
                            <div class="fub-info-text">
                                <p class="muted">by <span class="mono">{{ $fub->creator }}</span></p>
                                @if($fub->description)<p class="fub-info-desc" title="{{ $fub->description }}">{{ $fub->description }}</p>@endif
                            </div>
                        </div></div>
                        <span class="connector"></span>
                    </div>

                    <div class="fub-shell_panel">
                        <div class="widget-frame">
                            <div class="fub-shell_tabs" role="tablist" aria-label="Layers">
                                <template x-for="l in (fub ? fub.layers : [])" :key="l.key">
                                    <button type="button" role="tab" class="btn btn-sm btn-gray" :class="{active: layer === l.key}" :aria-selected="layer === l.key" @click="layer = l.key"><span class="inner" x-text="l.name + (l.required ? '' : ' (optional)')"></span></button>
                                </template>
                            </div>
                            @if(request()->query('published'))<p class="isl-notice isl-n-ok">Published! Your FUB building now stands on Island #{{ $fub->island_id }}.</p>@endif
                            @if($fub->suspended)<p class="isl-notice isl-error">This FUB is suspended.</p>@endif
                            @if($fub->status !== 'open')<p class="isl-notice isl-error">Minting is {{ $fub->status }}.</p>@endif
                            <template x-for="p in problems"><p class="isl-notice isl-error" x-text="p"></p></template>
                            <p class="isl-notice isl-error" x-show="quote && !quote.available" x-text="quote ? reasonText(quote.reason) : ''" x-cloak></p>
                            <p class="isl-notice isl-error" x-show="error" x-text="error" x-cloak role="alert"></p>
                            <p class="isl-notice isl-n-ok" x-show="minted" x-cloak data-fub="minted" role="status">Minted! <a :href="'/fub/fun/' + minted" x-show="minted > 0" x-text="'See F.U.N. #' + minted"></a></p>

                            <div class="widget-content scrollable-frame">
                                <div class="scroll-y" tabindex="-1">
                                    <template x-for="l in (fub ? fub.layers : [])" :key="'g' + l.key">
                                        <div class="fub-items fub-pad" x-show="layer === l.key" role="tabpanel">
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
                                    <div class="fub-pad">
                                        <p class="muted" x-show="quote && quote.available" x-text="quote ? ('Creator gets ' + fmtDust(quote.creatorAmount) + ' · ' + fmtDust(quote.burnedAmount) + ' burned') : ''"></p>
                                        <p class="muted" x-show="wallet" x-text="'Your ĐUST: ' + fmtDust(dust)"></p>
                                        @include('fub-v1.partials.report', ['fubId' => $fub->flow_id])
                                    </div>
                                </div>
                            </div>
                        </div>
                        <span class="connector"></span>
                    </div>
                </div>

                {{-- ================= RIGHT: the big preview ================= --}}
                <div class="flovatar-machine fub-shell_machine">
                    <div class="widget-frame machine-frame no-bg">
                        <div class="widget-content machine-box no-bg">
                            <div class="machine fub-shell_stage">
                                <div class="flovatar-img"><img class="builder-svg" :src="previewUrl" alt="Your F.U.N. preview" data-fub="preview"></div>
                            </div>
                        </div>
                    </div>
                    <ul class="btns-list">
                        <li><button type="button" @click="dice()" class="btn btn-lilac btn-tall" title="Show a random combination"><span class="inner">Randomize</span></button></li>
                        <li><button type="button" @click="mint()" :disabled="!!busy || problems.length > 0 || (quote && !quote.available)" class="btn btn-green btn-tall fub-btn-wide" data-fub="mint"><span class="inner" x-text="'Mint F.U.N. · ' + fmtDust(total)">Mint F.U.N.</span></button></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="fub-busy" x-show="busy" x-text="busy" x-cloak role="status"></div>
</section>
</x-guest-layout>
