<?php

namespace App\Services\Quests;

use App\Models\PasskeyAccount;
use App\Models\PasskeyWalletLink;
use App\Models\QuestAccountFlag;
use App\Models\QuestChallenge;
use App\Models\QuestClaim;
use App\Models\QuestDiscordLink;
use App\Services\Passkey\WalletLink;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * ĐUST Quests rules (KB notes/dust-quests.md).
 *
 * Eligibility: an ACTIVE passkey account created by us (passkey_accounts) with a VERIFIED email, signed in with that
 * passkey (the controller checks the session). Wallet users (Flow Wallet / Dapper) never see or reach any of it.
 * Each challenge pays once per account (UNIQUE(account, challenge)); amounts come from quest_challenges.
 * Budget: per-account cap (default Đ1,000, admin override per account), season budget (Đ1,000,000, counted on
 * committed = pending + verified + paid), daily payout cap (applied when the payout worker reserves a batch).
 */
class QuestEngine
{
    public function __construct(
        protected ?ChainEvidence $chain = null,
        protected ?ShareCheck $share = null,
    ) {
        $this->chain ??= new ChainEvidence;
        $this->share ??= new ShareCheck;
    }

    public static function make(): self
    {
        return app(self::class);
    }

    public function chain(): ChainEvidence
    {
        return $this->chain;
    }

    // ------------------------------------------------------------------ catalogue + settings

    /** Insert catalogue rows that don't exist yet (never overwrites admin edits). */
    public function syncCatalogue(): int
    {
        $existing = QuestChallenge::pluck('key')->all();
        $n = 0;
        foreach (config('quests.catalogue', []) as $c) {
            if (in_array($c['key'], $existing, true)) {
                continue;
            }
            QuestChallenge::create([
                'key' => $c['key'], 'title' => $c['title'], 'description' => $c['description'] ?? null,
                'amount' => $c['amount'], 'verify_type' => $c['verify'], 'url' => $c['url'] ?? null, 'action' => $c['action'] ?? null,
                'active' => $c['active'] ?? true, 'sort' => $c['sort'] ?? 0,
            ]);
            $n++;
        }

        return $n;
    }

    public function challenges(bool $activeOnly = true): Collection
    {
        if (! Cache::get('quests:catalogue-synced')) {
            $this->syncCatalogue();
            Cache::put('quests:catalogue-synced', 1, 300);
        }

        return QuestChallenge::query()->when($activeOnly, fn ($q) => $q->where('active', true))->orderBy('sort')->orderBy('id')->get()
            ->filter(fn ($c) => (! $c->starts_at || $c->starts_at->isPast()) && (! $c->ends_at || $c->ends_at->isFuture()))->values();
    }

    public static function setting(string $key, $default = null)
    {
        $v = DB::table('quest_settings')->where('key', $key)->value('value');

        return $v === null ? $default : json_decode($v, true);
    }

