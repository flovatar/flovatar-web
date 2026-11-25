<?php

namespace App\Services\Quests;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Discord OAuth2 for the "Join the Flovatar Discord" quest: scopes identify + guilds (no bot, no server permissions).
 * We read the user id/name and whether the Flovatar guild is among the user's guilds, then forget the token.
 * Config: quests.discord.* (DISCORD_QUESTS_CLIENT_ID / _SECRET / _GUILD_ID). The API base is overridable for tests.
 */
class DiscordOAuth
{
    public static function configured(): bool
    {
        return (string) config('quests.discord.client_id') !== '' && (string) config('quests.discord.client_secret') !== ''
            && (string) config('quests.discord.guild_id') !== '';
    }

    public static function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/quests/discord/callback';
    }

    public static function authorizeUrl(string $state): string
    {
        return config('quests.discord.authorize_url').'?'.http_build_query([
            'client_id' => config('quests.discord.client_id'),
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'scope' => 'identify guilds',
            'state' => $state,
            'prompt' => 'none',
        ]);
    }

    /** Exchange the code, read the user + guild membership. Returns ['id', 'username', 'in_guild']. */
    public function resolve(string $code): array
    {
        $api = rtrim((string) config('quests.discord.api'), '/');
        $tok = Http::asForm()->timeout(15)->acceptJson()->post($api.'/oauth2/token', [
            'client_id' => config('quests.discord.client_id'),
            'client_secret' => config('quests.discord.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
        ]);
        if (! $tok->successful() || ! $tok->json('access_token')) {
            throw new RuntimeException('Discord did not accept the sign-in (code expired?). Please try again.');
        }
        $bearer = (string) $tok->json('access_token');
        $me = Http::withToken($bearer)->timeout(15)->acceptJson()->get($api.'/users/@me');
        $guilds = Http::withToken($bearer)->timeout(15)->acceptJson()->get($api.'/users/@me/guilds', ['limit' => 200]);
        if (! $me->successful() || ! $guilds->successful() || ! preg_match('/^\d{5,25}$/', (string) $me->json('id'))) {
            throw new RuntimeException('Could not read your Discord account. Please try again.');
        }
        $guildId = (string) config('quests.discord.guild_id');
        $in = collect($guilds->json() ?: [])->contains(fn ($g) => (string) ($g['id'] ?? '') === $guildId);
        // best effort: revoke the token right away (we only needed it once)
        try {
            Http::asForm()->timeout(5)->post($api.'/oauth2/token/revoke', [
                'token' => $bearer, 'client_id' => config('quests.discord.client_id'), 'client_secret' => config('quests.discord.client_secret'),
            ]);
        } catch (\Throwable $e) {
        }

        return ['id' => (string) $me->json('id'), 'username' => mb_substr((string) ($me->json('global_name') ?: $me->json('username')), 0, 64), 'in_guild' => $in];
    }
}
