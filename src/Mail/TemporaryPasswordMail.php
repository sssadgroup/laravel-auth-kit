<?php

namespace S3Tech\AuthKit\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * TemporaryPasswordMail
 *
 * Email envoyé au nouvel utilisateur créé par un administrateur (mode 'admin').
 * Contient le mot de passe temporaire généré automatiquement.
 * L'utilisateur est invité à changer son mot de passe dès sa première connexion.
 */
class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly mixed  $user,
        public readonly string $temporaryPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bienvenue — Vos accès ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'auth-kit::emails.temporary-password',
            with: [
                'user'              => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl'          => config('app.url'),
            ]
        );
    }
}
