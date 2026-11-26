<x-guest-layout>
{{-- Asteroid Islands belt (ISLANDS_MODE; KB notes/islands-build.md section D).
     Full-viewport WebGL scene (resources/js/islands/belt/*, lazy chunk "b"): every island orbits the storm-covered rock at
     the heart of the Kaax belt. Alpine component islandsBelt (resources/js/app/islands.js) adds filters, search, tooltip
     and the details dialog. The island list below is the fallback (no WebGL, prefers-reduced-motion, ?view=list) and is
     server-rendered + paginated, so every island page stays linked from plain HTML. --}}
@php
    $sector = \App\Support\Islands::sector();
@endphp
@push('og')
    <meta property="og:title" content="Asteroid Islands · Kaax Asteroid Belt, Sector {{ $sector }} · Flovatar" />
    <meta property="og:description" content="{{ $total }} Asteroid Islands orbit the storm-wrapped heart of the Kaax Asteroid Belt in Sector {{ $sector }}. Each one was terraformed from a TerraKit land deed and is home to NFTs that stay in their owner's wallet." />
    <meta property="og:url" content="{{ rtrim(config('islands.site_url'), '/') }}/islands" />
    <meta property="og:site_name" content="Flovatar" />
    <meta property="og:image" content="{{ $ogImage }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Asteroid Islands · Kaax Asteroid Belt, Sector {{ $sector }}" />
    <meta name="twitter:image" content="{{ $ogImage }}" />
@endpush
{{-- Before first paint: when the 3D belt is going to start, hide the server-rendered list right away (no flash). --}}
<script>
(function () {
    try {
        var c = document.createElement('canvas');
        var gl = window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl'));
        var ok = !!gl;
        if (gl) { var lose = gl.getExtension('WEBGL_lose_context'); if (lose) { lose.loseContext(); } }
        window.__beltGL = ok;
        var view = new URLSearchParams(location.search).get('view');
        var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (ok && view !== 'list' && (view === 'belt' || !calm)) { document.documentElement.classList.add('belt-3d'); }
    } catch (e) { window.__beltGL = false; }
})();
</script>
<section class="isl isl-belt" x-data="islandsBelt" data-islands="belt" :class="'is-' + view" @keydown.escape.window="onEscape()">

    {{-- ------------------------------------------------------------ 3D stage --}}
    <div class="belt-stage" x-ref="stage">
        <canvas class="belt-canvas" x-ref="canvas" tabindex="0" role="img"
                aria-label="3D view of the Kaax Asteroid Belt in Sector {{ $sector }}: {{ $total }} islands orbit a rock wrapped in storm clouds. Use the island list for an accessible view."></canvas>
        <p class="belt-loading" x-show="view === 'belt' && sceneState !== 'ready'" role="status">Scanning Sector {{ $sector }}…</p>

        <div class="belt-tip" x-ref="tip" x-show="view === 'belt' && tipItem() && !modal" x-cloak :class="[tipPinned() ? 'is-pinned' : '', tipItem() && !tipItem().center ? 'isl-r-' + tipItem().rarity : '']" role="status"
             @click="tipPinned() && openModal(tipItem())">
            <template x-if="tipItem() && !tipItem().center">
                <div>
                    <span class="isl-designation" x-text="tipItem().designation"></span>
                    <strong class="belt-tip-name" x-text="title(tipItem())"></strong>
                    <span class="belt-tip-meta">
                        <span class="isl-chip" :class="'isl-r-' + tipItem().rarity" x-text="tipItem().rarity"></span>
                        <span x-text="tipItem().residents + '/' + tipItem().capacity + ' residents'"></span>
                    </span>
                    <button type="button" class="belt-tip-more" x-show="tipPinned()" @click.stop="openModal(tipItem())">Details</button>
                </div>
            </template>
            <template x-if="tipItem() && tipItem().center">
                <div class="belt-tip-center">
                    <span class="isl-designation">Kaax belt · central rock</span>
                    <strong class="belt-tip-name" x-text="center || 'Unidentified signal'"></strong>
                    <span class="belt-tip-meta">Signal obscured. Storm interference is too strong to see below the clouds.</span>
                </div>
            </template>
        </div>

        <div class="belt-foot">
            <p class="belt-hint" x-show="hint && sceneState === 'ready'" x-transition.opacity x-text="touch ? 'Drag to rotate · pinch to zoom · tap an island' : 'Drag to rotate · scroll to zoom · click an island'">Drag to rotate · scroll to zoom · click an island</p>
            <button type="button" class="belt-btn" x-show="following || pinItem" x-cloak @click="resetView()">Back to the whole belt</button>
            <span class="belt-demo" x-show="demo > 0" x-cloak x-text="'Demo: ' + demo + ' placeholder islands'"></span>
            <span class="belt-fps" x-show="showFps && fps" x-cloak x-text="fps ? fps.fps + ' fps · ' + fps.ms + ' ms · ' + fps.islands + ' islands · ' + fps.calls + ' draws · x' + fps.dpr : ''"></span>
        </div>
    </div>

    {{-- ------------------------------------------------------------ title, filters, search --}}
    <div class="belt-panel" :class="{'is-open': panelOpen}">
        <header class="belt-head">
            <span class="isl-eyebrow">Kaax Asteroid Belt · Sector {{ $sector }}</span>
            <h1>Asteroid Islands</h1>
            <p class="belt-count">
                <b x-text="ready ? total : {{ $total }}">{{ $total }}</b> islands in orbit<span x-show="ready && filtering()" x-cloak x-text="' · ' + shown() + ' shown'"></span>
            </p>
            <button type="button" class="belt-panel-toggle" @click="panelOpen = !panelOpen" :aria-expanded="panelOpen ? 'true' : 'false'" aria-controls="belt-tools">
                <span x-text="panelOpen ? 'Hide' : 'Filter & search'">Filter &amp; search</span>
            </button>
        </header>

        <div class="belt-tools" id="belt-tools">
            <p class="belt-intro">Every island was terraformed from a TerraKit land deed. They circle the storm-wrapped rock at the heart of the belt; Flovatars, Flobots, Sportvatars and Psyche Likees live in the scene, and any other NFT can move in too.</p>

            <div class="belt-filters" role="group" aria-label="Filter islands">
                <button type="button" class="belt-chip" :class="{'active': rarity === 'all'}" :aria-pressed="rarity === 'all' ? 'true' : 'false'" @click="rarity = 'all'">All</button>
                @foreach(['common', 'epic', 'legendary'] as $r)
                <button type="button" class="belt-chip isl-r-{{ $r }}" :class="{'active': rarity === '{{ $r }}'}" :aria-pressed="rarity === '{{ $r }}' ? 'true' : 'false'" @click="rarity = rarity === '{{ $r }}' ? 'all' : '{{ $r }}'">
                    <i aria-hidden="true"></i>{{ ucfirst($r) }} <b x-text="ready ? counts.{{ $r }} : {{ $counts[$r] }}">{{ $counts[$r] }}</b>
                </button>
                @endforeach
                <button type="button" class="belt-chip belt-chip-mine" x-show="user" x-cloak :class="{'active': mineOnly}" :aria-pressed="mineOnly ? 'true' : 'false'" @click="toggleMine()">
                    My islands <b x-text="mineCount()"></b>
                </button>
            </div>

            <form class="belt-search" role="search" @submit.prevent="search()">
                <label class="belt-sr" for="belt-q">Find an island by designation, number or name</label>
                <input id="belt-q" type="search" inputmode="search" autocomplete="off" spellcheck="false" placeholder="{{ \App\Support\Islands::designation(1) }}, a number or a name"
                       x-model="query" @input="onQuery()" aria-describedby="belt-search-msg">
                <button type="submit" class="belt-btn">Find</button>
                <ul class="belt-suggest" x-show="suggestions.length" x-cloak>
                    <template x-for="s in suggestions" :key="s.id">
                        <li><button type="button" @click="go(s)"><span class="isl-designation" x-text="s.designation"></span> <span x-text="s.name"></span></button></li>
                    </template>
                </ul>
                <p class="belt-search-msg" id="belt-search-msg" role="status" x-text="searchMsg"></p>
            </form>

            <div class="belt-mine" x-show="mineOnly" x-cloak>
                <template x-if="!user">
                    <p>Connect your wallet to see your islands. <button type="button" class="isl-link" @click="connect()">Connect wallet</button></p>
                </template>
                <template x-if="user && inv">
                    <p>Land deeds: <b x-text="deedCount('common')"></b> common · <b x-text="deedCount('epic')"></b> epic · <b x-text="deedCount('legendary')"></b> legendary · unopened TerraKit packs: <b x-text="packCount()"></b></p>
                </template>
            </div>

            <div class="belt-actions">
                <a href="/islands/build" class="btn btn-pink"><span class="inner">Terraform an island</span></a>
                <div class="belt-views" role="group" aria-label="View">
                    <button type="button" :class="{'active': view === 'belt'}" :aria-pressed="view === 'belt' ? 'true' : 'false'" x-show="canWebgl" x-cloak @click="setView('belt')">3D belt</button>
                    <button type="button" :class="{'active': view === 'list'}" :aria-pressed="view === 'list' ? 'true' : 'false'" @click="setView('list')">List</button>
                </div>
            </div>
            <p class="belt-note" x-show="sceneState === 'failed'" x-cloak>The 3D belt needs WebGL, which is not available here. Showing the list instead.</p>
            <p class="belt-note" x-show="reduced && view === 'list' && canWebgl && sceneState !== 'failed'" x-cloak>Your device asks for reduced motion, so the list is shown. The 3D belt runs in a calmer mode if you switch to it.</p>
        </div>
    </div>

    {{-- ------------------------------------------------------------ list (fallback, accessible view, SEO) --}}
    <div class="belt-list container" data-islands="belt-list">
        {{-- server-rendered until the bundle has the full list --}}
        <div class="belt-ssr" x-show="!ready">
            @if($islands->isEmpty())
                <p class="isl-empty">No island has been terraformed yet. Be the first: <a href="/islands/build">redeem a land deed</a>.</p>
            @else
            <div class="belt-grid">
                @foreach($islands as $i)
                <a href="/islands/{{ $i->flow_id }}" class="isl-card isl-r-{{ $i->rarity }}">
                    <span class="isl-card-img">
                        <img loading="lazy" src="{{ \App\Support\ItemImages::url('island', $i->flow_id, 'full.png', false) ?? '/islands/'.$i->flow_id.'/full.svg' }}" alt="{{ $i->display_name }}">
                    </span>
                    <span class="isl-card-body">
                        <span class="isl-designation">{{ $i->designation }}</span>
                        <span class="isl-card-name">{{ $i->name ?: 'Unnamed island' }}</span>
                        <span class="isl-card-meta"><span class="isl-chip isl-r-{{ $i->rarity }}">{{ $i->rarity }}</span>{{ $i->residents_count }}/{{ \App\Support\Islands::capacity($i->rarity) }} residents</span>
                    </span>
                </a>
                @endforeach
            </div>
            @if($islands->hasPages())
            <nav class="belt-pages" aria-label="Island list pages">
                @if($islands->previousPageUrl())<a href="{{ $islands->previousPageUrl() }}" rel="prev">Previous</a>@endif
                <span>Page {{ $islands->currentPage() }} of {{ $islands->lastPage() }}</span>
                @if($islands->nextPageUrl())<a href="{{ $islands->nextPageUrl() }}" rel="next">Next</a>@endif
            </nav>
            @endif
            @endif
        </div>

        <div x-show="ready" x-cloak>
            <p class="isl-error" x-show="loadError">We couldn't load the belt. Reload the page to try again.</p>
            <p class="isl-empty" x-show="!shown() && !filtering()">No island has been terraformed yet. Be the first: <a href="/islands/build">redeem a land deed</a>.</p>
            <p class="isl-empty" x-show="!shown() && filtering()">No island matches this filter.</p>
            <div class="belt-grid">
                <template x-for="i in listItems()" :key="i.id">
                    <a :href="i.url" class="isl-card" :class="['isl-r-' + i.rarity, listFocus === i.id ? 'is-focus' : '']" :data-island-card="i.id"
                       @click.prevent="openModal(i)">
                        <span class="isl-card-img"><img loading="lazy" :src="image(i)" :alt="title(i)"></span>
                        <span class="isl-card-body">
                            <span class="isl-designation" x-text="i.designation"></span>
                            <span class="isl-card-name" x-text="title(i)"></span>
                            <span class="isl-card-meta"><span class="isl-chip" :class="'isl-r-' + i.rarity" x-text="i.rarity"></span><span x-text="i.residents + '/' + i.capacity + ' residents'"></span></span>
                        </span>
                    </a>
                </template>
            </div>
            <p class="belt-more" x-show="shown() > listLimit">
                <button type="button" class="belt-btn" @click="listLimit += 120" x-text="'Show more (' + (shown() - listLimit) + ' left)'"></button>
            </p>
        </div>
    </div>

    {{-- ------------------------------------------------------------ details dialog --}}
    <div class="belt-modal" x-show="modal" x-cloak x-transition.opacity @click.self="closeModal()" @keydown.tab="trapFocus($event)">
        <div class="belt-modal-card" x-ref="modalCard" role="dialog" aria-modal="true" aria-labelledby="belt-modal-title" :class="modal ? 'isl-r-' + modal.rarity : ''">
            <button type="button" class="belt-modal-close" x-ref="modalClose" @click="closeModal()" aria-label="Close">×</button>
            <template x-if="modal">
                <div class="belt-modal-grid">
                    <div class="belt-modal-art"><img :src="(detail && detail.png) || image(modal)" :alt="title(modal)"></div>
                    <div class="belt-modal-info">
                        <span class="isl-designation" x-text="modal.designation"></span>
                        <h2 id="belt-modal-title" x-text="detail ? detail.name : title(modal)"></h2>
                        <p class="belt-modal-chips">
                            <span class="isl-chip" :class="'isl-r-' + modal.rarity" x-text="modal.rarity"></span>
                            <span x-show="detail && detail.fused">Fused from 5 deeds</span>
                        </p>
                        <p class="belt-modal-demo" x-show="modal.demo">Placeholder island of the demo belt (it reuses the art of <span x-text="designation(modal.src || 0)"></span>). It does not exist on chain.</p>
                        <p class="belt-modal-desc" x-show="detail && detail.description" x-text="detail ? detail.description : ''"></p>
                        <p class="muted" x-show="detailStatus === 'loading'" role="status">Reading the island log…</p>
                        <p class="isl-error" x-show="detailStatus === 'error'">We couldn't load the details. The island page has everything.</p>
                        <dl class="belt-facts" x-show="!modal.demo">
                            <div>
                                <dt>Owner</dt>
                                <dd><a x-show="(detail && detail.owner) || modal.owner" :href="'/collection/' + ((detail && detail.owner) || modal.owner)" class="mono" x-text="short((detail && detail.owner) || modal.owner)"></a></dd>
                            </div>
                            <div>
                                <dt>Residents</dt>
                                <dd x-text="(detail ? detail.residents : modal.residents) + ' of ' + modal.capacity"></dd>
                            </div>
                            <div x-show="terraformed()">
                                <dt>Terraformed</dt>
                                <dd x-text="terraformed()"></dd>
                            </div>
                        </dl>
                        <div class="belt-residents" x-show="detail && (premiumResidents().length || standardCount())">
                            <ul x-show="premiumResidents().length">
                                <template x-for="r in premiumResidents()" :key="r.slot">
                                    <li :title="familyLabel(r) + ' #' + r.id">
                                        <img x-show="residentThumb(r)" loading="lazy" :src="residentThumb(r)" :alt="familyLabel(r) + ' #' + r.id">
                                        <span x-show="!residentThumb(r)" x-text="familyLabel(r).slice(0, 2)"></span>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="standardCount()" x-text="'+ ' + standardCount() + (standardCount() === 1 ? ' standard resident' : ' standard residents')"></p>
                        </div>
                        <div class="belt-modal-actions">
                            <a :href="modal.url" class="btn btn-pink"><span class="inner" x-text="modal.demo ? 'Visit ' + designation(modal.src || 0) : 'Visit the island'"></span></a>
                            <button type="button" class="belt-btn" x-show="canWebgl" @click="flyFromModal()">Fly to it</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>
</x-guest-layout>
