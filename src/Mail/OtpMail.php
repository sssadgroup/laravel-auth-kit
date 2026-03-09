<?php

namespace S3Tech\AuthKit\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * OtpMail
 *
 * Email envoyé lors de la demande de réinitialisation de mot de passe.
 * Contient le code OTP à usage unique et limité dans le temps.
 */
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de vérification');
    }

    public function content(): Content
    {
        return new Content(
            view: 'auth-kit::emails.otp',
            with: [
                'otp'       => $this->otp,
                'expiresIn' => config('auth-kit.otp.expires_in', 10),
            ]
        );
    }
}
