<?php

namespace App\Services\Quests;

use App\Models\PasskeyAccount;
use App\Models\PasskeyTransaction;
use App\Services\Flow\CadenceJson;
use App\Services\Passkey\FlowTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Chain-first evidence for ĐUST Quests (testnet REST).
 *
 * Every transaction of a passkey account is co-signed by our sponsor, so passkey_transactions lists them all (kind =
 * allowlisted template id). A challenge is proven by one of those transactions when, ON CHAIN: it is sealed without
 * error, the account is its authorizer, and it emitted one of the expected events of our contracts (exact address).
 */
class ChainEvidence
{
    protected string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim($base ?? (string) config('services.flow.rest'), '/');
    }

    protected function http()
    {
        return Http::baseUrl($this->base)->acceptJson()->timeout(20)->connectTimeout(8)
            ->retry(4, fn ($a) => 400 * $a + random_int(0, 200), function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429);
            }, throw: false);
    }

    /** /v1/transactions/{id}: ['authorizers' => [0x..], 'proposer' => 0x.., 'payer' => 0x.., 'arguments' => [raw JSON-Cadence], 'script' => string] */
    public function transaction(string $txId): array
    {
        $res = $this->http()->get('/v1/transactions/'.$txId);
        if ($res->status() === 404) {
            throw new RuntimeException('Transaction not found');
        }
        $res->throw();
        $j = $res->json();

        return [
            'authorizers' => array_map(fn ($a) => '0x'.FlowTransaction::addr($a), $j['authorizers'] ?? []),
            'proposer' => '0x'.FlowTransaction::addr($j['proposal_key']['address'] ?? '0'),
            'payer' => '0x'.FlowTransaction::addr($j['payer'] ?? '0'),
            'arguments' => array_map(fn ($a) => json_decode(base64_decode($a), true, 512, JSON_BIGINT_AS_STRING), $j['arguments'] ?? []),
            'script' => base64_decode((string) ($j['script'] ?? '')),
        ];
    }

    /** ['status', 'error', 'events' => [['type', 'values']]] (sealed results are immutable → cached). */
    public function result(string $txId): array
    {
        $key = 'quests:txres:'.$txId;
        if ($hit = Cache::get($key)) {
            return $hit;
        }
        $res = $this->http()->get('/v1/transaction_results/'.$txId);
        if ($res->status() === 404) {
            return ['status' => 'Unknown', 'error' => '', 'events' => []];
        }
        $res->throw();
        $j = $res->json();
        $out = [
            'status' => $j['status'] ?? 'Unknown',
            'error' => (string) ($j['error_message'] ?? ''),
            'events' => array_map(fn ($e) => ['type' => $e['type'], 'values' => CadenceJson::decodePayload($e['payload'] ?? '')], $j['events'] ?? []),
        ];
        if ($out['status'] === 'Sealed') {
            Cache::put($key, $out, 86400);
        }

        return $out;
    }

    /** Full event type id for "Contract.Event": storefront events at the storefront address, the rest at the Flovatar account. */
    public static function eventType(string $short): string
    {
        [$contract] = explode('.', $short, 2);
        $addr = $contract === 'NFTStorefrontV2' ? config('services.flow.storefrontaddress') : (config('services.flow.address') ?: config('flovatar.contractAddress'));

        return 'A.'.FlowTransaction::addr((string) $addr).'.'.$short;
    }

    /**
     * First sealed, error-free sponsored transaction of the account with one of $kinds that the account authorized and
     * that emitted one of $events. Returns ['tx_id', 'kind', 'event'] or null.
     */
    public function find(PasskeyAccount $account, array $kinds, array $events, ?callable $extra = null): ?array
    {
        $types = array_map([self::class, 'eventType'], $events);
        $rows = PasskeyTransaction::where('passkey_account_id', $account->id)->whereIn('kind', $kinds)
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['Failed', 'Expired']))
            ->orderBy('id')->limit((int) config('quests.limits.chain_lookback', 25))->get(['tx_id', 'kind']);
        $address = strtolower((string) $account->address);
        foreach ($rows as $row) {
            $r = $this->result($row->tx_id);
            if ($r['status'] !== 'Sealed' || $r['error'] !== '') {
                continue;
            }
            $hit = null;
            foreach ($r['events'] as $e) {
                if (in_array($e['type'], $types, true)) {
                    $hit = $e;
                    break;
                }
            }
            if (! $hit || ($extra && ! $extra($r, $row))) {
                continue;
            }
            $tx = $this->transaction($row->tx_id);
            if (! in_array($address, $tx['authorizers'], true)) {
                continue;
            }

            return ['tx_id' => $row->tx_id, 'kind' => $row->kind, 'event' => $hit['type']];
        }

        return null;
    }

    /** A specific transaction (e.g. the starter gift) is sealed without error. */
    public function sealedOk(?string $txId): bool
    {
        if (! $txId || ! preg_match('/^[0-9a-f]{64}$/', $txId)) {
            return false;
        }
        $r = $this->result($txId);

        return $r['status'] === 'Sealed' && $r['error'] === '';
    }

    /** ĐUST balance of an address (public balance capability), null when the vault is missing. */
    public function dustBalance(string $address): ?float
    {
        $flovatar = '0x'.FlowTransaction::addr((string) (config('services.flow.address') ?: config('flovatar.contractAddress')));
        $ft = (string) config('passkey.contracts.FungibleToken', '0x9a0766d93b6608b7');
        $code = "import FungibleToken from {$ft}\nimport FlovatarDustToken from {$flovatar}\n"
            ."access(all) fun main(a: Address): UFix64? { return getAccount(a).capabilities.borrow<&{FungibleToken.Balance}>(FlovatarDustToken.VaultBalancePath)?.balance }";
        $res = $this->http()->post('/v1/scripts?block_height=sealed', [
            'script' => base64_encode($code),
            'arguments' => [base64_encode(json_encode(['type' => 'Address', 'value' => '0x'.FlowTransaction::addr($address)]))],
        ]);
        if (! $res->successful()) {
            throw new RuntimeException('Flow script failed: '.substr((string) ($res->json('message') ?? $res->body()), 0, 300));
        }
        $v = json_decode(base64_decode(trim((string) $res->body(), '"')), true);

        return $v && ($v['value'] ?? null) !== null ? (float) ($v['value']['value'] ?? 0) : null;
    }
}
