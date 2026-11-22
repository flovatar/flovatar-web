<?php

namespace App\Console\Commands;

use App\Services\Flow\FlovatarPackAuthorizer;
use App\Services\Flow\FlowRestClient;
use Illuminate\Console\Command;

/**
 * Packs v2: checks that the local pack-signer key (PACK_SIGNER_KEY_FILE) and epoch (FLOVATAR_PACK_EPOCH)
 * match the pack verifier inside FlovatarPack on-chain, and that the key is NOT a key of the contract account.
 * Read-only; never prints the private key.
 */
class PackSignerCheck extends Command
{
    protected $signature = 'flovatar:pack-signer-check';

    protected $description = 'Compare the local pack-signer key / epoch with the FlovatarPack verifier on-chain';

    public function handle(FlovatarPackAuthorizer $authorizer, FlowRestClient $flow): int
    {
        $contract = (string) config('services.flow.address');
        $cfg = $flow->script("import FlovatarPack from $contract\naccess(all) fun main(): FlovatarPack.VerifierConfig { return FlovatarPack.getVerifierConfig() }");
        $keys = $flow->script("access(all) fun main(a: Address): [String] {\n let out: [String] = []\n var i = 0\n while true {\n  if let k = getAccount(a).keys.get(keyIndex: i) { out.append(String.encodeHex(k.publicKey.publicKey)) } else { break }\n  i = i + 1\n }\n return out\n}", [['type' => 'Address', 'value' => $contract]]);

        $local = $authorizer->publicKeyHex();
        $chainKey = strtolower((string) ($cfg['signerPublicKey'] ?? ''));
        $isAccountKey = in_array($local, array_map('strtolower', (array) $keys), true);

        $this->line('contract           : '.$contract);
        $this->line('local public key   : '.$local);
        $this->line('on-chain signer    : '.($chainKey ?: 'none'));
        $this->line('epoch local/chain  : '.config('flovatar.packsV2Epoch').' / '.($cfg['epoch'] ?? '?'));
        $this->line('purchases enabled  : '.json_encode($cfg['purchasesEnabled'] ?? null));
        $this->line('account key?       : '.($isAccountKey ? 'YES - this is a key of the contract account: remove it from the server' : 'no'));

        $ok = ! $isAccountKey && $chainKey === $local && (int) ($cfg['epoch'] ?? 0) === (int) config('flovatar.packsV2Epoch');
        $ok ? $this->info('OK: signer and epoch match') : $this->error('MISMATCH: fix PACK_SIGNER_KEY_FILE / FLOVATAR_PACK_EPOCH or the on-chain config');

        return $ok ? 0 : 1;
    }
}
