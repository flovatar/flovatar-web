<?php

namespace App\Services\Fub;

use App\Models\Fun;
use App\Services\Flow\FlowEventTypes;
use App\Support\Fub;
use Carbon\Carbon;

/**
 * Applies FlovatarUniverseBuilder events (address-exact) and NonFungibleToken.Deposited of F.U.N.s to the FUB tables.
 * Called from ProcessGraffleEvents in chain order. Every FUB event triggers a chain re-read of that FUB (FubIndexer);
 * FunMinted also stores the F.U.N.; Deposited updates the owner.
 */
class FubEventProcessor
{
    public const EVENTS = ['FubCommitted', 'FubAbsorbed', 'ManifestOp', 'FubInfoChanged', 'LayersChanged', 'OptionsChanged', 'ItemPut',
        'ItemUpdated', 'ItemReplaced', 'ItemRemoved', 'FubPublished', 'ItemsAdded', 'FubStatusChanged', 'RoyaltyLowered', 'PayoutChanged',
        'FunMinted', 'FubSuspended', 'ItemSuspended', 'CreatorRebound', 'TankRefilled'];

    public function __construct(protected ?FubIndexer $indexer = null)
    {
    }

    protected function indexer(): FubIndexer
    {
        return $this->indexer ??= app(FubIndexer::class);
    }

    public static function types(): array
    {
        return Fub::active() ? array_map(fn ($e) => Fub::typeId($e), self::EVENTS) : [];
    }

    public static function suffix(string $type): ?string
    {
        if (! Fub::active()) {
            return null;
        }
        $p = 'A.'.Fub::contractHex().'.'.Fub::NAME.'.';

        return str_starts_with($type, $p) ? substr($type, strlen($p)) : null;
    }

    protected function meta($event): array
    {
        $m = $event->flow_metadata;

        return $m instanceof \Illuminate\Support\Collection ? $m->toArray() : (array) $m;
    }

    public function handles($event): bool
    {
        $type = (string) $event->type;
        if (self::suffix($type) !== null) {
            return true;
        }

        return Fub::active() && FlowEventTypes::isStandardNft($type) && str_ends_with($type, '.NonFungibleToken.Deposited')
            && (($this->meta($event)['type'] ?? null) === Fub::typeId('NFT'));
    }

    public function process($event): bool
    {
        $m = $this->meta($event);
        $suffix = self::suffix((string) $event->type);
        if ($suffix === null) { // F.U.N. deposit
            $to = Fub::normalizeAddress($m['to'] ?? null);
            if ($to && ($id = (int) ($m['id'] ?? 0)) > 0) {
                Fun::where('flow_id', $id)->update(['owner' => $to]);
            }

            return true;
        }
        if ($suffix === 'TankRefilled' || $suffix === 'ManifestOp') {
            return true; // the assistant API verifies refills itself; manifest ops are covered by the FUB refresh
        }
        $fubId = (int) ($m['fubId'] ?? ($suffix === 'FunMinted' ? ($m['fubId'] ?? 0) : ($m['id'] ?? 0)));
        if ($suffix === 'FunMinted') {
            $this->indexer()->recordMint($m, $event->transaction_id ?? null, $event->event_date ? Carbon::parse($event->event_date) : now());
            $fubId = (int) ($m['fubId'] ?? 0);
        }
        if ($fubId > 0) {
            try {
                $this->indexer()->refreshFub($fubId);
            } catch (\Throwable $e) {
                report($e);

                return false; // retried by the event pipeline
            }
        }

        return true;
    }
}
