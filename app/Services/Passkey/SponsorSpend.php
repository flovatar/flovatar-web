<?php

namespace App\Services\Passkey;

use App\Models\PasskeyTransaction;
use Carbon\CarbonInterface;
use Throwable;

/**
 * What the passkey sponsor spends: per transaction (fee = FlowFees.FeesDeducted, outflow = every FlowToken withdrawal
 * from the sponsor incl. the fee, account funding and storage top-ups) and per day (passkey:sponsor-spend).
 */
class SponsorSpend
{
    /** ['fee' => ?float, 'outflow' => float] from a transaction result (FlowGateway::result()). */
    public static function fromResult(array $result, string $sponsor): array
    {
        $sponsor = '0x'.FlowTransaction::addr($sponsor);
        $fee = null;
        $outflow = 0.0;
        foreach ($result['events'] ?? [] as $e) {
            $type = (string) ($e['type'] ?? '');
            $v = (array) ($e['values'] ?? []);
            if (str_ends_with($type, '.FlowFees.FeesDeducted')) {
                $fee = ($fee ?? 0.0) + (float) ($v['amount'] ?? 0);
            } elseif (str_ends_with($type, '.FlowToken.TokensWithdrawn')) {
                $from = isset($v['from']) && $v['from'] ? '0x'.FlowTransaction::addr((string) $v['from']) : null;
                if ($from === $sponsor) {
                    $outflow += (float) ($v['amount'] ?? 0);
                }
            }
        }

        return ['fee' => $fee, 'outflow' => round(max($outflow, (float) $fee), 8)];
    }

    /** Fill fee / sponsor_amount for finished rows that don't have them yet (max $limit lookups). */
    public static function backfill(FlowGateway $flow, CarbonInterface $from, CarbonInterface $to, int $limit = 300): int
    {
        $sponsor = PasskeyPoc::sponsorAddress();
        $n = 0;
        $rows = PasskeyTransaction::whereBetween('created_at', [$from, $to])->whereNull('fee')->orderBy('id')->limit($limit)->get();
        foreach ($rows as $row) {
            try {
                $r = $flow->result($row->tx_id);
            } catch (Throwable $e) {
                continue;
            }
            if (! in_array($r['status'], ['Sealed', 'Expired'], true) && $r['error'] === '') {
                continue;
            }
            $s = self::fromResult($r, $sponsor);
            $row->update(['fee' => $s['fee'] ?? 0, 'sponsor_amount' => $s['outflow'], 'status' => $r['error'] !== '' ? 'Failed' : $r['status']]);
            $n++;
            usleep(100_000); // access node rate limit is shared with the indexer
        }

        return $n;
    }

    /** Daily summary: counts per kind, total fees, total sponsor outflow. */
    public static function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = PasskeyTransaction::whereBetween('created_at', [$from, $to])->get(['kind', 'status', 'fee', 'sponsor_amount']);
        $kinds = [];
        foreach ($rows as $r) {
            $k = $r->kind;
            $kinds[$k] ??= ['count' => 0, 'failed' => 0, 'fees' => 0.0, 'outflow' => 0.0];
            $kinds[$k]['count']++;
            $kinds[$k]['failed'] += $r->status === 'Failed' ? 1 : 0;
            $kinds[$k]['fees'] += (float) $r->fee;
            $kinds[$k]['outflow'] += (float) ($r->sponsor_amount ?? $r->fee);
        }
        ksort($kinds);

        return [
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(),
            'transactions' => $rows->count(),
            'unpriced' => $rows->whereNull('fee')->count(),
            'fees' => round(array_sum(array_column($kinds, 'fees')), 8),
            'outflow' => round(array_sum(array_column($kinds, 'outflow')), 8),
            'kinds' => array_map(fn ($k) => ['count' => $k['count'], 'failed' => $k['failed'], 'fees' => round($k['fees'], 8), 'outflow' => round($k['outflow'], 8)], $kinds),
        ];
    }
}
