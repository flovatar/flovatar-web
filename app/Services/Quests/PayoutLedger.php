<?php

namespace App\Services\Quests;

use App\Models\QuestAccountFlag;
use App\Models\QuestClaim;
use App\Models\QuestPayoutBatch;
use App\Services\Passkey\FlowTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Payout side of ĐUST Quests. The web app NEVER holds the rewards key: a worker on another box (stand-in today: the
 * agent box, ~/.hermes/scripts/dust-quests-payout.py) pulls a batch over an HMAC-signed API, sends ONE transaction
 * for N recipients from the rewards wallet, and reports the tx id. We verify that transaction ON CHAIN before marking
 * claims paid (sealed, no error, signed by the rewards wallet, arguments = exactly this batch's claim ids, recipients
 * and amounts).
 *
 * Idempotency: only one batch is open at a time; until it is reported the worker gets the SAME batch (same id, same
 * claims) back, so a crash between "sent" and "reported" never pays twice (the worker's journal maps batch → tx).
 */
class PayoutLedger
{
    public function __construct(protected ?ChainEvidence $chain = null)
    {
        $this->chain ??= new ChainEvidence;
    }

    // ------------------------------------------------------------------ HMAC

    public static function signature(string $secret, string $ts, string $nonce, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $ts."\n".$nonce."\n".strtoupper($method)."\n".$path."\n".hash('sha256', $body), $secret);
    }

    /** null when the request is authentic, else the reason. */
    public static function authError(Request $request): ?string
    {
        $secret = (string) config('quests.payout.secret');
        if (strlen($secret) < 32) {
            return 'payout API not configured';
        }
        $ts = (string) $request->header('X-Quests-Timestamp');
        $nonce = (string) $request->header('X-Quests-Nonce');
        $sig = strtolower((string) $request->header('X-Quests-Signature'));
        if (! ctype_digit($ts) || abs(time() - (int) $ts) > (int) config('quests.payout.clock_skew', 300)) {
            return 'stale timestamp';
        }
        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) || ! preg_match('/^[0-9a-f]{64}$/', $sig)) {
            return 'bad headers';
        }
        $path = '/'.ltrim($request->path(), '/');
        $expected = self::signature($secret, $ts, $nonce, $request->method(), $path, (string) $request->getContent());
        if (! hash_equals($expected, $sig)) {
            return 'bad signature';
        }
        if (! Cache::add('quests:payout-nonce:'.$nonce, 1, 900)) {
            return 'replayed nonce';
        }

        return null;
    }

    // ------------------------------------------------------------------ reserve

    public static function ufix(float $v): string
    {
        return number_format(round($v, 8), 8, '.', '');
    }

    protected function payload(QuestPayoutBatch $b): array
    {
        $items = QuestClaim::where('batch_id', $b->id)->orderBy('id')->get(['id', 'address', 'amount'])
            ->map(fn ($c) => ['claim_id' => $c->id, 'address' => '0x'.FlowTransaction::addr($c->address), 'amount' => self::ufix((float) $c->amount)])->values();

        return [
            'batch_id' => $b->id, 'status' => $b->status, 'items' => $items, 'total' => self::ufix((float) $b->total),
            'rewards_address' => config('quests.payout.rewards_address'),
            'limits' => ['per_user_cap' => QuestEngine::defaultUserCap(), 'daily_cap' => QuestEngine::dailyCap(), 'season_budget' => QuestEngine::seasonBudget()],
        ];
    }

    /** The open batch (unchanged) or a new one built from verified claims within every cap. */
    public function reserve(int $max): array
    {
        $lock = Cache::lock('quests:payout-reserve', 30);
        $lock->block(10);
        try {
            $open = QuestPayoutBatch::where('status', 'reserved')->orderBy('reserved_at')->first();
            if ($open) {
                return $this->payload($open) + ['reused' => true];
            }
            if (QuestEngine::payoutsPaused()) {
                return ['batch_id' => null, 'items' => [], 'paused' => true];
            }
            $max = max(1, min($max, (int) config('quests.payout.max_batch', 20)));
            $season = QuestEngine::season();
            $paidOrReserved = fn ($q) => $q->where('season', $season)->where(fn ($q) => $q->where('status', QuestClaim::PAID)->orWhere(fn ($q) => $q->where('status', QuestClaim::VERIFIED)->whereNotNull('batch_id')));
            $todayLeft = QuestEngine::dailyCap() - (float) QuestClaim::where('season', $season)->where(function ($q) {
                $q->where(fn ($q) => $q->where('status', QuestClaim::PAID)->where('paid_at', '>=', now()->utc()->startOfDay()))
                    ->orWhere(fn ($q) => $q->where('status', QuestClaim::VERIFIED)->whereNotNull('batch_id'));
            })->sum('amount');
            $seasonLeft = QuestEngine::seasonBudget() - (float) QuestClaim::query()->where($paidOrReserved)->sum('amount');
            $frozen = QuestAccountFlag::where('frozen', true)->pluck('passkey_account_id')->all();
            $engine = QuestEngine::make();
            $candidates = QuestClaim::where('season', $season)->where('status', QuestClaim::VERIFIED)->whereNull('batch_id')
                ->when($frozen, fn ($q) => $q->whereNotIn('passkey_account_id', $frozen))
                ->orderBy('verified_at')->orderBy('id')->limit($max * 5)->get();
            $pick = [];
            $perAccount = [];
            $total = 0.0;
            foreach ($candidates as $c) {
                if (count($pick) >= $max) {
                    break;
                }
                $amt = (float) $c->amount;
                if ($amt <= 0 || $total + $amt > $todayLeft + 1e-6 || $total + $amt > $seasonLeft + 1e-6) {
                    continue;
                }
                $acct = $c->account;
                if (! $acct) {
                    continue;
                }
                $perAccount[$c->passkey_account_id] ??= (float) QuestClaim::query()->where($paidOrReserved)->where('passkey_account_id', $c->passkey_account_id)->sum('amount');
                if ($perAccount[$c->passkey_account_id] + $amt > $engine->capFor($acct) + 1e-6) {
                    continue;
                }
                $perAccount[$c->passkey_account_id] += $amt;
                $total += $amt;
                $pick[] = $c->id;
            }
            if (! $pick) {
                return ['batch_id' => null, 'items' => [], 'daily_left' => max(0, $todayLeft), 'season_left' => max(0, $seasonLeft)];
            }
            $batch = DB::transaction(function () use ($pick, $total) {
                $b = QuestPayoutBatch::create(['id' => (string) Str::uuid(), 'status' => 'reserved', 'count' => count($pick), 'total' => round($total, 8), 'reserved_at' => now()]);
                QuestClaim::whereIn('id', $pick)->whereNull('batch_id')->where('status', QuestClaim::VERIFIED)->update(['batch_id' => $b->id]);

                return $b;
            });

            return $this->payload($batch) + ['reused' => false];
        } finally {
            $lock->release();
        }
    }

    // ------------------------------------------------------------------ report

    /** Decode a JSON-Cadence argument without losing UFix64 precision. */
    protected static function rawArray($arg): array
    {
        return array_map(fn ($v) => (string) ($v['value'] ?? ''), (array) ($arg['value'] ?? []));
    }

    /**
     * Worker report for a batch:
     *  - tx_id given: verify on chain → paid (or pending: try again later, or failed → claims released)
     *  - tx_id null + released claim ids: the worker did NOT send (e.g. a recipient has no ĐUST vault yet) → release them
     */
    public function report(string $batchId, ?string $txId, array $notSent = [], ?string $reason = null): array
    {
        $b = QuestPayoutBatch::find($batchId);
        if (! $b) {
            return ['ok' => false, 'error' => 'unknown batch', 'code' => 404];
        }
        if ($b->status === 'paid') {
            return ['ok' => true, 'status' => 'paid', 'tx_id' => $b->tx_id, 'already' => true];
        }
        if ($b->status !== 'reserved') {
            return ['ok' => false, 'error' => 'batch is '.$b->status, 'code' => 409];
        }
        $claims = QuestClaim::where('batch_id', $b->id)->orderBy('id')->get()->keyBy('id');
        if (! $txId) {
            $ids = $notSent ? array_map('intval', $notSent) : $claims->keys()->all();
            QuestClaim::where('batch_id', $b->id)->whereIn('id', $ids)->update(['batch_id' => null]);
            $left = QuestClaim::where('batch_id', $b->id)->count();
            $b->update(['status' => $left ? 'reserved' : 'failed', 'error' => mb_substr((string) $reason, 0, 500), 'reported_at' => now()]);

            return ['ok' => true, 'status' => $left ? 'reserved' : 'released', 'released' => count($ids)];
        }
        if (! preg_match('/^[0-9a-f]{64}$/', $txId)) {
            return ['ok' => false, 'error' => 'bad tx id', 'code' => 422];
        }
        try {
            $r = $this->chain->result($txId);
            if (! in_array($r['status'], ['Sealed'], true) && $r['error'] === '') {
                return ['ok' => true, 'status' => 'pending', 'chain_status' => $r['status']];
            }
            if ($r['error'] !== '') {
                QuestClaim::where('batch_id', $b->id)->update(['batch_id' => null]);
                $b->update(['status' => 'failed', 'tx_id' => $txId, 'error' => mb_substr($r['error'], 0, 500), 'reported_at' => now()]);

                return ['ok' => true, 'status' => 'failed', 'released' => $claims->count()];
            }
            $tx = $this->chain->transaction($txId);
            $rewards = '0x'.FlowTransaction::addr((string) config('quests.payout.rewards_address'));
            if (! in_array($rewards, $tx['authorizers'], true)) {
                return ['ok' => false, 'error' => 'tx not authorized by the rewards wallet', 'code' => 422];
            }
            [$recipients, $amounts, $ids] = [self::rawArray($tx['arguments'][0] ?? []), self::rawArray($tx['arguments'][1] ?? []), self::rawArray($tx['arguments'][2] ?? [])];
            if (count($recipients) !== count($amounts) || count($ids) !== count($recipients)) {
                return ['ok' => false, 'error' => 'tx arguments malformed', 'code' => 422];
            }
            $sent = [];
            foreach ($ids as $i => $id) {
                $c = $claims->get((int) $id);
                if (! $c || '0x'.FlowTransaction::addr($recipients[$i]) !== '0x'.FlowTransaction::addr($c->address) || bccomp($amounts[$i], self::ufix((float) $c->amount), 8) !== 0) {
                    Log::warning('quests: payout tx '.$txId.' does not match batch '.$b->id.' at claim '.$id);

                    return ['ok' => false, 'error' => 'tx does not match the batch (claim '.$id.')', 'code' => 422];
                }
                $sent[] = (int) $id;
            }
            DB::transaction(function () use ($b, $sent, $txId) {
                QuestClaim::where('batch_id', $b->id)->whereIn('id', $sent)->update(['status' => QuestClaim::PAID, 'tx_id' => $txId, 'paid_at' => now()]);
                // claims of the batch that are not in the tx (worker skipped them) go back to the queue
                QuestClaim::where('batch_id', $b->id)->whereNotIn('id', $sent)->where('status', QuestClaim::VERIFIED)->update(['batch_id' => null]);
                $b->update(['status' => 'paid', 'tx_id' => $txId, 'reported_at' => now(), 'count' => count($sent),
                    'total' => (float) QuestClaim::whereIn('id', $sent)->sum('amount')]);
            });

            return ['ok' => true, 'status' => 'paid', 'paid' => count($sent), 'tx_id' => $txId];
        } catch (Throwable $e) {
            Log::warning('quests: payout report failed: '.$e->getMessage());

            return ['ok' => false, 'error' => 'could not verify the transaction yet: '.$e->getMessage(), 'code' => 502];
        }
    }

    public function status(): array
    {
        return [
            'season' => QuestEngine::season(), 'totals' => QuestEngine::seasonTotals(),
            'budget' => QuestEngine::seasonBudget(), 'daily_cap' => QuestEngine::dailyCap(), 'per_user_cap' => QuestEngine::defaultUserCap(),
            'paused' => QuestEngine::payoutsPaused(),
            'open_batch' => QuestPayoutBatch::where('status', 'reserved')->value('id'),
            'last_paid' => QuestPayoutBatch::where('status', 'paid')->latest('reported_at')->first(['id', 'tx_id', 'count', 'total', 'reported_at']),
        ];
    }
}
