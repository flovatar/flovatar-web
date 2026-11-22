<?php

namespace App\Services\Flow;

/**
 * Event types the app indexes: every Flovatar contract event handled by
 * ProcessGraffleEvents (BaseJob::$eventsList) on FLOW_MARKET_ADDRESS, plus the global
 * NFTStorefrontV2 listing events, filtered to our NFT types.
 */
class FlowEventTypes
{
    const CONTRACT_EVENTS = [
        'FlovatarComponentTemplate.Created',
        'FlovatarComponent.Created', 'FlovatarComponent.Withdraw', 'FlovatarComponent.Deposit', 'FlovatarComponent.Destroyed',
        'FlovatarPack.Created', 'FlovatarPack.Deposit', 'FlovatarPack.Opened',
        'Flovatar.Created', 'Flovatar.Withdraw', 'Flovatar.Deposit', 'Flovatar.Updated', 'Flovatar.NameSet',
        'Flovatar.PositionChanged', 'Flovatar.StoryAdded', 'Flovatar.Unlocked3DFile',
        'Flobot.Created', 'Flobot.Deposit', 'Flobot.Updated',
        'FlovatarMarketplace.FlovatarForSale', 'FlovatarMarketplace.FlovatarPurchased',
        'FlovatarMarketplace.FlovatarPriceChanged', 'FlovatarMarketplace.FlovatarSaleWithdrawn',
        'FlovatarMarketplace.FlovatarComponentForSale', 'FlovatarMarketplace.FlovatarComponentPurchased',
        'FlovatarMarketplace.FlovatarComponentPriceChanged', 'FlovatarMarketplace.FlovatarComponentSaleWithdrawn',
        'FlovatarMarketplace.RoyaltyPaid',
        'FlovatarDustCollectibleTemplate.CreatedSeries', 'FlovatarDustCollectibleTemplate.Created',
        'FlovatarDustCollectible.Created', 'FlovatarDustCollectible.Withdraw', 'FlovatarDustCollectible.Deposit',
        'FlovatarDustCollectible.Updated', 'FlovatarDustCollectible.NameSet',
        'FlovatarDustCollectible.PositionChanged', 'FlovatarDustCollectible.StoryAdded',
        'FlovatarDustCollectibleAccessory.Created', 'FlovatarDustCollectibleAccessory.Withdraw',
        'FlovatarDustCollectibleAccessory.Deposit', 'FlovatarDustCollectibleAccessory.Destroyed',
        'FlovatarInbox.FlovatarWithdrawDust', 'FlovatarInbox.WalletWithdrawDust',
        'FlovatarInbox.FlovatarClaimedCommunityDust',
        'FlovatarDustToken.TokensBurned',
    ];

    const STOREFRONT_EVENTS = ['NFTStorefrontV2.ListingAvailable', 'NFTStorefrontV2.ListingCompleted'];

    const NFT_CONTRACTS = ['Flovatar.NFT', 'FlovatarComponent.NFT', 'FlovatarDustCollectible.NFT',
        'FlovatarDustCollectibleAccessory.NFT', 'Flobot.NFT', 'FlovatarPack.Pack'];

    public static function contractAddress(): string
    {
        return strtolower(str_replace('0x', '', (string) config('services.flow.address')));
    }

    public static function storefrontAddress(): string
    {
        return strtolower(str_replace('0x', '', (string) config('services.flow.storefrontaddress')));
    }

    public static function contractTypes(): array
    {
        $a = static::contractAddress();

        return array_map(fn ($e) => "A.$a.$e", self::CONTRACT_EVENTS);
    }

    public static function storefrontTypes(): array
    {
        $s = static::storefrontAddress();

        return array_map(fn ($e) => "A.$s.$e", self::STOREFRONT_EVENTS);
    }

    public static function all(): array
    {
        return array_merge(static::contractTypes(), static::storefrontTypes());
    }

    public static function isStorefront(string $type): bool
    {
        return str_starts_with($type, 'A.'.static::storefrontAddress().'.');
    }

    public static function ourNftTypes(): array
    {
        $a = static::contractAddress();

        return array_map(fn ($c) => "A.$a.$c", self::NFT_CONTRACTS);
    }

    /** Storefront events are global: keep only listings of our NFT types. */
    public static function keep(string $type, $payload): bool
    {
        if (! in_array($type, static::all(), true)) {
            return false;
        }
        if (! static::isStorefront($type)) {
            return true;
        }
        $nft = is_array($payload) ? ($payload['nftType'] ?? null) : null;

        return is_array($nft) && in_array($nft['typeID'] ?? null, static::ourNftTypes(), true);
    }
}
