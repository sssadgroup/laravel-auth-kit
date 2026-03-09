<?php

namespace S3Tech\AuthKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * PasswordResetOtp
 *
 * Stocke les codes OTP et reset_token pour le flux de réinitialisation
 * de mot de passe en 3 étapes.
 *
 * Workflow :
 *   1. Création avec otp hashé, expires_at, used=false
 *   2. Après vérification OTP → reset_token hashé ajouté
 *   3. Après reset → used=true
 */
class PasswordResetOtp extends Model
{
    protected $table = 'auth_kit_password_reset_otps';

    protected $fillable = [
        'email',
        'otp',           // Hash bcrypt du code OTP
        'reset_token',   // Hash bcrypt du token de reset (null avant vérification OTP)
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used'       => 'boolean',
    ];

    /**
     * Vérifier si l'OTP/token est expiré.
     */
    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }
}
