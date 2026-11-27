<x-guest-layout>
{{-- FUB v1 home (FUB_MODE): my FUBs + drafts (browser, wallet) and the directory (server, listed FUBs only). --}}
<section class="isl fub" x-data="fubHome()" data-fub="home">
    <div class="container">
        <p class="isl-eyebrow">Flovatar Universe Builder</p>
        <h1>Build your own F.U.N. collection</h1>
        <div class="isl-hero">
            <p>Turn your art into an on-chain builder. Upload your layers, set your prices, publish on your Asteroid Island, and collectors mint
               their own F.U.N.s in ĐUST. You keep 95% of every sale.</p>
            <p class="muted">Each FUB voucher you commit adds room: common 25 item slots and 500 mints, epic 125 and 2,500, legendary 625 and 12,500.
               Merge up to 30 vouchers before you publish.</p>
        </div>

        <div class="isl-panel" x-show="!wallet">
            <p>Connect your wallet to see your vouchers, drafts and FUBs.</p>
            <button class="fub-btn" @click="connect()">Connect wallet</button>
        </div>

        <template x-if="wallet">
            <div>
                <div class="isl-panel" x-show="loading"><p>Reading your wallet…</p></div>
                <p class="isl-notice isl-error" x-show="error" x-text="error" x-cloak></p>
                <div class="isl-panel" x-show="state" x-cloak data-fub="wallet">
                    <h2>Your wallet</h2>
                    <p>
                        <b x-text="voucherCount"></b> FUB voucher(s)
                        <template x-for="(ids, r) in (state ? state.vouchers : {})" :key="r"><span class="isl-chip" :class="'isl-r-' + r" x-text="r + ' ' + ids.length"></span></template>
                        · <b x-text="state ? state.islands.length : 0"></b> island(s) free for a FUB building
                    </p>
                    <p class="muted" x-show="state && !state.islands.length">You need an Asteroid Island without a FUB to publish. <a href="/islands/build">Terraform one</a>.</p>
                    <div class="fub-row">
                        <input type="text" maxlength="40" placeholder="Name your FUB" x-model="newName" @keydown.enter="create()" style="max-width:320px;border-radius:8px;border:0;padding:8px 10px;">
                        <button class="fub-btn" @click="create()" :disabled="creating" data-fub="new-draft">Start a new FUB</button>
                    </div>
                </div>

                <div x-show="drafts.length" x-cloak>
                    <h2>Your drafts</h2>
                    <div class="fub-cards">
                        <template x-for="d in drafts" :key="d.id">
                            <a :href="'/fub/studio/' + d.id" data-fub="draft-card">
                                <div class="t"><b x-text="d.name"></b><small x-text="d.status + ' · ' + d.items + ' item(s)' + (d.chainFubId ? ' · FUB #' + d.chainFubId : '')"></small></div>
                            </a>
                        </template>
                    </div>
                </div>
                <div x-show="fubs.length" x-cloak>
                    <h2>Your FUBs</h2>
                    <div class="fub-cards">
                        <template x-for="f in fubs" :key="f.id">
                            <a :href="f.status === 'building' ? '/fub' : f.url">
                                <div class="t"><b x-text="f.name"></b><small x-text="'#' + f.id + ' · ' + f.status + ' · ' + f.minted + ' minted'"></small></div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <h2>Fresh FUBs</h2>
        @if($fresh->isEmpty())
            <p class="isl-empty">No FUB is published yet. Yours could be the first.</p>
        @else
            <div class="fub-cards" data-fub="directory">
                @foreach($fresh as $f)
                    <a href="/fub/{{ $f->flow_id }}">
                        <div class="t"><b>{{ $f->name }}</b><small>#{{ $f->flow_id }} · {{ ucfirst($f->tier) }} · {{ number_format($f->minted) }} / {{ number_format($f->max_supply) }} minted · Island #{{ $f->island_id }}</small></div>
                    </a>
                @endforeach
            </div>
        @endif
        @if($directory->isNotEmpty())
            <h2>Directory</h2>
            <div class="fub-cards">
                @foreach($directory as $f)
                    <a href="/fub/{{ $f->flow_id }}"><div class="t"><b>{{ $f->name }}</b><small>#{{ $f->flow_id }} · {{ number_format($f->minted) }} minted</small></div></a>
                @endforeach
            </div>
        @endif
    </div>
</section>
</x-guest-layout>
