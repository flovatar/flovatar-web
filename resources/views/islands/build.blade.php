<x-guest-layout>
{{-- Asteroid Islands redeem / builder (ISLANDS_MODE; KB notes/islands-build.md). Static shell, no user data: the wallet is
     read and the terraform transaction is signed in the browser (resources/js/app/islands.js islandsBuilder).
     Redeem only: 1 land deed → island of that rarity, 5 same-rarity deeds → next tier. The deeds are burned. No fees. --}}
<section class="isl isl-build" x-data="islandsBuilder" data-islands="builder">
    <div class="container">
        <header class="isl-hero">
            <span class="isl-eyebrow">TerraKit · redeem</span>
            <h1>Terraform your island</h1>
            <p>Turn a TerraKit land deed into an Asteroid Island. One deed gives an island of the same rarity. Five deeds of the same rarity fuse into one island of the next tier (common → epic, epic → legendary). Fusion only happens now, when the island is created.</p>
        </header>

        <ol class="isl-steps" aria-label="Steps">
            <li :class="{active: step === 'deeds', done: step !== 'deeds'}">1 · Deeds</li>
            <li :class="{active: step === 'build', done: ['confirm', 'signing', 'done'].includes(step)}">2 · Build</li>
            <li :class="{active: ['confirm', 'signing'].includes(step), done: step === 'done'}">3 · Confirm</li>
        </ol>

        <div class="isl-panel isl-error" x-show="status === 'error'" x-cloak><p x-text="error"></p><button type="button" class="btn btn-sm" @click="loadInventory()" x-show="user"><span class="inner">Try again</span></button></div>

        {{-- connect --}}
        <div class="isl-panel" x-show="!user" x-cloak data-islands="connect">
            <p>Connect the wallet that holds your TerraKit land deeds.</p>
            <button type="button" class="btn btn-pink" @click="connect()"><span class="inner">Connect wallet</span></button>
            <p class="muted">Not sure your wallet still works? <a href="/terrakit/check">Check your TerraKit</a>.</p>
        </div>

        <div class="isl-panel isl-warn" x-show="user && dapper()" x-cloak data-islands="dapper">
            <p><b>Dapper Wallet can't sign this transaction.</b> Redeeming land deeds needs a wallet that signs the island transaction itself, such as Flow Wallet. There's no separate path for Dapper accounts.</p>
        </div>

        <p class="isl-loading" x-show="status === 'loading'" x-cloak>Reading your wallet on the Flow blockchain…</p>

        {{-- STEP 1: deeds + packs --}}
        <div x-show="user && status === 'ready' && step === 'deeds'" x-cloak data-islands="step-deeds">
            <p class="isl-for">Wallet <span class="mono" x-text="user && user.addr"></span> <button type="button" class="isl-link" @click="loadInventory()">refresh</button></p>

            <template x-if="packs.length">
                <div class="isl-panel isl-packs" data-islands="packs">
                    <h2>Unopened TerraKit packs</h2>
                    <p>Each TerraKit pack holds one land deed, one FUB voucher and one Flobot Constructor Kit. Open it to use its land deed.</p>
                    <div class="isl-pack-list">
                        <template x-for="p in packs" :key="p">
                            <div class="isl-pack">
                                <span class="isl-pack-art" aria-hidden="true"></span>
                                <span>Pack #<span x-text="p"></span></span>
                                <button type="button" class="btn btn-sm" :disabled="openingPack !== null" @click="openPack(p)" :data-pack="p">
                                    <span class="inner" x-text="openingPack === p ? 'Opening…' : 'Open pack'"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            <p class="isl-notice" x-show="packMessage" x-text="packMessage" aria-live="polite"></p>

            <template x-if="!hasDeeds">
                <div class="isl-panel"><p>No TerraKit land deeds in this wallet<span x-show="packs.length"> yet: open a pack above</span>.</p></div>
            </template>

            <template x-if="hasDeeds">
                <div>
                    <div class="isl-mode" role="radiogroup" aria-label="How many deeds">
                        <button type="button" :class="mode === 'single' ? 'active' : ''" @click="setMode('single')" role="radio" :aria-checked="mode === 'single'"><b>1 deed</b><span>Island of the same rarity</span></button>
                        <button type="button" :class="mode === 'fuse' ? 'active' : ''" @click="setMode('fuse')" role="radio" :aria-checked="mode === 'fuse'"><b>Fuse 5 deeds</b><span>Same rarity → next tier</span></button>
                    </div>

                    <template x-for="r in rarities" :key="r">
                        <div class="isl-deed-group" x-show="deedsOf(r).length">
                            <h3><span class="isl-chip" :class="'isl-r-' + r" x-text="r"></span> <span x-text="deedsOf(r).length + ' land deed' + (deedsOf(r).length === 1 ? '' : 's')"></span>
                                <button type="button" class="isl-link" x-show="mode === 'fuse' && canFuse(r)" @click="pickFive(r)">pick 5</button></h3>
                            <div class="isl-deeds">
                                <template x-for="d in deedsOf(r)" :key="d.id">
                                    <button type="button" class="isl-deed" :class="['isl-r-' + d.rarity, isSelected(d.id) ? 'selected' : '', (mode === 'fuse' && d.rarity === 'legendary') ? 'disabled' : '']"
                                            :disabled="mode === 'fuse' && d.rarity === 'legendary'" @click="toggleDeed(d)" :data-deed="d.id" :aria-pressed="isSelected(d.id)">
                                        <img loading="lazy" :src="'/api/image/template/' + d.templateId" alt="">
                                        <span x-text="'#' + d.id"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="isl-bar">
                        <span x-text="selectionHint"></span>
                        <button type="button" class="btn btn-pink" :disabled="!rarity" @click="goBuild()" data-islands="to-build"><span class="inner">Build the island</span></button>
                    </div>
                </div>
            </template>
        </div>

        {{-- STEP 2: build --}}
        <div x-show="step === 'build'" x-cloak data-islands="step-build">
            <div class="isl-builder">
                <div class="isl-preview">
                    <div class="isl-preview-frame" :class="previewBusy ? 'busy' : ''">
                        <img :src="preview" alt="Island preview" x-show="preview" data-islands="preview">
                    </div>
                    <div class="isl-meter" :class="budgetPct >= 100 ? 'over' : (budgetPct > 85 ? 'near' : '')" data-islands="budget">
                        <div class="isl-meter-bar"><span :style="'width:' + budgetPct + '%'"></span></div>
                        <span x-text="kb(bytes) + ' of ' + kb(budget) + ' (' + rarity + ' budget)'"></span>
                    </div>
                </div>
                <div class="isl-options">
                    <p><span class="isl-chip" :class="'isl-r-' + rarity" x-text="rarity"></span> island from <span x-text="selected.length === 5 ? '5 fused ' + selectedRarity + ' deeds' : '1 ' + selectedRarity + ' deed'"></span>. Every part must match the island's rarity.</p>
                    <template x-for="name in sceneLayers" :key="name">
                        <fieldset class="isl-layer" :data-layer="name">
                            <legend><span x-text="name"></span> <small x-text="isRequired(name) ? 'required' : 'optional'"></small></legend>
                            <div class="isl-choices">
                                <template x-if="!isRequired(name)">
                                    <button type="button" class="isl-choice" :class="!layers[name] ? 'active' : ''" @click="pick(name, null)">None</button>
                                </template>
                                <template x-for="t in optionsFor(name)" :key="t.id">
                                    <button type="button" class="isl-choice" :class="layers[name] === t.id ? 'active' : ''" @click="pick(name, t.id)" :data-template="t.id">
                                        <span x-text="t.name"></span><small x-text="kb(t.svgLength + t.svgFrontLength)"></small>
                                    </button>
                                </template>
                                <p class="muted" x-show="!optionsFor(name).length">No <span x-text="rarity"></span> art for this layer yet.</p>
                            </div>
                        </fieldset>
                    </template>
                    <fieldset class="isl-layer" x-show="tintAllowed">
                        <legend>Tint <small>optional</small></legend>
                        <label class="isl-tint"><input type="checkbox" x-model="tintOn" @change="renderPreview()"> Tint the island
                            <input type="color" x-model="tint" @change="renderPreview()" :disabled="!tintOn" aria-label="Tint colour"></label>
                    </fieldset>
                    <ul class="isl-problems" x-show="problems.length"><template x-for="p in problems"><li x-text="p"></li></template></ul>
                    <div class="isl-bar">
                        <button type="button" class="btn btn-sm" @click="step = 'deeds'"><span class="inner">Back</span></button>
                        <button type="button" class="btn btn-pink" :disabled="problems.length > 0" @click="goConfirm()" data-islands="to-confirm"><span class="inner">Review</span></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- STEP 3: confirm + sign --}}
        <div x-show="['confirm', 'signing', 'done'].includes(step)" x-cloak data-islands="step-confirm">
            <div class="isl-builder">
                <div class="isl-preview"><div class="isl-preview-frame"><img :src="preview" alt="Island preview"></div></div>
                <div class="isl-options">
                    <h2>Check and sign</h2>
                    <dl class="isl-summary">
                        <dt>Island</dt><dd><span class="isl-chip" :class="'isl-r-' + rarity" x-text="rarity"></span> <span x-text="selected.length === 5 ? '(fused)' : ''"></span></dd>
                        <template x-for="name in sceneLayers" :key="name"><div><dt x-text="name"></dt><dd x-text="layerName(name)"></dd></div></template>
                        <dt x-show="tintValue">Tint</dt><dd x-show="tintValue"><span class="isl-swatch" :style="'background:' + tintValue"></span> <span x-text="tintValue"></span></dd>
                        <dt>Cost</dt><dd>Free (network fee only)</dd>
                    </dl>
                    <div class="isl-panel isl-burn" data-islands="burn-warning">
                        <p><b>Your land deed<span x-show="selected.length > 1">s</span> will be burned.</b>
                            <span x-text="selected.length === 1 ? 'Land deed #' + selected[0] : 'Land deeds #' + selected.join(', #')"></span>
                            <span x-text="selected.length === 1 ? 'is' : 'are'"></span> destroyed for good when the island is created. This can't be undone, and the island's scene can't be changed later.</p>
                        <label><input type="checkbox" x-model="understood" data-islands="understood"> I understand: burn <span x-text="selected.length"></span> land deed<span x-show="selected.length > 1">s</span> and create this island.</label>
                    </div>
                    <p class="isl-check" :class="chainCheck === 'ok' ? 'ok' : (chainCheck && chainCheck !== 'checking' ? 'err' : '')" data-islands="chain-check">
                        <span x-show="chainCheck === 'checking'">Checking the scene with the island contract…</span>
                        <span x-show="chainCheck === 'ok'">The island contract accepts this scene.</span>
                        <span x-show="chainCheck && chainCheck !== 'checking' && chainCheck !== 'ok'" x-text="'The island contract rejects this scene: ' + chainCheck"></span>
                    </p>
                    <p class="isl-error" x-show="error" x-text="error" data-islands="tx-error"></p>
                    <p class="isl-notice" x-show="txStatus" x-text="txStatus" aria-live="polite" data-islands="tx-status"></p>
                    <p x-show="txId" class="muted">Transaction <a :href="explorer(txId)" target="_blank" rel="noopener" class="mono" x-text="short(txId)"></a></p>
                    <p x-show="step === 'done' && islandId"><a :href="'/islands/' + islandId" class="btn btn-pink"><span class="inner" x-text="'Visit ' + designation(islandId)"></span></a></p>
                    <div class="isl-bar" x-show="step === 'confirm'">
                        <button type="button" class="btn btn-sm" @click="step = 'build'"><span class="inner">Back</span></button>
                        <button type="button" class="btn btn-pink" :disabled="!canSign" @click="terraform()" data-islands="sign"><span class="inner">Burn and terraform</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</x-guest-layout>
