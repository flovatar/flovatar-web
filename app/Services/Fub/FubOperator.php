<?php

namespace App\Services\Fub;

use App\Services\Flow\UserMessageSigner;
use App\Services\Passkey\FlowGateway;
use App\Services\Passkey\FlowTransaction;
use App\Support\Fub;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use SensitiveParameter;

/**
 * The scoped FUB operator: sends Uploader transactions (draft writes only) as the operator account
 * (config fub.operator.address), signed with a local P-256 / SHA2-256 key file that never leaves the server
 * (~/secure, generated there). The account holds ONLY the Uploader capability (revocable by the admin by uuid) and a
 * little FLOW for fees; it can't publish, mint, move funds or change live items (contract-enforced).
 *
 * One proposal key = one sequence number: every send holds a cache lock for the key.
 */
class FubOperator
{
    public function __construct(protected ?FlowGateway $flow = null)
    {
    }

    protected function flow(): FlowGateway
    {
        return $this->flow ??= new FlowGateway();
    }

    public static function configured(): bool
    {
        $c = (array) config('fub.operator');

        return Fub::normalizeAddress($c['address'] ?? '') !== null && ($c['key_file'] ?? '') !== '' && is_readable((string) $c['key_file']);
    }

    public function address(): string
    {
        return Fub::normalizeAddress((string) config('fub.operator.address')) ?? throw new RuntimeException('FUB_OPERATOR_ADDRESS not set');
    }

    /** Raw r||s (64 bytes) over SHA2-256($message), low-S, verified against the key's own public half. */
    public static function sign(string $message, #[SensitiveParameter] string $keyHex): string
    {
        $pem = self::pem($keyHex);
        $key = openssl_pkey_get_private($pem);
        if (! $key || ! openssl_sign($message, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Operator signing failed');
        }
        $raw = UserMessageSigner::normalizeLowS(UserMessageSigner::derToRaw($der));
        $pub = openssl_pkey_get_public(openssl_pkey_get_details($key)['key']);
        if (openssl_verify($message, UserMessageSigner::rawToDer($raw), $pub, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Operator signature does not verify');
        }

        return $raw;
    }

    public static function publicKeyHex(#[SensitiveParameter] string $keyHex): string
    {
        $d = openssl_pkey_get_details(openssl_pkey_get_private(self::pem($keyHex)));

        return bin2hex($d['ec']['x']).bin2hex($d['ec']['y']);
    }

    private static function pem(#[SensitiveParameter] string $hex): string
    {
        $hex = strtolower(preg_replace('/^0x/', '', trim($hex)));
        if (! preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new RuntimeException('Operator key must be 64 hex chars');
        }
        $der = hex2bin('30310201010420'.$hex.'a00a06082a8648ce3d030107');

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    private function keyHex(): string
    {
        $f = (string) config('fub.operator.key_file');
        $hex = is_readable($f) ? trim((string) file_get_contents($f)) : '';
        if (! preg_match('/^(0x)?[0-9a-fA-F]{64}$/', $hex)) {
            throw new RuntimeException('FUB operator key file missing or invalid');
        }

        return $hex;
    }

    /**
     * Send one operator transaction and wait for the seal. Returns ['id', 'status', 'error', 'events'].
     * $cadence uses `import FlovatarUniverseBuilder from 0xFUB` (replaced here).
     */
    public function send(string $cadence, array $args): array
    {
        $code = str_replace('0xFUB', Fub::contract(), $cadence);
        $addr = $this->address();
        $keyIndex = (int) config('fub.operator.key_index', 1);
        $lock = Cache::lock('fub:operator:'.$addr.':'.$keyIndex, 120);
        if (! $lock->block(90)) {
            throw new RuntimeException('The operator is busy, try again in a minute');
        }
        try {
            $tx = new FlowTransaction($code, $args, $this->flow()->latestSealedBlockId(), (int) config('fub.operator.compute_limit', 9999),
                $addr, $keyIndex, $this->flow()->sequenceNumber($addr, $keyIndex), $addr, [$addr]);
            $tx->addEnvelopeSignature($addr, $keyIndex, self::sign($tx->envelopeMessage(), $this->keyHex()));
            $id = $this->flow()->send($tx);
            $r = $this->flow()->wait($id, 60);
        } finally {
            $lock->release();
        }

        return ['id' => $id] + $r;
    }

    // ------------------------------------------------------------------------------------- transactions (Uploader)

    private const PRE = <<<'CDC'
import FlovatarUniverseBuilder from 0xFUB

transaction(%s) {
    let up: auth(FlovatarUniverseBuilder.Upload) &FlovatarUniverseBuilder.Uploader
    prepare(signer: auth(CopyValue) &Account) {
        let cap = signer.storage.copy<Capability<auth(FlovatarUniverseBuilder.Upload) &FlovatarUniverseBuilder.Uploader>>(from: /storage/FlovatarUniverseBuilderUploaderCap)
            ?? panic("signer holds no Uploader capability")
        self.up = cap.borrow() ?? panic("Uploader capability revoked")
    }
    execute {
        %s
    }
}
CDC;

    private const INPUT = 'FlovatarUniverseBuilder.ItemInput(layer: layer, name: name, description: description, rarity: rarity, price: price, maxMint: maxMint, increase: increase, priceCap: priceCap, large: large, metadata: metadata)';

    private const ITEM_PARAMS = 'layer: String, name: String, description: String, rarity: String, price: UFix64, maxMint: UInt64, increase: UFix64, priceCap: UFix64, large: Bool, metadata: {String: String}';

    public static function tx(string $op): string
    {
        $i = self::INPUT;
        $p = self::ITEM_PARAMS;
        [$params, $body] = match ($op) {
            'setInfo' => ['fubId: UInt64, name: String, description: String', 'self.up.setInfo(fubId: fubId, name: name, description: description)'],
            'setLayers' => ['fubId: UInt64, keys: [String], names: [String], required: [Bool]', 'self.up.setLayers(fubId: fubId, keys: keys, names: names, required: required)'],
            'setOptions' => ['fubId: UInt64, royaltyCut: UFix64, unique: Bool, maxSupply: UInt64, license: String', 'self.up.setOptions(fubId: fubId, royaltyCut: royaltyCut, unique: unique, maxSupply: maxSupply, license: license)'],
            'putItem' => ["fubId: UInt64, $p, svg: String", "self.up.putItem(fubId: fubId, input: $i, svg: svg)"],
            'updateItem' => ["itemId: UInt64, $p", "self.up.updateItem(itemId: itemId, input: $i)"],
            'replaceItem' => ["itemId: UInt64, $p, svg: String", "self.up.replaceItem(itemId: itemId, input: $i, svg: svg)"],
            'removeItem' => ['itemId: UInt64', 'self.up.removeItem(itemId: itemId)'],
            'recomputeLayerMax' => ['fubId: UInt64, layer: String', 'self.up.recomputeLayerMax(fubId: fubId, layer: layer)'],
        };

        return sprintf(self::PRE, $params, $body);
    }
}
