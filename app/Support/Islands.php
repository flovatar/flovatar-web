<?php

namespace App\Support;

/**
 * Asteroid Islands switch + slot model helpers (config/islands.php; KB notes/islands-build.md).
 *
 * active()  = ISLANDS_MODE stealth|on AND an island contract address is configured (config only: route:cache safe,
 *             gates the indexer event types and renders).
 * enabled() = visible to this request (on, or stealth + stealth gate).
 *
 * Slots (spec 2026-11-26): PREMIUM P1..Pn (in the scene, Flovatar family only) + STANDARD S1..Sm (any NFT, carousel),
 * n = settings premiumSlots.<rarity> (default 3/6/10), n + m = capacity 5/25/125. While the deployed contract still
 * uses the older pad grammar (A1..A5 / A1..E5 / D1A1..D5E5), logical slots map onto pad ids in order (P first).
 */
class Islands
{
    public const RARITIES = ['common', 'epic', 'legendary'];

    public static function active(): bool
    {
        if (! app()->bound('config')) {
            return false; // plain unit tests (no application): feature off
        }

        return Stealth::active('islands') && self::contract() !== '';
    }

    public static function enabled(): bool
    {
        return self::active() && Stealth::visible('islands');
    }

    /** '0x…' (16 hex) or '' */
    public static function contract(): string
    {
        $a = strtolower(trim((string) config('islands.contract', '')));
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

    /** A.<hex>.FlovatarAsteroidIsland.<suffix> */
    public static function typeId(string $suffix = 'NFT'): string
    {
        return 'A.'.self::contractHex().'.FlovatarAsteroidIsland.'.$suffix;
    }

    public const PREFIX_CACHE_KEY = 'islands:designation-prefix';

    private static ?string $prefixMemo = null;

    private static float $prefixMemoAt = 0.0;

    /**
     * Designation prefix ("K313/" -> K313/0001). Source of truth = the contract setting `designationPrefix`:
     * App\Services\Islands\IslandChain::readConfig() stores it in the cache every time the chain config is read, so
     * pages never wait for the chain. Before the first read (or without a contract): config islands.designation_prefix.
     */
    public static function designationPrefix(): string
    {
        // memo for a minute: one cache read per request, and long-running workers still follow a changed setting
        if (self::$prefixMemo !== null && microtime(true) - self::$prefixMemoAt < 60) {
            return self::$prefixMemo;
        }
        self::$prefixMemoAt = microtime(true);
        $p = null;
        try {
            $p = \Illuminate\Support\Facades\Cache::get(self::PREFIX_CACHE_KEY.':'.self::contractHex());
        } catch (\Throwable $e) {
            // cache store down: fall back to the configured prefix
        }

        return self::$prefixMemo = self::cleanPrefix($p) ?? self::cleanPrefix(config('islands.designation_prefix')) ?? 'K313/';
    }

    /** Called by IslandChain when the contract settings were read. */
    public static function rememberDesignationPrefix($prefix): void
    {
        $clean = self::cleanPrefix($prefix);
        if ($clean === null) {
            return;
        }
        self::$prefixMemo = $clean;
        self::$prefixMemoAt = microtime(true);
        try {
            \Illuminate\Support\Facades\Cache::forever(self::PREFIX_CACHE_KEY.':'.self::contractHex(), $clean);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function forgetDesignationPrefix(): void
    {
        self::$prefixMemo = null;
    }

    /** Short printable prefixes only (it is printed into HTML, JSON and OG tags). */
    public static function cleanPrefix($prefix): ?string
    {
        $p = is_string($prefix) ? trim($prefix) : '';

        return $p !== '' && preg_match('~^[A-Za-z0-9][A-Za-z0-9 ./#_-]{0,15}$~', $p) ? $p : null;
    }

    /** Sector label for UI copy: the prefix without its separator ("K313/" -> "K313"). */
    public static function sector(): string
    {
        return rtrim(self::designationPrefix(), " /#._-");
    }

    public static function designation(int $id): string
    {
        return self::designationPrefix().str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public static function url(int $id): string
    {
        return rtrim((string) config('islands.site_url'), '/').'/islands/'.$id;
    }

    public static function capacity(string $rarity): int
    {
        return (int) (config('islands.capacity.'.$rarity) ?? 0);
    }

    // ---------------------------------------------------------------- premium types

    /** Flovatar-family NFT type identifiers that may take a premium slot => family key. */
    public static function premiumTypes(): array
    {
        $out = [];
        foreach ((array) config('islands.premium_contracts', []) as $name => $address) {
            $hex = strtolower(preg_replace('/^0x/', '', (string) $address));
            if (preg_match('/^[0-9a-f]{16}$/', $hex)) {
                $out['A.'.$hex.'.'.$name.'.NFT'] = $name;
            }
        }

        return $out;
    }

    public static function familyOf(string $typeId): ?string
    {
        return self::premiumTypes()[$typeId] ?? null;
    }

    // ---------------------------------------------------------------- slot model

    /** Chain slot ids of the pad grammar, in fill order. */
    public static function padSlots(string $rarity): array
    {
        $pads = ['A', 'B', 'C', 'D', 'E'];
        $out = [];
        if ($rarity === 'common') {
            foreach (range(1, 5) as $i) {
                $out[] = 'A'.$i;
            }
        } elseif ($rarity === 'epic') {
            foreach ($pads as $p) {
                foreach (range(1, 5) as $i) {
                    $out[] = $p.$i;
                }
            }
        } elseif ($rarity === 'legendary') {
            foreach (range(1, 5) as $d) {
                foreach ($pads as $p) {
                    foreach (range(1, 5) as $i) {
                        $out[] = 'D'.$d.$p.$i;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Logical slot list for a rarity: [['slot' => 'P1', 'chain' => 'P1'|'A1', 'premium' => true], ...].
     * $model: 'ps' (contract speaks P/S) or 'pad' (legacy grammar, mapped).
     */
    public static function slots(string $rarity, int $premium, string $model = 'ps'): array
    {
        $cap = self::capacity($rarity);
        $premium = max(0, min($premium, $cap));
        $pad = self::padSlots($rarity);
        $out = [];
        for ($i = 0; $i < $cap; $i++) {
            $isP = $i < $premium;
            $logical = $isP ? 'P'.($i + 1) : 'S'.($i - $premium + 1);
            $out[] = ['slot' => $logical, 'chain' => $model === 'pad' ? ($pad[$i] ?? $logical) : $logical, 'premium' => $isP];
        }

        return $out;
    }

    /** chain slot id -> logical slot id ('caretaker' stays). */
    public static function logicalSlot(string $rarity, string $chainSlot, int $premium, string $model = 'ps'): string
    {
        if ($model !== 'pad' || $chainSlot === 'caretaker') {
            return $chainSlot;
        }
        foreach (self::slots($rarity, $premium, $model) as $s) {
            if ($s['chain'] === $chainSlot) {
                return $s['slot'];
            }
        }

        return $chainSlot;
    }

    public static function isPremiumSlot(string $logical): bool
    {
        return (bool) preg_match('/^P\d+$/', $logical);
    }

    /** "13-15,20" -> [[13,15],[20,20]] (same grammar as TERRAKIT_PACK_ID_RANGES). */
    public static function packRanges(): array
    {
        return TerrakitCheck::packRanges((string) config('islands.pack_ranges'));
    }

    /**
     * window.ISLANDS — static-safe runtime config (same for every visitor, no user data). Chain config (templates,
     * settings, slot model) is fetched by the bundle from /api/islands/config.
     */
    public static function bootstrap(): array
    {
        return [
            'enabled' => true,
            'network' => (string) config('services.flow.network'),
            'contract' => self::contract(),
            'flovatar' => (string) config('services.flow.address'),
            'premiumTypes' => self::premiumTypes(),
            'families' => config('islands.families'),
            'packRanges' => self::packRanges(),
            'designationPrefix' => self::designationPrefix(),
            'sector' => self::sector(),
            'belt' => [
                'center' => (string) config('islands.belt.center_name', ''),
                'demo' => (bool) config('islands.belt.demo', false),
                'demoDefault' => (int) config('islands.belt.demo_default', 2000),
            ],
        ];
    }
}
