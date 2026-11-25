<?php

namespace App\Http\Controllers;

use App\Models\PasskeyAccount;
use App\Models\QuestAccountFlag;
use App\Models\QuestChallenge;
use App\Models\QuestClaim;
use App\Models\QuestPayoutBatch;
use App\Services\Quests\QuestEngine;
use App\Support\DustQuests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * /quests/admin — claims review, freeze, amounts, budget, rewards-wallet balance. Only for a WALLET session whose
 * address is in ADMIN_ADDRESSES (DustQuests::adminAddress). The page is a static shell; data comes from /quests/admin/data.
 */
class QuestsAdminController extends Controller
{
    public function __construct()
    {
        abort_unless(DustQuests::enabled(), 404);
    }

    private function admin(Request $request): string
    {
        $a = DustQuests::adminAddress($request);
        if (! $a) {
            abort(response()->json(['error' => 'Sign in with an admin wallet.'], 403));
        }

        return $a;
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }

    public function page()
    {
        return response()->view('quests.admin')->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    public function data(Request $request, QuestEngine $engine): JsonResponse
    {
        $this->admin($request);
        $status = (string) $request->query('status', 'pending');
        $q = trim(strtolower((string) $request->query('q', '')));
        $claims = QuestClaim::query()
            ->when(in_array($status, ['pending', 'verified', 'paid', 'rejected'], true), fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where('address', 'like', '%'.str_replace(['%', '_'], '', $q).'%'))
            ->latest('id')->limit(200)->get();
        $accounts = PasskeyAccount::whereIn('id', $claims->pluck('passkey_account_id')->unique())->get()->keyBy('id');
        $flags = QuestAccountFlag::whereIn('passkey_account_id', $claims->pluck('passkey_account_id')->unique())->get()->keyBy('passkey_account_id');
        $frozen = QuestAccountFlag::where('frozen', true)->get()->map(fn ($f) => [
            'address' => optional(PasskeyAccount::find($f->passkey_account_id))->address, 'reason' => $f->frozen_reason, 'since' => optional($f->updated_at)->toIso8601String(),
        ])->values();
        $rewards = (string) config('quests.payout.rewards_address');
        $balance = null;
        if ($rewards) {
            try {
                $balance = Cache::remember('quests:rewards-balance', 30, fn () => $engine->chain()->dustBalance($rewards));
            } catch (Throwable $e) {
                Log::warning('quests: rewards balance '.$e->getMessage());
            }
        }

        return $this->json([
            'season' => QuestEngine::season(),
            'settings' => [
                'season_budget' => QuestEngine::seasonBudget(), 'daily_cap' => QuestEngine::dailyCap(), 'per_user_cap' => QuestEngine::defaultUserCap(),
                'payouts_paused' => QuestEngine::payoutsPaused(), 'claims_paused' => QuestEngine::claimsPaused(),
            ],
            'totals' => QuestEngine::seasonTotals(),
            'rewards' => ['address' => $rewards, 'balance' => $balance],
            'challenges' => $engine->challenges(false)->map(fn ($c) => [
                'id' => $c->id, 'key' => $c->key, 'title' => $c->title, 'description' => $c->description, 'amount' => (float) $c->amount,
                'verify' => $c->verify_type, 'active' => $c->active, 'sort' => $c->sort, 'max_claims_total' => $c->max_claims_total,
                'claims' => QuestClaim::where('challenge_id', $c->id)->where('status', '!=', 'rejected')->count(),
            ])->values(),
            'claims' => $claims->map(fn ($c) => [
                'id' => $c->id, 'address' => $c->address, 'key' => $c->challenge_key, 'amount' => (float) $c->amount, 'status' => $c->status,
                'evidence' => $c->evidence, 'note' => $c->review_note, 'tx_id' => $c->tx_id, 'batch_id' => $c->batch_id,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'email_verified' => (bool) optional($accounts->get($c->passkey_account_id))->email_verified_at,
                'frozen' => (bool) optional($flags->get($c->passkey_account_id))->frozen,
                'cap_override' => optional($flags->get($c->passkey_account_id))->cap_override,
            ])->values(),
            'frozen' => $frozen,
            'batches' => QuestPayoutBatch::latest('created_at')->limit(15)->get(['id', 'status', 'count', 'total', 'tx_id', 'error', 'reserved_at', 'reported_at']),
            'flowscan' => config('passkey.flowscan'),
        ]);
    }

    public function claimAction(Request $request, int $claim, string $action, QuestEngine $engine): JsonResponse
    {
        $admin = $this->admin($request);
        $c = QuestClaim::findOrFail($claim);
        $note = mb_substr(trim((string) $request->input('note', '')), 0, 255) ?: null;
        $out = $action === 'approve' ? $engine->approve($c, $note) : $engine->reject($c, $note);
        Log::info("quests-admin: {$admin} {$action} claim {$c->id} ({$c->challenge_key}, {$c->address}): ".($out['ok'] ? 'ok' : $out['error']));

        return $this->json($out, $out['ok'] ? 200 : 422);
    }

    public function account(Request $request, string $address, QuestEngine $engine): JsonResponse
    {
        $admin = $this->admin($request);
        $a = PasskeyAccount::where('address', strtolower($address))->firstOrFail();
        if ($request->has('frozen')) {
            $engine->freeze($a, $request->boolean('frozen'), mb_substr((string) $request->input('reason', ''), 0, 255) ?: null);
        }
        if ($request->has('cap_override')) {
            $v = $request->input('cap_override');
            $engine->setCapOverride($a, ($v === null || $v === '') ? null : max(0, (float) $v));
        }
        Log::info("quests-admin: {$admin} updated account {$a->address}: ".json_encode($request->only(['frozen', 'cap_override', 'reason'])));
        $f = QuestAccountFlag::where('passkey_account_id', $a->id)->first();

        return $this->json(['ok' => true, 'frozen' => (bool) optional($f)->frozen, 'cap_override' => optional($f)->cap_override]);
    }

    public function challenge(Request $request, int $challenge): JsonResponse
    {
        $admin = $this->admin($request);
        $c = QuestChallenge::findOrFail($challenge);
        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:0|max:100000',
            'active' => 'sometimes|boolean',
            'title' => 'sometimes|string|max:160',
            'description' => 'sometimes|nullable|string|max:1000',
            'sort' => 'sometimes|integer',
            'max_claims_total' => 'sometimes|nullable|integer|min:0',
        ]);
        $c->update($data);
        Cache::forget('quests:catalogue-synced');
        Log::info("quests-admin: {$admin} updated challenge {$c->key}: ".json_encode($data));

