<?php

namespace App\Console\Commands;

use App\Services\Passkey\FlowGateway;
use App\Services\Passkey\PasskeyPoc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Passkey sponsor balance watchdog. Silent (exit 0) when the balance is fine; prints one WARNING line, logs a warning
 * and exits 1 when it is below the threshold (PASSKEY_SPONSOR_MIN_BALANCE, default 50 FLOW) or unreadable (exit 2).
 * Scheduled hourly when the passkey feature is on; the Hermes cron ~/.hermes/scripts/passkey-sponsor-watch.sh does the
 * same check from the agent box (Discord alert only when it prints).
 */
class PasskeySponsorWatch extends Command
{
    protected $signature = 'passkey:sponsor-watch {--threshold= : FLOW (default config passkey.sponsor.min_balance)} {--verbose-ok : print the balance when it is fine}';

    protected $description = 'Warn when the passkey sponsor balance is below the threshold (prints nothing when OK)';

    public function handle(): int
    {
        if (! config('passkey.enabled') || ! config('passkey.sponsor.address')) {
            if ($this->option('verbose-ok')) {
                $this->line('passkey sponsor not configured');
            }

            return 0;
        }
        $threshold = (float) ($this->option('threshold') ?? config('passkey.sponsor.min_balance'));
        $address = PasskeyPoc::sponsorAddress();
        $network = (string) config('services.flow.network');
        try {
            $balance = (float) (new FlowGateway)->account($address)['balance'];
        } catch (Throwable $e) {
            $this->line("WARNING passkey sponsor {$address} ({$network}): balance unreadable: ".substr($e->getMessage(), 0, 200));
            Log::warning('passkey-sponsor-watch: balance unreadable', ['address' => $address, 'error' => $e->getMessage()]);

            return 2;
        }
        if ($balance < $threshold) {
            $this->line(sprintf('WARNING passkey sponsor %s (%s) balance %.4f FLOW < %.2f — top it up (sign-ups and sponsored transactions stop when it runs dry)', $address, $network, $balance, $threshold));
            Log::warning('passkey-sponsor-watch: low balance', ['address' => $address, 'network' => $network, 'balance' => $balance, 'threshold' => $threshold]);

            return 1;
        }
        if ($this->option('verbose-ok')) {
            $this->line(sprintf('OK passkey sponsor %s (%s) balance %.4f FLOW >= %.2f', $address, $network, $balance, $threshold));
        }

        return 0;
    }
}
