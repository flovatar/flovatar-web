<?php

namespace App\Services\Passkey;

use Illuminate\Support\Facades\Http;

/**
 * Flow key indexer (public key -> accounts holding it): https://{staging|production}.key-indexer.flow.com/key/<hex>.
 * Answer: {"publicKey": "...", "accounts": [{"address","keyId","weight","sigAlgo","hashAlgo","isRevoked","signing","hashing"}]}.
 * Index lag: new keys can take a little while to appear; AccountRecovery always confirms against the chain.
 */
class KeyIndexer
{
    protected string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim($base ?? self::defaultUrl(), '/');
    }

    public static function defaultUrl(): string
    {
        $configured = (string) config('passkey.recovery.key_indexer');
        if ($configured !== '') {
            return $configured;
        }

        return config('services.flow.network') === 'mainnet' ? 'https://production.key-indexer.flow.com' : 'https://staging.key-indexer.flow.com';
    }

    /** @return array<int, array{address:string,keyId:int,weight:int,revoked:bool,signing:string,hashing:string}> */
    public function accounts(string $publicKeyHex): array
    {
        $hex = strtolower(preg_replace('/^0x/i', '', $publicKeyHex));
        if (! preg_match('/^[0-9a-f]{128}$/', $hex)) {
            return [];
        }
        $res = Http::acceptJson()->timeout(15)->connectTimeout(6)->retry(2, 500, throw: false)->get($this->base.'/key/'.$hex);
        if ($res->status() === 404 || ! $res->successful()) {
            return [];
        }

        return array_values(array_map(fn ($a) => [
            'address' => '0x'.FlowTransaction::addr((string) ($a['address'] ?? '')),
            'keyId' => (int) ($a['keyId'] ?? -1),
            'weight' => (int) ($a['weight'] ?? 0),
            'revoked' => (bool) ($a['isRevoked'] ?? false),
            'signing' => (string) ($a['signing'] ?? ''),
            'hashing' => (string) ($a['hashing'] ?? ''),
        ], array_filter((array) ($res->json('accounts') ?? []), 'is_array')));
    }
}
