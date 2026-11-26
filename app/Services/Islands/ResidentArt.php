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

    /**
     * Image of a STANDARD resident for the display screen as a data: URI (the renderer and <img>-embedded SVG can't load
     * external URLs). Flovatar family = own art; anything else = its MetadataViews.Display thumbnail, fetched once and
     * cached. The URL comes from on-chain metadata anyone can write, so only public http(s) hosts are fetched (no private
     * / loopback / link-local addresses), images only, at most 3 MB.
     */
    public function screenImage(string $typeId, int $nftId, ?string $thumbnail): ?string
    {
        if (Islands::familyOf($typeId) && ($svg = $this->svg($typeId, $nftId))) {
            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return $thumbnail ? $this->dataUri($thumbnail) : null;
    }

    public function dataUri(string $url): ?string
    {
        $url = self::httpUrl($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'data:image/')) {
            return strlen($url) <= 4_000_000 ? $url : null;
        }
        $v = Cache::get($key = 'islands:screen-img:'.sha1($url));
        if ($v !== null) {
            return $v !== '' ? $v : null;
        }
        $out = '';
        try {
            if (self::publicHost((string) parse_url($url, PHP_URL_HOST))) {
                $r = Http::timeout(12)->connectTimeout(5)->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FlovatarIslands/1.0; +https://flovatar.com)', 'Accept' => 'image/*'])->get($url);
                $type = strtolower(trim(explode(';', (string) $r->header('Content-Type'))[0]));
                $body = (string) $r->body();
                if ($r->successful() && in_array($type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'], true) && $body !== '' && strlen($body) <= 3_000_000) {
                    $out = 'data:'.$type.';base64,'.base64_encode($body);
                }
            }
        } catch (Throwable $e) {
            // unreachable host: the screen stays dark, try again later
        }
        Cache::put($key, $out, $out !== '' ? 86400 : 600);

        return $out !== '' ? $out : null;
    }

    /** ipfs:// and bare CIDs -> gateway; http(s) and data:image kept; anything else ''. (compose.mjs httpUrl) */
    public static function httpUrl(string $uri): string
    {
        $s = trim($uri);
        if (str_starts_with($s, 'ipfs://')) {
            return 'https://ipfs.io/ipfs/'.preg_replace('~^ipfs/~', '', substr($s, 7));
        }
        if (preg_match('~^https?://~i', $s) || str_starts_with($s, 'data:image/')) {
            return $s;
        }
        if (preg_match('~^(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{50,})~', $s)) {
            return 'https://ipfs.io/ipfs/'.$s;
        }

        return '';
    }

    /** Host resolves to public addresses only (SSRF guard). */
    public static function publicHost(string $host): bool
    {
        $host = trim($host, '[]');
        if ($host === '' || strtolower($host) === 'localhost') {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    protected function remote(string $url): string
    {
        return (string) Cache::remember('islands:resident-svg:'.sha1($url), 3600, function () use ($url) {
            $r = Http::timeout(15)->connectTimeout(5)->withHeaders(['User-Agent' => 'flovatar-islands/1.0'])->get($url);

            return $r->successful() && str_contains((string) $r->header('Content-Type'), 'svg') ? (string) $r->body() : '';
        });
    }
}
