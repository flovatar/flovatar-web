<x-guest-layout>
{{-- Asteroid Island page (ISLANDS_MODE; KB notes/islands-build.md, round 4). Island-level data only (edge-cacheable); owner
     tools, the collection box (pick-and-place, one transaction per Save), the layer tools (Change <mutable layer>, one-time Choose
     display) and live resident checks run in the browser (resources/js/app/islands.js islandPage). --}}
@if($island)
@push('og')
    <meta property="og:title" content="{{ $island->display_name }} · {{ $island->designation }} · Flovatar Asteroid Islands" />
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($island->description ?: ucfirst($island->rarity).' Asteroid Island in the Kaax belt with '.$island->residents_count.' resident'.($island->residents_count == 1 ? '' : 's').'.', 200) }}" />
    <meta property="og:url" content="{{ \App\Support\Islands::url((int) $island->flow_id) }}" />
    <meta property="og:site_name" content="Flovatar" />
    <meta property="og:image" content="{{ $og['url'] }}" />
    <meta property="og:image:width" content="{{ $og['width'] }}" />
    <meta property="og:image:height" content="{{ $og['height'] }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ $og['url'] }}" />
@endpush
@endif
<section class="isl isl-page isp" x-data="islandPage({{ (int) $id }})" data-islands="island" data-island-id="{{ (int) $id }}"
         @keydown.escape.window="onEscape()" :class="{'isp-owner': isOwner, 'isp-has-changes': dirty, 'isp-sheet-open': sheetOpen}">
    <div class="container">
        <p class="isl-crumbs"><a href="/islands">Asteroid belt</a> › <span>{{ \App\Support\Islands::designation((int) $id) }}</span></p>
        @if(!empty($fub))
            <div class="isl-panel isl-fub-link" data-islands="fub-link"><p>🏗️ This island hosts the FUB <b>{{ $fub->name }}</b> ({{ number_format($fub->minted) }} F.U.N.s minted). <a class="fub-btn" href="/fub/{{ $fub->flow_id }}">Visit the FUB</a></p></div>
        @elseif(!empty($fubBuilding))
            <div class="isl-panel isl-fub-link" data-islands="fub-building"><p>🏗️ A FUB is under construction on this island. Its builder opens here when the creator publishes it.</p></div>
        @endif

        <div class="isl-panel" x-show="status === 'loading'"><p>Loading the island…</p></div>
        <div class="isl-panel isl-error" x-show="status === 'missing'" x-cloak><p>This island isn't in the belt yet. If you just terraformed it, give the blockchain a minute and reload.</p></div>

        <div class="isp-grid" x-show="status === 'ready'" x-cloak>
            {{-- ================= scene ================= --}}
            <div class="isp-stage">
                <div class="isp-frame" x-ref="frame" :class="(island ? 'isl-r-' + island.rarity : '') + (zoom ? ' is-zoom' : '') + (needsLive ? ' is-live' : '') + ((drag || selected) ? ' is-targeting' : '')" data-islands="scene-frame">
                    <div class="isp-cam" :style="camStyle" :class="{on: zoomOn}">
                        <img class="isp-scene" :src="sceneSrc" :alt="island && (island.rawName || island.designation)" data-islands="scene" draggable="false">
                        {{-- residents the browser couldn't inline (cross-origin art) while previewing --}}
                        <template x-for="o in overlays" :key="o.slot"><img class="isp-overlay" :src="o.src" alt="" :style="pct(o.box)"></template>
                        <img class="isp-overlay isp-screen-overlay" x-show="screenOverlay" :src="screenOverlay" alt="" :style="pct(screenRect())">

                        {{-- premium slots at the art's anchors --}}
                        <template x-for="h in hotspots()" :key="h.chain">
                            <button type="button" class="isp-spot" :style="pct(h.box)" :data-drop="isOwner ? h.chain : null" :data-slot="h.slot"
                                    :class="{filled: !!h.r, empty: !h.r, invalid: h.r && !h.r.pending && !isValid(h.r), pending: slotChanged(h.chain), target: targetActive(h.chain), hover: dropHover === h.chain, picked: selected === 'slot:' + h.chain}"
                                    @click="clicked(() => hotspotClick(h))" @pointerdown="isOwner && h.r && dragStart($event, 'slot:' + h.chain)"
                                    :aria-label="h.slot + ': ' + (h.r ? nftName(h.r) : (isOwner ? 'free scene slot' : 'free'))"
                                    :tabindex="isOwner || h.r ? 0 : -1" :data-label="h.r ? nftName(h.r) : h.slot">
                                <span class="isp-spot-tag" x-text="h.slot"></span>
                                <span class="isp-spot-name" x-show="h.r" x-text="h.r ? nftName(h.r) : ''"></span>
                                <span class="isp-spot-x" x-show="isOwner && h.r" role="button" aria-label="Remove" @click.stop="removeFrom(h.chain)" @pointerdown.stop>×</span>
                            </button>
                        </template>

                        {{-- the display: click = zoom into the screen; owner drop target for standard residents --}}
                        <button type="button" class="isp-screen" x-show="screenRect()" :style="pct(screenRect())" data-drop="display"
                                :class="{target: targetActive('display'), hover: dropHover === 'display'}" @click="clicked(() => displayClick())"
                                :aria-label="carousel.length ? 'Open the display: ' + carousel.length + ' NFT' + (carousel.length === 1 ? '' : 's') : 'The display (no NFTs yet)'" data-islands="display">
                            <span class="isp-screen-hint" x-text="(drag || selected) && isOwner ? 'Drop on the display' : (carousel.length ? '▶ ' + carousel.length : '')"></span>
                        </button>
                    </div>

                    {{-- zoomed display: carousel of the standard residents, over the screen --}}
                    <div class="isp-zoom" x-show="zoom" x-transition.opacity.duration.300ms x-cloak role="dialog" aria-modal="true" aria-label="The island's display" x-ref="zoomPanel" @keydown="zoomKey($event)" data-islands="zoom">
                        <div class="isp-zoom-screen" :style="zoomBox">
                            <template x-if="carousel.length">
                                <div class="isp-zoom-track" x-ref="track" @scroll.debounce.80ms="zoomScrolled()" data-islands="zoom-track">
                                    <template x-for="(r, i) in carousel" :key="r.chainSlot">
                                        <figure class="isp-zoom-item" :aria-label="(i + 1) + ' of ' + carousel.length" :data-slot="r.slot">
                                            <span class="isp-zoom-img"><img :src="thumb(r)" :alt="nftName(r)" loading="lazy" x-show="thumb(r)"></span>
                                            <figcaption><b x-text="nftName(r)"></b><small x-text="typeLabel(r.type) + ' #' + r.id"></small>
                                                <a :href="nftLink(r)" class="isp-zoom-link">View</a></figcaption>
                                        </figure>
                                    </template>
                                </div>
                            </template>
                            <p class="isp-zoom-empty" x-show="!carousel.length">Nothing on the display yet.<span x-show="isOwner"> Drag NFTs from your collection onto it.</span></p>
                        </div>
                        <div class="isp-zoom-bar">
                            <button type="button" class="isp-zoom-nav" @click="zoomGo(zoomIdx - 1)" :disabled="carousel.length < 2" aria-label="Previous NFT">‹</button>
                            <span class="isp-zoom-count" x-show="carousel.length" x-text="(zoomIdx + 1) + ' / ' + carousel.length" aria-live="polite"></span>
                            <button type="button" class="isp-zoom-nav" @click="zoomGo(zoomIdx + 1)" :disabled="carousel.length < 2" aria-label="Next NFT">›</button>
                            <button type="button" class="isp-zoom-close" x-ref="zoomClose" @click="closeZoom()" data-islands="zoom-close">Close</button>
                        </div>
                    </div>
                    <span class="isp-live-badge" x-show="needsLive && !zoom" x-text="liveBusy ? 'Preview…' : 'Preview'"></span>
                </div>
                <p class="isp-hint" x-show="!zoom">
                    <span x-show="isOwner && selected">Now tap a glowing slot or the display. <button type="button" class="isl-link" @click="selected = null">cancel</button></span>
                    <span x-show="isOwner && !selected">Drag an NFT from your collection onto a scene slot or onto the display, or tap it and then a slot.</span>
                    <span x-show="!isOwner && hasDisplay">Tap the display to browse the NFTs on it.</span>
                </p>
            </div>

            {{-- ================= details + owner tools + collection ================= --}}
            <div class="isp-side">
                <div class="isp-card isp-details">
                    <span class="isl-designation" x-text="island && island.designation"></span>
                    <h1 x-text="island && (island.rawName || 'Unnamed island')" data-islands="name"></h1>
                    <p class="isl-desc" x-text="island && island.description" x-show="island && island.description"></p>
                    <div class="isp-chips">
                        <span class="isl-chip" :class="island ? 'isl-r-' + island.rarity : ''" x-text="island && island.rarity"></span>
                        <span x-show="island && island.fused" x-text="island && ('fused from 5 ' + island.deedRarity + ' TerraKits')"></span>
                        <span x-text="island && (island.residents + ' / ' + island.capacity + ' residents')"></span>
                        <span>Owner <a class="mono" :href="island && '/collection/' + island.owner" x-text="island && short(island.owner)"></a></span>
                    </div>
                    <dl class="isl-summary">
                        <template x-for="(tname, lname) in (island ? island.layerNames : {})" :key="lname"><div><dt x-text="lname === 'fx' ? 'effects' : lname"></dt><dd x-text="tname"></dd></div></template>
                        <div x-show="island && island.tint"><dt>Tint</dt><dd><span class="isl-swatch" :style="island && ('background:' + island.tint)"></span></dd></div>
                        <div x-show="island && island.terraformedAt"><dt>Terraformed</dt><dd x-text="island && island.terraformedAt && new Date(island.terraformedAt).toLocaleDateString()"></dd></div>
                    </dl>
                    <p class="isl-downloads" x-show="island && island.png"><a :href="island && island.png" target="_blank" rel="noopener">PNG</a> · <a :href="island && island.full" target="_blank" rel="noopener">SVG</a></p>
                    <div class="isl-panel isl-new" x-show="isNew" x-cloak><p><b>Welcome to your island!</b> Name it and move your NFTs in: drag them from your collection below onto the scene or the display.</p></div>

                    <div class="isp-tools" x-show="isOwner" x-cloak data-islands="owner">
                        {{-- one "Change …" per layer the contract lists as mutable (API settableLayers ⊂ config mutableLayers) --}}
                        <template x-for="l in changeLayers" :key="l">
                            <button type="button" class="isp-btn" @click="openLayer(l)" :disabled="busy" :data-islands="'change-' + l" x-text="'Change ' + layerNoun(l)"></button>
                        </template>
                        <button type="button" class="isp-btn isp-btn-hl" x-show="canChooseDisplay" @click="openLayer('display')" :disabled="busy" data-islands="choose-display">Choose display <small>(one time)</small></button>
                        <button type="button" class="isp-btn" @click="editing = !editing" data-islands="edit-name">Name &amp; story</button>
                    </div>
                    <div class="isl-panel isl-warn" x-show="isOwner && dapper()" x-cloak><p>Dapper Wallet can't sign island transactions.</p></div>
                    <p class="isl-notice" :class="'isl-n-' + noticeKind" x-show="notice" x-text="notice" aria-live="polite" data-islands="notice"></p>

                    {{-- layer picker: Change sky / Change effect / … / one-time Choose display, with a live preview in the scene --}}
                    <div class="isp-layer" x-show="layerPick" x-cloak x-ref="layerPanel" data-islands="layer-picker">
                        <template x-if="layerPick">
                            <div>
                                <h3 x-text="pickTitle(layerPick)"></h3>
                                <p class="isp-layer-note" x-show="!layerPick.oneTime">You can change the <span x-text="layerNoun(layerPick.layer)"></span> any time. One transaction, free (network fee only).</p>
                                <p class="isp-layer-note isp-layer-warn" x-show="layerPick.oneTime">The display shows your other NFTs: every NFT you place on its display slots plays on this screen. You can choose it <b>once</b>; after that it is fixed.</p>
                                <ul class="isp-layer-grid">
                                    <template x-if="layerRemovable(layerPick.layer)">
                                        <li><button type="button" class="isp-opt" :class="{selected: !layerPick.choice, current: !layerPick.original}" @click="chooseLayer(null)" :aria-pressed="!layerPick.choice" data-template="none">
                                            <span class="isp-opt-art" :class="{'isp-opt-none': !pickThumbs.none}"><img :src="pickThumbs.none" alt="" x-show="pickThumbs.none"></span>
                                            <span x-text="'No ' + layerNoun(layerPick.layer)"></span><small x-show="!layerPick.original">current</small></button></li>
                                    </template>
                                    <template x-for="t in layerOptionsOf(layerPick.layer)" :key="t.id">
                                        <li><button type="button" class="isp-opt" :class="{selected: layerPick.choice === t.id, current: layerPick.original === t.id}" @click="chooseLayer(t.id)" :aria-pressed="layerPick.choice === t.id" :data-template="t.id">
                                            <span class="isp-opt-art"><img :src="pickThumbs[t.id]" alt="" x-show="pickThumbs[t.id]"></span>
                                            <span x-text="t.name"></span><small x-show="layerPick.original === t.id">current</small></button></li>
                                    </template>
                                </ul>
                                <div class="isp-layer-bar">
                                    <button type="button" class="isp-btn" @click="cancelLayer()" :disabled="busy">Cancel</button>
                                    <button type="button" class="isp-btn isp-btn-cta" @click="applyLayer()" :disabled="busy || layerPick.choice === layerPick.original || dirty" data-islands="apply-layer"
                                            x-text="applyLabel(layerPick)"></button>
                                </div>
                                <p class="muted" x-show="dirty">Save or discard your resident changes first.</p>
                            </div>
                        </template>
                    </div>

                    <template x-if="isOwner && editing">
                        <form class="isl-form" @submit.prevent="saveText()">
                            <label>Name <small x-text="nameInput.length + '/' + maxName"></small><input type="text" x-model="nameInput" :maxlength="maxName" data-islands="name-input"></label>
                            <p class="isl-error" x-show="nameError" x-text="nameError"></p>
                            <label>Description <small x-text="descInput.length + '/' + maxDesc"></small><textarea x-model="descInput" :maxlength="maxDesc" rows="4"></textarea></label>
                            <div class="isl-bar"><button type="button" class="isp-btn" @click="editing = false">Cancel</button>
                                <button type="submit" class="isp-btn isp-btn-cta" :disabled="busy || !!nameError" data-islands="save-name">Save (sign)</button></div>
                        </form>
                    </template>
                </div>

                {{-- the owner's collection: desktop = a box under the details; phones = a bottom sheet --}}
                <div class="isp-card isp-collection" x-show="isOwner" x-cloak data-drop="remove" data-islands="collection">
                    <button type="button" class="isp-sheet-handle" @click="sheetOpen = !sheetOpen" :aria-expanded="sheetOpen">
                        <span x-text="selected ? 'Tap a glowing slot or the display' : 'Your collection'"></span><i aria-hidden="true" x-text="sheetOpen ? '▾' : '▴'"></i>
                    </button>
                    <div class="isp-col-head">
                        <h2>Your collection</h2>
                        <p class="isp-free"><span x-text="freeCounts().premium + ' free scene slot' + (freeCounts().premium === 1 ? '' : 's')"></span> · <span x-text="freeCounts().standard + ' free on the display'"></span></p>
                    </div>
                    <div class="isp-tabs" role="tablist">
                        <button type="button" role="tab" :aria-selected="colTab === 'family'" :class="{active: colTab === 'family'}" @click="colTab = 'family'" data-islands="tab-family">Flovatar family <small x-text="walletNfts ? familyNfts.length : ''"></small></button>
                        <button type="button" role="tab" :aria-selected="colTab === 'all'" :class="{active: colTab === 'all'}" @click="colTab = 'all'" data-islands="tab-all">All NFTs <small x-text="walletNfts ? walletNfts.length : ''"></small></button>
                    </div>
                    <p class="isp-col-help" x-text="colTab === 'family' ? (hasDisplay ? 'Flovatars, Sportvatars, Psyche Likees and Flobots live in the scene (or on the display).' : 'Flovatars, Sportvatars, Psyche Likees and Flobots live in the scene.') : (hasDisplay ? 'Any NFT can go on the display.' : 'Any NFT can go in the Other NFTs list.')"></p>
                    <input type="search" class="isp-search" placeholder="Search name, collection or #id" x-model.debounce.150ms="colQuery" aria-label="Search your NFTs" data-islands="col-search">
                    <p x-show="walletStatus === 'loading'">Reading the NFTs in your wallet…</p>
                    <p x-show="walletStatus === 'error'" class="isl-error">We couldn't read your wallet's NFTs. <button type="button" class="isl-link" @click="loadWalletNfts()">Try again</button></p>
                    <p x-show="walletStatus === 'done' && !colItems.length" class="muted">Nothing here<span x-show="colQuery"> for that search</span>.</p>
                    <ul class="isp-col-grid" data-islands="col-grid">
                        <template x-for="n in colItems" :key="nftKey(n)">
                            <li><button type="button" class="isp-nft" :class="{selected: selected === nftKey(n), placed: !!placedAt(n), family: isPremiumType(n.type)}"
                                        @click="clicked(() => selectNft(n))" @pointerdown="dragStart($event, nftKey(n))" @contextmenu.prevent" :data-nft="nftKey(n)" :data-label="n.name || (typeLabel(n.type) + ' #' + n.id)"
                                        :aria-pressed="selected === nftKey(n)" :title="(n.name || typeLabel(n.type) + ' #' + n.id) + (placedAt(n) ? ' (on the island: ' + placedAt(n) + ')' : '')">
                                <span class="isp-nft-img"><img :src="nftThumb(n)" alt="" loading="lazy" draggable="false" x-show="nftThumb(n)"></span>
                                <span class="isp-nft-name" x-text="n.name || (typeLabel(n.type) + ' #' + n.id)"></span>
                                <span class="isp-nft-at" x-show="placedAt(n)" x-text="placedAt(n)"></span>
                            </button></li>
                        </template>
                    </ul>
                </div>
                {{-- residents: read-only for visitors; the owner edits them here and in the scene (pending until Save) --}}
                <div class="isp-card isp-residents" data-islands="residents">
                    <h2>Residents</h2>
                    <h3>In the scene <small x-text="premiumSlots.filter((s) => isOwner ? plan[s.chain] : residentAt(s.chain)).length + ' / ' + premiumSlots.length"></small></h3>
                    <ul class="isp-rlist">
                        <template x-for="s in premiumSlots" :key="s.chain">
                            <li :class="{empty: !(isOwner ? planned(s.chain) : residentAt(s.chain)), pending: isOwner && slotChanged(s.chain)}" :data-slot="s.slot" x-show="isOwner || residentAt(s.chain)">
                                <b class="isp-rslot" x-text="s.slot"></b>
                                <template x-for="r in [isOwner ? planned(s.chain) : residentAt(s.chain)].filter(Boolean)" :key="r.type + r.id">
                                    <span class="isp-rnft" :class="{invalid: !r.pending && !isValid(r)}">
                                        <img :src="thumb(r) || null" alt="" loading="lazy" x-on:error="$el.style.visibility = 'hidden'"><a :href="nftLink(r)" x-text="nftName(r)"></a>
                                        <small class="isl-slot-why" x-show="!r.pending && !isValid(r)" x-text="reasonText(r)"></small>
                                        <button type="button" class="isp-x" x-show="isOwner" @click="removeFrom(s.chain)" :aria-label="'Remove from ' + s.slot">×</button>
                                    </span>
                                </template>
                                <span class="muted" x-show="!(isOwner ? planned(s.chain) : residentAt(s.chain))">free</span>
                            </li>
                        </template>
                    </ul>
                    <div class="isp-dzone" :data-drop="isOwner ? 'display' : null" :class="{target: isOwner && !!(drag || selected) && targetActive('display'), hover: dropHover === 'display'}">
                        <h3><span x-text="hasDisplay ? 'On the display' : 'Other NFTs'"></span> <small x-text="displayList(isOwner).length + ' / ' + standardSlots.length"></small>
                            <button type="button" class="isl-link" x-show="hasDisplay && carousel.length" @click="openZoom()">open</button></h3>
                        {{-- islands minted before the display layer (and no display migration): plain list, no screen, no zoom --}}
                        <p class="muted" x-show="!hasDisplay && !(island && island.canChooseDisplay)" data-islands="no-display">This island has no display: these NFTs are listed here, not shown in the scene.</p>
                        <p class="muted" x-show="!hasDisplay && island && island.canChooseDisplay">No display yet<span x-show="isOwner">: choose one above (one time)</span>. NFTs placed here show once it has one.</p>
                        <ol class="isp-rlist isp-dlist" data-islands="display-list">
                            <template x-for="(r, i) in displayList(isOwner)" :key="r.chainSlot">
                                <li :class="{pending: isOwner && slotChanged(r.chainSlot)}" :data-slot="r.slot">
                                    <span class="isp-rnft" :class="{invalid: !r.pending && !isValid(r)}">
                                        <img :src="thumb(r) || null" alt="" loading="lazy" x-on:error="$el.style.visibility = 'hidden'"><a :href="nftLink(r)" x-text="nftName(r)"></a>
                                        <small class="isp-onscreen" x-show="i === 0 && hasDisplay">on screen</small>
                                        <small class="isl-slot-why" x-show="!r.pending && !isValid(r)" x-text="reasonText(r)"></small>
                                        <button type="button" class="isl-link" x-show="isOwner && i > 0" @click="showFirst(r.chainSlot)">show first</button>
                                        <button type="button" class="isp-x" x-show="isOwner" @click="removeFrom(r.chainSlot)" :aria-label="'Remove ' + nftName(r) + ' from the display'">×</button>
                                    </span>
                                </li>
                            </template>
                        </ol>
                        <button type="button" class="isp-btn isp-btn-sm" x-show="isOwner && selected && targetActive('display')" @click="place(selected, 'display')" data-islands="to-display">Put it on the display</button>
                    </div>
                    <p class="muted" x-show="!displayList(isOwner).length" x-text="hasDisplay ? 'Nothing on the display yet.' : 'No other NFTs yet.'"></p>
                    <button type="button" class="isl-link" x-show="isOwner && invalidCount" @click="clearInvalidPlan()" x-text="'Remove ' + invalidCount + ' moved-out'"></button>
                </div>

            </div>
        </div>
    </div>

    {{-- pending changes: one Save = one wallet approval --}}
    <div class="isp-pending" x-show="isOwner && dirty" x-cloak x-transition data-islands="pending-bar">
        <span><b x-text="changeCount"></b> <span x-text="changeCount === 1 ? 'change' : 'changes'"></span> not saved yet
            <small x-show="ops.sets.length > 25" x-text="'(' + Math.ceil(ops.sets.length / 25) + ' approvals: max 25 NFTs per transaction)'"></small></span>
        <button type="button" class="isp-btn" @click="discard()" :disabled="busy" data-islands="discard">Discard</button>
        <button type="button" class="isp-btn isp-btn-cta" @click="save()" :disabled="busy" data-islands="save" x-text="busy ? 'Saving…' : 'Save (one approval)'"></button>
    </div>

    {{-- drag ghost --}}
    <div class="isp-ghost" x-show="drag" x-cloak :style="drag ? 'transform:translate(' + (drag.x - 34) + 'px,' + (drag.y - 34) + 'px)' : ''" aria-hidden="true">
        <img :src="drag && drag.img" alt="" x-show="drag && drag.img"><span x-text="drag && drag.label"></span>
    </div>
</section>
</x-guest-layout>