        return $this->json(['ok' => true]);
    }

    public function settings(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate([
            'season_budget' => 'sometimes|numeric|min:0|max:100000000',
            'daily_cap' => 'sometimes|numeric|min:0|max:100000000',
            'per_user_cap' => 'sometimes|numeric|min:0|max:1000000',
            'payouts_paused' => 'sometimes|boolean',
            'claims_paused' => 'sometimes|boolean',
        ]);
        foreach ($data as $k => $v) {
            QuestEngine::setSetting($k, in_array($k, ['payouts_paused', 'claims_paused'], true) ? (bool) $v : (float) $v);
        }
        Log::info("quests-admin: {$admin} settings ".json_encode($data));

        return $this->json(['ok' => true]);
    }

    /** Manual release of an open batch (only when the worker certainly did NOT send it: type the batch id to confirm). */
    public function releaseBatch(Request $request, string $batch): JsonResponse
    {
        $admin = $this->admin($request);
        $b = QuestPayoutBatch::findOrFail($batch);
        if ($b->status !== 'reserved' || (string) $request->input('confirm') !== $b->id) {
            return $this->json(['error' => 'Only an open batch can be released, and you must confirm its id.'], 422);
        }
        QuestClaim::where('batch_id', $b->id)->where('status', QuestClaim::VERIFIED)->update(['batch_id' => null]);
        $b->update(['status' => 'failed', 'error' => 'released by admin '.$admin, 'reported_at' => now()]);
        Log::warning("quests-admin: {$admin} released batch {$b->id}");

        return $this->json(['ok' => true]);
    }
}
