<?php

namespace App\Console\Commands;

use App\Services\Flow\FlowEventTypes;
use App\Support\Blocklist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Flags NFTs that were deposited into a blocklisted address (2026-11-16 incident:
 * 0x0c7deb7a0c9cb424 received 1,998 Flobits from abused Sportvatar free packs).
 * Flagged items are hidden from marketplace listings/search and show a notice.
 */
class FlagIncidentItems extends Command
{
    protected $signature = 'flovatar:flag-incident-items {--since=2026-11-16 00:00:00 : only deposits at/after this block time}';

    protected $description = 'Flag NFTs received by blocklisted addresses (incident 2026-11-16)';

    public function handle(): int
    {
        $addresses = Blocklist::addresses();
        if (! $addresses) {
            $this->warn('No blocklisted addresses configured');

            return self::SUCCESS;
        }
        $a = FlowEventTypes::contractAddress();
        $map = [
            "A.$a.FlovatarComponent.Deposit" => 'App\Models\FlovatarComponent',
            "A.$a.Flovatar.Deposit" => 'App\Models\Flovatar',
            "A.$a.Flobot.Deposit" => 'App\Models\Flobot',
            "A.$a.FlovatarDustCollectible.Deposit" => 'App\Models\DustCollectible',
            "A.$a.FlovatarPack.Deposit" => 'App\Models\Pack',
        ];
        $now = now();
        $total = 0;
        foreach ($map as $type => $model) {
            $rows = DB::table('events')->where('type', $type)->where('event_date', '>=', $this->option('since'))
                ->whereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(flow_metadata, '$.to'))"), $addresses)
                ->get(['transaction_id', 'flow_metadata']);
            $insert = [];
            foreach ($rows as $r) {
                $m = json_decode($r->flow_metadata, true);
                $insert[] = ['item_type' => $model, 'flow_id' => (int) $m['id'], 'reason' => 'incident-2026-11-16',
                    'address' => $m['to'], 'transaction_id' => $r->transaction_id, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($insert, 500) as $chunk) {
                $total += DB::table('flagged_items')->insertOrIgnore($chunk);
            }
            $this->line("$model: ".count($insert).' deposits');
        }
        Cache::flush();
        $this->info("Flagged $total new items (total ".DB::table('flagged_items')->count().')');

        return self::SUCCESS;
    }
}
