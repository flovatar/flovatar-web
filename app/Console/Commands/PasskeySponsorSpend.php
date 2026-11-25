<?php

namespace App\Console\Commands;

use App\Services\Passkey\FlowGateway;
use App\Services\Passkey\PasskeyPoc;
use App\Services\Passkey\SponsorSpend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-day passkey sponsor spend: prices the day's transactions from their results (fees + FLOW sent by the sponsor) and
 * writes one 'passkey-sponsor-spend' line to the log (scheduled daily 00:20 UTC for the previous day).
 */
class PasskeySponsorSpend extends Command
{
    protected $signature = 'passkey:sponsor-spend {--date= : YYYY-MM-DD (default: yesterday, UTC)} {--no-backfill : do not fetch missing fees}';

    protected $description = 'Summarise one day of passkey sponsor spending (fees + account funding) into the log';

    public function handle(): int
    {
        if (! config('passkey.enabled') || ! config('passkey.sponsor.address')) {
            $this->line('passkey sponsor not configured');

            return 0;
        }
        $day = $this->option('date') ? Carbon::parse($this->option('date'), 'UTC') : Carbon::yesterday('UTC');
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $flow = new FlowGateway;
        if (! $this->option('no-backfill')) {
            SponsorSpend::backfill($flow, $from, $to);
        }
        $summary = SponsorSpend::summary($from, $to);
        try {
            $summary['balance'] = (float) $flow->account(PasskeyPoc::sponsorAddress())['balance'];
        } catch (Throwable $e) {
            $summary['balance'] = null;
        }
        $summary['sponsor'] = PasskeyPoc::sponsorAddress();
        $summary['network'] = (string) config('services.flow.network');
        Log::info('passkey-sponsor-spend', $summary);
        $this->line(sprintf('%s %s: %d txs, fees %.6f FLOW, sponsor outflow %.6f FLOW, balance %s', $summary['network'], $from->toDateString(),
            $summary['transactions'], $summary['fees'], $summary['outflow'], $summary['balance'] === null ? '?' : number_format($summary['balance'], 4)));
        foreach ($summary['kinds'] as $kind => $k) {
            $this->line(sprintf('  %-16s %4d (failed %d)  fees %.6f  outflow %.6f', $kind, $k['count'], $k['failed'], $k['fees'], $k['outflow']));
        }

        return 0;
    }
}
