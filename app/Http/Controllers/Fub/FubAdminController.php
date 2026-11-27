<?php

namespace App\Http\Controllers\Fub;

use App\Http\Controllers\Controller;
use App\Models\Fub;
use App\Models\FubItem;
use App\Models\FubReport;
use App\Support\Fub as FubSupport;
use App\Support\FubAuth;
use Illuminate\Http\Request;

/**
 * FUB moderation (Q11 b / Q13 a). Hide = SITE-ONLY delist (pages, images, directory, API); the chain is never purged.
 * Suspend is the on-chain admin switch (contract Admin.setFubSuspended / setItemSuspended): it needs the contract
 * account's key, so this page prints the exact admin command instead of signing anything.
 */
class FubAdminController extends Controller
{
    protected function admin(): string
    {
        abort_unless(FubSupport::enabled(), 404);
        $a = FubAuth::address();
        abort_unless($a !== null && FubSupport::isAdmin($a), 404);

        return $a;
    }

    public function index()
    {
        $this->admin();

        return view('fub-v1.admin', [
            'reports' => FubReport::orderByRaw("status = 'open' desc")->orderByDesc('id')->limit(200)->get(),
            'fubs' => Fub::orderByDesc('flow_id')->limit(200)->get(),
        ]);
    }

    public function hide(Request $request, int $id)
    {
        $this->admin();
        $v = $request->validate(['hidden' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:200'], 'item' => ['nullable', 'integer']]);
        if (! empty($v['item'])) {
            FubItem::where('fub_flow_id', $id)->where('flow_id', $v['item'])->update(['hidden_at' => $v['hidden'] ? now() : null]);
        } else {
            Fub::where('flow_id', $id)->update(['hidden_at' => $v['hidden'] ? now() : null, 'hidden_reason' => $v['hidden'] ? ($v['reason'] ?? null) : null]);
        }

        return back()->with('status', $v['hidden'] ? 'Hidden on the site (chain untouched).' : 'Visible again.');
    }

    public function report(Request $request, int $reportId)
    {
        $this->admin();
        $v = $request->validate(['status' => ['required', 'in:open,dismissed,actioned']]);
        FubReport::where('id', $reportId)->update(['status' => $v['status']]);

        return back();
    }
}
