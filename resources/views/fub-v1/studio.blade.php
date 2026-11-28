<x-guest-layout>
{{-- FUB studio (FUB_MODE): manual editor + Commit / Publish / creator powers + optional design-assistant machine.
     All state is loaded in the browser for the signed-in wallet (fubStudio); user art only via <img>.
     Layout = the Flovatar / Sportvatar builder shell (.flovatar-builder: options LEFT, big preview RIGHT; on phones the
     preview comes first): shared layout classes in resources/scss/layouts/_fub-v1.scss (.fub-shell). --}}
<section class="isl fub flovatar-builder fub-shell" :class="{'is-assistant': tab === 'assistant'}" x-data="fubStudio({{ (int) $draftId }})" data-fub="studio">
    <div class="flovatar-builder_bg"></div>
    <div class="flovatar-builder_frame">
        <div class="flovatar-builder_content">

            {{-- ======== before the draft is loaded: one centred panel ======== --}}
            <div class="fub-shell_gate" x-show="!d">
                <div class="widget-frame"><div class="widget-content">
                    <p class="isl-crumbs"><a href="/fub">FUB</a> › <span>Studio</span></p>
                    <div x-show="!wallet"><p>Connect your wallet to open your studio.</p><button class="fub-btn" @click="connect()">Connect wallet</button></div>
                    <p x-show="wallet && loading">Loading your draft…</p>
                    <p class="isl-notice isl-error" x-show="error" x-text="error" x-cloak data-fub="error"></p>
                    <div x-show="needsVerify" x-cloak data-fub="verify"><button class="fub-btn" @click="verifyWallet()" :disabled="verifying" x-text="verifying ? 'Check your wallet…' : 'Verify wallet (free signature)'"></button></div>
                </div></div>
            </div>

            <template x-if="d">
            <div class="fub-shell_wrap">
                {{-- ================= LEFT: options ================= --}}
                <div class="fub-shell_options">
                    {{-- info box (the builder's info panel): draft, state, budget --}}
                    <div class="fub-shell_info">
                        <div class="widget-frame"><div class="widget-content">
                            <div class="fub-info-head">
                                <p class="isl-crumbs"><a href="/fub">FUB</a> › <span>Studio</span></p>
                                <h1 x-text="d.name"></h1>
                                <p class="fub-info-state">
                                    <span class="fub-badge" x-text="d.status"></span>
                                    <span class="fub-badge" x-show="d.chainFubId" x-text="'FUB #' + d.chainFubId"></span>
                                    <span class="fub-badge sync" x-show="committed" x-text="d.syncState === 'idle' ? 'on chain' : (d.syncState === 'error' ? 'sync error' : 'writing to chain…')" data-fub="sync-state"></span>
                                    <span class="fub-badge" x-text="budget.tier + ' tier'"></span>
                                </p>
                            </div>
                            <div class="fub-info-meters">
                                <div class="fub-meter" :class="{warn: budget.used >= budget.slots}"><span x-text="'Item slots ' + budget.used + ' / ' + budget.slots"></span><div class="bar"><i :style="'width:' + pct(budget.used, budget.slots) + '%'"></i></div></div>
                                <div class="fub-meter"><span x-text="'Layers ' + budget.layers + ' / ' + budget.maxLayers"></span><div class="bar"><i :style="'width:' + pct(budget.layers, budget.maxLayers) + '%'"></i></div></div>
                                <div class="fub-meter" :class="{warn: budget.heaviest > budget.composedCap}" title="The heaviest F.U.N. your items can compose, against the size limit of one F.U.N."><span x-text="'Heaviest ' + fmtBytes(budget.heaviest) + ' / ' + fmtBytes(budget.composedCap)"></span><div class="bar"><i :style="'width:' + pct(budget.heaviest, budget.composedCap) + '%'"></i></div></div>
                                <div class="fub-meter"><span x-text="'Large items ' + budget.largeItems + ' / ' + budget.largeCap"></span><div class="bar"><i :style="'width:' + pct(budget.largeItems, budget.largeCap) + '%'"></i></div></div>
                            </div>
                        </div></div>
                        <span class="connector"></span>
                    </div>

                    {{-- options panel: section buttons + one scrollable area --}}
                    <div class="fub-shell_panel">
                        <div class="widget-frame">
                            <div class="fub-shell_tabs" role="tablist" aria-label="Studio sections">
                                <button type="button" role="tab" class="btn btn-sm btn-gray" :class="{active: tab === 'layers'}" :aria-selected="tab === 'layers'" @click="tab = 'layers'"><span class="inner">Layers</span></button>
                                <button type="button" role="tab" class="btn btn-sm btn-gray" :class="{active: tab === 'settings'}" :aria-selected="tab === 'settings'" @click="tab = 'settings'" x-show="!published"><span class="inner">Settings</span></button>
                                <button type="button" role="tab" class="btn btn-sm btn-gray" :class="{active: tab === 'commit'}" :aria-selected="tab === 'commit'" @click="tab = 'commit'" x-show="!committed" data-fub="tab-commit"><span class="inner">Commit</span></button>
                                <button type="button" role="tab" class="btn btn-sm btn-gray" :class="{active: tab === 'publish'}" :aria-selected="tab === 'publish'" @click="tab = 'publish'; preflight()" x-show="committed && !published" data-fub="tab-publish"><span class="inner">Publish</span></button>
                                <button type="button" role="tab" class="btn btn-sm btn-gray" :class="{active: tab === 'manage'}" :aria-selected="tab === 'manage'" @click="tab = 'manage'" x-show="published" data-fub="tab-manage"><span class="inner">Manage</span></button>
                                <button type="button" role="tab" class="btn btn-sm btn-lilac fub-tab-assistant" :class="{active: tab === 'assistant'}" :aria-selected="tab === 'assistant'" @click="openAssistant()" x-show="hasAssistant" data-fub="tab-assistant" :aria-label="assistantName" :title="assistantName"><span class="inner"><svg viewBox="0 0 24 30" aria-hidden="true"><path d="M5 9h14l-1.7 17a2 2 0 0 1-2 1.8H8.7a2 2 0 0 1-2-1.8L5 9z" fill="#EF2AC1" stroke="#000" stroke-width="2.4" stroke-linejoin="round"/><path d="M3 9h18" stroke="#000" stroke-width="3" stroke-linecap="round"/><path d="M14 9l2.5-7H21" fill="none" stroke="#000" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="fub-tab-name" x-text="assistantName"></span></span></button>
                            </div>
                            <p class="isl-notice isl-error" x-show="error" x-text="error" x-cloak data-fub="error" role="alert"></p>
                            <p class="isl-notice isl-error" x-show="d.syncState === 'error'" x-cloak><span x-text="d.syncError"></span> <button class="isl-link" @click="resync()">Retry</button></p>
                            <p class="isl-notice isl-n-ok" x-show="notice" x-cloak role="status"><span x-text="notice"></span> <button class="isl-link" @click="notice = ''" aria-label="Dismiss this message">Dismiss</button></p>

                            <div class="widget-content scrollable-frame" :class="{'fub-noscroll': tab === 'assistant'}">
                                <div class="scroll-y" tabindex="-1">

                                    {{-- layers & items --}}
                                    <div x-show="tab === 'layers'" role="tabpanel">
                                        <template x-for="(l, li) in d.layers" :key="l.key">
                                            <div class="fub-layerbox">
                                                <div class="fub-layer">
                                                    <b class="grow" x-text="(li + 1) + '. ' + l.name"></b>
                                                    <span class="fub-badge" x-text="l.required ? 'required' : 'optional'"></span>
                                                    <template x-if="!published">
                                                        <span class="fub-layer-tools">
                                                            <button class="isl-link" @click="moveLayer(li, -1)" title="Move toward the back (drawn earlier)" aria-label="Move layer toward the back">↑</button>
                                                            <button class="isl-link" @click="moveLayer(li, 1)" title="Move toward the front (drawn later)" aria-label="Move layer toward the front">↓</button>
                                                            <button class="isl-link" @click="renameLayer(l)">Rename</button>
                                                            <button class="isl-link" @click="toggleRequired(l)" x-text="l.required ? 'Make optional' : 'Make required'"></button>
                                                            <button class="isl-link" @click="deleteLayer(l)">Delete</button>
                                                        </span>
                                                    </template>
                                                </div>
                                                <div class="fub-items">
                                                    <template x-for="it in itemsOf(l.key)" :key="it.id">
                                                        <div class="fub-item" :class="{on: picks[l.key] === it.id}" @click="pick(l, it)" data-fub="studio-item">
                                                            <div class="fub-thumb"><img :src="draftItemImg(it)" :alt="it.name" loading="lazy"></div>
                                                            <b x-text="it.name"></b>
                                                            <small x-text="fmtDust(it.price) + ' · ' + fmtBytes(it.size) + (it.maxMint ? ' · max ' + it.maxMint : '') + (it.increase > 0 ? ' · curve' : '')"></small>
                                                            <small><span class="fub-badge ai" x-show="it.aiAssisted">AI</span><span class="fub-badge" x-show="it.chainState !== 'synced' && committed" x-text="it.chainState"></span></small>
                                                            <small x-show="!published || !it.chainItemId || it.chainState === 'new'"><button class="isl-link" @click.stop="openItem(it)">Edit</button><button class="isl-link" @click.stop="deleteItem(it)">Delete</button></small>
                                                        </div>
                                                    </template>
                                                    <button class="fub-item fub-item-add" @click="openItem(null, l.key)" data-fub="add-item">+ Add item</button>
                                                </div>
                                            </div>
                                        </template>
                                        <div class="fub-layerbox" x-show="!published">
                                            <h3>Add a layer</h3>
                                            <p class="muted">Layers stack in this order: the first is drawn at the back (a background), the last on top.</p>
                                            <div class="fub-row">
                                                <input type="text" maxlength="24" placeholder="e.g. Background" x-model="newLayer.name" @keydown.enter="addLayer()" aria-label="New layer name" class="fub-input" style="max-width:260px" data-fub="layer-name">
                                                <label><input type="checkbox" x-model="newLayer.required"> required</label>
                                                <button class="fub-btn alt" @click="addLayer()" data-fub="add-layer">Add layer</button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- settings (before publish) --}}
                                    <div x-show="tab === 'settings'" class="fub-form fub-pad" role="tabpanel">
                                        <label>Name<input type="text" maxlength="40" :value="d.name" @change="saveSettings({name: $event.target.value})"></label>
                                        <label>Description<textarea maxlength="500" :value="d.description" @change="saveSettings({description: $event.target.value})"></textarea></label>
                                        <label>Royalty on resales (0-10 %, can only go down after publish)<input type="number" min="0" max="10" step="0.5" :value="(d.settings.royalty || 0) * 100" @change="saveSettings({royalty: Number($event.target.value) / 100})"></label>
                                        <label><span><input type="checkbox" :checked="d.settings.unique !== false" @change="saveSettings({unique: $event.target.checked})"> Every F.U.N. is a unique combination</span></label>
                                        <label>Max F.U.N.s (up to your mint cap)<input type="number" min="1" :value="d.settings.max_supply || ''" @change="saveSettings({max_supply: Number($event.target.value)})"></label>
                                        <label>Licence for holders
                                            <select :value="d.settings.license || 'holder-commercial'" @change="saveSettings({license: $event.target.value})"><option value="holder-commercial">Holder may use commercially</option><option value="personal">Personal use</option><option value="cc0">CC0 (public domain)</option></select>
                                        </label>
                                        <p><button class="isl-link" @click="deleteDraft()">Delete this draft now</button> <span class="muted">(drafts and uploaded originals are deleted automatically after 90 days without changes)</span></p>
                                    </div>

                                    {{-- commit --}}
                                    <div x-show="tab === 'commit'" class="fub-pad" data-fub="commit-panel" role="tabpanel">
                                        <h3>Commit: burn FUB vouchers</h3>
                                        <p>Committing burns the vouchers you pick and reserves your FUB on chain. From then on your items are written to the chain while you keep editing. You can absorb more vouchers until you publish (up to 30 in total).</p>
                                        <template x-if="chain">
                                            <div>
                                                <template x-for="(ids, r) in chain.vouchers" :key="r">
                                                    <div style="margin:8px 0"><span class="isl-chip" :class="'isl-r-' + r" x-text="r"></span>
                                                        <div class="fub-vouchers"><template x-for="id in ids" :key="id"><button :class="{on: selVouchers.includes(id)}" @click="toggleVoucher(id)" x-text="'#' + id" data-fub="voucher"></button></template></div>
                                                    </div>
                                                </template>
                                                <p x-show="!Object.keys(chain.vouchers).length">This wallet has no FUB vouchers.</p>
                                                <p x-show="selVouchers.length" x-text="'Selected ' + selVouchers.length + ': ' + capacity.slots + ' item slots, ' + capacity.mints.toLocaleString('en-US') + ' mints, ' + capacity.tier + ' tier (' + ({common: 8, epic: 12, legendary: 16})[capacity.tier] + ' layers)'"></p>
                                                <button class="fub-btn" @click="commit()" :disabled="!selVouchers.length || !!busy" data-fub="commit">Commit and burn</button>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- publish --}}
                                    <div x-show="tab === 'publish'" class="fub-pad" data-fub="publish-panel" role="tabpanel">
                                        <h3>Publish</h3>
                                        <template x-if="chain && chain.vouchers && Object.keys(chain.vouchers).length">
                                            <div class="isl-notice"><b>More room?</b> Absorb more vouchers before you publish:
                                                <div class="fub-vouchers" style="margin-top:6px"><template x-for="id in [].concat(...Object.values(chain.vouchers))" :key="'a' + id"><button :class="{on: selVouchers.includes(id)}" @click="toggleVoucher(id)" x-text="'#' + id"></button></template></div>
                                                <button class="fub-btn alt" style="margin-top:6px" @click="absorb()" :disabled="!selVouchers.length">Absorb selected</button>
                                            </div>
                                        </template>
                                        <template x-if="preflightData">
                                            <div>
                                                <template x-for="b in preflightData.blockers"><p class="isl-error" x-text="b"></p></template>
                                                <template x-if="!preflightData.blockers.length">
                                                    <div>
                                                        <p x-text="preflightData.items + ' items in ' + preflightData.layers.length + ' layers · heaviest F.U.N. ' + fmtBytes(preflightData.heaviest) + ' of ' + fmtBytes(preflightData.composedCap)"></p>
                                                        <p class="mono muted" style="font-size:12px" x-text="'Manifest ' + preflightData.manifest"></p>
                                                        <label style="display:block;margin:10px 0"><b>Island for your FUB building</b> (permanent, one FUB per island)
                                                            <select x-model.number="islandId" class="fub-input" style="display:block;margin-top:4px" data-fub="island-picker">
                                                                <template x-for="i in (chain ? chain.islands : [])" :key="i.id"><option :value="i.id" x-text="'Island #' + i.id + ' (' + i.rarity + ')'"></option></template>
                                                            </select>
                                                        </label>
                                                        <p class="isl-error" x-show="chain && !chain.islands.length">You need an Asteroid Island without a FUB. <a href="/islands/build">Terraform one</a> first.</p>
                                                        <details><summary>Creator terms</summary>@include('fub-v1.partials.terms')</details>
                                                        <label style="display:flex;gap:8px;margin:10px 0"><input type="checkbox" x-model="acceptTerms" data-fub="terms"> I accept the creator terms. Publishing is final.</label>
                                                        <button class="fub-btn" @click="publish()" :disabled="!acceptTerms || !islandId || !!busy" data-fub="publish">Publish</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <button class="isl-link" @click="preflight()">Check again</button>
                                    </div>

                                    {{-- manage (after publish: Q25 powers) --}}
                                    <div x-show="tab === 'manage'" class="fub-pad" data-fub="manage-panel" role="tabpanel">
                                        <p><a :href="'/fub/' + d.chainFubId">Open the public builder</a></p>
                                        <div class="fub-row">
                                            <button class="fub-btn alt" @click="setStatus('pause')">Pause minting</button>
                                            <button class="fub-btn alt" @click="setStatus('resume')">Resume</button>
                                            <button class="fub-btn alt" @click="setStatus('close')">Close for good</button>
                                        </div>
                                        <div class="fub-row"><input type="number" min="0" max="10" step="0.5" placeholder="New royalty %" x-model="royaltyNew" aria-label="New royalty in percent" class="fub-input" style="max-width:160px"><button class="fub-btn alt" @click="lowerRoyalty()">Lower royalty</button></div>
                                        <div class="fub-row"><input type="text" placeholder="0x… payout address" x-model="payoutNew" aria-label="New payout address" class="fub-input" style="max-width:260px"><button class="fub-btn alt" @click="setPayout()">Change payout</button></div>
                                        <h3>New items</h3>
                                        <p class="muted">Add items to free slots in your existing layers (Layers tab). Once they are on chain, make them live:</p>
                                        <button class="fub-btn" @click="commitAdditions()">Make new items live</button>
                                    </div>

                                    {{-- ======== design assistant: the vending machine (optional; name, sizes and prices come from its API) ======== --}}
                                    <template x-if="assistant">
                                    <div class="fub-vm" x-show="tab === 'assistant'" role="tabpanel" data-fub="assistant" :class="{'is-dry': assistant.dry, 'is-busy': !!assistant.busy, 'is-off': !assistant.token || !assistant.tank}">
                                        {{-- display: name + tank gauge --}}
                                        <div class="fub-vm-sign">
                                            <svg class="fub-vm-logo" viewBox="0 0 40 44" aria-hidden="true"><path d="M8 14h24l-3 26a3 3 0 0 1-3 2.6H14a3 3 0 0 1-3-2.6L8 14z" fill="#91E3F9" stroke="#000" stroke-width="3" stroke-linejoin="round"/><path d="M10.3 24h19.4l-1.8 15.7a3 3 0 0 1-3 2.6H15.1a3 3 0 0 1-3-2.6L10.3 24z" fill="#EF2AC1"/><path d="M5 14h30" stroke="#000" stroke-width="4" stroke-linecap="round"/><path d="M23 14l4-11h7" fill="none" stroke="#000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 14h24l-3 26a3 3 0 0 1-3 2.6H14a3 3 0 0 1-3-2.6L8 14z" fill="none" stroke="#000" stroke-width="3" stroke-linejoin="round"/></svg>
                                            <b class="fub-vm-name" x-text="assistant.meta ? assistant.meta.name : 'Design assistant'"></b>
                                            <template x-if="assistant.token && assistant.tank">
                                                <div class="fub-vm-tank" data-fub="assistant-tank" :class="{low: aTankLow(), empty: Number(assistant.tank.balance) <= 0}" x-effect="aSeeTank(assistant.tank.balance)">
                                                    <span class="fub-vm-tank-label">Tank</span>
                                                    <span class="fub-vm-gauge" role="meter" aria-label="Tank level" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="aTankPct()" :aria-valuetext="fmtDust2(assistant.tank.balance) + ' in your tank'"><i :style="'width:' + aTankPct() + '%'"></i></span>
                                                    <b class="fub-vm-balance" x-text="fmtDust2(assistant.tank.balance)" data-fub="assistant-balance"></b>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- not signed in / not reachable --}}
                                        <template x-if="!assistant.token || !assistant.tank">
                                            <div class="fub-vm-off">
                                                <p class="isl-error" x-show="assistant.error" x-text="assistant.error" role="alert"></p>
                                                <template x-if="assistant.meta && !assistant.token">
                                                    <div><p>Sign in to the assistant with your wallet (a free signature, no transaction).</p><button class="fub-btn" @click="aLogin()" :disabled="!!assistant.busy">Sign in</button></div>
                                                </template>
                                                <p class="muted" x-show="assistant.busy" x-text="assistant.busy"></p>
                                                <p class="muted" x-show="!assistant.busy && !assistant.error && (!assistant.meta || assistant.token)">Starting the machine…</p>
                                            </div>
                                        </template>

                                        <template x-if="assistant.token && assistant.tank">
                                        <div class="fub-vm-body">
                                            {{-- glass window: the conversation is the ONLY scrollable area of the machine --}}
                                            <div class="fub-vm-glass">
                                                <div class="fub-vm-total" x-show="assistant.convTotal > 0 || assistant.live"><span>This conversation</span><b x-text="fmtDust2(Number(assistant.convTotal || 0) + (assistant.live ? Number(assistant.live.burned || 0) : 0)) + ' burned'" data-fub="assistant-conv-total"></b></div>
                                                <div class="msgs" tabindex="0" role="log" aria-label="Conversation with the assistant" aria-live="polite" data-fub="assistant-msgs"
                                                     @scroll.passive="aScrolled($el)" x-effect="aFollow($el, assistant.messages.length, assistant.busy)">
                                                    <p class="fub-vm-empty" x-show="!assistant.messages.length">Pick a size, then tell the machine what to make or change. It works on this draft only.</p>
                                                    <template x-for="(m, mi) in assistant.messages" :key="mi">
                                                        <div class="m" :class="m.role">
                                                            <span class="t" x-text="m.content"></span>
                                                            <template x-if="m.receipt">
                                                                <details class="fub-vm-receipt">
                                                                    <summary><b x-show="aBurned(m.receipt)" x-text="aBurned(m.receipt)" data-fub="assistant-burned"></b><span x-text="aReceiptShort(m.receipt)"></span></summary>
                                                                    <small data-fub="assistant-receipt" x-text="aReceipt(m.receipt)"></small>
                                                                </details>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <div class="m assistant fub-vm-typing" x-show="assistant.busy && assistant.live" aria-hidden="true"><i></i><i></i><i></i></div>
                                                </div>
                                                <button type="button" class="fub-vm-jump" x-show="assistant.unseen" x-cloak @click="aJump()" x-text="'↓ New messages'"></button>
                                            </div>

                                            {{-- control panel: sizes (chunky buttons) + coin slot --}}
                                            <div class="fub-vm-controls">
                                                <div class="fub-vm-levels" role="group" aria-label="Message size" data-fub="assistant-levels">
                                                    <template x-for="(l, i) in assistant.meta.levels" :key="l.key">
                                                        <button type="button" class="fub-vm-level" :class="['s' + Math.min(i, 2), assistant.level === l.key ? 'on' : '']" :aria-pressed="assistant.level === l.key ? 'true' : 'false'" @click="assistant.level = l.key" :data-level="l.key">
                                                            <svg viewBox="0 0 24 30" aria-hidden="true"><path d="M5 9h14l-1.7 17a2 2 0 0 1-2 1.8H8.7a2 2 0 0 1-2-1.8L5 9z" fill="#fff" stroke="#000" stroke-width="2.4" stroke-linejoin="round"/><path d="M3 9h18" stroke="#000" stroke-width="3" stroke-linecap="round"/><path d="M14 9l2.5-7H21" fill="none" stroke="#000" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                            <b x-text="l.name"></b>
                                                            <small x-text="aLevelBurn(l)"></small>
                                                        </button>
                                                    </template>
                                                </div>
                                                <div class="fub-vm-coin" data-fub="assistant-refill" :class="{open: assistant.slotOpen || assistant.dry}">
                                                    <button type="button" class="fub-vm-slot" @click="assistant.slotOpen = !assistant.slotOpen" :aria-expanded="(assistant.slotOpen || assistant.dry) ? 'true' : 'false'" aria-controls="fub-vm-drawer">
                                                        <svg viewBox="0 0 34 30" aria-hidden="true"><rect x="2" y="2" width="30" height="26" rx="6" fill="#C7BCFB" stroke="#000" stroke-width="3"/><rect x="15" y="7" width="4" height="16" rx="2" fill="#000"/></svg>
                                                        <span>Insert ĐUST</span>
                                                    </button>
                                                    <div class="fub-vm-drawer" id="fub-vm-drawer">
                                                        <label class="fub-vm-amount"><span>Đ</span><input type="text" inputmode="numeric" x-model="assistant.refillAmount" :placeholder="assistant.meta.refill.min + '–' + assistant.meta.refill.max.toLocaleString('en-US')" aria-label="Refill amount in ĐUST" @keydown.enter.prevent="aRefill()" data-fub="assistant-refill-amount"></label>
                                                        <div class="fub-vm-chips"><template x-for="p in (assistant.meta.refill.chips || [])"><button type="button" :class="{on: String(assistant.refillAmount) === String(p)}" @click="assistant.refillAmount = String(p)" x-text="fmtDustShort(p)" :title="fmtDust(p)" :aria-label="'Set the refill amount to ' + fmtDust(p)"></button></template></div>
                                                        <button type="button" class="fub-vm-refill" @click="aRefill()" :disabled="!!aRefillError() || !!assistant.busy" data-fub="assistant-refill-btn">Refill <small>(burn)</small></button>
                                                        <p class="fub-vm-hint warn" x-show="assistant.refillAmount !== '' && aRefillError()" x-text="aRefillError()" role="alert"></p>
                                                        <p class="fub-vm-hint fub-vm-note3" x-text="aNote()"></p>
                                                    </div>
                                                </div>
                                                <p class="fub-vm-left" x-show="aMsgsLeft()" x-text="aMsgsLeft()" data-fub="assistant-left"></p>
                                                <p class="fub-vm-note" x-text="aNote()" data-fub="assistant-note"></p>
                                            </div>

                                            {{-- dispenser: notices, status, keypad (text + voice + send) --}}
                                            <div class="fub-vm-tray">
                                                <p class="fub-vm-alert" x-show="assistant.error" role="alert"><span x-text="assistant.error"></span> <button type="button" class="isl-link" @click="assistant.error = ''" aria-label="Dismiss this message">Dismiss</button></p>
                                                <template x-if="assistant.dry">
                                                    <div class="fub-vm-alert dry" data-fub="assistant-dry"><p>Your tank ran dry. Refill to continue: your changes so far are saved, and the assistant picks up where it stopped when you say “continue”.</p>
                                                        <button class="fub-btn" @click="aRefill()" :disabled="!!aRefillError() || !!assistant.busy" x-text="'Refill ' + (aRefillError() ? '' : fmtDust(Number(String(assistant.refillAmount).replace(/[,\s_]/g, ''))))"></button></div>
                                                </template>
                                                <template x-if="assistant.confirm"><div class="fub-vm-alert ask" data-fub="assistant-confirm"><p x-text="assistant.confirm.summary"></p><button class="fub-btn" @click="aConfirm(true)">Confirm</button> <button class="fub-btn alt" @click="aConfirm(false)">Cancel</button></div></template>
                                                {{-- voice: up to 10 min (or what the tank affords), live timer + Đ counter while recording --}}
                                                <p class="fub-vm-status rec" x-show="assistant.recording" data-fub="assistant-rec">
                                                    <b class="dot">&#9679; REC</b>
                                                    <b data-fub="assistant-rec-timer" x-text="mmss(assistant.recSec) + ' / ' + mmss(assistant.recLimit)"></b>
                                                    <b data-fub="assistant-rec-cost" x-text="fmtDust2(aRecCost())"></b>
                                                    <span x-text="'(' + fmtDust2(aVoiceRate() * 60) + ' per minute)'"></span>
                                                </p>
                                                <p class="fub-vm-status" x-show="!assistant.recording && assistant.busy" role="status"><i class="led" aria-hidden="true"></i><span x-text="assistant.busy" data-fub="assistant-busy"></span></p>
                                                <p class="fub-vm-status note" x-show="!assistant.recording && !assistant.busy && assistant.voiceNote" x-text="assistant.voiceNote" data-fub="assistant-voice-note"></p>
                                                <div class="fub-vm-keys">
                                                    <textarea x-model="assistant.input" placeholder="Describe what to make or change…" aria-label="Your message to the assistant" @keydown.enter="if ($event.metaKey || $event.ctrlKey) { $event.preventDefault(); aSend(); }" data-fub="assistant-input"></textarea>
                                                    <button type="button" class="fub-vm-key voice" :class="{rec: assistant.recording}" @click="aRecord()" :disabled="!assistant.recording && !!assistant.busy" :aria-label="assistant.recording ? 'Stop recording' : 'Record a voice message'" data-fub="assistant-voice">
                                                        <svg x-show="!assistant.recording" viewBox="0 0 24 24" aria-hidden="true"><rect x="8.5" y="2.5" width="7" height="12" rx="3.5" fill="#fff" stroke="#000" stroke-width="2.2"/><path d="M5 11.5a7 7 0 0 0 14 0M12 18.5v3.5M8.5 22h7" fill="none" stroke="#000" stroke-width="2.2" stroke-linecap="round"/></svg>
                                                        <svg x-show="assistant.recording" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2.5" fill="#fff" stroke="#000" stroke-width="2.2"/></svg>
                                                        <span x-text="assistant.recording ? 'Stop' : 'Voice'"></span>
                                                    </button>
                                                    <button type="button" class="fub-vm-key send" @click="aSend()" :disabled="!!assistant.busy || assistant.recording" data-fub="assistant-send">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12l17-8-5 8 5 8-17-8z" fill="#fff" stroke="#000" stroke-width="2.2" stroke-linejoin="round"/></svg>
                                                        <span>Send</span>
                                                    </button>
                                                </div>
                                                <div class="fub-vm-foot">
                                                    <small class="fub-vm-hint fub-vm-note2" x-text="aNote()"></small>
                                                    <small class="fub-vm-hint" x-show="!assistant.recording" x-text="'Voice: up to ' + mmss(aVoiceLimit()) + (aVoiceLimit() < (assistant.meta.voice ? assistant.meta.voice.maxSeconds : 600) ? ' (what your tank can pay)' : '') + ' · ' + fmtDust2(aVoiceRate() * 60) + ' per minute'" data-fub="assistant-voice-limit"></small>
                                                </div>
                                            </div>
                                        </div>
                                        </template>
                                    </div>
                                    </template>

                                </div>
                            </div>
                        </div>
                        <span class="connector"></span>
                    </div>
                </div>

                {{-- ================= RIGHT: the big preview (the builder's machine) ================= --}}
                <div class="flovatar-machine fub-shell_machine">
                    <div class="widget-frame machine-frame no-bg">
                        <div class="widget-content machine-box no-bg">
                            <div class="machine fub-shell_stage">
                                <div class="flovatar-img"><img class="builder-svg" :src="previewUrl" alt="Preview of your F.U.N. with the selected items" data-fub="studio-preview"></div>
                            </div>
                        </div>
                    </div>
                    <ul class="btns-list">
                        <li x-show="hasAssistant"><button type="button" @click="openAssistant()" class="btn btn-orange btn-tall fub-btn-wide" :class="{active: tab === 'assistant'}" data-fub="assistant-open" :aria-label="'Open ' + assistantName"><span class="inner" x-text="assistantName"></span></button></li>
                        <li><button type="button" @click="shuffle()" class="btn btn-lilac btn-tall" title="Show a random combination of your items"><span class="inner">Randomize</span></button></li>
                        <li><a :href="previewUrl" target="_blank" rel="noopener" class="btn btn-lilac btn-icon" title="Open the preview in a new tab" aria-label="Open the preview in a new tab"><span class="inner"><i class="icon-camera" aria-hidden="true"></i></span></a></li>
                    </ul>
                </div>
            </div>
            </template>
        </div>
    </div>

    {{-- ======== item dialog ======== --}}
    <template x-if="d">
    <div class="fub-dialog" x-show="formOpen" x-cloak @keydown.escape.window="formOpen = false" role="dialog" aria-modal="true" :aria-label="form.id ? 'Edit item' : 'New item'">
        <div class="fub-form" data-fub="item-dialog">
            <h3 x-text="form.id ? 'Edit item' : 'New item'"></h3>
            <label>SVG file <span class="muted">(3000×3000 canvas, shapes only: no text, no scripts, no external images; up to 32 KB after cleaning, 64 KB for a large item)</span>
                <input type="file" accept=".svg,image/svg+xml" @change="pickFile($event)" data-fub="item-file">
            </label>
            <template x-if="form.check">
                <div>
                    <template x-for="e in form.check.errors"><p class="isl-error" x-text="e"></p></template>
                    <template x-for="w in form.check.warnings"><p class="muted" x-text="'⚠ ' + w"></p></template>
                    <template x-if="form.check.server">
                        <p :class="form.check.server.ok ? 'isl-n-ok' : 'isl-error'" x-text="form.check.server.ok ? ('Clean: ' + fmtBytes(form.check.server.size) + (form.check.server.removed.length ? ' · removed ' + form.check.server.removed.length + ' thing(s)' : '')) : form.check.server.errors.join(' ')"></p>
                    </template>
                </div>
            </template>
            <label>Layer<select x-model="form.layer"><template x-for="l in d.layers" :key="l.key"><option :value="l.key" x-text="l.name"></option></template></select></label>
            <label>Name<input type="text" maxlength="32" x-model="form.name" data-fub="item-name"></label>
            <label>Description<textarea maxlength="200" x-model="form.description"></textarea></label>
            <label>Rarity<select x-model="form.rarity"><template x-for="r in RARITIES"><option :value="r" x-text="r"></option></template></select></label>
            <label>Price in ĐUST (minimum Đ1)<input type="number" min="1" step="1" x-model.number="form.price" data-fub="item-price"></label>
            <label>Supply
                <select x-model="form.supply"><option value="unlimited">Unlimited</option><option value="capped">Limited edition</option><option value="curve">Price curve (rises with every mint)</option></select>
            </label>
            <label x-show="form.supply !== 'unlimited'">Max mints of this item<input type="number" min="1" x-model.number="form.max_mint"></label>
            <label x-show="form.supply === 'curve'">Increase per mint (%)<input type="number" min="0.01" max="10" step="0.01" :value="(form.increase * 100).toFixed(2)" @input="form.increase = Number($event.target.value) / 100"></label>
            <label x-show="form.supply === 'curve'">Ceiling price (ĐUST)<input type="number" :min="form.price" x-model.number="form.price_cap"></label>
            <label><span><input type="checkbox" x-model="form.large"> Large item (64 KB budget, limited per FUB)</span></label>
            <div class="fub-row">
                <button class="fub-btn" @click="saveItem()" :disabled="!!busy" data-fub="item-save">Save item</button>
                <button class="fub-btn alt" @click="formOpen = false">Cancel</button>
            </div>
        </div>
    </div>
    </template>
    <div class="fub-busy" x-show="busy" x-text="busy" x-cloak role="status"></div>
</section>
</x-guest-layout>
