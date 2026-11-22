<?php

namespace App\Services\Flow;

use RuntimeException;

/**
 * Flovatar packs v2: signs purchase authorisations checked on-chain by the verifier inside FlovatarPack
 * (called from FlovatarPack.purchasePack). See docs/PACK-SIGNING.md.
 *
 * Message (UTF-8, signed with NO domain tag; must match FlovatarPack.buildVerifierMessage exactly):
 *   FLOVATAR-PACK-V2|<kind>|<packId>|<recipient>|<expiry>|<epoch>
 *     kind      "flow" | "dust" | "dapper"  (how the buyer pays; the contract derives it from the vault type)
 *     recipient "0x" + 16 lowercase hex digits (Cadence Address.toString())
 *     expiry    unix seconds
 *     epoch     FlovatarPack.getVerifierEpoch() (FLOVATAR_PACK_EPOCH; bump both together)
 *
 * The key is the dedicated pack-signer key (PACK_SIGNER_KEY_FILE): its public key is registered in
 * the pack verifier inside FlovatarPack. It is not an account key, so it can't sign transactions or move funds.
 */
class FlovatarPackAuthorizer
{
    public const PREFIX = 'FLOVATAR-PACK-V2';
    public const KINDS = ['flow', 'dust', 'dapper'];

    public function __construct(private readonly UserMessageSigner $signer)
    {
    }

    public static function normalizeAddress(string $address): string
    {
        $hex = strtolower(preg_replace('/^0x/i', '', trim($address)));
        if (!preg_match('/^[0-9a-f]{1,16}$/', $hex)) {
            throw new RuntimeException('Invalid Flow address');
        }
        return '0x' . str_pad($hex, 16, '0', STR_PAD_LEFT);
    }

    public static function message(string $kind, int $packId, string $recipient, int $expiry, int $epoch): string
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new RuntimeException('Invalid pack payment kind');
        }
        if ($packId < 0 || $expiry < 0 || $epoch < 1) {
            throw new RuntimeException('Invalid pack authorisation parameters');
        }
        return implode('|', [self::PREFIX, $kind, (string) $packId, self::normalizeAddress($recipient), (string) $expiry, (string) $epoch]);
    }

    /** Payment kind for a pack name and wallet (mirrors the website: DUST packs burn DUST; Dapper pays FUT). */
    public static function kindFor(string $packName, ?string $wallet): string
    {
        if ($packName === 'Dust Flobit Pack') {
            return 'dust';
        }
        return $wallet === 'dapper' ? 'dapper' : 'flow';
    }

    /**
     * @return array{packId:int, kind:string, recipient:string, expiry:int, epoch:int, signature:string}
     */
    public function authorize(string $kind, int $packId, string $recipient, ?int $ttl = null, ?int $now = null): array
    {
        $ttl = $ttl ?? (int) config('flovatar.packsV2Ttl', 900);
        $expiry = ($now ?? time()) + max(60, $ttl);
        $epoch = (int) config('flovatar.packsV2Epoch', 1);
        $recipient = self::normalizeAddress($recipient);
        $message = self::message($kind, $packId, $recipient, $expiry, $epoch);
        return [
            'packId' => $packId,
            'kind' => $kind,
            'recipient' => $recipient,
            'expiry' => $expiry,
            'epoch' => $epoch,
            'signature' => $this->signer->sign($message, UserMessageSigner::NO_DOMAIN_TAG),
        ];
    }

    public function publicKeyHex(): string
    {
        return $this->signer->publicKeyHex();
    }
}
