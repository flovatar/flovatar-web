<?php

namespace App\Support;

/**
 * ĐUST marketplace switch + helpers (KB notes/dust-marketplace.md).
 *
 * enabled() = DUST_MARKET_ENABLED (testnet only for now). Off: the indexer skips ĐUST storefront events, public queries
 * exclude currency DUST (scopeNotDust / currencyFilter), Blade prints no ĐUST markup and window.DUST_MARKET is absent,
 * so prod behaves exactly as before.
 */
class DustMarket
{
    public const CURRENCY = 'DUST';

    public static function enabled(): bool
    {
        return (bool) config('flovatar.dustMarket.enabled', false);
    }

    /** A.<flovatar>.FlovatarDustToken.Vault on this network (exact contract address, never a substring match). */
    public static function vaultTypeId(): string
    {
        return 'A.'.self::contractHex().'.FlovatarDustToken.Vault';
    }

    public static function contractHex(): string
    {
        return strtolower(str_replace('0x', '', (string) (config('services.flow.address') ?: config('flovatar.contractAddress'))));
    }

    /** Listing/sale currency from an event's salePaymentVaultType (Type object or String in ResourceDestroyed). */
    public static function currencyFromVault($vault): string
    {
        $id = is_array($vault) ? (string) ($vault['typeID'] ?? '') : (string) $vault;
        if ($id === self::vaultTypeId()) {
            return self::CURRENCY;
        }
        if (str_ends_with($id, '.FlowUtilityToken.Vault') || str_ends_with($id, '.DapperUtilityCoin.Vault')) {
            return 'DUC';
        }

        return 'FLOW';
    }

    public static function isDustVault($vault): bool
    {
        return self::currencyFromVault($vault) === self::CURRENCY;
    }

    /** NFT type id => site kind used by the dust-sale-* transactions. */
    public static function kinds(): array
    {
        $a = self::contractHex();

        return [
            "A.$a.Flovatar.NFT" => 'flovatar',
            "A.$a.FlovatarComponent.NFT" => 'flobit',
            "A.$a.Flobot.NFT" => 'flobot',
            "A.$a.FlovatarDustCollectible.NFT" => 'collectible',
            "A.$a.FlovatarDustCollectibleAccessory.NFT" => 'accessory',
        ];
    }

    /** Static-safe runtime config for the browser (window.DUST_MARKET): same for every visitor. */
    public static function bootstrap(): array
    {
        return [
            'enabled' => true,
            'listingTtl' => (int) config('flovatar.dustMarket.listingTtl'),
            'feeAddress' => '0x'.self::contractHex(),
        ];
    }

    /** Model currency with the legacy fallback for rows written before the column existed. */
    public static function currencyOf($row): string
    {
        $c = is_array($row) ? ($row['currency'] ?? null) : ($row->currency ?? null);
        if ($c) {
            return (string) $c;
        }
        $dapper = is_array($row) ? ($row['is_dapper'] ?? false) : ($row->is_dapper ?? false);

        return $dapper ? 'DUC' : 'FLOW';
    }
}
