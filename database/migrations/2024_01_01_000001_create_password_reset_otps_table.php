<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : auth_kit_password_reset_otps
 *
 * Stocke les OTP de réinitialisation de mot de passe.
 * Les codes sont hashés avant stockage (jamais en clair).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_kit_password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();

            // OTP hashé (bcrypt) — jamais stocké en clair
            $table->string('otp');

            // Reset token hashé — généré après vérification de l'OTP (étape 2)
            $table->string('reset_token')->nullable();

            // Date/heure d'expiration de l'OTP
            $table->timestamp('expires_at');

            // Marque l'enregistrement comme utilisé après le reset (usage unique)
            $table->boolean('used')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_kit_password_reset_otps');
    }
};
