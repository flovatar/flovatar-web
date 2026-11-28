<?php

namespace App\Services\Flow;

use RuntimeException;

/**
 * Pack Rewind (testnet): "purchase-flow" authorisations for SportvatarPack.purchasePackFlow, checked on-chain by the
 * Sportvatar pack verifier (same message format as the Sportvatar site's packs v2):
 *   SPORTVATAR-PACK-V2|purchase-flow|<packId>|<recipient>|<expiry>|<epoch>   (UTF-8, no domain tag, SHA3-256, P-256)
 * Key: the Sportvatar testnet pack-signer key (PACK_REWIND_SPORTVATAR_SIGNER_KEY_FILE, 0600, outside the app dir),
 * registered in SportvatarPack's verifier. It is not an account key.
 */
class SportvatarRewindAuthorizer
{
    public const PREFIX = 'SPORTVATAR-PACK-V2';

    public const KIND = 'purchase-flow';

    public function __construct(private readonly UserMessageSigner $signer)
    {
    }

    public static function fromConfig(): self
    {
        $file = (string) config('pack_rewind.sportvatar.signer_key_file');
        if ($file === '') {
            throw new RuntimeException('Sportvatar pack signer is not configured');
        }

        return new self(new UserMessageSigner($file, base_path()));
    }

    public static function message(int $packId, string $recipient, int $expiry, int $epoch): string
    {
        if ($packId < 0 || $expiry < 0 || $epoch < 1) {
            throw new RuntimeException('Invalid pack authorisation parameters');
        }

        return implode('|', [self::PREFIX, self::KIND, (string) $packId, FlovatarPackAuthorizer::normalizeAddress($recipient), (string) $expiry, (string) $epoch]);
    }

    /** @return array{packId:int, kind:string, recipient:string, expiry:int, epoch:int, signature:string} */
    public function authorize(int $packId, string $recipient, int $ttl, ?int $now = null): array
    {
        $expiry = ($now ?? time()) + max(60, $ttl);
        $epoch = (int) config('pack_rewind.sportvatar.epoch', 2);
        $recipient = FlovatarPackAuthorizer::normalizeAddress($recipient);

        return [
            'packId' => $packId, 'kind' => self::KIND, 'recipient' => $recipient, 'expiry' => $expiry, 'epoch' => $epoch,
            'signature' => $this->signer->sign(self::message($packId, $recipient, $expiry, $epoch), UserMessageSigner::NO_DOMAIN_TAG),
        ];
    }
}
