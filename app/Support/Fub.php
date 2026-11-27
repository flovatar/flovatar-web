<?php

namespace App\Support;

/**
 * FUB switch (config/fub.php, FUB_MODE off|stealth|on) + contract helpers. Mirrors App\Support\Islands.
 */
class Fub
{
    public const NAME = 'FlovatarUniverseBuilder';

    public static function active(): bool
    {
        if (! app()->bound('config')) {
            return false;
        }

        return Stealth::active('fub') && self::contract() !== '';
    }

    public static function enabled(): bool
    {
        return self::active() && Stealth::visible('fub');
    }

    /** '0x…' (16 hex) or '' */
    public static function contract(): string
    {
        $a = strtolower(trim((string) config('fub.contract', '')));
        if ($a === '') {
            return '';
        }
        $a = str_starts_with($a, '0x') ? $a : '0x'.$a;

        return preg_match('/^0x[0-9a-f]{16}$/', $a) ? $a : '';
    }

    public static function contractHex(): string
    {
        return substr(self::contract(), 2);
    }

    /** A.<hex>.FlovatarUniverseBuilder.<suffix> */
    public static function typeId(string $suffix = 'NFT'): string
    {
        return 'A.'.self::contractHex().'.'.self::NAME.'.'.$suffix;
    }

    public static function normalizeAddress(?string $a): ?string
    {
        $a = strtolower(trim((string) $a));
        if (! preg_match('/^(0x)?[0-9a-f]{1,16}$/', $a)) {
            return null;
        }
        $a = preg_replace('/^0x/', '', $a);

        return '0x'.str_pad($a, 16, '0', STR_PAD_LEFT);
    }

    public static function isAdmin(?string $address): bool
    {
        $a = self::normalizeAddress($address);

        return $a !== null && in_array($a, array_map(fn ($x) => self::normalizeAddress($x), (array) config('fub.admins', [])), true);
    }

    /** window.FUB: same for every visitor, no user data. */
    public static function bootstrap(): array
    {
        return [
            'contract' => self::contract(),
            'islands' => Islands::contract(),
            'network' => (string) config('services.flow.network'),
            'assistantUrl' => (string) config('fub.assistant_url', ''),
        ];
    }

    public static function contentUrl(string $path): string
    {
        $base = (string) config('fub.content_origin', '');

        return ($base !== '' ? $base : '').'/fub-content/'.ltrim($path, '/');
    }
}