    public static function setSetting(string $key, $value): void
    {
        DB::table('quest_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
    }

    public static function season(): string
    {
        return (string) config('quests.season.key', 'season-1');
    }

    public static function seasonBudget(): float
    {
        return (float) self::setting('season_budget', (float) config('quests.season.budget'));
    }

    public static function dailyCap(): float
    {
        return (float) self::setting('daily_cap', (float) config('quests.season.daily_cap'));
    }

    public static function defaultUserCap(): float
    {
        return (float) self::setting('per_user_cap', (float) config('quests.season.per_user_cap'));
    }

    public static function payoutsPaused(): bool
    {
        return (bool) self::setting('payouts_paused', false);
    }

    public static function claimsPaused(): bool
    {
        return (bool) self::setting('claims_paused', false);
    }

    // ------------------------------------------------------------------ accounts

    public function flags(PasskeyAccount $a): ?QuestAccountFlag
    {
        return QuestAccountFlag::where('passkey_account_id', $a->id)->first();
    }

    public function capFor(PasskeyAccount $a): float
    {
        $f = $this->flags($a);

        return $f && $f->cap_override !== null ? (float) $f->cap_override : self::defaultUserCap();
    }

    public function isFrozen(PasskeyAccount $a): bool
    {
        return (bool) optional($this->flags($a))->frozen;
    }

    /** ['eligible' => bool, 'reason' => code, 'message' => text] */
    public function eligibility(?PasskeyAccount $a): array
    {
        if (! $a || $a->status !== 'active' || ! $a->address) {
            return ['eligible' => false, 'reason' => 'not_passkey', 'message' => 'ĐUST Quests are for accounts created with Email + passkey.'];
        }
        if (! $a->hasVerifiedEmail()) {
            return ['eligible' => false, 'reason' => 'email', 'message' => 'Confirm your email in the passkey account panel to start the quests.'];
        }
        if ($this->isFrozen($a)) {
            return ['eligible' => false, 'reason' => 'frozen', 'message' => 'Your quests are paused while the Flovatar team reviews your account.'];
        }

        return ['eligible' => true, 'reason' => null, 'message' => null];
    }

    /** ĐUST per status for one account in this season. */
    public function totals(PasskeyAccount $a): array
    {
        $rows = QuestClaim::where('passkey_account_id', $a->id)->where('season', self::season())
            ->selectRaw('status, SUM(amount) as s')->groupBy('status')->pluck('s', 'status');
        $t = ['pending' => (float) ($rows['pending'] ?? 0), 'verified' => (float) ($rows['verified'] ?? 0), 'paid' => (float) ($rows['paid'] ?? 0)];
        $t['committed'] = $t['pending'] + $t['verified'] + $t['paid'];

        return $t;
    }

    public static function seasonTotals(): array
    {
        $rows = QuestClaim::where('season', self::season())->selectRaw('status, SUM(amount) as s, COUNT(*) as n')->groupBy('status')->get()->keyBy('status');
        $g = fn ($k) => (float) ($rows[$k]->s ?? 0);
        $reserved = (float) QuestClaim::where('season', self::season())->where('status', QuestClaim::VERIFIED)->whereNotNull('batch_id')->sum('amount');
        $today = (float) QuestClaim::where('season', self::season())->where(function ($q) {
            $q->where(fn ($q) => $q->where('status', QuestClaim::PAID)->where('paid_at', '>=', now()->utc()->startOfDay()))
                ->orWhere(fn ($q) => $q->where('status', QuestClaim::VERIFIED)->whereNotNull('batch_id'));
        })->sum('amount');

        return [
            'pending' => $g('pending'), 'verified' => $g('verified'), 'paid' => $g('paid'), 'rejected' => $g('rejected'),
            'committed' => $g('pending') + $g('verified') + $g('paid'), 'reserved' => $reserved, 'paid_today' => $today,
            'counts' => collect(['pending', 'verified', 'paid', 'rejected'])->mapWithKeys(fn ($k) => [$k => (int) ($rows[$k]->n ?? 0)])->all(),
        ];
    }

    /** Record that the account was seen signed in today (UTC) — 7-day streak. */
    public function recordActivity(PasskeyAccount $a): void
    {
        $day = now()->utc()->toDateString();
        $key = 'quests:seen:'.$a->id.':'.$day;
        if (Cache::get($key)) {
            return;
        }
        try {
            DB::table('quest_activity_days')->insertOrIgnore(['passkey_account_id' => $a->id, 'day' => $day, 'created_at' => now(), 'updated_at' => now()]);
            Cache::put($key, 1, 86400);
        } catch (Throwable $e) {
            Log::warning('quests: activity '.$e->getMessage());
        }
    }

    public function streak(PasskeyAccount $a): int
    {
        $days = DB::table('quest_activity_days')->where('passkey_account_id', $a->id)->orderByDesc('day')->limit(60)->pluck('day')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $n = 0;
        $cursor = now()->utc()->startOfDay();
        foreach ($days as $d) {
            if ($d === $cursor->toDateString()) {
                $n++;
                $cursor = $cursor->subDay();
            } elseif ($d < $cursor->toDateString()) {
                break;
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------ state for the UI

    public function state(PasskeyAccount $a): array
    {
        $elig = $this->eligibility($a);
        $claims = QuestClaim::where('passkey_account_id', $a->id)->get()->keyBy('challenge_id');
        $progress = DB::table('quest_progress')->where('passkey_account_id', $a->id)->get()->keyBy('challenge_key');
        $totals = $this->totals($a);
        $cap = $this->capFor($a);
        $honor = (int) config('quests.limits.honor_seconds');
        $discord = QuestDiscordLink::where('passkey_account_id', $a->id)->first();
        $list = $this->challenges()->map(function (QuestChallenge $c) use ($claims, $progress, $honor, $discord) {
            $claim = $claims->get($c->id);
            $p = $progress->get($c->key);
            $row = [
                'key' => $c->key, 'title' => $c->title, 'description' => $c->description, 'amount' => (float) $c->amount,
                'verify' => $c->verify_type, 'url' => $c->url, 'action' => $c->action,
                'status' => $claim ? $claim->status : 'open',
                'awarded' => $claim ? (float) $claim->amount : null,
                'tx_id' => $claim?->tx_id,
                'note' => $claim && in_array($claim->status, ['pending', 'rejected'], true) ? ($claim->review_note ?: null) : null,
            ];
            if ($c->verify_type === 'honor' && $p && $p->started_at) {
                $row['started_ago'] = max(0, now()->getTimestamp() - Carbon::parse($p->started_at)->getTimestamp());
                $row['wait'] = max(0, $honor - $row['started_ago']);
            }
            if ($c->verify_type === 'quiz') {
                $row['attempts_left'] = $this->quizAttemptsLeft($p);
            }
            if ($c->verify_type === 'discord') {
                $row['configured'] = DiscordOAuth::configured();
                $row['discord'] = $discord ? ['username' => $discord->username, 'in_guild' => $discord->in_guild] : null;
                $row['invite'] = config('quests.discord.invite');
            }

            return $row;
        })->values();

        return [
            'eligible' => $elig['eligible'], 'reason' => $elig['reason'], 'message' => $elig['message'],
            'address' => $a->address,
            'cap' => $cap,
            'earned' => $totals['committed'], 'paid' => $totals['paid'], 'pending' => $totals['pending'], 'verified' => $totals['verified'],
            'streak' => $this->streak($a), 'streak_days' => (int) config('quests.limits.streak_days', 7),
            'claims_paused' => self::claimsPaused(),
            'challenges' => $list,
            'quiz' => collect(config('quests.quiz', []))->map(fn ($q, $i) => ['id' => $i, 'q' => $q['q'], 'options' => $q['options']])->values(),
            'flowscan' => config('passkey.flowscan'),
        ];
    }

    // ------------------------------------------------------------------ honor clicks + quiz bookkeeping

    public function startHonor(PasskeyAccount $a, QuestChallenge $c): int
    {
        $row = DB::table('quest_progress')->where('passkey_account_id', $a->id)->where('challenge_key', $c->key)->first();
        if (! $row) {
            DB::table('quest_progress')->insertOrIgnore(['passkey_account_id' => $a->id, 'challenge_key' => $c->key, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        } elseif (! $row->started_at) {
            DB::table('quest_progress')->where('id', $row->id)->update(['started_at' => now(), 'updated_at' => now()]);
        }

        return (int) config('quests.limits.honor_seconds');
    }

    protected function quizAttemptsLeft($p): int
    {
        $max = (int) config('quests.limits.quiz_attempts_per_day', 5);
        if (! $p || ! $p->attempts_day || Carbon::parse($p->attempts_day)->toDateString() !== now()->utc()->toDateString()) {
            return $max;
        }

        return max(0, $max - (int) $p->attempts);
    }

    protected function countQuizAttempt(PasskeyAccount $a): void
    {
        $today = now()->utc()->toDateString();
        $row = DB::table('quest_progress')->where('passkey_account_id', $a->id)->where('challenge_key', 'lore_quiz')->first();
        if (! $row) {
            DB::table('quest_progress')->insertOrIgnore(['passkey_account_id' => $a->id, 'challenge_key' => 'lore_quiz', 'attempts' => 1, 'attempts_day' => $today, 'created_at' => now(), 'updated_at' => now()]);

            return;
        }
        $same = $row->attempts_day && Carbon::parse($row->attempts_day)->toDateString() === $today;
        DB::table('quest_progress')->where('id', $row->id)->update(['attempts' => $same ? $row->attempts + 1 : 1, 'attempts_day' => $today, 'updated_at' => now()]);
    }

    // ------------------------------------------------------------------ verification

    /**
     * Check one challenge for the account. Returns
     *   ['ok' => true, 'status' => 'verified'|'pending', 'evidence' => [...], 'evidence_key' => ?string, 'note' => ?string]
     *   ['ok' => false, 'error' => text, 'status_code' => int]
     */
    public function verify(PasskeyAccount $a, QuestChallenge $c, array $input = []): array
    {
        $no = fn (string $msg, int $code = 422) => ['ok' => false, 'error' => $msg, 'status_code' => $code];
        $yes = fn (array $ev, ?string $key = null, string $status = 'verified', ?string $note = null) => ['ok' => true, 'status' => $status, 'evidence' => $ev, 'evidence_key' => $key, 'note' => $note];
        $chainCfg = config('quests.chain.'.$c->key);

        try {
            switch ($c->key) {
                case 'account_email':
                    return $a->hasVerifiedEmail()
                        ? $yes(['email_verified_at' => $a->email_verified_at->toIso8601String()], 'email:'.hash('sha256', strtolower(trim((string) $a->email))))
                        : $no('Confirm your email first (passkey account panel).');
                case 'starter_flobit':
                    if ($a->gift_tx && $this->chain->sealedOk($a->gift_tx)) {
                        return $yes(['tx_id' => $a->gift_tx]);
                    }

                    return $no('Claim your starter Flobit in the passkey account panel first (it takes a few seconds to arrive).');
                case 'streak_7':
                    $s = $this->streak($a);
                    $need = (int) config('quests.limits.streak_days', 7);

                    return $s >= $need ? $yes(['streak' => $s]) : $no("You are on day {$s} of {$need}. Come back tomorrow!");
                case 'graduation':
                    return $this->verifyGraduation($a, $yes, $no);
                case 'discord':
                    $l = QuestDiscordLink::where('passkey_account_id', $a->id)->first();
                    if (! $l) {
                        return $no('Connect your Discord account first.');
                    }
                    if (! $l->in_guild) {
                        return $no('Join the Flovatar Discord server, then connect again.');
                    }

                    return $yes(['discord_user_id' => $l->discord_user_id, 'username' => $l->username], 'discord:'.$l->discord_user_id);
                case 'first_sale':
                    $sale = DB::table('sales')->where('flow_seller_address', strtolower($a->address))
                        ->where(fn ($q) => $q->whereNull('flow_buyer_address')->orWhere('flow_buyer_address', '!=', strtolower($a->address)))
                        ->orderBy('id')->first(['id', 'flow_buyer_address', 'currency', 'transaction_id']);

                    return $sale ? $yes(['sale_id' => $sale->id, 'buyer' => $sale->flow_buyer_address, 'currency' => $sale->currency ?? null, 'transaction_id' => $sale->transaction_id ?? null])
                        : $no('No sale found yet. Sales show up here about a minute after they happen.');
                case 'first_listing':
                    $offer = DB::table('sale_offers')->where('flow_address', strtolower($a->address))->orderBy('id')->first(['id', 'currency']);
                    if ($offer) {
                        return $yes(['sale_offer_id' => $offer->id, 'currency' => $offer->currency ?? null]);
                    }
                    $hit = $this->chain->find($a, $chainCfg['kinds'], $chainCfg['events']);

                    return $hit ? $yes($hit) : $no('No listing found yet. List an item from your collection, then come back.');
                case 'first_dust_spend':
                    $sf = ChainEvidence::eventType('NFTStorefrontV2.ListingCompleted');
                    $hit = $this->chain->find($a, $chainCfg['kinds'], $chainCfg['events'], function ($r, $row) use ($sf) {
                        if ($row->kind !== 'dust-sale-buy') {
                            return true;
                        }
                        foreach ($r['events'] as $e) {
                            if ($e['type'] === $sf && ! empty($e['values']['purchased'])) {
                                return true;
                            }
                        }

                        return false;
                    });

                    return $hit ? $yes($hit) : $no('Not yet: buy something with ĐUST on the marketplace, or create a Psyche Likee or a Flovatar.');
                case 'lore_quiz':
                    return $this->verifyQuiz($a, $input, $yes, $no);
                case 'share_card':
                    $post = ShareCheck::parse((string) ($input['url'] ?? ''));
                    if (! $post) {
                        return $no('Paste the link of your post on X, TikTok or Instagram (https://x.com/you/status/…).');
                    }
                    $r = $this->share->check($post);

                    return $yes($r['evidence'], 'share:'.$post['key'], $r['status'], $r['reason'] ?: null);
            }
            if ($c->verify_type === 'honor') {
                $p = DB::table('quest_progress')->where('passkey_account_id', $a->id)->where('challenge_key', $c->key)->first();
                if (! $p || ! $p->started_at) {
                    return $no('Open the link first.');
                }
                $ago = now()->getTimestamp() - Carbon::parse($p->started_at)->getTimestamp();
                $wait = (int) config('quests.limits.honor_seconds') - $ago;

                return $wait > 0 ? $no("Almost there: claim in {$wait} s.", 425) : $yes(['opened' => $c->url, 'seconds' => $ago]);
            }
            if ($chainCfg) {
                $hit = $this->chain->find($a, $chainCfg['kinds'], $chainCfg['events']);

                return $hit ? $yes($hit) : $no('We could not find this on the blockchain yet. Do it on the site (it is gasless), wait for the confirmation, then try again.');
            }

            return $no('This quest cannot be claimed here.');
        } catch (Throwable $e) {
            Log::warning('quests: verify '.$c->key.' failed: '.$e->getMessage());

            return $no('We could not check this right now (blockchain read failed). Please try again in a minute.', 502);
        }
    }

    protected function verifyGraduation(PasskeyAccount $a, callable $yes, callable $no): array
    {
        $links = PasskeyWalletLink::where('passkey_account_id', $a->id)->where('status', 'linked')->orderBy('id')->get();
        if ($links->isEmpty()) {
            return $no('Link your account to Flow Wallet or Dapper Wallet in the passkey account panel first.');
        }
        foreach ($links as $l) {
            $parent = strtolower($l->parent_address);
            // one graduation reward per wallet, ever
            if (QuestClaim::where('evidence_key', 'wallet:'.$parent)->exists()) {
                continue;
            }
            $state = WalletLink::make()->status($a->address, $parent);
            if (($state['parents'][$parent] ?? false) && $state['managerHasChild']) {
                $l->update(['rewarded_at' => now()]);

                return $yes(['parent' => $parent, 'wallet' => $l->wallet, 'claim_tx' => $l->claim_tx], 'wallet:'.$parent);
            }
        }

        return $no('This wallet link is not confirmed on the blockchain, or that wallet already received the graduation reward.');
    }

    protected function verifyQuiz(PasskeyAccount $a, array $input, callable $yes, callable $no): array
    {
        $p = DB::table('quest_progress')->where('passkey_account_id', $a->id)->where('challenge_key', 'lore_quiz')->first();
        if ($this->quizAttemptsLeft($p) <= 0) {
            return $no('No more tries today. Read a chapter of A Dust Odyssey and come back tomorrow!', 429);
        }
        $quiz = config('quests.quiz', []);
        $answers = $input['answers'] ?? null;
        if (! is_array($answers) || count($answers) !== count($quiz)) {
            return $no('Answer all '.count($quiz).' questions.');
        }
        $this->countQuizAttempt($a);
        $right = 0;
        foreach ($quiz as $i => $q) {
            if (isset($answers[$i]) && is_numeric($answers[$i]) && (int) $answers[$i] === (int) $q['answer']) {
                $right++;
            }
        }
        if ($right < count($quiz)) {
            $left = $this->quizAttemptsLeft(DB::table('quest_progress')->where('passkey_account_id', $a->id)->where('challenge_key', 'lore_quiz')->first());

            return $no("{$right} of ".count($quiz)." right. Try again ({$left} ".($left === 1 ? 'try' : 'tries').' left today).');
        }

        return $yes(['right' => $right]);
    }

    // ------------------------------------------------------------------ claim (verify + caps + record)

    public static function ipBucket(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $bin = @inet_pton($ip);
            if ($bin !== false) {
                return bin2hex(substr($bin, 0, 8)).'::/64';
            }
        }

        return $ip;
    }

    /**
     * Verify and record one challenge. ['ok' => true, 'claim' => QuestClaim, 'message'] or ['ok' => false, 'error', 'status_code'].
     */
    public function claim(PasskeyAccount $a, QuestChallenge $c, array $input, string $ip): array
    {
        $fail = fn (string $m, int $code = 422) => ['ok' => false, 'error' => $m, 'status_code' => $code];
        if (! $c->active) {
            return $fail('This quest is not active.', 404);
        }
        if (self::claimsPaused()) {
            return $fail('Quests are paused for a moment. Please come back later.', 503);
        }
        $elig = $this->eligibility($a);
        if (! $elig['eligible']) {
            return $fail($elig['message'], 403);
        }
        $bucket = self::ipBucket($ip);
        $rl = 'quests:ip:'.$bucket;
        if (RateLimiter::tooManyAttempts($rl, (int) config('quests.limits.claims_per_ip_per_hour'))) {
            return $fail('Too many attempts from your network. Please try again later.', 429);
        }
        RateLimiter::hit($rl, 3600);
        if ($existing = QuestClaim::where('passkey_account_id', $a->id)->where('challenge_id', $c->id)->first()) {
            return $fail($existing->status === QuestClaim::REJECTED ? 'This quest was not approved for your account.' : 'You already completed this quest.', 409);
        }
        $lock = Cache::lock('quests:claim:'.$a->id, 30);
        if (! $lock->get()) {
            return $fail('Another claim is being checked. Try again in a few seconds.', 409);
        }
        try {
            $v = $this->verify($a, $c, $input);
            if (! $v['ok']) {
                return $fail($v['error'], $v['status_code']);
            }
            if ($v['evidence_key'] && QuestClaim::where('evidence_key', $v['evidence_key'])->exists()) {
                return $fail(match (explode(':', $v['evidence_key'])[0]) {
                    'discord' => 'This Discord account was already used by another Flovatar account.',
                    'share' => 'This post was already used.',
                    'wallet' => 'This wallet already received the graduation reward.',
                    'email' => 'This email already received the reward.',
                    default => 'Already used.',
                }, 409);
            }
            $status = $v['status'];
            $note = $v['note'];
            // per-IP gate: many quest accounts from one network → review
            $others = QuestClaim::where('ip', $bucket)->where('created_at', '>=', now()->subDay())->where('passkey_account_id', '!=', $a->id)
                ->distinct()->count('passkey_account_id');
            if ($status === QuestClaim::VERIFIED && $others >= (int) config('quests.limits.accounts_per_ip_per_day')) {
                $status = QuestClaim::PENDING;
                $note = 'Thanks! The Flovatar team reviews this one (several accounts on the same network).';
                $v['evidence']['review'] = 'ip-limit';
            }
            if ($c->max_claims_total && QuestClaim::where('challenge_id', $c->id)->whereIn('status', ['pending', 'verified', 'paid'])->count() >= $c->max_claims_total) {
                return $fail('This quest is fully claimed.', 409);
            }

            return $this->award($a, $c, $status, $v['evidence'], $v['evidence_key'], $note, $bucket);
        } finally {
            $lock->release();
        }
    }

    /** Apply the per-account cap (clip to what is left) and the season budget, then insert the claim row. */
    protected function award(PasskeyAccount $a, QuestChallenge $c, string $status, array $evidence, ?string $evidenceKey, ?string $note, string $bucket): array
    {
        $season = Cache::lock('quests:season-lock', 15);
        $season->block(10);
        try {
            $left = $this->capFor($a) - $this->totals($a)['committed'];
            if ($left <= 0.000001) {
                return ['ok' => false, 'error' => 'You reached the season cap of Đ '.number_format($this->capFor($a)).'. Thank you for playing!', 'status_code' => 409];
            }
            $amount = round(min((float) $c->amount, $left), 8);
            if (self::seasonTotals()['committed'] + $amount > self::seasonBudget() + 0.000001) {
                return ['ok' => false, 'error' => 'The ĐUST Quests budget for this season is used up. Thank you for playing!', 'status_code' => 409];
            }
            if ($amount < (float) $c->amount) {
                $evidence['clipped_from'] = (float) $c->amount;
            }
            try {
                $claim = QuestClaim::create([
                    'passkey_account_id' => $a->id, 'address' => strtolower($a->address), 'challenge_id' => $c->id, 'challenge_key' => $c->key,
                    'season' => self::season(), 'amount' => $amount, 'status' => $status, 'evidence' => $evidence, 'evidence_key' => $evidenceKey,
                    'review_note' => $status === QuestClaim::PENDING ? $note : null, 'ip' => $bucket,
                    'verified_at' => $status === QuestClaim::VERIFIED ? now() : null,
                ]);
            } catch (QueryException $e) {
                return ['ok' => false, 'error' => 'You already completed this quest.', 'status_code' => 409];
            }
        } finally {
            $season->release();
        }

        return ['ok' => true, 'claim' => $claim, 'message' => $status === QuestClaim::VERIFIED
            ? 'Quest complete! Đ '.rtrim(rtrim(number_format($amount, 2, '.', ','), '0'), '.').' is on its way to your account.'
            : ($note ?: 'Thanks! The Flovatar team will review it.')];
    }

    // ------------------------------------------------------------------ admin

    /** Pending → verified (re-checks the cap and budget at approval time). */
    public function approve(QuestClaim $claim, ?string $note = null): array
    {
        if ($claim->status !== QuestClaim::PENDING) {
            return ['ok' => false, 'error' => 'Only pending claims can be approved.'];
        }
        $a = PasskeyAccount::find($claim->passkey_account_id);
        if (! $a) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }
        $others = $this->totals($a)['committed'] - (float) $claim->amount;
        if ($others + (float) $claim->amount > $this->capFor($a) + 0.000001) {
            return ['ok' => false, 'error' => 'Over the account cap.'];
        }
        $claim->update(['status' => QuestClaim::VERIFIED, 'verified_at' => now(), 'review_note' => $note]);

        return ['ok' => true];
    }

    public function reject(QuestClaim $claim, ?string $note = null): array
    {
        if (! in_array($claim->status, [QuestClaim::PENDING, QuestClaim::VERIFIED], true) || $claim->batch_id) {
            return ['ok' => false, 'error' => 'Only pending or verified (not yet in a payout batch) claims can be rejected.'];
        }
        $claim->update(['status' => QuestClaim::REJECTED, 'rejected_at' => now(), 'review_note' => $note ?: 'Not approved.']);

        return ['ok' => true];
    }

    public function freeze(PasskeyAccount $a, bool $frozen, ?string $reason = null): void
    {
        QuestAccountFlag::updateOrCreate(['passkey_account_id' => $a->id], ['frozen' => $frozen, 'frozen_reason' => $frozen ? $reason : null]);
    }

    public function setCapOverride(PasskeyAccount $a, ?float $cap): void
    {
        QuestAccountFlag::updateOrCreate(['passkey_account_id' => $a->id], ['cap_override' => $cap]);
    }
}
