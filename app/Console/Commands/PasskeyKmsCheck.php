<?php

namespace App\Console\Commands;

use App\Services\Passkey\FlowGateway;
use App\Services\Passkey\KmsSponsorSigner;
use App\Services\Passkey\PasskeyPoc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Checks the KMS-backed passkey sponsor (flovatar-kb notes/mainnet-launch-stealth.md):
 *  1. GetPublicKey (spec ECC_NIST_P256 / SIGN_VERIFY) → X||Y; equals PASSKEY_SPONSOR_KMS_PUBLIC_KEY?
 *  2. the sponsor account's keys: which indices hold that key (ECDSA_P256 / SHA2_256, weight 1000, not revoked)
 *     → the value for PASSKEY_SPONSOR_PROPOSAL_KEYS;
 *  3. a test signature through KMS verifies with the configured key;
 *  4. --send=N (TESTNET ONLY): N no-op sponsor transactions in parallel (proposal-key pool + sequence numbers).
 * --mock-key-file (TESTNET ONLY): the same code path with the mock KMS provider (local key in KMS wire format).
 * Read-only on mainnet. Prints public data only (key prefix, indices), never credentials.
 */
class PasskeyKmsCheck extends Command
{
    protected $signature = 'passkey:kms-check
        {--mock-key-file= : testnet only: mock KMS provider backed by this local 64-hex key file}
        {--send=0 : testnet only: send N no-op sponsor transactions in parallel through the KMS signer}
        {--address= : sponsor account to check/use instead of PASSKEY_SPONSOR_ADDRESS (e.g. a mock sponsor on testnet)}
        {--keys= : proposal key indices instead of PASSKEY_SPONSOR_PROPOSAL_KEYS, e.g. 0,1,2,3}
        {--send-one : (internal) send one no-op sponsor transaction}';

    protected $description = 'Check the AWS KMS passkey sponsor key (public key, account keys, test signature; testnet: sealed txs)';

    public function handle(): int
    {
        $mock = (string) $this->option('mock-key-file');
        if ($this->option('address')) {
            config(['passkey.sponsor.address' => (string) $this->option('address')]);
        }
        if ($this->option('keys') !== null && $this->option('keys') !== '') {
            $keys = array_map('intval', array_filter(explode(',', (string) $this->option('keys')), 'strlen'));
            config(['passkey.sponsor.proposal_keys' => $keys, 'passkey.sponsor.key_index' => $keys[0] ?? 0]);
        }
        if ($mock !== '' && ! PasskeyPoc::isTestnet()) {
            $this->error('--mock-key-file is testnet only');

            return 1;
        }
        try {
            $signer = $this->signer($mock);
        } catch (\Throwable $e) {
            $this->error('KMS signer: '.$e->getMessage());

            return 1;
        }
        if ($this->option('send-one')) {
            return $this->sendOne($signer);
        }

        $ok = true;
        $kmsPub = $signer->publicKeyFromKms();
        $cfgPub = $signer->publicKeyHex();
        $this->line('kms public key   '.substr($kmsPub, 0, 16).'… (ECC_NIST_P256, SIGN_VERIFY)');
        $match = hash_equals($kmsPub, $cfgPub);
        $this->line('matches config   '.($match ? 'yes' : 'NO — set PASSKEY_SPONSOR_KMS_PUBLIC_KEY to the KMS key'));
        $ok = $ok && $match;

        $msg = 'flovatar kms-check '.bin2hex(random_bytes(8));
        $raw = $signer->sign($msg);
        $sigOk = KmsSponsorSigner::verifyRaw($msg, $raw, $kmsPub);
        $this->line('test signature   '.($sigOk ? 'verifies (64-byte r||s)' : 'DOES NOT VERIFY'));
        $ok = $ok && $sigOk;

        $address = (string) config('passkey.sponsor.address');
        if ($address === '') {
            $this->warn('PASSKEY_SPONSOR_ADDRESS not set: create the sponsor account with this public key (signing session).');

            return $ok ? 0 : 1;
        }
        $same = [];
        foreach ((new FlowGateway)->account($address)['keys'] as $k) {
            $mine = hash_equals($kmsPub, $k['public_key']);
            $good = $mine && ! $k['revoked'] && $k['weight'] >= 1000 && $k['signing_algorithm'] === 'ECDSA_P256' && $k['hashing_algorithm'] === 'SHA2_256';
            if ($good) {
                $same[] = $k['index'];
            }
            $this->line(sprintf('key %d %s/%s weight %d seq %d %s%s', $k['index'], $k['signing_algorithm'], $k['hashing_algorithm'], $k['weight'], $k['sequence_number'], $mine ? 'kms-key' : 'other', $k['revoked'] ? ' REVOKED' : ''));
        }
        $this->line('PASSKEY_SPONSOR_PROPOSAL_KEYS='.implode(',', $same));
        $cfgKeys = PasskeyPoc::proposalKeys();
        $missing = array_diff($cfgKeys, $same);
        if ($missing) {
            $this->error('configured proposal keys not usable with this KMS key: '.implode(',', $missing));
            $ok = false;
        }

        $n = max(0, min(8, (int) $this->option('send')));
        if ($n > 0) {
            if (! PasskeyPoc::isTestnet()) {
                $this->error('--send is testnet only');

                return 1;
            }
            $cmd = [PHP_BINARY, base_path('artisan'), 'passkey:kms-check', '--send-one'];
            if ($mock !== '') {
                $cmd[] = '--mock-key-file='.$mock;
            }
            foreach (['address', 'keys'] as $opt) {
                if ($this->option($opt)) {
                    $cmd[] = '--'.$opt.'='.$this->option($opt);
                }
            }
            $results = Process::pool(function ($pool) use ($n, $cmd) {
                for ($i = 0; $i < $n; $i++) {
                    $pool->as('tx'.$i)->path(base_path())->timeout(120)->command($cmd);
                }
            })->start()->wait();
            if ($results->collect()->count() !== $n) {
                $this->error('expected '.$n.' results, got '.$results->collect()->count());
                $ok = false;
            }
            foreach ($results->collect() as $name => $r) {
                $out = trim($r->output().$r->errorOutput());
                $this->line($name.': '.$out);
                $ok = $ok && $r->successful();
            }
        }

        return $ok ? 0 : 1;
    }

    private function signer(string $mock): KmsSponsorSigner
    {
        if ($mock === '') {
            return KmsSponsorSigner::fromConfig();
        }
        $arn = 'arn:aws:kms:mock:000000000000:key/flovatar-mock';
        $probe = new KmsSponsorSigner('mock', $arn, str_repeat('0', 128), null, null, null, $mock);

        return new KmsSponsorSigner('mock', $arn, $probe->publicKeyFromKms(), null, null, null, $mock);
    }

    private function sendOne(KmsSponsorSigner $signer): int
    {
        if (! PasskeyPoc::isTestnet()) {
            $this->error('testnet only');

            return 1;
        }
        $poc = new PasskeyPoc(new FlowGateway, $signer);
        $t = microtime(true);
        [$id, $r] = $poc->sendAsSponsor('sponsor_noop', [], 'kms_check');
        $key = \App\Models\PasskeyTransaction::where('tx_id', $id)->value('signed_by');
        $this->line(sprintf('%s %s %s %.1fs', $id, $key, $r['error'] !== '' ? 'FAILED '.substr($r['error'], 0, 200) : $r['status'], microtime(true) - $t));

        return $r['error'] === '' && $r['status'] === 'Sealed' ? 0 : 1;
    }
}
