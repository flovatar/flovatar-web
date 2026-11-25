<?php

namespace App\Http\Controllers;

use App\Models\PasskeyAccount;
use App\Models\PasskeyCredential;
use App\Models\PasskeyTransaction;
use App\Services\Passkey\FlowTransaction;
use App\Services\Passkey\PasskeyCrypto;
use App\Services\Passkey\PasskeyPoc;
use App\Services\Passkey\TemplateRegistry;
use App\Support\PasskeyLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use lbuchs\WebAuthn\WebAuthn;
use RuntimeException;
use Throwable;

/**
 * /passkey — walletless onboarding proof of concept (TESTNET ONLY, routes exist only when PASSKEY_POC_ENABLED=true).
 * WebAuthn ceremonies are verified with lbuchs/webauthn; Flow transactions are built and co-signed in
 * App\Services\Passkey (FLIP-264 signature extension data, sponsor pays every fee).
 */
class PasskeyPocController extends Controller
{
    private const SESSION_ACCOUNT = PasskeyLogin::SESSION_ACCOUNT;
    private const SESSION_CREDENTIAL = PasskeyLogin::SESSION_CREDENTIAL;

    public function __construct()
    {
        abort_unless(config('passkey.enabled') && PasskeyPoc::isTestnet(), 404);
    }

    private function rpId(Request $request): string
    {
        return (string) (config('passkey.rp_id') ?: $request->getHost());
    }

    private function webauthn(Request $request): WebAuthn
    {
        return new WebAuthn((string) config('passkey.rp_name'), $this->rpId($request), ['none', 'packed', 'apple', 'android-key', 'android-safetynet', 'fido-u2f', 'tpm']);
    }

    private function poc(): PasskeyPoc
    {
        return PasskeyPoc::make();
    }

    private function current(Request $request): array
    {
        $account = PasskeyAccount::find($request->session()->get(self::SESSION_ACCOUNT));
        $cred = $account ? PasskeyCredential::where('passkey_account_id', $account->id)->find($request->session()->get(self::SESSION_CREDENTIAL)) : null;

        return [$account, $cred];
    }

    private function requireLogin(Request $request): array
    {
        [$account, $cred] = $this->current($request);
        if (! $account || ! $cred || $account->status !== 'active') {
            abort(response()->json(['error' => 'Please sign in with your passkey first'], 401));
        }

        return [$account, $cred];
    }

    private function fail(string $message, int $status = 422, ?Throwable $e = null): JsonResponse
    {
        if ($e) {
            Log::warning('passkey-poc: '.$message.' — '.$e->getMessage());
        }

        return response()->json(['error' => $message], $status);
    }

    private static function deviceLabel(Request $request): string
    {
        $ua = (string) $request->userAgent();
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS X') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Device',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'browser',
        };

