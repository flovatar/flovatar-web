<x-guest-layout>
{{-- FUB studio (FUB_MODE): manual editor + Commit / Publish / creator powers + optional design-assistant panel.
     All state is loaded in the browser for the signed-in wallet (fubStudio); user art only via <img>. --}}
<section class="isl fub" x-data="fubStudio({{ (int) $draftId }})" data-fub="studio">
    <div class="container">
        <p class="isl-crumbs"><a href="/fub">FUB</a> › <span>Studio</span></p>

        <div class="isl-panel" x-show="!wallet"><p>Connect your wallet to open your studio.</p><button class="fub-btn" @click="connect()">Connect wallet</button></div>
        <div class="isl-panel" x-show="wallet && loading"><p>Loading your draft…</p></div>
        <p class="isl-notice isl-error" x-show="error" x-text="error" x-cloak data-fub="error"></p>
        <div class="isl-panel" x-show="needsVerify" x-cloak data-fub="verify"><button class="fub-btn" @click="verifyWallet()" :disabled="verifying" x-text="verifying ? 'Check your wallet…' : 'Verify wallet (free signature)'"></button></div>
        <p class="isl-notice isl-n-ok" x-show="notice" x-text="notice" x-cloak @click="notice = ''"></p>

        <template x-if="d">
        <div>
            <h1 x-text="d.name"></h1>
            <p class="muted">
                <span x-text="d.status"></span>
                <span x-show="d.chainFubId" x-text="' · FUB #' + d.chainFubId"></span>
                <span class="fub-badge sync" x-show="committed" x-text="d.syncState === 'idle' ? 'on chain' : (d.syncState === 'error' ? 'sync error' : 'writing to chain…')" data-fub="sync-state"></span>
                · <span x-text="budget.tier"></span> tier
            </p>
            <p class="isl-error" x-show="d.syncState === 'error'"><span x-text="d.syncError"></span> <button class="isl-link" @click="resync()">Retry</button></p>

            <div class="fub-grid">
                {{-- ======== preview + budget ======== --}}
                <div class="fub-stage">
                    <div class="fub-canvas"><img :src="previewUrl" alt="Preview" data-fub="studio-preview"></div>
                    <p class="muted" style="font-size:14px">Click items on the right to try combinations. This preview is the exact composition the contract serves.</p>
                    <div class="fub-meter" :class="{warn: budget.used >= budget.slots}"><span x-text="'Item slots ' + budget.used + ' / ' + budget.slots"></span><div class="bar"><i :style="'width:' + pct(budget.used, budget.slots) + '%'"></i></div></div>
                    <div class="fub-meter"><span x-text="'Layers ' + budget.layers + ' / ' + budget.maxLayers"></span><div class="bar"><i :style="'width:' + pct(budget.layers, budget.maxLayers) + '%'"></i></div></div>
                    <div class="fub-meter" :class="{warn: budget.heaviest > budget.composedCap}"><span x-text="'Heaviest F.U.N. ' + fmtBytes(budget.heaviest) + ' / ' + fmtBytes(budget.composedCap)"></span><div class="bar"><i :style="'width:' + pct(budget.heaviest, budget.composedCap) + '%'"></i></div></div>
                    <div class="fub-meter"><span x-text="'Large items ' + budget.largeItems + ' / ' + budget.largeCap"></span></div>
                    <button class="fub-btn alt" style="margin-top:10px" x-show="hasAssistant" @click="openAssistant()" data-fub="assistant-open">Design assistant</button>
                </div>

                {{-- ======== editor ======== --}}
                <div>
                    <div class="fub-tabs">
                        <button :class="{on: tab === 'layers'}" @click="tab = 'layers'">Layers &amp; items</button>
                        <button :class="{on: tab === 'settings'}" @click="tab = 'settings'" x-show="!published">Settings</button>
                        <button :class="{on: tab === 'commit'}" @click="tab = 'commit'" x-show="!committed" data-fub="tab-commit">Commit</button>
                        <button :class="{on: tab === 'publish'}" @click="tab = 'publish'; preflight()" x-show="committed && !published" data-fub="tab-publish">Publish</button>
                        <button :class="{on: tab === 'manage'}" @click="tab = 'manage'" x-show="published" data-fub="tab-manage">Manage</button>
                    </div>

                    {{-- layers & items --}}
                    <div x-show="tab === 'layers'">
                        <template x-for="(l, li) in d.layers" :key="l.key">
                            <div class="isl-panel" style="padding:12px 14px">
                                <div class="fub-layer" style="background:none;padding:0">
                                    <b class="grow" x-text="(li + 1) + '. ' + l.name"></b>
                                    <span class="fub-badge" x-text="l.required ? 'required' : 'optional'"></span>
                                    <template x-if="!published">
                                        <span>
                                            <button class="isl-link" @click="moveLayer(li, -1)" title="Move toward the back (drawn earlier)" aria-label="Move layer toward the back">↑</button>
                                            <button class="isl-link" @click="moveLayer(li, 1)" title="Move toward the front (drawn later)" aria-label="Move layer toward the front">↓</button>
                                            <button class="isl-link" @click="renameLayer(l)">Rename</button>
                                            <button class="isl-link" @click="toggleRequired(l)" x-text="l.required ? 'Make optional' : 'Make required'"></button>
                                            <button class="isl-link" @click="deleteLayer(l)">Delete</button>
                                        </span>
                                    </template>
                                </div>
                                <div class="fub-items" style="margin-top:10px">
                                    <template x-for="it in itemsOf(l.key)" :key="it.id">
                                        <div class="fub-item" :class="{on: picks[l.key] === it.id}" @click="pick(l, it)" data-fub="studio-item">
                                            <div class="fub-thumb"><img :src="draftItemImg(it)" :alt="it.name" loading="lazy"></div>
                                            <b x-text="it.name"></b>
                                            <small x-text="fmtDust(it.price) + ' · ' + fmtBytes(it.size) + (it.maxMint ? ' · max ' + it.maxMint : '') + (it.increase > 0 ? ' · curve' : '')"></small>
                                            <small><span class="fub-badge ai" x-show="it.aiAssisted">AI</span><span class="fub-badge" x-show="it.chainState !== 'synced' && committed" x-text="it.chainState"></span></small>
                                            <small x-show="!published || !it.chainItemId || it.chainState === 'new'"><button class="isl-link" @click.stop="openItem(it)">Edit</button><button class="isl-link" @click.stop="deleteItem(it)">Delete</button></small>
                                        </div>
                                    </template>
                                    <button class="fub-item" @click="openItem(null, l.key)" style="display:flex;align-items:center;justify-content:center;min-height:120px" data-fub="add-item">+ Add item</button>
                                </div>
                            </div>
                        </template>
                        <div class="isl-panel" x-show="!published">
                            <h3>Add a layer</h3>
                            <p class="muted">Layers stack in this order: the first is drawn at the back (a background), the last on top.</p>
                            <div class="fub-row">
                                <input type="text" maxlength="24" placeholder="e.g. Background" x-model="newLayer.name" @keydown.enter="addLayer()" style="max-width:260px;border-radius:8px;border:0;padding:8px 10px;color:#1A1E2D" data-fub="layer-name">
                                <label><input type="checkbox" x-model="newLayer.required"> required</label>
                                <button class="fub-btn alt" @click="addLayer()" data-fub="add-layer">Add layer</button>
                            </div>
                        </div>
                    </div>

                    {{-- settings (before publish) --}}
                    <div x-show="tab === 'settings'" class="isl-panel fub-form">
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
                    <div x-show="tab === 'commit'" class="isl-panel" data-fub="commit-panel">
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
                    <div x-show="tab === 'publish'" class="isl-panel" data-fub="publish-panel">
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
                                            <select x-model.number="islandId" style="display:block;margin-top:4px;border-radius:8px;padding:6px;color:#1A1E2D" data-fub="island-picker">
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
                    <div x-show="tab === 'manage'" class="isl-panel" data-fub="manage-panel">
                        <p><a :href="'/fub/' + d.chainFubId">Open the public builder</a></p>
                        <div class="fub-row">
                            <button class="fub-btn alt" @click="setStatus('pause')">Pause minting</button>
                            <button class="fub-btn alt" @click="setStatus('resume')">Resume</button>
                            <button class="fub-btn alt" @click="setStatus('close')">Close for good</button>
                        </div>
                        <div class="fub-row"><input type="number" min="0" max="10" step="0.5" placeholder="New royalty %" x-model="royaltyNew" style="max-width:160px;border-radius:8px;border:0;padding:8px;color:#1A1E2D"><button class="fub-btn alt" @click="lowerRoyalty()">Lower royalty</button></div>
                        <div class="fub-row"><input type="text" placeholder="0x… payout address" x-model="payoutNew" style="max-width:260px;border-radius:8px;border:0;padding:8px;color:#1A1E2D"><button class="fub-btn alt" @click="setPayout()">Change payout</button></div>
                        <h3>New items</h3>
                        <p class="muted">Add items to free slots in your existing layers (Layers tab). Once they are on chain, make them live:</p>
                        <button class="fub-btn" @click="commitAdditions()">Make new items live</button>
                    </div>
                </div>
            </div>

            {{-- ======== item dialog ======== --}}
            <div class="fub-dialog" x-show="formOpen" x-cloak @keydown.escape.window="formOpen = false">
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

            {{-- ======== design assistant panel (optional) ======== --}}
            <template x-if="assistant && assistant.open">
                <div class="fub-assistant" data-fub="assistant">
                    <div class="fub-row" style="margin:0"><b class="grow" style="flex:1" x-text="assistant.meta ? assistant.meta.name : 'Design assistant'"></b><button class="isl-link" @click="assistant.open = false">Close</button></div>
                    <p class="isl-error" x-show="assistant.error" x-text="assistant.error"></p>
                    <template x-if="!assistant.token">
                        <div><p>Sign in to the assistant with your wallet (a free signature, no transaction).</p><button class="fub-btn" @click="aLogin()">Sign in</button></div>
                    </template>
                    <template x-if="assistant.token && assistant.tank">
                        <div style="display:flex;flex-direction:column;min-height:0;flex:1">
                            {{-- tank: refill any amount (burned on chain), quick chips, running totals (token-metered, Luca 2026-11-27) --}}
                            <div class="fub-row" style="margin:4px 0;font-size:14px;flex-wrap:wrap;gap:6px" data-fub="assistant-tank">
                                <b x-text="'Tank ' + fmtDust2(assistant.tank.balance)" data-fub="assistant-balance"></b>
                                <span class="muted" style="font-size:12px" x-show="assistant.convTotal > 0" x-text="'this conversation: ' + fmtDust2(assistant.convTotal)" data-fub="assistant-conv-total"></span>
                            </div>
                            <div class="fub-row" style="margin:2px 0;font-size:13px;flex-wrap:wrap;gap:6px" data-fub="assistant-refill">
                                <input type="text" inputmode="numeric" x-model="assistant.refillAmount" :placeholder="'Đ ' + assistant.meta.refill.min + '–' + assistant.meta.refill.max.toLocaleString('en-US')" aria-label="Refill amount in ĐUST" style="border-radius:6px;color:#1A1E2D;background:#fff;width:120px;padding:2px 6px" data-fub="assistant-refill-amount">
                                <template x-for="p in (assistant.meta.refill.chips || [])"><button type="button" class="isl-link" style="font-size:12px" @click="assistant.refillAmount = String(p)" x-text="fmtDust(p)"></button></template>
                                <button class="fub-btn alt" style="padding:2px 10px" @click="aRefill()" :disabled="!!aRefillError() || !!assistant.busy" data-fub="assistant-refill-btn">Refill (burn)</button>
                            </div>
                            <p class="muted" style="margin:0;font-size:12px;color:#ffb4b4" x-show="assistant.refillAmount !== '' && aRefillError()" x-text="aRefillError()"></p>
                            <template x-if="assistant.dry">
                                <div class="isl-notice" data-fub="assistant-dry" style="margin:4px 0"><p style="margin:0 0 4px">Your tank ran dry. Refill to continue: your changes so far are saved, and the assistant picks up where it stopped when you say “continue”.</p>
                                    <button class="fub-btn" @click="aRefill()" :disabled="!!aRefillError() || !!assistant.busy" x-text="'Refill ' + (aRefillError() ? '' : fmtDust(Number(String(assistant.refillAmount).replace(/[,\s_]/g, ''))))"></button></div>
                            </template>
                            <div style="margin:4px 0;font-size:13px" data-fub="assistant-levels">
                                <template x-for="l in assistant.meta.levels" :key="l.key"><label style="display:block"><input type="radio" :value="l.key" x-model="assistant.level"> <b x-text="aLevelLabel(l)"></b> <span class="muted" x-text="aLevelBurn(l)"></span></label></template>
                                <small class="muted" style="display:block;font-size:11px;opacity:.75" x-show="assistant.meta.billing" x-text="'Billed for the tokens each message actually uses (' + Number(assistant.meta.billing ? assistant.meta.billing.dustPerUsd : 0).toLocaleString('en-US') + ' Đ per $1 of AI cost); the size only sets the model and how fast the tank empties.'"></small>
                            </div>
                            <div class="msgs" x-effect="assistant.messages.length; $nextTick(() => { $el.scrollTop = $el.scrollHeight; })"><template x-for="(m, mi) in assistant.messages" :key="mi"><div class="m" :class="m.role"><span x-text="m.content"></span><template x-if="m.receipt && aBurned(m.receipt)"><b data-fub="assistant-burned" style="display:block;margin-top:4px;font-size:12px" x-text="aBurned(m.receipt)"></b></template><template x-if="m.receipt"><small data-fub="assistant-receipt" style="display:block;margin-top:2px;font-size:12px;opacity:.7" x-text="aReceipt(m.receipt)"></small></template></div></template></div>
                            <template x-if="assistant.confirm"><div class="isl-notice"><p x-text="assistant.confirm.summary"></p><button class="fub-btn" @click="aConfirm(true)">Confirm</button> <button class="fub-btn alt" @click="aConfirm(false)">Cancel</button></div></template>
                            <textarea x-model="assistant.input" placeholder="Describe what to make or change…" style="border-radius:8px;padding:8px;color:#1A1E2D;background:#fff;min-height:60px"></textarea>
                            {{-- voice: up to 10 min (or what the tank affords), live timer + Đ counter while recording --}}
                            <div class="fub-row" style="margin:4px 0 0;font-size:13px" x-show="assistant.recording" data-fub="assistant-rec">
                                <b style="color:#ff5c5c">&#9679; REC</b>
                                <b data-fub="assistant-rec-timer" x-text="mmss(assistant.recSec) + ' / ' + mmss(assistant.recLimit)"></b>
                                <b data-fub="assistant-rec-cost" x-text="fmtDust2(aRecCost())"></b>
                                <span class="muted" x-text="'(' + fmtDust2(aVoiceRate() * 60) + ' per minute)'"></span>
                            </div>
                            <p class="muted" style="margin:2px 0 0;font-size:12px" x-show="!assistant.recording && assistant.voiceNote" x-text="assistant.voiceNote" data-fub="assistant-voice-note"></p>
                            <div class="fub-row" style="margin:6px 0 0">
                                <button class="fub-btn alt" @click="aRecord()" :disabled="!assistant.recording && !!assistant.busy" x-text="assistant.recording ? '■ Stop' : '🎤 Voice'" data-fub="assistant-voice"></button>
                                <button class="fub-btn" @click="aSend()" :disabled="!!assistant.busy || assistant.recording">Send</button>
                                <span class="muted" x-text="assistant.busy" data-fub="assistant-busy"></span>
                            </div>
                            <small class="muted" style="font-size:11px;opacity:.75" x-show="!assistant.recording" x-text="'Voice: up to ' + mmss(aVoiceLimit()) + (aVoiceLimit() < (assistant.meta.voice ? assistant.meta.voice.maxSeconds : 600) ? ' (what your tank can pay)' : '') + ' · ' + fmtDust2(aVoiceRate() * 60) + ' per minute'" data-fub="assistant-voice-limit"></small>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        </template>
    </div>
    <div class="fub-busy" x-show="busy" x-text="busy" x-cloak></div>
</section>
</x-guest-layout>
