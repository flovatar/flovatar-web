<?php

namespace App\Console\Commands;

use App\Services\Passkey\EmailVerification;
use Illuminate\Console\Command;

/**
 * TESTNET AUTOMATION ONLY: print the last email code sent to a Resend sink address (delivered[+label]@resend.dev).
 * Works only with PASSKEY_TEST_OTP_READ=true on testnet; refuses any other domain and mainnet.
 */
class PasskeyTestOtp extends Command
{
    protected $signature = 'passkey:test-otp {email}';

    protected $description = 'Testnet only: last one-time code sent to a @resend.dev test address';

    public function handle(): int
    {
        $email = EmailVerification::normalise($this->argument('email'));
        if (! EmailVerification::testReadEnabled($email)) {
            $this->error('Not available (needs PASSKEY_TEST_OTP_READ=true, testnet and a @resend.dev address).');

            return 1;
        }
        $code = EmailVerification::peekTestCode($email);
        if (! $code) {
            $this->error('No pending code for this address.');

            return 1;
        }
        $this->line($code);

        return 0;
    }
}
