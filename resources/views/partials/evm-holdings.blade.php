{{-- Profile section: Flovatar-family items this Flow account holds on Flow EVM (EVM-A 2026-11). Param: $evmFlowAddress.
     Owner viewing their own profile gets "Bring back" buttons. resources/js/app/evm.js (evmHoldings). --}}
@if(config('evm.ui'))
@include('partials.evm-config')
<div class="evm-profile" x-data="evmHoldings('{{ $evmFlowAddress }}', null)" x-init="init()" data-evm="profile" x-cloak>
    <h3>On Flow EVM</h3>
    <p x-show="!evmAddress && !loading" class="evm-muted">No Flow EVM address for this account yet. Bridge an item from its page to create one.</p>
    <p x-show="evmAddress">Flow EVM address <a :href="explorer + '/address/' + evmAddress" target="_blank" rel="noopener" class="evm-mono" x-text="evmAddress"></a> · <a href="{{ route('evm.gallery') }}">EVM gallery</a></p>
    <p x-show="loading">Reading Flow EVM…</p>
    <p x-show="error" x-text="error"></p>
    <p x-show="evmAddress && !loading && items.length === 0" class="evm-muted">No Flovatar-family items on Flow EVM.</p>
    <div class="evm-grid">
        <template x-for="it in items" :key="it.contract + it.id">
            <div class="evm-card" data-evm="card">
                <div class="evm-img"><img :src="it.image || '/images/logo-rounded.png'" :alt="it.name" loading="lazy"></div>
                <div class="evm-body">
                    <p class="evm-name" x-text="it.name"></p>
                    <span class="evm-tag">Flow EVM</span>
                    <span class="evm-tag is-legacy" x-show="it.legacy">bridge-deployed contract</span>
                    <div class="evm-card-actions">
                        <a x-show="it.url" :href="it.url" class="btn btn-sm btn-gray"><span class="inner">View</span></a>
                        <button type="button" x-show="mine" class="btn btn-sm btn-green" @click.prevent="bridgeBack(it)" data-evm="profile-back"><span class="inner">Bring back</span></button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
@endif
