<x-guest-layout>
{{-- Asteroid Islands builder v2 (ISLANDS_MODE; KB notes/islands-build.md §E). Layout and interaction follow the Sportvatar
     builder: category tabs (Deeds, scene layers, Tint) with thumbnail grids, an info card, a big live preview with a tool
     bar, then review + sign in a dialog. Static shell, no user data: the wallet is read and the terraform transaction is
     signed in the browser (resources/js/app/islands.js islandsBuilder). Redeem only: 1 land deed -> island of that
     rarity, 5 same-rarity deeds -> next tier. The deeds are burned. No fees. No size figures for users. --}}
<section class="isb" x-data="islandsBuilder" data-islands="builder" @keydown.escape.window="onEscape()">
    <header class="isb-head">
        <div class="isb-title">
            <span class="isb-eyebrow">TerraKit · Kaax Asteroid Belt</span>
            <h1>Terraform your island</h1>
        </div>
        <ol class="isb-steps" aria-label="Steps">
            <li :class="stepState.deeds"><button type="button" @click="setTab('deeds')"><span class="n">1</span> Deeds</button></li>
            <li :class="stepState.design"><button type="button" @click="setTab('base')"><span class="n">2</span> Design</button></li>
            <li :class="stepState.terraform"><button type="button" @click="ctaClick()"><span class="n">3</span> Terraform</button></li>
        </ol>
    </header>

    <div class="isb-panel isb-fail" x-show="cfgError" x-cloak><p>The island service is not reachable right now. Try again in a minute.</p></div>

    <div class="isb-wrap" x-show="cfg" x-cloak :class="'isb-r-' + rarity">
        {{-- ============ categories (desktop: vertical column; mobile: horizontal bar under the parts) ============ --}}
        <nav class="isb-cats" aria-label="Builder parts">
            <ul role="tablist" aria-orientation="vertical">
                <template x-for="t in tabs" :key="t.key">
                    <li>
                        <button type="button" role="tab" class="isb-cat" :class="{active: tab === t.key, 'is-deeds': t.key === 'deeds'}" :aria-selected="tab === t.key"
                                @click="setTab(t.key)" :data-tab="t.key">
                            <span class="isb-cat-label" x-text="t.label"></span>
                            {{-- category icon (public/images/islands/tab-*.svg): greys when idle, brand colours on the active tab --}}
                            <span class="isb-cat-art" :class="{'is-empty': tabEmpty(t.key)}">
                                <img class="isb-cat-icon" :src="tabIcon(t.key, false)" alt="" width="84" height="76" decoding="async">
                                <img class="isb-cat-icon isb-cat-icon-on" :src="tabIcon(t.key, true)" alt="" width="84" height="76" decoding="async">
                            </span>
                            <span class="isb-cat-badge" x-show="tabBadge(t.key)" x-text="tabBadge(t.key)"></span>
                            <template x-if="t.key === 'tint' && tintValue"><span class="isb-cat-dot" :style="{background: tintValue}" aria-hidden="true"></span></template>
                        </button>
                    </li>
                </template>
            </ul>
        </nav>

        {{-- ============ info card + option grid ============ --}}
        <div class="isb-parts">
            <div class="isb-info" :class="{'is-layer': isLayerTab()}" data-islands="info">
                <strong class="isb-info-cat" x-text="tab === 'deeds' ? 'Land deeds' : (tab === 'tint' ? 'Tint' : layerLabel(tab))"></strong>

                {{-- deeds --}}
                <template x-if="tab === 'deeds'">
                    <div class="isb-info-body">
                        <div class="isb-mode" role="radiogroup" aria-label="How many deeds">
                            <button type="button" :class="mode === 'single' ? 'active' : ''" @click="setMode('single')" role="radio" :aria-checked="mode === 'single'" data-islands="mode-single">
                                <b>1 deed</b><span>Island of the same rarity</span></button>
                            <button type="button" :class="mode === 'fuse' ? 'active' : ''" @click="setMode('fuse')" role="radio" :aria-checked="mode === 'fuse'" data-islands="mode-fuse">
                                <b>Fuse 5</b><span>Same rarity → next tier</span></button>
                        </div>
                        <p class="isb-info-hint" x-text="selectionHint" aria-live="polite"></p>
                        <div class="isb-preview-rarity" x-show="!fromDeeds">
                            <span>Just looking? Preview</span>
                            <template x-for="r in rarities" :key="r">
                                <button type="button" class="isb-chip" :class="['isb-chip-' + r, previewRarity === r ? 'active' : '']" @click="setPreviewRarity(r)" x-text="r" :aria-pressed="previewRarity === r"></button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- a scene layer --}}
                <template x-if="isLayerTab()">
                    <div class="isb-info-body isb-info-part">
                        <div class="isb-info-art">
                            <img :src="thumb(tab, layers[tab] || null)" alt="" x-show="thumb(tab, layers[tab] || null)">
                        </div>
                        <dl class="isb-info-data">
                            <div><dt>Part</dt><dd x-text="selectedInfo ? selectedInfo.name : 'None'"></dd></div>
                            <div><dt>Rarity</dt><dd><span class="isb-rar" :class="'isb-rar-' + rarity" x-text="rarity"></span></dd></div>
                            <div x-show="selectedInfo"><dt>Used by</dt><dd x-text="usedBy(selectedInfo)"></dd></div>
                            <div class="isb-info-tags">
                                <span class="isb-tag" x-show="isRequired(tab)">Required</span>
                                <span class="isb-tag isb-tag-anim" x-show="isAnimated(selectedInfo)">Animated</span>
                                <span class="isb-tag isb-tag-any" x-show="isShared(tab)">Any rarity</span>
                            </div>
                        </dl>
                        {{-- round 4: sky = changeable later, effects = permanent, display = shows the owner's other NFTs --}}
                        <p class="isb-info-note" :class="'isb-note-' + tab" x-show="layerNote(tab)" x-text="layerNote(tab)" data-islands="layer-note"></p>
                    </div>
                </template>

                {{-- tint --}}
                <template x-if="tab === 'tint'">
                    <div class="isb-info-body">
                        <p class="isb-info-hint">Epic and legendary islands can take a tint: it recolours the accent details of the art. Optional.</p>
                        <p class="isb-info-now">Now: <span class="isb-swatch" :style="tintValue ? 'background:' + tintValue : ''" :class="tintValue ? '' : 'none'"></span>
                            <b x-text="tintValue ? tintValue.toUpperCase() : 'No tint'"></b></p>
                    </div>
                </template>
            </div>

            <div class="isb-grid-wrap">
                {{-- phones: the info card is hidden on part tabs, so the layer note sits above the part strip --}}
                <p class="isb-info-note isb-mnote" :class="'isb-note-' + tab" x-show="isLayerTab() && layerNote(tab)" x-text="isLayerTab() ? layerNote(tab) : ''" x-cloak></p>
                <div class="isb-grid" x-ref="grid" data-islands="grid">
                    {{-- deeds --}}
                    <template x-if="tab === 'deeds'">
                        <div class="isb-deeds">
                            <template x-if="!user">
                                <div class="isb-empty" data-islands="connect">
                                    <p>Connect the wallet that holds your TerraKit land deeds. You can design an island without one.</p>
                                    <button type="button" class="isb-btn isb-btn-cta" @click="connect()"><span>Connect wallet</span></button>
                                    <p class="muted">Not sure your wallet still works? <a href="/terrakit/check">Check your TerraKit</a>.</p>
                                </div>
                            </template>
                            <template x-if="user && dapper()">
                                <div class="isb-empty isb-warn" data-islands="dapper"><p><b>Dapper Wallet can't sign this transaction.</b> Redeeming land deeds needs a wallet that signs the island transaction itself, such as Flow Wallet.</p></div>
                            </template>
                            <template x-if="user && status === 'loading'"><p class="isb-empty">Reading your wallet on the Flow blockchain…</p></template>
                            <template x-if="user && status === 'error'">
                                <div class="isb-empty isb-warn"><p x-text="error"></p><button type="button" class="isb-btn" @click="loadInventory()"><span>Try again</span></button></div>
                            </template>
                            <template x-if="user && status === 'ready'">
                                <div>
                                    <p class="isb-wallet">Wallet <span class="mono" x-text="short(user.addr)"></span> · <span x-text="deedCount + ' land deed' + (deedCount === 1 ? '' : 's')"></span>
                                        <button type="button" class="isb-link" @click="loadInventory()">refresh</button></p>
                                    <template x-if="packs.length">
                                        <div class="isb-group" data-islands="packs">
                                            <h3>Unopened TerraKit packs <small>1 land deed + 1 FUB voucher + 1 Flobot kit each</small></h3>
                                            <ul class="isb-cards">
                                                <template x-for="p in packs" :key="p">
                                                    <li><div class="isb-card isb-pack">
                                                        <span class="isb-pack-art" aria-hidden="true"><svg viewBox="0 0 48 48"><use href="#isb-i-pack"></use></svg></span>
                                                        <span class="isb-card-name" x-text="'Pack #' + p"></span>
                                                        <button type="button" class="isb-btn isb-btn-sm" :disabled="openingPack !== null" @click="openPack(p)" :data-pack="p">
                                                            <span x-text="openingPack === p ? 'Opening…' : 'Open'"></span></button>
                                                    </div></li>
                                                </template>
                                            </ul>
                                        </div>
                                    </template>
                                    <p class="isb-notice" x-show="packMessage" x-text="packMessage" aria-live="polite"></p>
                                    <template x-if="!hasDeeds">
                                        <div class="isb-empty"><p>No TerraKit land deeds in this wallet<span x-show="packs.length"> yet: open a pack above</span>. You can still design an island and share the link.</p></div>
                                    </template>
                                    <template x-for="r in rarities" :key="r">
                                        <div class="isb-group" x-show="deedsOf(r).length">
                                            <h3><span class="isb-rar" :class="'isb-rar-' + r" x-text="r"></span>
                                                <span x-text="deedsOf(r).length + ' deed' + (deedsOf(r).length === 1 ? '' : 's')"></span>
                                                <button type="button" class="isb-link" x-show="canFuse(r)" @click="pickFive(r)" x-text="'fuse 5 → ' + (r === 'common' ? 'epic' : 'legendary')"></button></h3>
                                            <ul class="isb-cards">
                                                <template x-for="d in deedsOf(r)" :key="d.id">
                                                    <li><button type="button" class="isb-card isb-deed" :class="{selected: isSelected(d.id), disabled: deedDisabled(d)}"
                                                                :disabled="deedDisabled(d)" @click="toggleDeed(d)" :data-deed="d.id" :aria-pressed="isSelected(d.id)"
                                                                :title="deedDisabled(d) ? 'Legendary deeds can\'t be fused' : ''">
                                                        <span class="isb-card-art"><img loading="lazy" :src="deedImage(d)" alt=""></span>
                                                        <span class="isb-card-name" x-text="'Deed #' + d.id"></span>
                                                        <span class="isb-check" x-show="isSelected(d.id)" x-text="mode === 'fuse' ? pickIndex(d.id) : '✓'"></span>
                                                    </button></li>
                                                </template>
                                            </ul>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- scene layer options --}}
                    <template x-if="isLayerTab()">
                        <ul class="isb-cards isb-options" :data-layer="tab">
                            <template x-if="!isRequired(tab)">
                                <li><button type="button" class="isb-card isb-none" :class="{selected: !layers[tab]}" @click="pick(tab, null)" :aria-pressed="!layers[tab]" data-template="none">
                                    <span class="isb-card-art"><img class="isb-none-icon" :src="tabIcon(tab, false)" alt=""></span>
                                    <span class="isb-card-name">None</span>
                                    <span class="isb-check" x-show="!layers[tab]">✓</span>
                                </button></li>
                            </template>
                            <template x-for="t in optionsFor(tab)" :key="t.id">
                                <li><button type="button" class="isb-card" :class="{selected: layers[tab] === t.id}" @click="pick(tab, t.id)" :data-template="t.id" :aria-pressed="layers[tab] === t.id" :title="t.description || t.name">
                                    <span class="isb-card-art"><img :src="thumb(tab, t.id)" alt="" x-show="thumb(tab, t.id)"></span>
                                    <span class="isb-card-name" x-text="t.name"></span>
                                    <span class="isb-card-rar" :class="'isb-rar-' + rarityTag(t)" x-show="rarityTag(t)" x-text="rarityTag(t)"></span>
                                    <span class="isb-anim" x-show="isAnimated(t)" title="Animated" aria-label="Animated">●</span>
                                    <span class="isb-check" x-show="layers[tab] === t.id">✓</span>
                                    @if(config('islands.show_size_meter'))<span class="isb-size" x-text="kb(t.svgLength + t.svgFrontLength)"></span>@endif
                                </button></li>
                            </template>
                            <li x-show="!optionsFor(tab).length" class="isb-empty"><p>No <span x-text="rarity"></span> art for this part yet.</p></li>
                        </ul>
                    </template>

                    {{-- tint swatches --}}
                    <template x-if="tab === 'tint'">
                        <ul class="isb-cards isb-tints">
                            <li><button type="button" class="isb-card isb-tint" :class="{selected: !tintValue}" @click="setTint(null)" :aria-pressed="!tintValue">
                                <span class="isb-tint-dot none"></span><span class="isb-card-name">No tint</span></button></li>
                            <template x-for="c in tints" :key="c">
                                <li><button type="button" class="isb-card isb-tint" :class="{selected: tintValue === c.toLowerCase()}" @click="setTint(c)" :aria-pressed="tintValue === c.toLowerCase()" :data-tint="c">
                                    <span class="isb-tint-dot" :style="'background:' + c"></span><span class="isb-card-name" x-text="c"></span></button></li>
                            </template>
                            <li><label class="isb-card isb-tint isb-tint-custom" :class="{selected: tintValue && !tints.map((x) => x.toLowerCase()).includes(tintValue)}">
                                <input type="color" :value="tint" @input="setTint($event.target.value)" aria-label="Custom tint colour">
                                <span class="isb-card-name">Custom</span></label></li>
                        </ul>
                    </template>
                </div>
            </div>
        </div>

        {{-- ============ machine: preview + tools ============ --}}
        <div class="isb-machine">
            <div class="isb-stage" :class="{busy: previewBusy, 'no-sky': !layers.sky}">
                <img class="isb-stage-bg" :src="preview" alt="" aria-hidden="true" x-show="preview">
                <img :src="preview" alt="Island preview" x-show="preview" data-islands="preview">
                <p class="isb-toast" x-show="notice" x-transition.opacity x-text="notice" aria-live="polite"></p>
                <div class="isb-rarity-badge" :class="'isb-rar-' + rarity" data-islands="rarity-badge">
                    <span class="t" x-text="fromDeeds ? 'Island rarity' : 'Preview'"></span>
                    <span class="r" x-text="rarity"></span>
                </div>
                <div class="isb-burn" :class="{ready: deedsReady, empty: !fromDeeds}" data-islands="burn-badge" aria-hidden="true">
                    <div class="isb-burn-core">
                        <svg viewBox="0 0 48 48"><use href="#isb-i-flame"></use></svg>
                        <b x-text="fromDeeds ? '×' + selected.length : '?'"></b>
                    </div>
                    <svg class="isb-burn-text" viewBox="0 0 100 100">
                        <defs><path id="isb-circle" d="M 50,50 m -38,0 a 38,38 0 1,1 76,0 a 38,38 0 1,1 -76,0"/></defs>
                        <text><textPath href="#isb-circle" x-text="burnText"></textPath></text>
                    </svg>
                </div>
            </div>
            <div class="isb-tools">
                <button type="button" class="isb-btn" @click="randomize()" data-islands="randomize"><span>Randomize</span></button>
                <button type="button" class="isb-btn isb-btn-icon" @click="savePng()" aria-label="Save a PNG of the preview" title="Save PNG"><svg viewBox="0 0 48 48" aria-hidden="true"><use href="#isb-i-camera"></use></svg></button>
                <div class="isb-cta-wrap">
                    <div class="isb-tip" x-show="cta.hint" x-cloak role="status"><span x-text="cta.hint"></span></div>
                    <button type="button" class="isb-btn isb-btn-cta" :class="{disabled: cta.disabled}" :aria-disabled="!!cta.disabled" @click="ctaClick()" data-islands="cta"><span x-text="cta.label"></span></button>
                </div>
            </div>
            @if(config('islands.show_size_meter'))
                {{-- internal art-production aid only (ISLANDS_SHOW_SIZE_METER); never rendered for users --}}
                <div class="isb-meter" :class="budgetPct >= 100 ? 'over' : (budgetPct > 85 ? 'near' : '')" data-islands="budget">
                    <div class="isb-meter-bar"><span :style="'width:' + budgetPct + '%'"></span></div>
                    <span x-text="'Internal: ' + kb(bytes) + ' of ' + kb(budget)"></span>
                </div>
            @endif
        </div>
    </div>

    {{-- ============ review + sign dialog ============ --}}
    <div class="isb-modal" x-show="review" x-cloak @click.self="closeReview()" data-islands="review">
        <div class="isb-modal-card" role="dialog" aria-modal="true" aria-labelledby="isb-review-title" x-ref="reviewCard" @keydown.tab="trapFocus($event)">
            <button type="button" class="isb-modal-close" @click="closeReview()" x-ref="reviewClose" aria-label="Close" :disabled="step === 'signing'">×</button>
            <template x-if="snap">
                <div class="isb-modal-grid">
                    <div class="isb-modal-art" :class="'isb-r-' + snap.rarity"><img :src="snap.url" alt="Your island"></div>

                    {{-- review / signing --}}
                    <div class="isb-modal-body" x-show="step !== 'done'">
                        <h2 id="isb-review-title">Terraform this island?</h2>
                        <dl class="isb-summary">
                            <div><dt>Island</dt><dd><span class="isb-rar" :class="'isb-rar-' + snap.rarity" x-text="snap.rarity"></span>
                                <span x-text="snap.fused ? 'fused from 5 ' + snap.deedRarity + ' deeds' : 'from 1 ' + snap.deedRarity + ' deed'"></span></dd></div>
                            <template x-for="p in snap.parts" :key="p.layer"><div :data-part="p.key"><dt x-text="p.layer"></dt><dd><span x-text="p.name"></span>
                                <span class="isb-part-note" :class="p.note === 'permanent' ? 'perm' : 'free'" x-show="p.note" x-text="p.note"></span></dd></div></template>
                            <div x-show="snap.tint"><dt>Tint</dt><dd><span class="isb-swatch" :style="'background:' + snap.tint"></span> <span x-text="snap.tint && snap.tint.toUpperCase()"></span></dd></div>
                            <div><dt>Cost</dt><dd>Free (network fee only)</dd></div>
                        </dl>
                        <div class="isb-burnbox" data-islands="burn-warning">
                            <p class="isb-burnbox-title"><svg viewBox="0 0 48 48" aria-hidden="true"><use href="#isb-i-flame"></use></svg>
                                <span x-text="snap.deeds.length === 1 ? 'This land deed will be BURNED' : 'These ' + snap.deeds.length + ' land deeds will be BURNED'"></span></p>
                            <ul class="isb-burn-deeds">
                                <template x-for="d in snap.deeds" :key="d.id"><li><img :src="'/api/image/template/' + d.templateId" alt=""><span x-text="'#' + d.id"></span></li></template>
                            </ul>
                            <p><span x-text="snap.deeds.length === 1 ? 'It is' : 'They are'"></span> destroyed for good when the island is created. This can't be undone. <span x-show="snap.mutable && snap.mutable.length">You can change the sky later; the other parts, the display and the effects are permanent.</span><span x-show="!snap.mutable || !snap.mutable.length">The island's scene can't be changed later.</span></p>
                            <label><input type="checkbox" x-model="understood" data-islands="understood" :disabled="step === 'signing'">
                                <span>I understand: burn <span x-text="snap.deeds.length"></span> land deed<span x-show="snap.deeds.length > 1">s</span> and create this island.</span></label>
                        </div>
                        <p class="isb-chain" :class="chainCheck === 'ok' ? 'ok' : (chainCheck && chainCheck !== 'checking' ? 'err' : '')" data-islands="chain-check">
                            <span x-show="chainCheck === 'checking'">Checking the scene with the island contract…</span>
                            <span x-show="chainCheck === 'ok'">✓ The island contract accepts this scene.</span>
                            <span x-show="chainCheck && chainCheck !== 'checking' && chainCheck !== 'ok'" x-text="chainCheck"></span>
                        </p>
                        <p class="isb-error" x-show="txError" x-text="txError" data-islands="tx-error"></p>
                        <p class="isb-notice" x-show="txStatus" x-text="txStatus" aria-live="polite" data-islands="tx-status"></p>
                        <p x-show="txId && step === 'signing'" class="muted">Transaction <a :href="explorer(txId)" target="_blank" rel="noopener" class="mono" x-text="short(txId)"></a></p>
                        <div class="isb-modal-actions">
                            <button type="button" class="isb-btn" @click="closeReview()" :disabled="step === 'signing'"><span>Back to the builder</span></button>
                            <button type="button" class="isb-btn isb-btn-burn" :class="{disabled: !canSign}" :aria-disabled="!canSign" @click="terraform()" data-islands="sign">
                                <span x-text="step === 'signing' ? 'Terraforming…' : 'Burn & terraform'"></span></button>
                        </div>
                    </div>

                    {{-- done (Sportvatar's "Congratulations" message) --}}
                    <div class="isb-modal-body isb-done" x-show="step === 'done'" data-islands="done">
                        <h2>Congratulations</h2>
                        <p>Your island is in orbit<span x-show="islandId">: <b class="isb-designation" x-text="designation(islandId)"></b></span>. Welcome to the Kaax Asteroid Belt.</p>
                        <p class="muted" x-show="txId">Transaction <a :href="explorer(txId)" target="_blank" rel="noopener" class="mono" x-text="short(txId)"></a></p>
                        <div class="isb-modal-actions">
                            <a x-show="islandId" :href="'/islands/' + islandId + '?new=1' + (user ? '&owner=' + user.addr : '')" class="isb-btn isb-btn-cta" data-islands="visit"><span>Go check it out</span></a>
                            <button type="button" class="isb-btn" @click="startOver()"><span>Terraform another</span></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- icons (outline, currentColor) --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="isb-i-pack" viewBox="0 0 48 48"><path d="M10 14l14-8 14 8v20l-14 8-14-8z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M10 14l14 8 14-8M24 22v20" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></symbol>
        <symbol id="isb-i-flame" viewBox="0 0 48 48"><path d="M24 4c2 8 12 12 12 24a12 12 0 0 1-24 0c0-6 3-9 5-12 1 4 3 6 5 6-2-7 0-13 2-18z" fill="currentColor"/></symbol>
        <symbol id="isb-i-camera" viewBox="0 0 48 48"><path d="M6 16h8l4-6h12l4 6h8v22H6z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><circle cx="24" cy="26" r="7" fill="none" stroke="currentColor" stroke-width="3"/></symbol>
    </svg>
</section>
</x-guest-layout>
