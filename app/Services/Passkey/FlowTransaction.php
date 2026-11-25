<?php

namespace App\Services\Passkey;

use InvalidArgumentException;

/**
 * A Flow transaction with the canonical signing encodings (payload / envelope) and the REST body.
 *
 * Encoding (https://developers.flow.com/build/basics/transactions, FLIP-264 for extension data):
 *   payload  = [script, [arg...], refBlock(32), computeLimit, proposer(8), keyIndex, seq, payer(8), [authorizer(8)...]]
 *   envelope = [payload, [[signerIndex, keyIndex, sig(, extensionData)]...]]   (payload signatures, sorted)
 *   message  = pad32("FLOW-V0.0-transaction") || RLP(...)
 * A signature with WebAuthn extension data is serialised with the extra 4th field; plain signatures keep 3 fields.
 */
final class FlowTransaction
{
    public const DOMAIN_TAG = 'FLOW-V0.0-transaction';

    /** @var string[] JSON-Cadence argument documents, exactly the bytes that are signed and submitted. */
    public array $arguments = [];

    /** @var array<int, array{address:string,keyIndex:int,signature:string,extensionData:?string}> raw bytes */
    public array $payloadSignatures = [];

    /** @var array<int, array{address:string,keyIndex:int,signature:string,extensionData:?string}> */
    public array $envelopeSignatures = [];

    /** @param string[] $authorizers */
    public function __construct(
        public string $script,
        array $arguments,
        public string $referenceBlockId,
        public int $computeLimit,
        public string $proposerAddress,
        public int $proposerKeyIndex,
        public int $proposerSequence,
        public string $payer,
        public array $authorizers,
    ) {
        foreach ($arguments as $a) {
            $this->arguments[] = is_string($a) ? $a : json_encode($a, JSON_UNESCAPED_SLASHES);
        }
        $this->proposerAddress = self::addr($proposerAddress);
        $this->payer = self::addr($payer);
        $this->authorizers = array_map([self::class, 'addr'], $authorizers);
        if (! preg_match('/^[0-9a-f]{64}$/', strtolower($referenceBlockId))) {
            throw new InvalidArgumentException('Reference block id must be 32 bytes hex');
        }
        $this->referenceBlockId = strtolower($referenceBlockId);
    }

    /** Normalise to 16 lowercase hex chars, no 0x. */
    public static function addr(string $address): string
    {
        $a = strtolower(preg_replace('/^0x/i', '', trim($address)));
        if (! preg_match('/^[0-9a-f]{1,16}$/', $a)) {
            throw new InvalidArgumentException('Bad Flow address');
        }

        return str_pad($a, 16, '0', STR_PAD_LEFT);
    }

    public static function paddedTag(): string
    {
        return str_pad(self::DOMAIN_TAG, 32, "\0", STR_PAD_RIGHT);
    }

    public function payloadFields(): array
    {
        return [
            $this->script,
            $this->arguments,
            hex2bin($this->referenceBlockId),
            $this->computeLimit,
            hex2bin($this->proposerAddress),
            $this->proposerKeyIndex,
            $this->proposerSequence,
            hex2bin($this->payer),
            array_map(fn ($a) => hex2bin($a), $this->authorizers),
        ];
    }

    /** Signers in Flow order: proposer, payer, authorizers (first occurrence wins). */
    public function signerIndex(): array
    {
        $map = [];
        foreach (array_merge([$this->proposerAddress, $this->payer], $this->authorizers) as $a) {
            if (! array_key_exists($a, $map)) {
                $map[$a] = count($map);
            }
        }

        return $map;
    }

    private function encodeSignatures(array $sigs): array
    {
        $index = $this->signerIndex();
        $rows = [];
        foreach ($sigs as $s) {
            $addr = self::addr($s['address']);
            if (! array_key_exists($addr, $index)) {
                throw new InvalidArgumentException('Signature from an address that is not a signer');
            }
            $rows[] = ['i' => $index[$addr], 'k' => (int) $s['keyIndex'], 'sig' => $s['signature'], 'ext' => $s['extensionData'] ?? null];
        }
        usort($rows, fn ($a, $b) => $a['i'] === $b['i'] ? $a['k'] <=> $b['k'] : $a['i'] <=> $b['i']);

        return array_map(function ($r) {
            $item = [$r['i'], $r['k'], $r['sig']];
            if ($r['ext'] !== null && $r['ext'] !== '') {
                $item[] = $r['ext'];
            }

            return $item;
        }, $rows);
    }

    public function payloadMessage(): string
    {
        return self::paddedTag().Rlp::encode($this->payloadFields());
    }

    public function envelopeMessage(): string
    {
        return self::paddedTag().Rlp::encode([$this->payloadFields(), $this->encodeSignatures($this->payloadSignatures)]);
    }

    public function addPayloadSignature(string $address, int $keyIndex, string $rawSignature, ?string $extensionData = null): void
    {
        $this->payloadSignatures[] = ['address' => self::addr($address), 'keyIndex' => $keyIndex, 'signature' => $rawSignature, 'extensionData' => $extensionData];
    }

    public function addEnvelopeSignature(string $address, int $keyIndex, string $rawSignature, ?string $extensionData = null): void
    {
        $this->envelopeSignatures[] = ['address' => self::addr($address), 'keyIndex' => $keyIndex, 'signature' => $rawSignature, 'extensionData' => $extensionData];
    }

    /** Body for POST /v1/transactions (Flow Access REST API). */
    public function toRestBody(): array
    {
        $sig = function (array $s) {
            $out = ['address' => $s['address'], 'key_index' => (string) $s['keyIndex'], 'signature' => base64_encode($s['signature'])];
            if (! empty($s['extensionData'])) {
                $out['extension_data'] = base64_encode($s['extensionData']);
            }

            return $out;
        };

        return [
            'script' => base64_encode($this->script),
            'arguments' => array_map('base64_encode', $this->arguments),
            'reference_block_id' => $this->referenceBlockId,
            'gas_limit' => (string) $this->computeLimit,
            'payer' => $this->payer,
            'proposal_key' => [
                'address' => $this->proposerAddress,
                'key_index' => (string) $this->proposerKeyIndex,
                'sequence_number' => (string) $this->proposerSequence,
            ],
            'authorizers' => array_values(array_unique($this->authorizers)),
            'payload_signatures' => array_map($sig, $this->payloadSignatures),
            'envelope_signatures' => array_map($sig, $this->envelopeSignatures),
        ];
    }

    /** Serialisable snapshot (cache between prepare and submit). */
    public function toArray(): array
    {
        return [
            'script' => $this->script, 'arguments' => $this->arguments, 'ref' => $this->referenceBlockId,
            'limit' => $this->computeLimit, 'proposer' => $this->proposerAddress, 'keyIndex' => $this->proposerKeyIndex,
            'seq' => $this->proposerSequence, 'payer' => $this->payer, 'authorizers' => $this->authorizers,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['script'], $a['arguments'], $a['ref'], $a['limit'], $a['proposer'], $a['keyIndex'], $a['seq'], $a['payer'], $a['authorizers']);
    }
}
