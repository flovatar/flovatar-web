<x-guest-layout>
    {{-- Standalone "Buy ĐUST" page (KB notes/flow-evm-program.md EVM-B): FLOW, a Flow EVM stablecoin, or another chain.
         404 unless the ĐUST swap feature is visible (routes/web.php). --}}
    <section class="hero-explore bd-hero">
        <div class="container">
            <h1><span class="text-blue">Buy ĐUST</span></h1>
            <div class="hero-content">
                <div class="col"><p>Get ĐUST for builders, packs and the design assistant, straight into your Flow wallet.</p></div>
                <div class="col"><p>Every option is a single on-chain transaction with a price guard: if the price moves past your limit, nothing is spent.</p></div>
            </div>
        </div>
    </section>
    @include('partials.buy-dust-widget')
</x-guest-layout>
