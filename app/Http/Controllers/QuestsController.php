<?php

namespace App\Http\Controllers;

use App\Models\PasskeyAccount;
use App\Models\PasskeyCredential;
use App\Models\QuestChallenge;
use App\Models\QuestDiscordLink;
use App\Services\Quests\DiscordOAuth;
use App\Services\Quests\QuestEngine;
use App\Support\DustQuests;
use App\Support\PasskeyLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * /quests — ĐUST Quests for passkey accounts (testnet, DUST_QUESTS_ENABLED). The page is a static shell (no user data,
 * headless-safe); everything personal comes from /quests/state with the passkey session. Wallet sessions get
 * {eligible:false, reason:not_passkey} and the UI shows nothing.
 */
class QuestsController extends Controller
{
    public function __construct()
    {
        abort_unless(DustQuests::enabled(), 404);
    }

    /** The passkey account of this session (signed in with its passkey, same address as the Laravel login) or null. */
    public static function sessionAccount(Request $request): ?PasskeyAccount
    {
        if (! $request->hasSession()) {
            return null;
        }
        $account = PasskeyAccount::find($request->session()->get(PasskeyLogin::SESSION_ACCOUNT));
        if (! $account || $account->status !== 'active' || ! $account->address) {
            return null;
        }
        $cred = PasskeyCredential::where('passkey_account_id', $account->id)->find($request->session()->get(PasskeyLogin::SESSION_CREDENTIAL));
        if (! $cred || $cred->status !== 'active') {
            return null;
        }
        $user = Auth::user();
        if (! $user || strtolower((string) $user->email) !== strtolower($account->address)) {
            return null;
        }

        return $account;
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }

    public function page()
    {
        return response()->view('quests.index')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function state(Request $request, QuestEngine $engine): JsonResponse
    {
        $a = self::sessionAccount($request);
        if (! $a) {
            return $this->json(['eligible' => false, 'reason' => Auth::check() ? 'not_passkey' : 'signed_out']);
        }
        $engine->recordActivity($a);

        return $this->json($engine->state($a));
    }

    public function seen(Request $request, QuestEngine $engine): JsonResponse
    {
        if ($a = self::sessionAccount($request)) {
            $engine->recordActivity($a);
        }

        return $this->json(['ok' => true]);
    }

    private function challenge(string $key): QuestChallenge
    {
        $c = QuestEngine::make()->challenges()->firstWhere('key', $key);
        abort_unless($c, 404);

        return $c;
    }

    public function start(Request $request, string $key, QuestEngine $engine): JsonResponse
    {
        $a = self::sessionAccount($request);
        if (! $a) {
            return $this->json(['error' => 'Sign in with your passkey first.'], 401);
        }
        $c = $this->challenge($key);
        if ($c->verify_type !== 'honor') {
            return $this->json(['error' => 'Nothing to start.'], 422);
        }
        $elig = $engine->eligibility($a);
        if (! $elig['eligible']) {
            return $this->json(['error' => $elig['message']], 403);
        }

        return $this->json(['started' => true, 'wait' => $engine->startHonor($a, $c), 'url' => $c->url]);
    }

    public function claim(Request $request, string $key, QuestEngine $engine): JsonResponse
    {
        $a = self::sessionAccount($request);
        if (! $a) {
            return $this->json(['error' => 'Sign in with your passkey first.'], 401);
        }
        $c = $this->challenge($key);
        $input = [];
        if ($c->verify_type === 'quiz') {
            $input['answers'] = array_values((array) $request->input('answers', []));
        }
        if ($c->verify_type === 'share') {
            $input['url'] = (string) $request->input('url', '');
        }
        $out = $engine->claim($a, $c, $input, (string) $request->ip());
        if (! $out['ok']) {
            return $this->json(['error' => $out['error']], $out['status_code']);
        }

        return $this->json(['ok' => true, 'status' => $out['claim']->status, 'amount' => (float) $out['claim']->amount, 'message' => $out['message'], 'state' => $engine->state($a)]);
    }

    // ------------------------------------------------------------------ Discord OAuth2

    public function discordConnect(Request $request, QuestEngine $engine)
    {
        $a = self::sessionAccount($request);
        if (! $a) {
            return redirect('/quests#discord=signin');
        }
        if (! DiscordOAuth::configured()) {
            return redirect('/quests#discord=unavailable');
        }
        if (! $engine->eligibility($a)['eligible']) {
            return redirect('/quests#discord=ineligible');
        }
        $state = Str::random(40);
        $request->session()->put('quests.discord_state', ['s' => $state, 'a' => $a->id, 't' => time()]);

        return redirect()->away(DiscordOAuth::authorizeUrl($state));
    }

    public function discordCallback(Request $request, QuestEngine $engine, DiscordOAuth $oauth)
    {
        $saved = $request->session()->pull('quests.discord_state');
        $a = self::sessionAccount($request);
        if (! $a || ! is_array($saved) || (int) $saved['a'] !== $a->id || time() - (int) $saved['t'] > 600
            || ! hash_equals((string) $saved['s'], (string) $request->query('state', ''))) {
            return redirect('/quests#discord=expired');
        }
        if ($request->query('error') || ! $request->query('code')) {
            return redirect('/quests#discord=cancelled');
        }
        try {
            $d = $oauth->resolve((string) $request->query('code'));
        } catch (Throwable $e) {
            Log::warning('quests: discord oauth '.$e->getMessage());

            return redirect('/quests#discord=error');
        }
        $taken = QuestDiscordLink::where('discord_user_id', $d['id'])->where('passkey_account_id', '!=', $a->id)->exists();
        if ($taken) {
            return redirect('/quests#discord=taken');
        }
        QuestDiscordLink::updateOrCreate(['passkey_account_id' => $a->id], [
            'discord_user_id' => $d['id'], 'username' => $d['username'], 'in_guild' => $d['in_guild'], 'verified_at' => now(),
        ]);
        if (! $d['in_guild']) {
            return redirect('/quests#discord=join');
        }
        $c = $engine->challenges()->firstWhere('key', 'discord');
        if ($c) {
            $out = $engine->claim($a, $c, [], (string) $request->ip());
            if (! $out['ok']) {
                return redirect('/quests#discord='.rawurlencode(Str::limit($out['error'], 120, '')));
            }
        }

        return redirect('/quests#discord=ok');
    }
}
