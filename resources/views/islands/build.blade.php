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
                            <span class="isb-cat-art">
                                <template x-if="tabThumb(t.key)"><img :src="tabThumb(t.key)" alt=""></template>
                                <template x-if="!tabThumb(t.key)"><svg viewBox="0 0 48 48" aria-hidden="true"><use :href="'#isb-i-' + t.key"></use></svg></template>
                            </span>
                            <span class="isb-cat-badge" x-show="tabBadge(t.key)" x-text="tabBadge(t.key)"></span>
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
                            </div>
                        </dl>
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
                                    <span class="isb-card-art"><svg viewBox="0 0 48 48" aria-hidden="true"><use :href="'#isb-i-' + tab"></use></svg></span>
                                    <span class="isb-card-name">None</span>
                                    <span class="isb-check" x-show="!layers[tab]">✓</span>
                                </button></li>
                            </template>
                            <template x-for="t in optionsFor(tab)" :key="t.id">
                                <li><button type="button" class="isb-card" :class="{selected: layers[tab] === t.id}" @click="pick(tab, t.id)" :data-template="t.id" :aria-pressed="layers[tab] === t.id" :title="t.description || t.name">
                                    <span class="isb-card-art"><img :src="thumb(tab, t.id)" alt="" x-show="thumb(tab, t.id)"></span>
                                    <span class="isb-card-name" x-text="t.name"></span>
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
                <img :src="preview" alt="Island preview" x-show="preview" data-islands="preview">
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
            <p class="isb-notice" x-show="notice" x-text="notice" aria-live="polite"></p>
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
                            <template x-for="p in snap.parts" :key="p.layer"><div><dt x-text="p.layer"></dt><dd x-text="p.name"></dd></div></template>
                            <div x-show="snap.tint"><dt>Tint</dt><dd><span class="isb-swatch" :style="'background:' + snap.tint"></span> <span x-text="snap.tint && snap.tint.toUpperCase()"></span></dd></div>
                            <div><dt>Cost</dt><dd>Free (network fee only)</dd></div>
                        </dl>
                        <div class="isb-burnbox" data-islands="burn-warning">
                            <p class="isb-burnbox-title"><svg viewBox="0 0 48 48" aria-hidden="true"><use href="#isb-i-flame"></use></svg>
                                <span x-text="snap.deeds.length === 1 ? 'This land deed will be BURNED' : 'These ' + snap.deeds.length + ' land deeds will be BURNED'"></span></p>
                            <ul class="isb-burn-deeds">
                                <template x-for="d in snap.deeds" :key="d.id"><li><img :src="'/api/image/template/' + d.templateId" alt=""><span x-text="'#' + d.id"></span></li></template>
                            </ul>
                            <p>They are destroyed for good when the island is created. This can't be undone, and the island's scene can't be changed later.</p>
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
                            <a x-show="islandId" :href="'/islands/' + islandId + '?new=1'" class="isb-btn isb-btn-cta" data-islands="visit"><span>Go check it out</span></a>
                            <button type="button" class="isb-btn" @click="startOver()"><span>Terraform another</span></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- icons (outline, currentColor) --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="isb-i-deeds" viewBox="0 0 48 48"><path d="M12 6h18l8 8v28H12z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M30 6v8h8M17 22h16M17 28h16M17 34h10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></symbol>
        <symbol id="isb-i-sky" viewBox="0 0 48 48"><path d="M30 8a14 14 0 1 0 10 22A12 12 0 0 1 30 8z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M10 10l1.5 3 3 1.5-3 1.5L10 19l-1.5-3-3-1.5 3-1.5z" fill="currentColor"/></symbol>
        <symbol id="isb-i-base" viewBox="0 0 48 48"><path d="M5 20c6-4 32-4 38 0-2 6-6 8-9 9l-4 10-5-6-5 8-3-10c-5-2-10-5-12-11z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M8 19c5 3 27 3 32 0" fill="none" stroke="currentColor" stroke-width="3"/></symbol>
        <symbol id="isb-i-structure" viewBox="0 0 48 48"><path d="M8 40V22L24 9l16 13v18zM20 40V29h8v11" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></symbol>
        <symbol id="isb-i-fub" viewBox="0 0 48 48"><rect x="7" y="12" width="34" height="24" rx="3" fill="none" stroke="currentColor" stroke-width="3"/><path d="M16 36v6M32 36v6M14 20h6M14 26h4M24 20v8h4M34 20h-4v8h4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></symbol>
        <symbol id="isb-i-foreground" viewBox="0 0 48 48"><path d="M24 42V20M24 26c-8 0-12-6-12-12 8 0 12 5 12 12zM24 22c0-8 5-13 13-13 0 8-5 13-13 13zM10 42h28" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="isb-i-fx" viewBox="0 0 48 48"><path d="M20 6l3 9 9 3-9 3-3 9-3-9-9-3 9-3zM36 28l2 5 5 2-5 2-2 5-2-5-5-2 5-2z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></symbol>
        <symbol id="isb-i-tint" viewBox="0 0 48 48"><path d="M24 6c8 10 13 17 13 23a13 13 0 0 1-26 0c0-6 5-13 13-23z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M18 30a6 6 0 0 0 6 6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></symbol>
        <symbol id="isb-i-pack" viewBox="0 0 48 48"><path d="M10 14l14-8 14 8v20l-14 8-14-8z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M10 14l14 8 14-8M24 22v20" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></symbol>
        <symbol id="isb-i-flame" viewBox="0 0 48 48"><path d="M24 4c2 8 12 12 12 24a12 12 0 0 1-24 0c0-6 3-9 5-12 1 4 3 6 5 6-2-7 0-13 2-18z" fill="currentColor"/></symbol>
        <symbol id="isb-i-camera" viewBox="0 0 48 48"><path d="M6 16h8l4-6h12l4 6h8v22H6z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><circle cx="24" cy="26" r="7" fill="none" stroke="currentColor" stroke-width="3"/></symbol>
    </svg>
</section>
</x-guest-layout>
