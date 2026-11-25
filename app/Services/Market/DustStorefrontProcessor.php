<?php

namespace App\Services\Market;

use App\Models\DustAccessory;
use App\Models\DustCollectible;
use App\Models\Event;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\Sale;
use App\Models\SaleOffer;
use App\Services\Flow\FlowEventTypes;
use App\Services\Flow\FlowRestClient;
use App\Support\DustMarket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ĐUST marketplace indexer (KB notes/dust-marketplace.md): NFTStorefrontV2 events of Flovatar item types paid in
 * FlovatarDustToken.Vault, plus Listing.ResourceDestroyed of ANY currency (Cadence 1.0 cancels/cleanups emit only that).
 *
 * Runs before the legacy branches of ProcessGraffleEvents (which treated every storefront listing of a Flovatar/Flobit as
 * a Dapper listing). With DUST_MARKET_ENABLED off ĐUST events are acknowledged without writing anything (prod
 * unchanged); `dust-market:backfill` replays them when the market is switched on.
 */
class DustStorefrontProcessor
{
    public function __construct(protected ?FlowRestClient $rest = null)
    {
    }

    protected function rest(): FlowRestClient
    {
        return $this->rest ??= new FlowRestClient();
    }

    public static function suffix(string $type): ?string
    {
        if (! FlowEventTypes::isStorefront($type)) {
            return null;
        }
        foreach (['ListingAvailable', 'ListingCompleted', 'Listing.ResourceDestroyed'] as $s) {
            if (str_ends_with($type, '.NFTStorefrontV2.'.$s)) {
                return $s;
            }
        }

        return null;
    }

    /** True when this processor owns the event (the legacy branches must not see it). */
    public function handles($event): bool
    {
        $s = self::suffix((string) $event->type);
        if ($s === null) {
            return false;
        }
        if ($s === 'Listing.ResourceDestroyed') {
            return true;
        }

        return DustMarket::isDustVault($event->flow_metadata['salePaymentVaultType'] ?? null);
    }

    /** Returns true when the event is fully applied (mark synced). */
    public function process($event): bool
    {
        $meta = $event->flow_metadata;
        $meta = is_object($meta) && method_exists($meta, 'toArray') ? $meta->toArray() : (array) $meta;
        $s = self::suffix((string) $event->type);
        $isDust = DustMarket::isDustVault($meta['salePaymentVaultType'] ?? null);

        if ($s === 'Listing.ResourceDestroyed') {
            // cancel / cleanup of a listing (any currency): the offer row goes, matched by the global listing uuid
            if (! empty($meta['listingResourceID']) && (! $isDust || DustMarket::active())) {
                SaleOffer::where('listing_id', $meta['listingResourceID'])->delete();
            }

            return true;
        }
        if (! DustMarket::active()) {
            return true;
        }
        if ($s === 'ListingAvailable') {
            return $this->available($meta);
        }

        return $this->completed($meta, $event);
    }

    public static function modelFor(?string $nftTypeId): ?string
    {
        $kind = DustMarket::kinds()[(string) $nftTypeId] ?? null;

        return match ($kind) {
            'flovatar' => Flovatar::class,
            'flobit' => FlovatarComponent::class,
            'flobot' => Flobot::class,
            'collectible' => DustCollectible::class,
            'accessory' => DustAccessory::class,
            default => null,
        };
    }

    protected static function typeId($t): ?string
    {
        return is_array($t) ? ($t['typeID'] ?? null) : (is_string($t) ? $t : null);
    }

    protected function available(array $meta): bool
    {
        $class = self::modelFor(self::typeId($meta['nftType'] ?? null));
        if (! $class) {
            return true;
        }
        $item = $class::where('flow_id', $meta['nftID'])->first();
        if (! $item) {
            info('ĐUST listing skipped: item not in DB yet', ['type' => $class, 'id' => $meta['nftID']]);

            return false; // retried by the pending processor (the item usually arrives with its own event)
        }
        $price = (float) $meta['salePrice'];
        if ($price <= 0 || $price > 999999) {
            return true;
        }
        $check = $this->verify((string) $meta['storefrontAddress'], (int) $meta['listingResourceID']);
        if ($check === false) {
            // gone already (sold/cancelled before we processed it) or does not pay the Flovatar fee: not shown
            return true;
        }
        SaleOffer::updateOrCreate(
            ['item_id' => $item->id, 'item_type' => $class, 'flow_id' => $meta['nftID']],
            [
                'flow_metadata' => $item->flow_metadata,
                'flow_address' => $meta['storefrontAddress'],
                'price' => $price,
                'currency' => DustMarket::CURRENCY,
                'is_completed' => false,
                'is_dapper' => false,
                'listing_id' => $meta['listingResourceID'],
                'expires_at' => ! empty($meta['expiry']) && (int) $meta['expiry'] < 4102444800 ? Carbon::createFromTimestamp((int) $meta['expiry']) : null,
            ]
        );
        if (! $item->flow_address) {
            $item->update(['flow_address' => $meta['storefrontAddress']]);
        }

        return true;
    }

