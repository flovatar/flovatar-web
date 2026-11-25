<?php

namespace App\Http\Controllers;

use App\Services\Quests\PayoutLedger;
use App\Support\DustQuests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payout worker API (HMAC-SHA256, see PayoutLedger::signature). The worker runs on a separate box with the rewards key;
 * this side only hands out batches of verified claims and checks the reported transaction on chain.
 */
class QuestsPayoutController extends Controller
{
    public function __construct()
    {
        abort_unless(DustQuests::active(), 404); // HMAC worker API: works in stealth mode (no cookie)
    }

    private function guard(Request $request): ?JsonResponse
    {
        if ($err = PayoutLedger::authError($request)) {
            Log::warning('quests-payout: rejected request ('.$err.') from '.$request->ip());

            return response()->json(['error' => 'unauthorized'], 401);
        }

        return null;
    }

    public function reserve(Request $request, PayoutLedger $ledger): JsonResponse
    {
        if ($r = $this->guard($request)) {
            return $r;
        }

        return response()->json($ledger->reserve((int) $request->input('max', 20)));
    }

    public function report(Request $request, PayoutLedger $ledger): JsonResponse
    {
        if ($r = $this->guard($request)) {
            return $r;
        }
        $batch = (string) $request->input('batch_id', '');
        $tx = $request->input('tx_id');
        $out = $ledger->report($batch, $tx ? strtolower((string) $tx) : null, (array) $request->input('not_sent', []), (string) $request->input('reason', ''));

        return response()->json($out, $out['ok'] ? 200 : ($out['code'] ?? 422));
    }

    public function status(Request $request, PayoutLedger $ledger): JsonResponse
    {
        if ($r = $this->guard($request)) {
            return $r;
        }

        return response()->json($ledger->status());
    }
}
