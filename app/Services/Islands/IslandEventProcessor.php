<?php

namespace App\Services\Islands;

use App\Jobs\RefreshIsland;
use App\Jobs\SyncIslandTemplates;
use App\Models\Island;
use App\Models\IslandResident;
use App\Services\Flow\FlowEventTypes;
use App\Support\Islands;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Applies FlovatarAsteroidIsland events (and NonFungibleToken.Deposited of island NFTs) to the islands tables.
 * Called from ProcessGraffleEvents in chain order (single flow-events worker). Payload-only and idempotent; after each
 * island event a debounced RefreshIsland job re-reads the island from chain (name/description, live resident validity)
 * and queues the static render.
 */
class IslandEventProcessor
{
    public static function suffix(string $type): ?string
    {
        if (! Islands::active()) {
            return null;
        }
        $prefix = 'A.'.Islands::contractHex().'.FlovatarAsteroidIsland.';

        return str_starts_with($type, $prefix) ? substr($type, strlen($prefix)) : null;
    }

    public function handles($event): bool
    {
        $type = (string) $event->type;
        if (self::suffix($type) !== null) {
            return true;
        }

        return $this->isIslandDeposit($event);
    }

    protected function isIslandDeposit($event): bool
    {
        return Islands::active() && FlowEventTypes::isStandardNft((string) $event->type)
            && str_ends_with((string) $event->type, '.NonFungibleToken.Deposited')
            && (($this->meta($event)['type'] ?? null) === Islands::typeId('NFT'));
    }

    protected function meta($event): array
    {
        $m = $event->flow_metadata;

        return $m instanceof \Illuminate\Support\Collection ? $m->toArray() : (array) $m;
    }

    protected static function addr($a): ?string
    {
        $a = strtolower(trim((string) $a));

        return preg_match('/^0x[0-9a-f]{1,16}$/', $a) ? '0x'.str_pad(substr($a, 2), 16, '0', STR_PAD_LEFT) : null;
    }

    /** @return bool processed (mark synced) */
    public function process($event): bool
    {
        $m = $this->meta($event);
        $at = $event->event_date ? Carbon::parse($event->event_date) : now();

        if ($this->isIslandDeposit($event)) {
            $id = (int) ($m['id'] ?? 0);
            $to = self::addr($m['to'] ?? null);
            if ($id <= 0 || ! $to) {
                return true; // deposit into an unstored collection (no owner): nothing to track
            }
            $island = Island::firstOrNew(['flow_id' => $id]);
            if (! $island->exists) {
                $island->rarity = 'common'; // placeholder until Terraformed / the chain refresh fills it
            }
            $island->flow_address = $to;
            $island->save();
            $this->residentsValidity($island);
            RefreshIsland::debounced($id);

            return true;
        }

        $suffix = self::suffix((string) $event->type);
        switch ($suffix) {
            case 'Terraformed':
                $id = (int) $m['id'];
                $island = Island::firstOrNew(['flow_id' => $id]);
                $island->fill([
                    'rarity' => (string) $m['rarity'],
                    'layers' => array_map('intval', (array) ($m['layers'] ?? [])),
                    'tint' => $m['tint'] ?? null,
                    'deed_ids' => array_map('intval', (array) ($m['deedIds'] ?? [])),
                    'deed_rarity' => (string) ($m['deedRarity'] ?? ''),
                    'fused' => (bool) ($m['fused'] ?? false),
                    'terraformed_at' => $at,
                    'terraform_tx' => $event->transaction_id,
                    'block_id' => $event->block_id,
                ]);
                if (! $island->flow_address && ($o = self::addr($m['owner'] ?? null))) {
                    $island->flow_address = $o;
                }
                $island->save();
                Cache::forget('islands:chain:config:'.Islands::contractHex()); // totalSupply
                RefreshIsland::debounced($id);

                return true;

            case 'IslandLayersChanged':
                Island::where('flow_id', (int) $m['id'])->update(['layers' => json_encode(array_map('intval', (array) ($m['layers'] ?? []))), 'tint' => $m['tint'] ?? null]);
                RefreshIsland::debounced((int) $m['id']);

                return true;

            case 'IslandNameChanged':
                Island::where('flow_id', (int) $m['id'])->update(['name' => mb_substr((string) ($m['name'] ?? ''), 0, 64)]);
                RefreshIsland::debounced((int) $m['id'], render: false);

                return true;

            case 'IslandDescriptionChanged':
            case 'IslandMetadataChanged':
                RefreshIsland::debounced((int) $m['id'], render: false);

                return true;

            case 'ResidentSet':
                $islandId = (int) $m['islandId'];
                IslandResident::updateOrCreate(['island_flow_id' => $islandId, 'slot' => (string) $m['slot']], [
                    'nft_type' => (string) $m['nftType'], 'nft_id' => (int) $m['nftId'],
                    'set_by' => self::addr($m['owner'] ?? null), 'set_at' => $at, 'valid' => true, 'reason' => null,
                ]);
                $this->recount($islandId);
                RefreshIsland::debounced($islandId);

                return true;

            case 'ResidentCleared':
                $islandId = (int) $m['islandId'];
                IslandResident::where('island_flow_id', $islandId)->where('slot', (string) $m['slot'])
                    ->where('nft_type', (string) $m['nftType'])->where('nft_id', (int) $m['nftId'])->delete();
                $this->recount($islandId);
                RefreshIsland::debounced($islandId);

                return true;

            case 'ResidentMoved':
                $islandId = (int) $m['islandId'];
                $from = (string) $m['fromSlot'];
                $to = (string) $m['toSlot'];
                DB::transaction(function () use ($islandId, $from, $to) {
                    $a = IslandResident::where('island_flow_id', $islandId)->where('slot', $from)->lockForUpdate()->first();
                    $b = IslandResident::where('island_flow_id', $islandId)->where('slot', $to)->lockForUpdate()->first();
                    if ($a) {
                        $a->update(['slot' => '__moving']);
                    }
                    if ($b) {
                        $b->update(['slot' => $from]);
                    }
                    if ($a) {
                        $a->update(['slot' => $to]);
                    }
                });
                RefreshIsland::debounced($islandId);

                return true;

            case 'TemplateCreated':
            case 'TemplateSvgAppended':
            case 'TemplateEnabled':
            case 'TemplateMetadataChanged':
            case 'SettingChanged':
                Cache::forget('islands:chain:config:'.Islands::contractHex());
                SyncIslandTemplates::dispatch()->delay(now()->addSeconds(20));

                return true;
        }

        return true; // other island events: nothing stored
    }

    /** Residents set by a previous owner are invalid ("island-owner-changed") until the new owner replaces them. */
    protected function residentsValidity(Island $island): void
    {
        IslandResident::where('island_flow_id', $island->flow_id)->where('set_by', '!=', $island->flow_address)
            ->update(['valid' => false, 'reason' => 'island-owner-changed']);
    }

    protected function recount(int $islandId): void
    {
        Island::where('flow_id', $islandId)->update(['residents_count' => IslandResident::where('island_flow_id', $islandId)->where('slot', '!=', 'caretaker')->count()]);
    }
}
