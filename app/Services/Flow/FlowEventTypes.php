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

    // Cadence 1.0 NFTStorefrontV2 emits no ListingCompleted on removeListing/cleanup, only the listing's default destroy
    // event Listing.ResourceDestroyed (nftType / salePaymentVaultType are Strings there, not Types).
    const STOREFRONT_EVENTS = ['NFTStorefrontV2.ListingAvailable', 'NFTStorefrontV2.ListingCompleted', 'NFTStorefrontV2.Listing.ResourceDestroyed'];

    /**
     * Standard NonFungibleToken events (global, on the NonFungibleToken contract account). Kept only for our NFT types
     * whose contract emits NO own Deposit event: the Cadence 1.0 Flobot contract declares Flobot.Deposit but never emits
     * it, so without NonFungibleToken.Deposited a minted/transferred Flobot never got an owner (Flobots 608/609).
     */
    const STANDARD_NFT_EVENTS = ['NonFungibleToken.Deposited'];

    const STANDARD_DEPOSIT_NFTS = ['Flobot.NFT'];

    /** Asteroid Islands (FlovatarAsteroidIsland, own contract; indexed only while App\Support\Islands::active()). */
    const ISLAND_EVENTS = [
        'Terraformed', 'IslandLayersChanged', 'IslandNameChanged', 'IslandDescriptionChanged', 'IslandMetadataChanged',
        'ResidentSet', 'ResidentCleared', 'ResidentMoved',
        'TemplateCreated', 'TemplateSvgAppended', 'TemplateEnabled', 'TemplateMetadataChanged', 'SettingChanged',
    ];

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

    /** A.<islands>.FlovatarAsteroidIsland.<event> while the islands feature is active (else []). */
    public static function islandTypes(): array
    {
        if (! \App\Support\Islands::active()) {
            return [];
        }

        return array_map(fn ($e) => \App\Support\Islands::typeId($e), self::ISLAND_EVENTS);
    }

    public static function isIsland(string $type): bool
    {
        return \App\Support\Islands::active() && str_starts_with($type, 'A.'.\App\Support\Islands::contractHex().'.FlovatarAsteroidIsland.');
    }

    /** Extra contract addresses the stream must follow (the island contract when it lives elsewhere). */
    public static function extraAddresses(): array
    {
        $hex = \App\Support\Islands::active() ? \App\Support\Islands::contractHex() : '';

        return ($hex !== '' && $hex !== static::contractAddress()) ? ['0x'.$hex] : [];
    }

    public static function storefrontTypes(): array
    {
        $s = static::storefrontAddress();

        return array_map(fn ($e) => "A.$s.$e", self::STOREFRONT_EVENTS);
    }

    public static function nftStandardAddress(): string
    {
        return strtolower(str_replace('0x', '', (string) config('services.flow.nftaddress')));
    }

    public static function standardTypes(): array
    {
        $n = static::nftStandardAddress();

        return $n === '' ? [] : array_map(fn ($e) => "A.$n.$e", self::STANDARD_NFT_EVENTS);
    }

    public static function isStandardNft(string $type): bool
    {
        return static::nftStandardAddress() !== '' && str_starts_with($type, 'A.'.static::nftStandardAddress().'.NonFungibleToken.');
    }

    /** Our NFT types that only announce transfers through the standard NonFungibleToken.Deposited event. */
    public static function standardDepositNftTypes(): array
    {
        $a = static::contractAddress();
        $types = array_map(fn ($c) => "A.$a.$c", self::STANDARD_DEPOSIT_NFTS);
        if (\App\Support\Islands::active()) {
            $types[] = \App\Support\Islands::typeId('NFT'); // island transfers (the contract has no own Deposit event)
        }

        return $types;
    }

    /** Global event types that need a server-side filter (stream subscription by type, not by address). */
    public static function globalTypes(): array
    {
        return array_merge(static::storefrontTypes(), static::standardTypes());
    }

    public static function all(): array
    {
        return array_merge(static::contractTypes(), static::islandTypes(), static::storefrontTypes(), static::standardTypes());
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

    /** Storefront and NonFungibleToken events are global: keep only listings/deposits of our NFT types. */
    public static function keep(string $type, $payload): bool
    {
        if (! in_array($type, static::all(), true)) {
            return false;
        }
        if (static::isStandardNft($type)) {
            return is_array($payload) && in_array($payload['type'] ?? null, static::standardDepositNftTypes(), true)
                && ! empty($payload['to']);
        }
        if (! static::isStorefront($type)) {
            return true;
        }
        $nft = is_array($payload) ? ($payload['nftType'] ?? null) : null;
        $typeId = is_array($nft) ? ($nft['typeID'] ?? null) : (is_string($nft) ? $nft : null);

        return in_array($typeId, static::ourNftTypes(), true);
    }
}
