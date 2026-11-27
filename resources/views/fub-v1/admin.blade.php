<x-guest-layout>
<section class="isl fub" data-fub="admin">
    <div class="container">
        <h1>FUB moderation</h1>
        <p class="muted">Hide = site-only (the chain is never purged). On-chain suspend needs the contract admin key:
            <span class="mono">flock ~/work/.testnet-flovatar-signer.lock flow transactions send transactions/admin/suspend_fub.cdc &lt;fubId&gt; true "&lt;reason&gt;" --signer flovatar -n testnet (cadence/fub)</span></p>
        @if(session('status'))<p class="isl-notice isl-n-ok">{{ session('status') }}</p>@endif
        <h2>Reports</h2>
        <table class="table" style="color:#fff;width:100%">
            <tr><th>#</th><th>FUB</th><th>Item</th><th>Reason</th><th>Details</th><th>Status</th><th></th></tr>
            @foreach($reports as $r)
                <tr><td>{{ $r->id }}</td><td><a href="/fub/{{ $r->fub_flow_id }}">#{{ $r->fub_flow_id }}</a></td><td>{{ $r->item_flow_id }}</td><td>{{ $r->reason }}</td><td>{{ $r->details }}</td><td>{{ $r->status }}</td>
                    <td><form method="post" action="/fub/admin/reports/{{ $r->id }}">@csrf<select name="status"><option>open</option><option>dismissed</option><option>actioned</option></select><button>Set</button></form></td></tr>
            @endforeach
        </table>
        <h2>FUBs</h2>
        <table class="table" style="color:#fff;width:100%">
            <tr><th>#</th><th>Name</th><th>Creator</th><th>Status</th><th>Hidden</th><th></th></tr>
            @foreach($fubs as $f)
                <tr><td>{{ $f->flow_id }}</td><td>{{ $f->name }}</td><td class="mono">{{ $f->creator }}</td><td>{{ $f->status }}{{ $f->suspended ? ' (suspended)' : '' }}</td><td>{{ $f->hidden_at ? 'yes: '.$f->hidden_reason : 'no' }}</td>
                    <td><form method="post" action="/fub/admin/{{ $f->flow_id }}/hide">@csrf<input type="hidden" name="hidden" value="{{ $f->hidden_at ? 0 : 1 }}"><input name="reason" placeholder="reason" maxlength="200"><button>{{ $f->hidden_at ? 'Unhide' : 'Hide' }}</button></form></td></tr>
            @endforeach
        </table>
    </div>
</section>
</x-guest-layout>
