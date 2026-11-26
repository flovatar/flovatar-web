<?php

namespace App\Services\Islands;

use App\Models\DustCollectible;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Support\Islands;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Art of PREMIUM (Flovatar-family) residents as a standalone SVG on the 3000×3000 resident canvas, background removed:
 * Flovatar / Flobot = the site's no-background composition (same as /api/image/nobg), Psyche Likee = the collectible
 * SVG, Sportvatar = the Sportvatar site's image API (cached). Standard residents are never drawn in the scene.
 */
class ResidentArt
{
    /** @return string|null standalone SVG document, or null when unknown / not premium */
    public function svg(string $typeId, int $nftId): ?string
    {
        $family = Islands::familyOf($typeId);
        if (! $family) {
            return null;
        }
        try {
            $svg = match ($family) {
                'Flovatar' => (string) (Flovatar::where('flow_id', $nftId)->first()?->full_svg_no_bg ?? ''),
                'Flobot' => (string) (Flobot::where('flow_id', $nftId)->first()?->full_svg_no_bg ?? ''),
                'FlovatarDustCollectible' => (string) (DustCollectible::where('flow_id', $nftId)->first()?->full_svg ?? ''),
                'Sportvatar' => $this->remote(str_replace('{id}', (string) $nftId, (string) config('islands.sportvatar_svg_url'))),
                default => '',
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        $svg = trim($svg);

        return str_starts_with($svg, '<svg') || str_starts_with($svg, '<?xml') ? $svg : null;
    }

    protected function remote(string $url): string
    {
        return (string) Cache::remember('islands:resident-svg:'.sha1($url), 3600, function () use ($url) {
            $r = Http::timeout(15)->connectTimeout(5)->withHeaders(['User-Agent' => 'flovatar-islands/1.0'])->get($url);

            return $r->successful() && str_contains((string) $r->header('Content-Type'), 'svg') ? (string) $r->body() : '';
        });
    }
}
