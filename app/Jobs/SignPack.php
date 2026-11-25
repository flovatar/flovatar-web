<?php

namespace App\Jobs;

use App\Models\Pack;
use App\Services\Flow\UserMessageSigner;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Legacy (v1) pack signing: signs the pack's randomString (`secret`) as a Flow user message
 * (FLOW-V0.0-user, ECDSA_P256/SHA3_256), as the old Go tool `go/examples/user_signature` did.
 * The Go tool (and its `go run` subprocess) is gone; signing is done in PHP by UserMessageSigner.
 *
 * FlovatarPack v1 checks this signature against ACCOUNT KEY 0 of the pack owner account, so v1 signing
 * needs the contract account's own key. That key must never be on a web server for mainnet: this job only
 * runs when both FLOVATAR_PACK_SALES_ENABLED and FLOVATAR_PACK_V1_SIGNING are true (testnet). Mainnet
 * uses v2 authorisations (App\Services\Flow\FlovatarPackAuthorizer). See docs/PACK-SIGNING.md.
 */
class SignPack extends BaseJob
{
    use Dispatchable;

    protected $pack;

    public function __construct(Pack $pack)
    {
        $this->pack = $pack;
        parent::__construct();
    }

    public function getName(){
        return 'SignPack';
    }

    public function handle(UserMessageSigner $signer)
    {
        if (!config('flovatar.packSalesEnabled') || !config('flovatar.packV1SigningEnabled')) {
            info('SignPack skipped for pack ' . $this->pack->id . ': v1 pack signing is disabled');
            return;
        }
        if (!$this->pack->secret) {
            return;
        }

        // The secret and the key are never logged (the Go version logged the secret).
        $signature = $signer->sign((string) $this->pack->secret, UserMessageSigner::USER_DOMAIN_TAG);
        $this->pack->update(['signature' => $signature]);

        info('Executed Signature for Pack ' . $this->pack->id);
    }
}
