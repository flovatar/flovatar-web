<?php

namespace App\Services\Passkey;

/**
 * Signer for the passkey sponsor account (payer of every passkey transaction, creator of new accounts).
 * Implementations: SponsorSigner (key file, testnet) and KmsSponsorSigner (cloud KMS, intended for mainnet).
 * Account key on chain: ECDSA_P256 + SHA2_256, weight 1000.
 */
interface SponsorKey
{
    /** Raw 64-byte r||s (low-S) over SHA2-256($message). */
    public function sign(string $message): string;

    /** 128 hex chars X||Y of the account key. */
    public function publicKeyHex(): string;
}
