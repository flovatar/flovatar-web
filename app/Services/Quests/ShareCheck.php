<?php

namespace App\Services\Quests;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * "Share your Flovatar card": checks a pasted post URL with the platforms' free oEmbed endpoints (no API key).
 * Auto-verified when the post text contains #Flovatar AND a link to flovatar.com or a mention of @flovatar.
 * Anything we can't read (Instagram, deleted/private posts, provider down, text without the tag) → admin review.
 */
class ShareCheck
{
    /** Normalised post URL + platform, or null when it is not a post URL we accept. */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if (strlen($url) > 300 || ! preg_match('#^https://#i', $url)) {
            return null;
        }
        $p = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|mobile\.|m\.|vm\.)/', '', (string) ($p['host'] ?? '')));
        $path = rtrim((string) ($p['path'] ?? ''), '/');
        if (in_array($host, ['x.com', 'twitter.com'], true) && preg_match('#^/([A-Za-z0-9_]{1,15})/status/(\d{5,25})#', $path, $m)) {
            return ['platform' => 'x', 'url' => 'https://x.com/'.$m[1].'/status/'.$m[2], 'key' => 'x:'.$m[2], 'author' => strtolower($m[1])];
        }
        if ($host === 'tiktok.com' && preg_match('#^/@([A-Za-z0-9._]{1,30})/video/(\d{5,25})#', $path, $m)) {
            return ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@'.$m[1].'/video/'.$m[2], 'key' => 'tiktok:'.$m[2], 'author' => strtolower($m[1])];
        }
        if ($host === 'instagram.com' && preg_match('#^/(p|reel)/([A-Za-z0-9_-]{5,40})#', $path, $m)) {
            return ['platform' => 'instagram', 'url' => 'https://www.instagram.com/'.$m[1].'/'.$m[2].'/', 'key' => 'instagram:'.$m[2], 'author' => null];
        }

        return null;
    }

    /**
     * ['status' => 'verified'|'pending', 'evidence' => [...], 'reason' => string]. Never throws.
     */
    public function check(array $post): array
    {
        $evidence = ['platform' => $post['platform'], 'url' => $post['url']];
        $endpoint = config('quests.share.oembed.'.$post['platform']);
        if (! $endpoint) {
            return ['status' => 'pending', 'evidence' => $evidence, 'reason' => 'We will check this post by hand (usually within a day).'];
        }
        try {
            $res = Http::timeout(10)->connectTimeout(5)->acceptJson()->get($endpoint, ['url' => $post['url'], 'omit_script' => 1, 'dnt' => 1]);
            if (! $res->successful()) {
                return ['status' => 'pending', 'evidence' => $evidence + ['oembed' => $res->status()], 'reason' => 'We could not read this post automatically. We will check it by hand.'];
            }
            $j = $res->json() ?: [];
        } catch (Throwable $e) {
            return ['status' => 'pending', 'evidence' => $evidence + ['oembed' => 'error'], 'reason' => 'We could not read this post automatically. We will check it by hand.'];
        }
        $text = html_entity_decode(strip_tags((string) ($j['html'] ?? '').' '.(string) ($j['title'] ?? '')), ENT_QUOTES | ENT_HTML5);
        $raw = strtolower((string) ($j['html'] ?? '').' '.$text);
        $evidence['author'] = (string) ($j['author_name'] ?? '');
        $evidence['text'] = mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 280);
        $hasTag = (bool) preg_match('/#flovatar\b/i', $text);
        $hasLink = str_contains($raw, 'flovatar.com') || (bool) preg_match('/@flovatar\b/i', $text);
        $evidence['hashtag'] = $hasTag;
        $evidence['mention'] = $hasLink;
        if ($hasTag && $hasLink) {
            return ['status' => 'verified', 'evidence' => $evidence, 'reason' => ''];
        }

        return ['status' => 'pending', 'evidence' => $evidence, 'reason' => $hasTag
            ? 'We found #Flovatar but no link to flovatar.com or @flovatar. We will check it by hand.'
            : 'We could not find #Flovatar in this post. We will check it by hand.'];
    }
}
