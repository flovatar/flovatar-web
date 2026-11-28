<?php

namespace App\Support;

/**
 * Pack Rewind switch (KB notes/pack-rewind.md). TESTNET ONLY.
 *
 * active()  = PACK_REWIND_MODE stealth|on AND network testnet (Stealth::mode forces 'off' elsewhere): routes registered.
 * enabled() = visible to this request.
 */
class PackRewind
{
    public static function active(): bool
    {
        if (! app()->bound('config')) {
            return false;
        }

        return Stealth::active('rewind') && (string) config('services.flow.network') === 'testnet';
    }

    public static function enabled(): bool
    {
        return self::active() && Stealth::visible('rewind');
    }

    /** Ordered chapters (config/pack_rewind.php series, chronological). */
    public static function series(): array
    {
        return (array) config('pack_rewind.series', []);
    }

    public static function flovatarContract(): string
    {
        return strtolower((string) config('flovatar.contractAddress', ''));
    }

    public static function sportvatarContract(): string
    {
        return strtolower((string) config('pack_rewind.sportvatar.contract'));
    }

    /** Static-safe runtime config for resources/js/app/pack-rewind.js (window.PACK_REWIND). */
    public static function bootstrap(): array
    {
        return [
            'network' => (string) config('services.flow.network'),
            'sportvatar' => self::sportvatarContract(),
            'sportvatarUrl' => (string) config('pack_rewind.sportvatar.site_url'),
            'slippage' => (float) config('pack_rewind.dust_swap_slippage', 3),
            // ĐUST pack paid with FLOW: Increment router/config/factory (same per-network defaults as the ĐUST swap)
            'swap' => [
                'router' => (string) config('flovatar.dustSwap.router'),
                'config' => (string) config('flovatar.dustSwap.swapConfig'),
                'factory' => (string) config('flovatar.dustSwap.factory'),
            ],
        ];
    }
}
