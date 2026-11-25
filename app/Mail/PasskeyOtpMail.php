<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** 6-digit code for passkey sign-up / email verification (HTML + plain text, brand.flovatar.com colours). */
class PasskeyOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
        public int $minutes,
    ) {
    }

    public function envelope(): Envelope
    {
        // code first: it shows in the notification preview and iOS / Android offer it as autofill
        return new Envelope(subject: $this->code.' is your Flovatar code');
    }

    public function content(): Content
    {
        $testnet = config('services.flow.network') !== 'mainnet';

        return new Content(
            view: 'emails.passkey-otp',
            text: 'emails.passkey-otp-text',
            with: [
                'code' => $this->code,
                'minutes' => $this->minutes,
                'intro' => $this->purpose === 'signup' ? 'Here is your code to create your Flovatar account.' : 'Here is your code to confirm your email for your Flovatar account.',
                'testnet' => $testnet,
                'site' => rtrim((string) config('app.url'), '/') ?: 'https://flovatar.com',
            ],
        );
    }
}
