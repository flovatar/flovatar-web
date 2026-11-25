<x-guest-layout>
{{-- ĐUST Quests (testnet, DUST_QUESTS_ENABLED). Static shell: no user data; resources/js/quests.js (dustQuests) loads /quests/state. --}}
<section class="quests-page" x-data="dustQuests" x-init="init()" data-quests="page">
    <div class="container">
        <header class="quests-hero">
            <span class="quests-eyebrow">Season 1 · new accounts</span>
            <h1>ĐUST Quests</h1>
            <p>Finish one-time quests, earn up to <b>{{ number_format((float) config('quests.season.per_user_cap')) }} ĐUST</b> and spend it on your first marketplace find, your first Psyche Likee or a Flovatar. No wallet, no fees: your passkey does the work.</p>
        </header>

        <div class="quests-loading" x-show="!loaded"><span>Loading your quests…</span></div>

        {{-- signed out / wallet session / email not verified / frozen --}}
        <div class="quests-gate" x-show="loaded && !state.eligible" x-cloak>
            <template x-if="state.reason === 'signed_out'">
                <div>
                    <h2>Sign in to start</h2>
                    <p>ĐUST Quests are for accounts created with <b>Email + passkey</b>. Create one in a few seconds: no wallet needed.</p>
                    <button type="button" class="btn btn-pink" @click.prevent="signIn()"><span class="inner">Sign in</span></button>
                </div>
            </template>
            <template x-if="state.reason === 'not_passkey'">
                <div>
                    <h2>For Email + passkey accounts</h2>
                    <p>ĐUST Quests welcome new collectors who signed up with Email + passkey. Wallet accounts are not part of this season.</p>
                </div>
            </template>
            <template x-if="state.reason === 'email' || state.reason === 'frozen'">
                <div>
                    <h2 x-text="state.reason === 'email' ? 'Confirm your email' : 'Quests paused'"></h2>
                    <p x-text="state.message"></p>
                    <button type="button" class="btn btn-pink" x-show="state.reason === 'email'" @click.prevent="openPanel()"><span class="inner">Open my account</span></button>
                </div>
            </template>
        </div>

        <div x-show="loaded && state.eligible" x-cloak>
            <div class="quests-progress" data-quests="progress">
                <div class="quests-progress-top">
                    <div>
                        <span class="quests-label">Earned this season</span>
                        <span class="quests-big"><span x-text="fmt(earned)"></span><small x-text="' / ' + fmt(cap)"></small></span>
                    </div>
                    <div class="quests-progress-meta">
                        <span><b x-text="done"></b> of <b x-text="(state.challenges || []).length"></b> quests done</span>
                        <span>Paid <b x-text="fmt(state.paid)"></b> · on the way <b x-text="fmt(Number(state.verified || 0) + Number(state.pending || 0))"></b></span>
                        <span>Streak <b x-text="(state.streak || 0) + ' / ' + (state.streak_days || 7)"></b> days</span>
                    </div>
                </div>
                <div class="quests-bar quests-bar-lg" role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100"><span class="quests-bar-fill" :style="'width:' + pct + '%'"></span></div>
                <p class="quests-hint">Rewards arrive in your account (<span class="mono" x-text="state.address"></span>) a few minutes after a quest is done. Each quest counts once per account.</p>
                <p class="quests-alert" x-show="state.claims_paused">Quests are paused for a moment. Please come back later.</p>
                <p class="quests-alert" x-show="earned >= cap">You reached the season cap of <span x-text="fmt(cap)"></span>. Thank you for playing!</p>
            </div>

            <p class="quests-msg ok" x-show="msg && msgKey === 'discord'" x-text="msg"></p>
            <p class="quests-msg err" x-show="err && msgKey === 'discord'" x-text="err"></p>

            <div class="quests-grid">
                <template x-for="c in state.challenges" :key="c.key">
                    <article class="quest-card" :class="'is-' + c.status" :data-quest="c.key">
                        <div class="quest-top">
                            <span class="quest-amount" x-text="fmt(c.awarded !== null && c.awarded !== undefined ? c.awarded : c.amount)"></span>
                            <span class="quest-chip" x-show="!isOpen(c)" x-text="statusLabel(c)"></span>
                        </div>
                        <h3 x-text="c.title"></h3>
                        <p class="quest-desc" x-text="c.description"></p>
                        <p class="quest-note" x-show="c.note" x-text="c.note"></p>
                        <p class="quest-paid" x-show="c.status === 'paid' && c.tx_id"><a :href="txUrl(c.tx_id)" target="_blank" rel="noopener">View payout on Flowscan</a></p>

                        <div class="quest-actions" x-show="isOpen(c) && earned < cap">
                            {{-- honor click: open the profile, claim after a few seconds --}}
                            <template x-if="c.verify === 'honor'">
                                <div class="quest-row">
                                    <button type="button" class="btn btn-sm" @click.prevent="startHonor(c)"><span class="inner">Open</span></button>
                                    <button type="button" class="btn btn-pink btn-sm" :disabled="busy === c.key || honorWait(c) === null || honorWait(c) > 0" @click.prevent="claim(c)">
                                        <span class="inner" x-text="honorWait(c) === null ? 'Claim' : (honorWait(c) > 0 ? 'Claim in ' + honorWait(c) + ' s' : 'Claim ' + fmt(c.amount))"></span>
                                    </button>
                                </div>
                            </template>

                            <template x-if="c.verify === 'discord'">
                                <div class="quest-row">
                                    <template x-if="c.configured">
                                        <div class="quest-row">
                                            <a class="btn btn-sm" :href="c.invite" target="_blank" rel="noopener"><span class="inner">Join the server</span></a>
                                            <a class="btn btn-pink btn-sm" href="/quests/discord/connect" data-quests="discord-connect"><span class="inner">Connect Discord</span></a>
                                        </div>
                                    </template>
                                    <template x-if="!c.configured"><span class="quest-soon">Opens soon</span></template>
                                    <span class="quest-sub" x-show="c.discord && !c.discord.in_guild">Connected as <b x-text="c.discord ? c.discord.username : ''"></b>, not in the server yet.</span>
                                </div>
                            </template>

                            <template x-if="c.verify === 'share'">
                                <div class="quest-share">
                                    <a class="btn btn-sm" :href="shareIntent()" target="_blank" rel="noopener"><span class="inner">Post on X</span></a>
                                    <label :for="'share-' + c.key">Link to your post</label>
                                    <input :id="'share-' + c.key" type="url" inputmode="url" class="quests-input" placeholder="https://x.com/you/status/…" x-model="shareUrl" maxlength="300">
                                    <button type="button" class="btn btn-pink btn-sm" :disabled="busy === c.key" @click.prevent="submitShare(c)"><span class="inner">Check my post</span></button>
                                </div>
                            </template>

                            <template x-if="c.verify === 'quiz'">
                                <div class="quest-quiz">
                                    <button type="button" class="btn btn-pink btn-sm" x-show="!quizOpen" @click.prevent="quizOpen = true" :disabled="c.attempts_left === 0"><span class="inner" x-text="c.attempts_left === 0 ? 'No tries left today' : 'Start the quiz'"></span></button>
                                    <form x-show="quizOpen" @submit.prevent="submitQuiz(c)" class="quiz-form">
                                        <template x-for="(q, qi) in state.quiz" :key="q.id">
                                            <fieldset class="quiz-q">
                                                <legend x-text="(qi + 1) + '. ' + q.q"></legend>
                                                <template x-for="(o, oi) in q.options" :key="oi">
                                                    <label class="quiz-opt" :class="{ 'is-on': answers[qi] === oi }">
                                                        <input type="radio" :name="'q' + qi" :value="oi" @change="answers[qi] = oi" :checked="answers[qi] === oi">
                                                        <span x-text="o"></span>
                                                    </label>
                                                </template>
                                            </fieldset>
                                        </template>
                                        <p class="quest-sub" x-text="(c.attempts_left || 0) + ' tries left today'"></p>
                                        <button type="submit" class="btn btn-pink btn-sm" :disabled="busy === c.key"><span class="inner">Submit answers</span></button>
                                    </form>
                                </div>
                            </template>

                            {{-- automatic / on-chain / marketplace / wallet link / streak --}}
                            <template x-if="['auto', 'chain', 'market', 'link'].includes(c.verify)">
                                <div class="quest-row">
                                    <template x-if="c.action === 'panel'">
                                        <button type="button" class="btn btn-sm" @click.prevent="openPanel()"><span class="inner">Open account</span></button>
                                    </template>
                                    <template x-if="c.url && c.action !== 'panel'">
                                        <a class="btn btn-sm" :href="c.url === '/collection' ? '/collection/' + state.address : c.url"><span class="inner">Go</span></a>
                                    </template>
                                    <button type="button" class="btn btn-pink btn-sm" :disabled="busy === c.key" @click.prevent="claim(c)"><span class="inner" x-text="busy === c.key ? 'Checking…' : 'Check & claim'"></span></button>
                                </div>
                            </template>
                        </div>

                        <p class="quests-msg ok" x-show="msg && msgKey === c.key" x-text="msg"></p>
                        <p class="quests-msg err" x-show="err && msgKey === c.key" x-text="err" role="alert"></p>
                    </article>
                </template>
            </div>

            <p class="quests-fine">ĐUST is the utility token of Flovatar: use it for names, bios, Psyche Likees, Flobit upgrades and the marketplace. Quest rewards have no cash value. One account per person; we may pause rewards for accounts that look automated.</p>
        </div>
    </div>
</section>
</x-guest-layout>
