<x-guest-layout>
{{-- Flow EVM gallery (EVM-A 2026-11): read-only view of the Flovatar-family ERC-721s held by an EVM address (MetaMask
     or pasted), plus guidance. Static shell; resources/js/app/evm.js (evmHoldings) reads Flow EVM JSON-RPC + FCL. --}}
@include('partials.evm-config')
<section class="evm-page" x-data="evmHoldings(null, null)" x-init="init()" data-evm="gallery">
    <div class="container">
        <header>
            <span class="evm-eyebrow">Flovatar · Flow EVM</span>
            <h1>Your Flovatars on Flow EVM</h1>
            <p>Flovatar items live in Cadence and can travel to <strong>Flow EVM</strong> (the Ethereum-compatible side of Flow) through the
                official Flow VM bridge, then be shown in MetaMask and EVM marketplaces. Connect an EVM wallet or paste an address
                to see what it holds. Read only: nothing is signed here.</p>
        </header>

        @if(! config('evm.ui'))
            <div class="evm-panel"><p>The Flow EVM gallery is not available yet.</p></div>
        @else
        <div class="evm-panel">
            <div class="evm-row">
                <button type="button" class="btn btn-pink" @click.prevent="connectMetaMask()" data-evm="metamask"><span class="inner">Connect MetaMask (read only)</span></button>
                <span x-show="mm" class="evm-mono" x-text="'Connected: ' + mm" x-cloak></span>
                <button type="button" class="btn btn-sm" x-show="mm && chainOk === false" @click.prevent="addNetwork()" x-cloak><span class="inner">Add / switch to {{ config('evm.chain_name') }}</span></button>
            </div>
            <form class="evm-row" style="margin-top:12px" @submit.prevent="lookup()" role="search" aria-label="Look up an address">
                <input type="text" x-model="input" placeholder="0x… Flow EVM address (40 hex) or Flow address (16 hex)" autocomplete="off" spellcheck="false" aria-label="Address">
                <button type="submit" class="btn btn-sm btn-lilac"><span class="inner">Show items</span></button>
            </form>
            <p class="evm-err" x-show="error" x-text="error" x-cloak></p>
        </div>

        <div x-show="evmAddress" x-cloak>
            <h2>Items on <span class="evm-mono" x-text="short(evmAddress)"></span></h2>
            <p x-show="loading">Reading Flow EVM…</p>
            <p x-show="!loading && items.length === 0">No Flovatar-family items on this address.</p>
            <div class="evm-grid">
                <template x-for="it in items" :key="it.contract + it.id">
                    <div class="evm-card" data-evm="card">
                        <div class="evm-img"><img :src="it.image || '/images/logo-rounded.png'" :alt="it.name" loading="lazy"></div>
                        <div class="evm-body">
                            <p class="evm-name" x-text="it.name"></p>
                            <span class="evm-tag" x-text="'#' + it.id"></span>
                            <span class="evm-tag is-legacy" x-show="it.legacy">bridge-deployed contract</span>
                            <div class="evm-card-actions">
                                <a x-show="it.url" :href="it.url" class="btn btn-sm btn-gray"><span class="inner">View</span></a>
                                <a :href="explorer + '/token/' + it.contract + '/instance/' + it.id" target="_blank" rel="noopener" class="btn btn-sm btn-gray"><span class="inner">Explorer</span></a>
                                <button type="button" x-show="mm && hasMetaMask()" class="btn btn-sm btn-lilac" @click.prevent="watch(it)"><span class="inner">Add to MetaMask</span></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="evm-panel">
            <h2 style="margin-top:0">How it works</h2>
            <ol class="evm-steps">
                <li><strong>Bridge from flovatar.com.</strong> On an item you own, press <em>Bridge to Flow EVM</em>. Your Flow Wallet signs one
                    transaction; the item is locked in the official bridge and the ERC-721 appears on your Flow EVM address (your Flow Wallet's
                    EVM account, created automatically the first time). Fee: a few hundredths of a FLOW, plus 1 FLOW once per collection if nobody
                    bridged that collection before.</li>
                <li><strong>See it in MetaMask.</strong> Add the {{ config('evm.chain_name') }} network (chain id {{ config('evm.chain_id') }}, RPC
                    <span class="evm-mono">{{ config('evm.rpc') }}</span>), then use <em>Add to MetaMask</em> on a card above. Flow Wallet shows EVM items too.</li>
                <li><strong>Bring it back.</strong> Items held by your Flow Wallet's EVM address show a <em>Bring back from Flow EVM</em> button on the
                    item page and in your profile. If the ERC-721 sits in another EVM wallet (e.g. MetaMask), first send it to your Flow Wallet's
                    EVM address: only a Flow account can unlock the Cadence item.</li>
                <li><strong>Everything else stays.</strong> Accessories, names, stories and ĐUST rewards live in Cadence: bring an item back to
                    change it. Items locked in the bridge do not earn ĐUST or appear in Cadence-only features (islands, FUB) while on Flow EVM.</li>
            </ol>
            <p class="evm-muted" style="margin-top:10px">Contracts: Flovatar uses its own Flovatar ERC-721 (registered with the bridge as the official
                Flow EVM twin); the other collections use the ERC-721 the bridge deployed for them.
                <a href="{{ config('evm.explorer') }}" target="_blank" rel="noopener">Flow EVM explorer</a>.</p>
        </div>
        @endif
    </div>
</section>
</x-guest-layout>
