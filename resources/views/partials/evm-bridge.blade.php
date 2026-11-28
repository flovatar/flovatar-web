{{-- Flow EVM bridge panel for an item page (EVM-A 2026-11). Params: $evmSlug (key of config('evm.collections')),
     $evmId (Cadence id = ERC-721 token id). Owner in Cadence -> "Bridge to Flow EVM"; item on Flow EVM held by the
     visitor's COA -> "Bring back from Flow EVM"; otherwise read-only status. resources/js/app/evm.js (evmBridge). --}}
@if(config('evm.ui'))
@include('partials.evm-config')
<div class="evm-bridge" x-data="evmBridge('{{ $evmSlug }}', '{{ (int) $evmId }}')" x-init="init()" data-evm="bridge" x-cloak>
    <div class="evm-head">
        <span class="evm-badge" :class="evmOwner ? 'is-evm' : ''" x-text="evmOwner ? 'On Flow EVM' : 'Flow EVM'">Flow EVM</span>
        <span x-show="loading" class="evm-muted">Reading the bridge…</span>
        <span x-show="!loading && evmOwner" class="evm-muted">ERC-721 held by <a :href="explorer + '/address/' + evmOwner" target="_blank" rel="noopener" class="evm-mono" x-text="short(evmOwner)"></a><span x-show="coa && evmOwner === coa"> (your Flow EVM address)</span><span x-show="isLegacy"> · bridge-deployed contract (migrates to the Flovatar ERC-721 when it comes back)</span></span>
        <span x-show="!loading && !evmOwner && ownedInCadence" class="evm-muted">You can move this item to Flow EVM (MetaMask, EVM marketplaces) and back at any time.</span>
        <span x-show="!loading && !evmOwner && !ownedInCadence" class="evm-muted">Lives in Cadence. Owners can bridge it to Flow EVM from this page.</span>
    </div>
    <p x-show="error" class="evm-muted" x-text="error"></p>
    <p x-show="!loading && dapper() && (ownedInCadence || evmOwner)" class="evm-muted">Bridging needs a Flow Wallet (self-custody) account; Dapper wallets cannot sign bridge transactions.</p>
    <div class="evm-actions" x-show="!loading && !confirm">
        <button type="button" class="btn btn-sm btn-lilac" x-show="canBridgeTo()" @click.prevent.stop="ask('to')" data-evm="to"><span class="inner">Bridge to Flow EVM</span></button>
        <button type="button" class="btn btn-sm btn-green" x-show="canBridgeBack()" @click.prevent.stop="ask('from')" data-evm="from"><span class="inner">Bring back from Flow EVM</span></button>
        <a class="btn btn-sm btn-gray" href="{{ route('evm.gallery') }}"><span class="inner">EVM gallery</span></a>
    </div>
    <div class="evm-confirm" x-show="confirm" role="dialog" aria-label="Confirm bridge transaction">
        <strong x-text="confirm === 'to' ? 'Bridge this item to Flow EVM' : 'Bring this item back to your Flow collection'"></strong>
        <ul>
            <template x-if="confirm === 'to'"><li>Destination: <span x-text="destText()"></span>.</li></template>
            <template x-if="confirm === 'to'"><li>Fee: <span x-text="feeText()"></span>, paid in FLOW by your wallet.</li></template>
            <template x-if="confirm === 'to'"><li>The item is locked in the official Flow VM bridge while it is on Flow EVM; bring it back here any time.</li></template>
            <template x-if="confirm === 'from'"><li>Fee: a tiny FLOW bridge fee (no storage cost). The ERC-721 goes back into bridge escrow and the item returns to your collection.</li></template>
            <template x-if="confirm === 'from' && isLegacy"><li>This token is on the old bridge-deployed contract: the bridge burns it and the item comes back to you (next time it goes to Flow EVM it lands on the Flovatar ERC-721).</li></template>
        </ul>
        <div class="evm-actions">
            <button type="button" class="btn btn-sm btn-green" @click.prevent.stop="go()" data-evm="confirm"><span class="inner">Confirm in wallet</span></button>
            <button type="button" class="btn btn-sm btn-gray" @click.prevent.stop="confirm = null"><span class="inner">Cancel</span></button>
        </div>
    </div>
</div>
@endif