    protected function completed(array $meta, $event): bool
    {
        $listingId = $meta['listingResourceID'] ?? null;
        $offer = $listingId ? SaleOffer::where('listing_id', $listingId)->first() : null;
        $purchased = (bool) ($meta['purchased'] ?? false);
        $class = self::modelFor(self::typeId($meta['nftType'] ?? null));

        if ($purchased && $class) {
            $item = $offer?->item ?? $class::where('flow_id', $meta['nftID'])->first();
            $seller = $offer?->flow_address ?? $this->sellerFromEvents($listingId);
            $buyer = $this->buyerOf((string) $event->transaction_id);
            if ($item) {
                // the same event can be processed by two workers at once (stream dispatch + minutely flow:process):
                // firstOrCreate is not atomic, so serialise per purchase transaction + listing
                Cache::lock('dust-market:sale:'.$event->transaction_id.':'.$listingId, 20)->block(10, fn () => Sale::firstOrCreate(
                    [
                        'item_id' => $item->id,
                        'item_type' => $class,
                        'transaction_id' => $event->transaction_id,
                    ],
                    [
                        'price' => (float) $meta['salePrice'],
                        'currency' => DustMarket::CURRENCY,
                        'flow_buyer_address' => $buyer,
                        'flow_seller_address' => $seller,
                        'is_dapper' => false,
                        'completed_at' => $event->event_date ?? Carbon::now(),
                    ]
                ));
            }
        }
        if ($listingId) {
            SaleOffer::where('listing_id', $listingId)->delete();
        }

        return true;
    }

    /** Storefront address of a listing from its stored ListingAvailable event. */
    protected function sellerFromEvents($listingId): ?string
    {
        if (! $listingId) {
            return null;
        }
        $type = 'A.'.FlowEventTypes::storefrontAddress().'.NFTStorefrontV2.ListingAvailable';
        $ev = Event::where('type', $type)->where('flow_metadata->listingResourceID', $listingId)->first();

        return $ev ? ($ev->flow_metadata['storefrontAddress'] ?? null) : null;
    }

    /** Buyer = the purchase transaction's authorizer (wallet or passkey account; the sponsor only pays). */
    protected function buyerOf(string $txId): ?string
    {
        try {
            $tx = $this->rest()->transaction($txId);
            $a = $tx['authorizers'][0] ?? null;

            return $a ? '0x'.str_pad(strtolower(preg_replace('/^0x/', '', (string) $a)), 16, '0', STR_PAD_LEFT) : null;
        } catch (Throwable $e) {
            info('ĐUST sale: buyer lookup failed', ['tx' => $txId, 'e' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Chain check of a ĐUST listing: exists, not purchased, seller still holds the NFT, not expired and pays at least the
     * marketplace fee (Flovatar.getMarketplaceCut()) to the Flovatar account. true = valid, false = invalid/gone,
     * null = could not check (network) -> keep, dust-market:reconcile re-checks.
     */
    public function verify(string $storefront, int $listingId): ?bool
    {
        try {
            $res = $this->rest()->script(self::verifyScript(), [
                ['type' => 'Address', 'value' => $storefront],
                ['type' => 'Array', 'value' => [['type' => 'UInt64', 'value' => (string) $listingId]]],
            ]);

            return (bool) (is_array($res) ? ($res[(string) $listingId] ?? $res[$listingId] ?? false) : false);
        } catch (Throwable $e) {
            info('ĐUST listing check failed', ['listing' => $listingId, 'e' => $e->getMessage()]);

            return null;
        }
    }

    /** Batch variant: listing id => valid. Missing ids are invalid. Throws on network errors. */
    public function verifyMany(string $storefront, array $listingIds): array
    {
        $res = $this->rest()->script(self::verifyScript(), [
            ['type' => 'Address', 'value' => $storefront],
            ['type' => 'Array', 'value' => array_map(fn ($id) => ['type' => 'UInt64', 'value' => (string) $id], array_values($listingIds))],
        ]);
        $out = [];
        foreach ($listingIds as $id) {
            $out[(string) $id] = (bool) (is_array($res) ? ($res[(string) $id] ?? $res[$id] ?? false) : false);
        }

        return $out;
    }

    public static function verifyScript(): string
    {
        $sf = '0x'.FlowEventTypes::storefrontAddress();
        $fl = '0x'.DustMarket::contractHex();

        return <<<CDC
import NFTStorefrontV2 from {$sf}
import Flovatar, FlovatarDustToken from {$fl}

access(all) fun main(storefront: Address, ids: [UInt64]): {UInt64: Bool} {
    let out: {UInt64: Bool} = {}
    let sf = getAccount(storefront).capabilities.borrow<&{NFTStorefrontV2.StorefrontPublic}>(NFTStorefrontV2.StorefrontPublicPath)
    let now = UInt64(getCurrentBlock().timestamp)
    for id in ids {
        var ok = false
        if sf != nil {
            if let l = sf!.borrowListing(listingResourceID: id) {
                let d = l.getDetails()
                var fee = 0.0
                for cut in d.saleCuts {
                    if cut.receiver.address == {$fl} {
                        fee = fee + cut.amount
                    }
                }
                ok = d.salePaymentVaultType == Type<@FlovatarDustToken.Vault>() && !d.purchased && d.expiry > now
                    && l.hasListingBecomeGhosted() && fee + 0.00000100 >= d.salePrice * Flovatar.getMarketplaceCut()
            }
        }
        out[id] = ok
    }
    return out
}
CDC;
    }
}
