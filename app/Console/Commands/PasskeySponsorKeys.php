<?php

namespace App\Console\Commands;

use App\Services\Passkey\FlowTransaction;
use App\Services\Passkey\PasskeyPoc;
use App\Services\Passkey\SponsorSigner;
use Illuminate\Console\Command;

/**
 * Testnet passkey sponsor: list its proposal keys, or add N more keys with the same public key (parallel sponsor
 * transactions, see PasskeyPoc::withProposalKey). Prints indices only, never key material.
 */
class PasskeySponsorKeys extends Command
{
    protected $signature = 'passkey:sponsor-keys {add=0 : number of keys to add}';

    protected $description = 'List / add proposal keys on the passkey sponsor account (testnet only)';

    public function handle(): int
    {
        if (! \App\Support\Stealth::active('passkey') || ! PasskeyPoc::isTestnet()) {
            $this->error('Passkey PoC disabled or not on testnet.');

            return 1;
        }
        $poc = PasskeyPoc::make();
        $sponsor = PasskeyPoc::sponsorAddress();
        $pub = SponsorSigner::fromConfig()->publicKeyHex();
        $add = max(0, min(10, (int) $this->argument('add')));
        if ($add > 0) {
            [$id, $r] = $poc->sendAsSponsor('sponsor_add_keys', [
                ['type' => 'String', 'value' => $pub],
                ['type' => 'Int', 'value' => (string) $add],
            ], 'sponsor_add_keys');
            $this->line('tx '.$id.' '.($r['error'] !== '' ? 'FAILED: '.substr($r['error'], 0, 300) : $r['status']));
            if ($r['error'] !== '') {
                return 1;
            }
        }
        $same = [];
        foreach ($poc->flow()->account($sponsor)['keys'] as $k) {
            $mine = hash_equals($pub, $k['public_key']);
            if ($mine && ! $k['revoked']) {
                $same[] = $k['index'];
            }
            $this->line(sprintf('key %d weight %d seq %d %s%s', $k['index'], $k['weight'], $k['sequence_number'], $mine ? 'sponsor-key' : 'other', $k['revoked'] ? ' REVOKED' : ''));
        }
        $this->line('PASSKEY_SPONSOR_PROPOSAL_KEYS='.implode(',', $same));

        return 0;
    }
}
