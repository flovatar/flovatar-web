<x-guest-layout>
{{-- Asteroid Island page (ISLANDS_MODE; KB notes/islands-build.md). Island-level data only (edge-cacheable); owner tools and
     live resident checks run in the browser (resources/js/app/islands.js islandPage). --}}
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
<section class="isl isl-page" x-data="islandPage({{ (int) $id }})" data-islands="island" data-island-id="{{ (int) $id }}">
    <div class="container">
        <p class="isl-crumbs"><a href="/islands">Asteroid belt</a> › <span>{{ \App\Support\Islands::designation((int) $id) }}</span></p>

        <div class="isl-panel" x-show="status === 'loading'"><p>Loading the island…</p></div>
        <div class="isl-panel isl-error" x-show="status === 'missing'" x-cloak><p>This island isn't in the belt yet. If you just terraformed it, give the blockchain a minute and reload.</p></div>

        <div class="isl-island" x-show="status === 'ready'" x-cloak>
            <div class="isl-scene">
                <div class="isl-scene-frame" :class="island ? 'isl-r-' + island.rarity : ''">
                    <img :src="island && island.full" :alt="island && island.name" data-islands="scene">
                </div>
                {{-- premium slots (in the scene) --}}
                <div class="isl-premium" data-islands="premium">
                    <template x-for="s in premiumSlots" :key="s.chain">
                        <div class="isl-slot" :class="residentAt(s.chain) ? (isValid(residentAt(s.chain)) ? 'filled' : 'filled invalid') : 'empty'" :data-slot="s.slot">
                            <span class="isl-slot-id" x-text="s.slot"></span>
                            <template x-if="residentAt(s.chain)">
                                <span class="isl-slot-nft">
                                    <img loading="lazy" :src="thumb(residentAt(s.chain))" alt="">
                                    <span x-text="familyLabel(residentAt(s.chain)) + ' #' + residentAt(s.chain).id"></span>
                                </span>
                            </template>
                            <template x-if="!residentAt(s.chain)"><span class="muted">empty</span></template>
                            <span class="isl-slot-why" x-show="residentAt(s.chain) && !isValid(residentAt(s.chain))" x-text="residentAt(s.chain) ? reasonText(residentAt(s.chain)) : ''"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="isl-info">
                <span class="isl-designation" x-text="island && island.designation"></span>
                <h1 x-text="island && (island.rawName || 'Unnamed island')" data-islands="name"></h1>
                <p class="isl-desc" x-text="island && island.description" x-show="island && island.description"></p>
                <dl class="isl-summary">
                    <dt>Rarity</dt><dd><span class="isl-chip" :class="island ? 'isl-r-' + island.rarity : ''" x-text="island && island.rarity"></span> <span x-show="island && island.fused">fused from 5 <span x-text="island && island.deedRarity"></span> deeds</span></dd>
                    <dt>Residents</dt><dd x-text="island && (island.residents + ' / ' + island.capacity + ' (' + island.premiumSlots + ' in the scene)')"></dd>
                    <dt>Owner</dt><dd><a class="mono" :href="island && '/collection/' + island.owner" x-text="island && short(island.owner)"></a></dd>
                    <template x-for="(tname, lname) in (island ? island.layerNames : {})" :key="lname"><div><dt x-text="lname"></dt><dd x-text="tname"></dd></div></template>
                    <dt x-show="island && island.tint">Tint</dt><dd x-show="island && island.tint"><span class="isl-swatch" :style="island && ('background:' + island.tint)"></span></dd>
                    <dt x-show="island && island.terraformedAt">Terraformed</dt><dd x-show="island && island.terraformedAt" x-text="island && island.terraformedAt && new Date(island.terraformedAt).toLocaleDateString()"></dd>
                </dl>
                <p class="isl-downloads" x-show="island && island.png"><a :href="island && island.png" target="_blank" rel="noopener">PNG</a> · <a :href="island && island.full" target="_blank" rel="noopener">SVG</a></p>
                <div class="isl-panel isl-new" x-show="isNew" x-cloak><p><b>Welcome to your island!</b> Name it and move your Flovatars in below.</p></div>
            </div>
        </div>

        {{-- standard residents carousel --}}
        <div class="isl-residents" x-show="status === 'ready'" x-cloak data-islands="carousel">
            <div class="isl-residents-head">
                <h2>Residents <small x-text="standardResidents.length + ' / ' + standardSlots.length + ' standard slots'"></small></h2>
                <span class="isl-carousel-nav" x-show="standardResidents.length > 3">
                    <button type="button" @click="scrollCarousel(-1)" aria-label="Previous">‹</button>
                    <button type="button" @click="scrollCarousel(1)" aria-label="Next">›</button>
                </span>
            </div>
            <p class="muted" x-show="!standardResidents.length">No standard residents yet. Any NFT can live here.</p>
            <div class="isl-carousel" x-ref="carousel" x-show="standardResidents.length">
                <template x-for="r in standardResidents" :key="r.chainSlot">
                    <figure class="isl-res" :class="isValid(r) ? '' : 'invalid'" :data-slot="r.slot">
                        <span class="isl-res-img"><img loading="lazy" :src="thumb(r)" :alt="nftName(r)" x-show="thumb(r)"></span>
                        <figcaption><b x-text="nftName(r)"></b><small x-text="typeLabel(r.type) + ' · ' + r.slot"></small><small class="isl-slot-why" x-show="!isValid(r)" x-text="reasonText(r)"></small></figcaption>
                    </figure>
                </template>
            </div>
        </div>

        {{-- owner tools --}}
        <div class="isl-owner" x-show="status === 'ready' && isOwner" x-cloak data-islands="owner">
            <h2>Manage your island</h2>
            <p class="isl-notice" :class="'isl-n-' + noticeKind" x-show="notice" x-text="notice" aria-live="polite" data-islands="notice"></p>
            <div class="isl-panel isl-warn" x-show="dapper()"><p>Dapper Wallet can't sign island transactions.</p></div>

            <div class="isl-panel">
                <h3>Name and story</h3>
                <template x-if="!editing"><button type="button" class="btn btn-sm" @click="editing = true" data-islands="edit-name"><span class="inner">Edit name &amp; description</span></button></template>
                <template x-if="editing">
                    <form class="isl-form" @submit.prevent="saveText()">
                        <label>Name <small x-text="nameInput.length + '/' + maxName"></small><input type="text" x-model="nameInput" :maxlength="maxName" data-islands="name-input"></label>
                        <p class="isl-error" x-show="nameError" x-text="nameError"></p>
                        <label>Description <small x-text="descInput.length + '/' + maxDesc"></small><textarea x-model="descInput" :maxlength="maxDesc" rows="4"></textarea></label>
                        <div class="isl-bar"><button type="button" class="btn btn-sm" @click="editing = false"><span class="inner">Cancel</span></button>
                            <button type="submit" class="btn btn-pink" :disabled="busy || !!nameError" data-islands="save-name"><span class="inner">Save (sign)</span></button></div>
                    </form>
                </template>
            </div>

            <div class="isl-panel">
                <h3>Residents</h3>
                <p class="muted">Residents are references: the NFTs stay in your wallet. Scene slots (P) take Flovatars, Flobots, Sportvatars and Psyche Likees; standard slots (S) take any NFT and show in the carousel.</p>
                <div class="isl-bar">
                    <button type="button" class="btn btn-sm" @click="addStandard()" :disabled="busy" data-islands="add-standard"><span class="inner">Add standard residents</span></button>
                    <button type="button" class="btn btn-sm" x-show="invalidCount" @click="clearInvalid()" :disabled="busy"><span class="inner" x-text="'Clear ' + invalidCount + ' moved-out'"></span></button>
                </div>
                <table class="isl-slots" data-islands="slots">
                    <thead><tr><th>Slot</th><th>Resident</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="s in slots.filter((x) => x.premium || residentAt(x.chain))" :key="s.chain">
                            <tr :class="residentAt(s.chain) && !isValid(residentAt(s.chain)) ? 'invalid' : ''" :data-slot="s.slot">
                                <td><b x-text="s.slot"></b> <small x-text="s.premium ? 'scene' : 'carousel'"></small></td>
                                <td>
                                    <template x-if="residentAt(s.chain)"><span><span x-text="nftName(residentAt(s.chain))"></span> <small class="isl-slot-why" x-text="reasonText(residentAt(s.chain))"></small></span></template>
                                    <template x-if="!residentAt(s.chain)"><span class="muted">empty</span></template>
                                </td>
                                <td class="isl-actions">
                                    <button type="button" class="isl-link" @click="setSlot(s)" :disabled="busy" x-text="residentAt(s.chain) ? 'replace' : 'set'"></button>
                                    <template x-if="residentAt(s.chain)"><span>
                                        <button type="button" class="isl-link" @click="startMove(residentAt(s.chain))" :disabled="busy" x-text="moveFrom === s.chain ? 'cancel move' : 'move'"></button>
                                        <button type="button" class="isl-link" @click="clearSlot(s.chain)" :disabled="busy">clear</button>
                                    </span></template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="isl-move" x-show="moveFrom" x-cloak>
                    <span x-text="'Move ' + slotLabel(moveFrom) + ' to:'"></span>
                    <select @change="moveTo($event.target.value)"><option value="">choose a slot</option>
                        <template x-for="s in moveTargets()" :key="s.chain"><option :value="s.chain" x-text="s.slot + (residentAt(s.chain) ? ' (swap)' : '')"></option></template>
                    </select>
                </div>
            </div>
        </div>

        {{-- NFT picker --}}
        <div class="isl-modal" x-show="picker" x-cloak @keydown.escape.window="closePicker()" data-islands="picker">
            <div class="isl-modal-box" role="dialog" aria-modal="true" aria-label="Pick residents">
                <header><h3 x-text="picker && picker.premium ? 'Pick a Flovatar-family NFT for ' + slotLabel(picker.slots[0]) : 'Pick NFTs for the standard slots'"></h3>
                    <button type="button" class="isl-close" @click="closePicker()" aria-label="Close">×</button></header>
                <input type="search" placeholder="Filter by name, collection or id" x-model="pickerFilter" class="isl-filter">
                <p x-show="walletStatus === 'loading'">Reading the NFTs in your wallet…</p>
                <p x-show="walletStatus === 'error'" class="isl-error">We couldn't read your wallet's NFTs.</p>
                <p x-show="walletStatus === 'done' && !pickerItems.length" class="muted">Nothing to place here<span x-show="picker && picker.premium"> (only Flovatars, Flobots, Sportvatars and Psyche Likees fit scene slots)</span>.</p>
                <div class="isl-picker-grid">
                    <template x-for="n in pickerItems" :key="pickerKey(n)">
                        <button type="button" class="isl-pick" :class="pickerSel.includes(pickerKey(n)) ? 'selected' : ''" @click="pickerToggle(n)" :data-nft="pickerKey(n)">
                            <span class="isl-res-img"><img loading="lazy" :src="nftThumb(n)" alt="" x-show="nftThumb(n)"></span>
                            <b x-text="n.name || (typeLabel(n.type) + ' #' + n.id)"></b><small x-text="typeLabel(n.type) + ' #' + n.id"></small>
                        </button>
                    </template>
                </div>
                <footer class="isl-bar">
                    <span x-text="pickerSel.length + ' selected' + (picker && picker.multi ? ' (max ' + picker.slots.length + ')' : '')"></span>
                    <button type="button" class="btn btn-pink" :disabled="!pickerSel.length || busy" @click="confirmPicker()" data-islands="picker-confirm"><span class="inner">Place (sign)</span></button>
                </footer>
            </div>
        </div>
    </div>
</section>
</x-guest-layout>
