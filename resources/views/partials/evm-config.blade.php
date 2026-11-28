{{-- Flow EVM config for resources/js/app/evm.js (printed once per page, only when config('evm.ui')). --}}
@if(config('evm.ui'))
@once
@php
    $evmCfg = [
        'network' => config('evm.network'),
        'chainId' => config('evm.chain_id'),
        'chainName' => config('evm.chain_name'),
        'rpc' => config('evm.rpc'),
        'explorer' => config('evm.explorer'),
        'collections' => config('evm.collections'),
    ];
@endphp
<script>window.EVM_CFG = @json($evmCfg);</script>
@endonce
@endif
