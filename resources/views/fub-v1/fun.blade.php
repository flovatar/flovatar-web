<x-guest-layout>
@push('og')
    <meta property="og:title" content="F.U.N. #{{ $fun->flow_id }} · {{ $fub->name }} · Flovatar" />
    <meta property="og:image" content="{{ url('/fub/fun/'.$fun->flow_id.'.png') }}" />
@endpush
<section class="isl fub" data-fub="fun">
    <div class="container">
        <p class="isl-crumbs"><a href="/fub">FUB</a> › <a href="/fub/{{ $fub->flow_id }}">{{ $fub->name }}</a> › <span>F.U.N. #{{ $fun->flow_id }}</span></p>
        <h1>{{ $fub->name }} #{{ $fun->serial }}</h1>
        <div class="fub-grid">
            <div class="fub-canvas"><img src="{{ \App\Support\Fub::contentUrl('fun/'.$fun->flow_id.'.svg') }}" alt="F.U.N. #{{ $fun->flow_id }}"></div>
            <div class="isl-panel">
                <p>Owner: <span class="mono">{{ $fun->owner ?? 'unknown' }}</span></p>
                <p>Minted for Đ{{ rtrim(rtrim(number_format($fun->price, 2), '0'), '.') }} @if($fun->minted_at) on {{ $fun->minted_at->format('M j, Y') }} @endif</p>
                <h3>Traits</h3>
                <ul>
                    @foreach(($fub->layers ?? []) as $l)
                        @if(isset($fun->picks[$l['key']]) && ($it = $items[$fun->picks[$l['key']]] ?? null))
                            <li>{{ $l['name'] }}: <b>{{ $it->name }}</b> <span class="muted">({{ $it->rarity }})</span></li>
                        @endif
                    @endforeach
                </ul>
                <p><a href="/fub/{{ $fub->flow_id }}">Build your own</a></p>
            </div>
        </div>
        @include('fub-v1.partials.report', ['fubId' => $fub->flow_id, 'funId' => $fun->flow_id])
    </div>
</section>
</x-guest-layout>