        return $os.' · '.$browser;
    }

    // ------------------------------------------------------------------ page + state

    public function page(Request $request)
    {
        return response()->view('passkey.poc', [
            'sponsor' => config('passkey.sponsor.address'),
            'flowscan' => config('passkey.flowscan'),
            'rpId' => $this->rpId($request),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function me(Request $request): JsonResponse
    {
        [$account, $cred] = $this->current($request);
        $poc = $this->poc();
        $out = [
            'authenticated' => (bool) ($account && $cred && $account->status === 'active'),
            'sponsor' => PasskeyPoc::sponsorAddress(),
            'flowscan' => config('passkey.flowscan'),
            'stock' => null,
            // session()->regenerate() on sign-in/out rotates the CSRF token: the page picks the new one up here
            'csrf' => $request->session()->token(),
        ];
        try {
            $stock = $poc->script('account_state', [['type' => 'Address', 'value' => PasskeyPoc::sponsorAddress()]]);
            $out['stock'] = ['flobits' => count($stock['components'] ?? []), 'sparks' => count(array_filter($stock['components'] ?? [], fn ($c) => ($c['category'] ?? '') === 'spark')), 'flow' => $stock['flow'] ?? null];
        } catch (Throwable $e) {
            Log::warning('passkey-poc: stock read failed: '.$e->getMessage());
        }
        if (! $out['authenticated']) {
            return response()->json($out);
        }
        $chain = null;
        $state = null;
        try {
            $chain = $poc->flow()->account($account->address);
            $state = $poc->script('account_state', [['type' => 'Address', 'value' => $account->address]]);
        } catch (Throwable $e) {
            Log::warning('passkey-poc: chain read failed: '.$e->getMessage());
        }
        $creds = $account->credentials()->orderBy('id')->get();
        $out += [
            'address' => $account->address,
            'email' => $account->email,
            'gift_tx' => $account->gift_tx,
            'creation_tx' => $account->creation_tx,
            'credential' => ['id' => $cred->credential_id, 'key_index' => $cred->key_index, 'label' => $cred->label],
            'credentials' => $creds->map(fn ($c) => [
                'id' => $c->id, 'credential_id' => substr($c->credential_id, 0, 12).'…', 'key_index' => $c->key_index, 'label' => $c->label,
                'status' => $c->status, 'this' => $c->id === $cred->id, 'backed_up' => $c->backed_up, 'public_key' => substr($c->public_key, 0, 16).'…',
            ]),
            'keys' => $chain['keys'] ?? [],
            'balance' => $chain['balance'] ?? null,
            'state' => $state,
            'transactions' => $account->transactions()->latest('id')->limit(20)->get(['kind', 'tx_id', 'status', 'signed_by', 'created_at']),
        ];

        return response()->json($out);
    }

    // ------------------------------------------------------------------ sign up

    public function registerOptions(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email === '') {
            return $this->fail('Please enter your email address to create your account.', 422);
        }
        if (strlen($email) > 190 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || validator(['e' => $email], ['e' => 'email:rfc'])->fails()) {
            return $this->fail('That doesn\'t look like a valid email address. Please check it and try again.', 422);
        }
        if (PasskeyAccount::whereRaw('LOWER(email) = ?', [$email])->where('status', 'active')->exists()) {
            return $this->fail('An account with this email already exists. Use "Sign in with my passkey" instead.', 422);
        }
        $ip = (string) $request->ip();
        $today = now()->startOfDay();
        if (PasskeyAccount::where('created_ip', $ip)->where('created_at', '>=', $today)->count() >= config('passkey.limits.accounts_per_ip_per_day')) {
            return $this->fail('Daily limit reached for new test accounts from your network. Try again tomorrow or sign in with your passkey.', 429);
        }
        if (PasskeyAccount::where('created_at', '>=', $today)->count() >= config('passkey.limits.accounts_per_day')) {
            return $this->fail('The demo has reached today\'s limit of new accounts. Please try again tomorrow.', 429);
        }

        return response()->json($this->creationOptions($request, 'signup', $email));
    }

    private function creationOptions(Request $request, string $mode, ?string $email, ?PasskeyAccount $account = null): array
    {
        $challenge = random_bytes(32);
        $userHandle = random_bytes(32);
        $request->session()->put('passkey_poc.reg', [
            'challenge' => bin2hex($challenge), 'handle' => bin2hex($userHandle), 'email' => $email, 'mode' => $mode,
            'account' => $account?->id, 'expires' => time() + 300,
        ]);
        $name = $email ?: ('flovatar-'.substr(bin2hex($userHandle), 0, 6));
        if ($account) {
            $name = $account->email ?: ('Flovatar '.$account->address);
        }
        $exclude = $account ? $account->credentials()->pluck('credential_id')->map(fn ($id) => ['type' => 'public-key', 'id' => $id])->values()->all() : [];

        return [
            'publicKey' => [
                'rp' => ['id' => $this->rpId($request), 'name' => (string) config('passkey.rp_name')],
                'user' => ['id' => PasskeyCrypto::base64UrlEncode($userHandle), 'name' => $name, 'displayName' => $name],
                'challenge' => PasskeyCrypto::base64UrlEncode($challenge),
                'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7]],
                'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
                'attestation' => 'none',
                'timeout' => 120000,
                'excludeCredentials' => $exclude,
            ],
        ];
    }

    /** Verify a registration ceremony started by creationOptions(); returns [lbuchs data, publicKeyHex, reg session]. */
    private function verifyCreation(Request $request, string $mode): array
    {
        $reg = $request->session()->pull('passkey_poc.reg');
        if (! $reg || $reg['mode'] !== $mode || $reg['expires'] < time()) {
            throw new RuntimeException('The passkey request expired, please start again');
        }
        $data = $request->validate([
            'id' => 'required|string|max:255', 'clientDataJSON' => 'required|string|max:4096', 'attestationObject' => 'required|string|max:16384',
        ]);
        $res = $this->webauthn($request)->processCreate(
            PasskeyCrypto::base64UrlDecode($data['clientDataJSON']),
            PasskeyCrypto::base64UrlDecode($data['attestationObject']),
            hex2bin($reg['challenge']),
            true, true, false, false,
        );
        $credentialId = PasskeyCrypto::base64UrlEncode($res->credentialId);
        if (! hash_equals($credentialId, $data['id'])) {
            throw new RuntimeException('Credential id mismatch');
        }
        if (PasskeyCredential::where('credential_id', $credentialId)->exists()) {
            throw new RuntimeException('This passkey is already registered');
        }
        $pub = PasskeyCrypto::publicKeyHexFromPem($res->credentialPublicKey);

        return [$res, $pub, $reg, $credentialId];
    }

    public function registerVerify(Request $request): JsonResponse
    {
        try {
            [$res, $pub, $reg, $credentialId] = $this->verifyCreation($request, 'signup');
        } catch (Throwable $e) {
            return $this->fail('Passkey could not be verified: '.$e->getMessage(), 422, $e);
        }
        if (empty($reg['email'])) {
            return $this->fail('Please enter your email address to create your account.', 422);
        }
        $account = PasskeyAccount::create(['email' => $reg['email'], 'status' => 'pending', 'created_ip' => (string) $request->ip()]);
        $cred = PasskeyCredential::create([
            'passkey_account_id' => $account->id, 'credential_id' => $credentialId, 'public_key' => $pub, 'key_index' => 0,
            'user_handle' => $reg['handle'], 'aaguid' => $res->AAGUID ? bin2hex($res->AAGUID) : null, 'sign_count' => (int) ($res->signatureCounter ?? 0),
            'label' => self::deviceLabel($request), 'backed_up' => (bool) ($res->isBackedUp ?? false), 'status' => 'pending',
        ]);
        try {
            [$address, $txId] = $this->poc()->createAccount($pub, $account->id);
        } catch (Throwable $e) {
            $account->update(['status' => 'failed']);

            return $this->fail('Creating your Flow account failed: '.$e->getMessage(), 502, $e);
        }
        $account->update(['address' => $address, 'status' => 'active', 'creation_tx' => $txId]);
        $cred->update(['status' => 'active', 'last_used_at' => now()]);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_ACCOUNT, $account->id);
        $request->session()->put(self::SESSION_CREDENTIAL, $cred->id);
        if (PasskeyLogin::enabled()) {
            PasskeyLogin::loginSite($request, $address);
        }

        return response()->json(['address' => $address, 'txId' => $txId, 'csrf' => $request->session()->token(), 'auth_type' => 'passkey']);
    }

    // ------------------------------------------------------------------ sign in

    public function loginOptions(Request $request): JsonResponse
    {
        $challenge = random_bytes(32);
        $request->session()->put('passkey_poc.login', ['challenge' => bin2hex($challenge), 'expires' => time() + 300]);

        return response()->json(['publicKey' => [
            'challenge' => PasskeyCrypto::base64UrlEncode($challenge),
            'rpId' => $this->rpId($request),
            'userVerification' => 'required',
            'timeout' => 120000,
            'allowCredentials' => [],
        ]]);
    }

    public function loginVerify(Request $request): JsonResponse
    {
        $login = $request->session()->pull('passkey_poc.login');
        $data = $request->validate([
            'id' => 'required|string|max:255', 'clientDataJSON' => 'required|string|max:4096',
            'authenticatorData' => 'required|string|max:4096', 'signature' => 'required|string|max:512',
        ]);
        if (! $login || $login['expires'] < time()) {
            return $this->fail('The sign-in request expired, please try again');
        }
        $cred = PasskeyCredential::where('credential_id', $data['id'])->first();
        if (! $cred) {
            return $this->fail('This passkey is not registered here (it may belong to an older test, or to another site).', 404);
        }
        if ($cred->status !== 'active') {
            return $this->fail('This passkey was created on this device but is not on your account yet. Finish "Add this device" by approving it with a passkey that is already on the account.', 409);
        }
        try {
            $wa = $this->webauthn($request);
            $wa->processGet(
                PasskeyCrypto::base64UrlDecode($data['clientDataJSON']),
                PasskeyCrypto::base64UrlDecode($data['authenticatorData']),
                PasskeyCrypto::base64UrlDecode($data['signature']),
                PasskeyCrypto::pemFromPublicKeyHex($cred->public_key),
                hex2bin($login['challenge']),
                null, true, true,
            );
        } catch (Throwable $e) {
            return $this->fail('Passkey sign-in failed: '.$e->getMessage(), 422, $e);
        }
        $cred->update(['last_used_at' => now(), 'sign_count' => (int) ($wa->getSignatureCounter() ?? $cred->sign_count)]);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_ACCOUNT, $cred->passkey_account_id);
        $request->session()->put(self::SESSION_CREDENTIAL, $cred->id);
        if (PasskeyLogin::enabled()) {
            PasskeyLogin::loginSite($request, $cred->account->address);
        }

        return response()->json(['address' => $cred->account->address, 'key_index' => $cred->key_index, 'csrf' => $request->session()->token(), 'auth_type' => 'passkey']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget([self::SESSION_ACCOUNT, self::SESSION_CREDENTIAL]);
        if (PasskeyLogin::enabled()) {
            PasskeyLogin::logoutSite($request);
        }
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'csrf' => $request->session()->token()]);
    }

    // ------------------------------------------------------------------ add another device

    public function deviceOptions(Request $request): JsonResponse
    {
        [$account] = $this->requireLogin($request);
        if ($account->credentials()->count() >= 10) {
            return $this->fail('This demo allows at most 10 passkeys per account.');
        }

        return response()->json($this->creationOptions($request, 'device', null, $account));
    }

    public function deviceVerify(Request $request): JsonResponse
    {
        [$account, $signer] = $this->requireLogin($request);
        try {
            [$res, $pub, $reg, $credentialId] = $this->verifyCreation($request, 'device');
            if ((int) $reg['account'] !== (int) $account->id) {
                throw new RuntimeException('Account changed while adding the device');
            }
        } catch (Throwable $e) {
            return $this->fail('Passkey could not be verified: '.$e->getMessage(), 422, $e);
        }
        $new = PasskeyCredential::create([
            'passkey_account_id' => $account->id, 'credential_id' => $credentialId, 'public_key' => $pub, 'key_index' => null,
            'user_handle' => $reg['handle'], 'aaguid' => $res->AAGUID ? bin2hex($res->AAGUID) : null,
            'label' => self::deviceLabel($request), 'backed_up' => (bool) ($res->isBackedUp ?? false), 'status' => 'pending',
        ]);
        $request->session()->put('passkey_poc.pending_device', $new->id);

        return response()->json(['pending' => $new->id, 'label' => $new->label]);
    }

    // ------------------------------------------------------------------ passkey-signed transactions

    public function txPrepare(Request $request): JsonResponse
    {
        [$account, $signer] = $this->requireLogin($request);
        $data = $request->validate([
            'action' => 'required|in:setup,send_flobit,create_flovatar,add_key',
            'params' => 'nullable|array',
        ]);
        $limit = (int) config('passkey.limits.txs_per_account_per_hour');
        if (PasskeyTransaction::where('passkey_account_id', $account->id)->where('created_at', '>=', now()->subHour())->count() >= $limit) {
            return $this->fail('Too many transactions for this demo account in the last hour.', 429);
        }
        $p = $data['params'] ?? [];
        $u64 = fn ($v) => ['type' => 'UInt64', 'value' => (string) (int) $v];
        $optU64 = fn ($v) => ['type' => 'Optional', 'value' => $v === null || $v === '' ? null : $u64($v)];
        $meta = [];
        try {
            switch ($data['action']) {
                case 'setup':
                    $cadence = 'setup_collections';
                    $args = [];
                    break;
                case 'send_flobit':
                    $to = '0x'.FlowTransaction::addr((string) ($p['to'] ?? PasskeyPoc::sponsorAddress()));
                    $cadence = 'send_flobit';
                    $args = [$u64($p['componentId'] ?? 0), ['type' => 'Address', 'value' => $to]];
                    break;
                case 'create_flovatar':
                    $cadence = 'create_flovatar';
                    $args = [
                        $u64($p['spark'] ?? 0), $u64($p['body'] ?? 0), $u64($p['hair'] ?? 0), $optU64($p['facialHair'] ?? null),
                        $u64($p['eyes'] ?? 0), $u64($p['nose'] ?? 0), $u64($p['mouth'] ?? 0), $u64($p['clothing'] ?? 0),
                        $optU64($p['accessory'] ?? null), $optU64(null), $optU64(null), $optU64(null),
                        ['type' => 'Array', 'value' => []], ['type' => 'Array', 'value' => []], ['type' => 'Array', 'value' => []],
                    ];
                    break;
                case 'add_key':
                    $pendingId = (int) ($p['credential'] ?? $request->session()->get('passkey_poc.pending_device'));
                    $pending = PasskeyCredential::where('passkey_account_id', $account->id)->where('status', 'pending')->find($pendingId);
                    if (! $pending) {
                        return $this->fail('No device is waiting to be added.');
                    }
                    if ($pending->id === $signer->id) {
                        return $this->fail('A new device must be approved by a passkey that is already on the account.');
                    }
                    $cadence = 'add_key';
                    $args = [['type' => 'String', 'value' => $pending->public_key]];
                    $meta = ['pending' => $pending->id];
                    break;
            }

            return response()->json($this->poc()->prepareUserTx($account, $signer, $data['action'], $cadence, $args, $meta));
        } catch (Throwable $e) {
            return $this->fail('Could not prepare the transaction: '.$e->getMessage(), 502, $e);
        }
    }

    /**
     * Site transaction from the allowlist (resources/js/flow/fcl-tx.js): {template: sha256 of the wallet Cadence,
     * args: JSON-Cadence}. The Cadence comes from the server-side manifest, never from the client.
     */
    public function txTemplate(Request $request): JsonResponse
    {
        abort_unless(PasskeyLogin::enabled(), 404);
        [$account, $signer] = $this->requireLogin($request);
        $data = $request->validate([
            'template' => 'required|string|max:80',
            'args' => 'present|array|max:32',
        ]);
        $limit = (int) config('passkey.limits.txs_per_account_per_hour');
        if (PasskeyTransaction::where('passkey_account_id', $account->id)->where('created_at', '>=', now()->subHour())->count() >= $limit) {
            return $this->fail('Too many transactions in the last hour for this account. Please wait a little.', 429);
        }
        try {
            $registry = new TemplateRegistry;
            $tpl = $registry->find($data['template']);
            $args = $registry->validateArgs($tpl['cadence'], $data['args']);
            $code = $registry->resolve($tpl['cadence']);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
        try {
            $out = $this->poc()->prepareUserTxCode($account, $signer, $tpl['id'], $code, $args, ['template' => $tpl['id']]);
        } catch (Throwable $e) {
            return $this->fail('Could not prepare the transaction: '.$e->getMessage(), 502, $e);
        }

        return response()->json($out + ['label' => $tpl['label'], 'template' => $tpl['id']]);
    }

    /** Passkey account panel: address, devices (credentials + on-chain keys), FLOW balance, recent transactions. */
    public function account(Request $request): JsonResponse
    {
        abort_unless(PasskeyLogin::enabled(), 404);
        [$account, $cred] = $this->current($request);
        if (! $account || ! $cred || $account->status !== 'active') {
            return response()->json(['authenticated' => false])->header('Cache-Control', 'private, no-store');
        }
        $chain = null;
        try {
            $chain = $this->poc()->flow()->account($account->address);
        } catch (Throwable $e) {
            Log::warning('passkey: chain read failed: '.$e->getMessage());
        }
        $keys = collect($chain['keys'] ?? [])->keyBy('index');
        $creds = $account->credentials()->orderBy('id')->get();
        // refresh the status of recent transactions the browser followed itself (fcl.tx), max 3 lookups
        $open = $account->transactions()->where(fn ($q) => $q->whereNull('status')->orWhereIn('status', ['Pending', 'Finalized', 'Executed', 'Unknown']))->where('created_at', '>=', now()->subHour())->latest('id')->limit(3)->get();
        foreach ($open as $t) {
            try {
                $r = $this->poc()->flow()->result($t->tx_id);
                if (in_array($r['status'], ['Sealed', 'Expired'], true) || $r['error'] !== '') {
                    $t->update(['status' => $r['error'] !== '' ? 'Failed' : $r['status'], 'error' => $r['error'] ?: null]);
                }
            } catch (Throwable $e) {
                break;
            }
        }

        return response()->json([
            'authenticated' => true,
            'auth_type' => 'passkey',
            'address' => $account->address,
            'email' => $account->email,
            'gift_tx' => $account->gift_tx,
            'created_at' => optional($account->created_at)->toIso8601String(),
            'credential' => ['id' => $cred->id, 'key_index' => $cred->key_index, 'label' => $cred->label],
            'devices' => $creds->map(fn ($c) => [
                'id' => $c->id, 'label' => $c->label, 'status' => $c->status, 'key_index' => $c->key_index,
                'this' => $c->id === $cred->id, 'backed_up' => (bool) $c->backed_up,
                'last_used_at' => optional($c->last_used_at)->toIso8601String(),
                'on_chain' => $c->key_index !== null && $keys->has($c->key_index) && ! $keys[$c->key_index]['revoked'],
                'add_key_tx' => $c->add_key_tx,
            ])->values(),
            'keys' => array_map(fn ($k) => ['index' => $k['index'], 'weight' => $k['weight'], 'revoked' => $k['revoked'], 'sequence_number' => $k['sequence_number']], $chain['keys'] ?? []),
            'flow_balance' => $chain['balance'] ?? null,
            'transactions' => $account->transactions()->latest('id')->limit(10)->get(['kind', 'tx_id', 'status', 'signed_by', 'created_at']),
            'flowscan' => config('passkey.flowscan'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function txSubmit(Request $request): JsonResponse
    {
        [$account] = $this->requireLogin($request);
        $data = $request->validate([
            'token' => 'required|string|max:64',
            'assertion' => 'required|array',
            'assertion.id' => 'required|string|max:255',
            'assertion.clientDataJSON' => 'required|string|max:4096',
            'assertion.authenticatorData' => 'required|string|max:4096',
            'assertion.signature' => 'required|string|max:512',
        ]);
        try {
            $out = $this->poc()->submitUserTx($account, $data['token'], $data['assertion'], $this->rpId($request));
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422, $e);
        }
        if (($out['entry']['kind'] ?? '') === 'add_key') {
            PasskeyCredential::where('id', $out['entry']['meta']['pending'] ?? 0)->update(['add_key_tx' => $out['txId']]);
        }

        return response()->json(['txId' => $out['txId']]);
    }

    /** Status of one of the account's transactions (and post-processing: key index of an added device). */
    public function txStatus(Request $request, string $txId): JsonResponse
    {
        [$account] = $this->requireLogin($request);
        abort_unless(preg_match('/^[0-9a-f]{64}$/', $txId), 404);
        $log = PasskeyTransaction::where('passkey_account_id', $account->id)->where('tx_id', $txId)->firstOrFail();
        $poc = $this->poc();
        $r = $poc->flow()->result($txId);
        $status = $r['error'] !== '' ? 'Failed' : $r['status'];
        if ($log->status !== $status) {
            $log->update(['status' => $status, 'error' => $r['error'] ?: null]);
        }
        if ($status === 'Sealed' && $log->kind === 'add_key') {
            $pending = PasskeyCredential::where('passkey_account_id', $account->id)->where('add_key_tx', $txId)->where('status', 'pending')->first();
            if ($pending) {
                foreach ($poc->flow()->account($account->address)['keys'] as $k) {
                    if ($k['public_key'] === $pending->public_key && ! $k['revoked']) {
                        $pending->update(['key_index' => $k['index'], 'status' => 'active']);
                        break;
                    }
                }
            }
        }

        return response()->json(['txId' => $txId, 'status' => $status, 'error' => $r['error'], 'kind' => $log->kind]);
    }

    // ------------------------------------------------------------------ starter gift (sponsor-signed)

    public function gift(Request $request): JsonResponse
    {
        [$account] = $this->requireLogin($request);
        if ($account->gift_tx) {
            return $this->fail('You already received your starter gift.', 409);
        }
        $lock = Cache::lock('passkey:gift:'.$account->id, 120);
        if (! $lock->get()) {
            return $this->fail('Your gift is already on its way.', 409);
        }
        try {
            $poc = $this->poc();
            $state = $poc->script('account_state', [['type' => 'Address', 'value' => $account->address]]);
            if (empty($state['initialized'])) {
                return $this->fail('Set up your Flovatar collections first (step 3).', 409);
            }
            $stock = $poc->script('account_state', [['type' => 'Address', 'value' => PasskeyPoc::sponsorAddress()]]);
            $components = $stock['components'] ?? [];
            $sparks = array_values(array_filter($components, fn ($c) => ($c['category'] ?? '') === 'spark'));
            $pick = $sparks[0] ?? ($components[0] ?? null);
            if (! $pick) {
                return $this->fail('The starter-gift stock is empty right now. The rest of the demo still works; ask the Flovatar team to refill it.', 409);
            }
            [$txId, $r] = $poc->sendAsSponsor('gift_flobits', [
                ['type' => 'Address', 'value' => $account->address],
                ['type' => 'Array', 'value' => [['type' => 'UInt64', 'value' => (string) $pick['id']]]],
            ], 'gift', $account->id);
            if ($r['error'] !== '') {
                return $this->fail('The gift transaction failed: '.substr($r['error'], 0, 300), 502);
            }
            $account->update(['gift_tx' => $txId]);

            return response()->json(['txId' => $txId, 'item' => $pick]);
        } catch (Throwable $e) {
            return $this->fail('The gift could not be sent: '.$e->getMessage(), 502, $e);
        } finally {
            $lock->release();
        }
    }

    /** Series-1 core templates for the mini builder (cached). */
    public function templates(): JsonResponse
    {
        $rows = Cache::remember('passkey:core-templates', 600, fn () => $this->poc()->script('core_templates'));

        return response()->json(['templates' => array_values($rows ?? [])]);
    }

    public function combination(Request $request): JsonResponse
    {
        $p = $request->validate(['body' => 'required|integer', 'hair' => 'required|integer', 'eyes' => 'required|integer', 'nose' => 'required|integer', 'mouth' => 'required|integer', 'clothing' => 'required|integer']);
        $u = fn ($v) => ['type' => 'UInt64', 'value' => (string) (int) $v];
        $taken = $this->poc()->script('combination_taken', [
            $u($p['body']), $u($p['hair']), ['type' => 'Optional', 'value' => null], $u($p['eyes']), $u($p['nose']), $u($p['mouth']), $u($p['clothing']),
        ]);

        return response()->json(['taken' => (bool) $taken]);
    }
}
