<?php

namespace App\Services\Passkey;

use App\Services\Flow\CadenceJson;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Flow Access REST calls needed by the passkey PoC (testnet). */
class FlowGateway
{
    protected string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim($base ?? (string) config('services.flow.rest'), '/');
    }

    protected function http()
    {
        // The access node rate-limits per IP (shared with the site's indexer): retry 429s and connection errors only.
        return Http::baseUrl($this->base)->acceptJson()->timeout(20)->connectTimeout(8)
            ->retry(5, fn ($attempt) => 400 * $attempt + random_int(0, 200), function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429);
            }, throw: false);
    }

    public function latestSealedBlockId(): string
    {
        $res = $this->http()->get('/v1/blocks', ['height' => 'sealed'])->throw()->json();

        return $res[0]['header']['id'];
    }

    /** ['address','balance'(FLOW float),'keys'=>[['index','public_key','sequence_number','weight','revoked','signing_algorithm','hashing_algorithm']]] */
    public function account(string $address): array
    {
        $res = $this->http()->get('/v1/accounts/0x'.FlowTransaction::addr($address), ['expand' => 'keys'])->throw()->json();
        $keys = array_map(fn ($k) => [
            'index' => (int) $k['index'],
            'public_key' => strtolower(preg_replace('/^0x/', '', $k['public_key'])),
            'sequence_number' => (int) $k['sequence_number'],
            'weight' => (int) $k['weight'],
            'revoked' => (bool) $k['revoked'],
            'signing_algorithm' => $k['signing_algorithm'],
            'hashing_algorithm' => $k['hashing_algorithm'],
        ], $res['keys'] ?? []);

        return ['address' => '0x'.FlowTransaction::addr($res['address']), 'balance' => ((int) $res['balance']) / 1e8, 'keys' => $keys];
    }

    public function sequenceNumber(string $address, int $keyIndex): int
    {
        foreach ($this->account($address)['keys'] as $k) {
            if ($k['index'] === $keyIndex) {
                return $k['sequence_number'];
            }
        }
        throw new RuntimeException('Key index not found on account');
    }

    public function send(FlowTransaction $tx): string
    {
        $res = $this->http()->post('/v1/transactions', $tx->toRestBody());
        if (! $res->successful()) {
            $msg = (string) ($res->json('message') ?? $res->body());
            throw new RuntimeException('Flow rejected the transaction: '.substr($msg, 0, 600));
        }

        return (string) $res->json('id');
    }

    /** ['status' => Pending|Finalized|Executed|Sealed|Expired, 'error' => string, 'events' => [...]] */
    public function result(string $txId): array
    {
        $res = $this->http()->get('/v1/transaction_results/'.$txId);
        if ($res->status() === 404) {
            return ['status' => 'Unknown', 'error' => '', 'events' => []];
        }
        $res->throw();
        $j = $res->json();
        $events = array_map(function ($e) {
            $payload = json_decode(base64_decode($e['payload'] ?? ''), true);

            return ['type' => $e['type'], 'values' => $payload ? CadenceJson::decode($payload) : null];
        }, $j['events'] ?? []);

        return ['status' => $j['status'] ?? 'Unknown', 'error' => (string) ($j['error_message'] ?? ''), 'events' => $events];
    }

    /** Poll until sealed (or executed with an error); returns the last result. */
    public function wait(string $txId, int $timeoutSeconds = 40, bool $untilSealed = true): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $r = ['status' => 'Unknown', 'error' => '', 'events' => []];
        while (microtime(true) < $deadline) {
            $r = $this->result($txId);
            if ($r['error'] !== '' || $r['status'] === 'Sealed' || $r['status'] === 'Expired' || (! $untilSealed && $r['status'] === 'Executed')) {
                return $r;
            }
            usleep(1_500_000);
        }

        return $r;
    }

    public function script(string $code, array $args = [])
    {
        $res = $this->http()->post('/v1/scripts?block_height=sealed', [
            'script' => base64_encode($code),
            'arguments' => array_map(fn ($a) => base64_encode(json_encode($a)), $args),
        ]);
        if (! $res->successful()) {
            throw new RuntimeException('Flow script failed: '.substr((string) ($res->json('message') ?? $res->body()), 0, 500));
        }

        return CadenceJson::decode(json_decode(base64_decode(trim((string) $res->body(), '"')), true));
    }
}
