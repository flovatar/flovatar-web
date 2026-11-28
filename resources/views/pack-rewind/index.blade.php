<x-guest-layout>
{{-- Pack Rewind (PACK_REWIND_MODE, TESTNET ONLY; KB notes/pack-rewind.md). Server-rendered timeline (works without JS);
     the Alpine component packRewind (resources/js/app/pack-rewind.js, lazy bundle "r") adds balances, buy, open, reveal. --}}
@push('og')
    <meta property="og:title" content="Pack Rewind · Flovatar (testnet)" />
    <meta property="og:description" content="Replay every Flovatar pack drop, from Genesis 2021 to the ĐUST packs, with the original contents and prices. Testnet only." />
    <meta name="robots" content="noindex, nofollow" />
@endpush
<section class="isl fub rw" x-data="packRewind(@js($series))" data-rewind="page" @keydown.escape.window="onEscape()">
    <div class="container">
        <p class="isl-eyebrow">Testnet · nothing here is real</p>
        <h1 class="rw-title">Pack Rewind</h1>
        <div class="isl-hero rw-hero">
            <p>Every pack we ever sold, from the Genesis drop of December 2021 to the ĐUST Flobit packs of 2023, re-minted on
               testnet with <b>exactly the contents they had on mainnet</b> and their original prices. Pick a year, buy a pack,
               open it, then mint your Flovatar from the Spark like it's 2021 again.</p>
        </div>

        {{-- wallet strip --}}
        <div class="isl-panel rw-wallet" data-rewind="wallet">
            <template x-if="!address">
                <div class="rw-row">
                    <p>Connect a testnet wallet (Flow Wallet) or sign in with a passkey to replay the packs.</p>
                    <button class="fub-btn" type="button" @click="connect()" data-rewind="connect">Connect</button>
                </div>
            </template>
            <template x-if="address">
                <div class="rw-row">
                    <div class="rw-bal">
                        <span><small>Account</small><b x-text="address"></b><em x-show="passkey" class="fub-badge sync">passkey</em></span>
                        <span><small>FLOW</small><b x-text="flow === null ? '…' : fmtNum(flow, 2)" data-rewind="flow"></b></span>
                        <span><small>ĐUST</small><b x-text="dust === null ? '…' : fmtNum(dust, 0)"></b></span>
                        <span><small>Replayed</small><b x-text="prog.done + ' / ' + prog.total + ' drops'"></b></span>
                    </div>
                    <div class="rw-grant" x-show="passkey && grant && grant.enabled" x-cloak data-rewind="grant">
                        <template x-if="grant && grant.state === 'none'">
                            <button class="fub-btn" type="button" @click="claimGrant()">Get my <span x-text="fmtNum(grant.amount, 0)"></span> testnet FLOW</button>
                        </template>
                        <span x-show="grant && grant.state === 'pending'">Sending your starting FLOW…</span>
                        <span x-show="grant && grant.state === 'sent'">✓ Starting FLOW received</span>
                        <span x-show="grant && grant.state === 'failed'" class="isl-error">
                            <span x-text="grant && grant.error ? grant.error : 'The starting FLOW could not be sent.'"></span>
                            <button class="fub-btn small" type="button" @click="claimGrant()">Retry</button>
                        </span>
                    </div>
                </div>
            </template>
            <p class="isl-notice isl-error" x-show="error" x-text="error" x-cloak data-rewind="error"></p>
            <div x-show="needsVerify && !passkey" x-cloak><button class="fub-btn" type="button" @click="verifyWallet()">Verify wallet (free signature)</button></div>
            <p class="rw-status" x-show="status" x-text="status" x-cloak role="status" aria-live="polite"></p>
        </div>

        {{-- packs in the wallet --}}
        <div class="isl-panel rw-owned" x-show="owned.length" x-cloak data-rewind="owned">
            <h2>Your packs</h2>
            <div class="rw-owned-list">
                <template x-for="p in owned" :key="p.collection + p.packId">
                    <div class="rw-owned-card">
                        <img :src="p.art" alt="" loading="lazy">
                        <div>
                            <b x-text="p.title"></b>
                            <small x-text="'Testnet pack #' + p.packId + ' · mainnet #' + p.mainnetId"></small>
                            <button class="fub-btn small" type="button" @click="openReveal(p, 'bought')" :disabled="busy !== ''" data-rewind="open">Open</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- timeline --}}
        <ol class="rw-timeline" data-rewind="timeline">
            @php($lastYear = null)
            @foreach($series as $s)
                @if($s['year'] !== $lastYear)
                    @php($lastYear = $s['year'])
                    <li class="rw-year"><span>{{ $s['year'] }}</span>@if($s['year'] === 2023)<small>Sportvatar joins the family</small>@endif</li>
                @endif
                <li class="rw-drop rw-{{ $s['collection'] }}" data-series="{{ $s['key'] }}">
                    <div class="rw-art"><img src="{{ $s['art'] }}" alt="{{ $s['title'] }} artwork" loading="lazy"></div>
                    <div class="rw-body" x-data="{ get s() { return series.find((x) => x.key === '{{ $s['key'] }}') || {} } }">
                        <p class="rw-date">{{ \Carbon\Carbon::parse($s['date'])->format('j M Y') }}@if($s['collection'] === 'sportvatar') · Sportvatar @endif</p>
                        <h2>{{ $s['title'] }}</h2>
                        <p class="rw-contents">{{ $s['contents'] }}</p>
                        <p class="rw-story">{{ $s['story'] }}</p>
                        <div class="rw-meta">
                            <span class="rw-price">{{ $s['currency'] === 'DUST' ? 'Đ'.number_format((float) $s['price']) : rtrim(rtrim(number_format((float) $s['price'], 2), '0'), '.').' FLOW' }}</span>
                            <span class="rw-left"><b x-text="fmtNum(s.remaining, 0)">{{ number_format($s['remaining']) }}</b> of <span x-text="fmtNum(s.supply, 0)">{{ number_format($s['supply']) }}</span> left</span>
                            <span class="rw-bar"><i :style="'width:' + soldPct(s) + '%'"></i></span>
                            <span class="rw-mine" x-show="mine[s.key]" x-cloak>✓ replayed <span x-text="mine[s.key]"></span>×</span>
                        </div>
                        <div class="rw-buy">
                            <template x-if="plan(s).mode === 'flow' || plan(s).mode === 'dust' || plan(s).mode === 'swap'">
                                <div class="rw-row">
                                    <button class="fub-btn" type="button" @click="buy(s, plan(s).mode === 'swap' ? 'swap' : null)" :disabled="busy !== ''"
                                            :data-rewind="'buy-' + s.key"
                                            x-text="busy === s.key ? 'Working…' : (plan(s).mode === 'swap' ? 'Buy with FLOW' : 'Buy for ' + fmtPrice(s.price, s.currency))"></button>
                                    <button class="fub-btn ghost" type="button" x-show="plan(s).alt === 'swap'" @click="buy(s, 'swap')" :disabled="busy !== ''">Pay with FLOW instead</button>
                                    <small x-show="s.currency === 'DUST' && quote" x-text="swapLabel(s)"></small>
                                </div>
                            </template>
                            <template x-if="plan(s).mode === 'none'">
                                <p class="rw-why"><span x-text="planText(s)"></span>
                                    <button class="fub-btn small" type="button" x-show="plan(s).reason === 'connect'" @click="connect()">Connect</button></p>
                            </template>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="muted rw-foot">Contents are the originals, read from mainnet (unopened packs straight from the chain, opened packs from their
            opening transactions). A few dozen early Flovatar packs and the Sportvatar free packs opened after our records stop were rebuilt with the
            same mix of items. Testnet FLOW only: nothing here has any value.</p>
    </div>

    {{-- buy → open → reveal dialog --}}
    <div class="rw-reveal" x-show="reveal.open" x-cloak role="dialog" aria-modal="true" aria-labelledby="rw-reveal-title" data-rewind="reveal">
        <div class="rw-reveal-box" :class="'is-' + reveal.stage">
            <template x-if="reveal.pack">
                <div>
                    <h2 id="rw-reveal-title" x-text="reveal.stage === 'done' ? 'Your ' + reveal.pack.title : reveal.pack.title"></h2>
                    <div class="rw-pack-art" x-show="reveal.stage === 'bought' || reveal.stage === 'opening'">
                        <img :src="reveal.pack.art" alt="" :class="reveal.stage === 'opening' ? 'shake' : 'float'">
                        <p x-show="reveal.stage === 'bought'">It's in your wallet. Ready to open it?</p>
                        <p x-show="reveal.stage === 'opening'">Opening… approve it, then hold tight.</p>
                    </div>
                    <div class="rw-items" x-show="reveal.stage === 'reveal' || reveal.stage === 'done'">
                        <template x-for="(it, i) in reveal.items" :key="i">
                            <div class="rw-item" :class="[i < reveal.shown ? 'in' : '', 'r-' + it.r]">
                                <div class="rw-item-img"><img :src="itemImage(it, reveal.pack)" alt="" loading="lazy"></div>
                                <b x-text="it.n"></b>
                                <small x-text="(it.r || '') + (it.c ? ' · ' + it.c : '')"></small>
                            </div>
                        </template>
                    </div>
                    <p class="muted" x-show="reveal.stage === 'done' && reveal.pack.source === 'regenerated'">This pack's original opening isn't in our records: it was rebuilt with the same mix of items.</p>
                    <div class="rw-row rw-reveal-actions">
                        <button class="fub-btn" type="button" data-autofocus x-show="reveal.stage === 'bought'" @click="open(reveal.pack)" :disabled="busy !== ''" data-rewind="open-now">Open now</button>
                        <a class="fub-btn" x-show="reveal.stage === 'done'" :href="builderUrl(reveal.pack)" data-rewind="builder"
                           x-text="builderLabel(reveal.pack)"></a>
                        <button class="fub-btn ghost" type="button" x-show="reveal.stage !== 'opening'" @click="closeReveal()" x-text="reveal.stage === 'done' ? 'Back to the timeline' : 'Later'"></button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>
</x-guest-layout>
