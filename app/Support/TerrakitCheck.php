<?php

namespace App\Support;

/**
 * TerraKit wallet check switch + static-safe bootstrap (KB notes/terrakit-check.md).
 *
 * enabled() = TERRAKIT_CHECK_ENABLED. Off: routes/terrakit.php is not loaded (and the controller 404s anyway), the
 * headless build skips the page, Blade prints nothing, window.TERRAKIT_CHECK is absent → production unchanged.
 */
class TerrakitCheck
{
    /** Visible to this request (TERRAKIT_CHECK_MODE on, or stealth + stealth gate; legacy TERRAKIT_CHECK_ENABLED). */
    public static function enabled(): bool
    {
        return Stealth::visible('terrakit');
    }

    /** Routes registered (mode stealth or on). Config only (route:cache safe). */
    public static function active(): bool
    {
        return Stealth::active('terrakit');
    }

    /** "10800-15799,20000" → [[10800, 15799], [20000, 20000]] (invalid parts ignored). */
    public static function packRanges(?string $spec = null): array
    {
        $out = [];
        foreach (explode(',', (string) ($spec ?? config('terrakit.pack_ranges'))) as $part) {
            $part = trim($part);
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $lo = (int) $m[1];
                $hi = (int) $m[2];
                if ($lo <= $hi) {
                    $out[] = [$lo, $hi];
                }
            } elseif (ctype_digit($part)) {
                $out[] = [(int) $part, (int) $part];
            }
        }

        return $out;
    }

    /** window.TERRAKIT_CHECK (same for every visitor: no user data, safe for static/headless shells). */
    public static function bootstrap(): array
    {
        return [
            'enabled' => true,
            'packRanges' => self::packRanges(),
            'categories' => array_values(config('terrakit.categories', ['land', 'fub', 'flobotkit'])),
        ];
    }
}
