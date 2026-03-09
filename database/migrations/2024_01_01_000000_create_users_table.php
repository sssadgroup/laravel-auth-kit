<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : création complète de la table users pour auth-kit
 *
 * Cette migration REMPLACE la migration users par défaut de Laravel.
 * Elle inclut tous les champs nécessaires au package :
 *
 *   ── Identité ────────────────────────────────────────────────────────
 *   id                    → clé primaire auto-incrémentée (bigInteger)
 *   first_name            → prénom (obligatoire)
 *   last_name             → nom de famille (obligatoire)
 *   email                 → adresse email unique (obligatoire)
 *   phone                 → numéro de téléphone (optionnel)
 *
 *   ── Authentification ────────────────────────────────────────────────
 *   password              → mot de passe hashé
 *   remember_token        → token "se souvenir de moi"
 *   must_change_password  → flag mode 'admin' : forcer le changement au 1er login
 *
 *   ── Statut du compte ────────────────────────────────────────────────
 *   status                → état du compte : 'active' | 'inactive' | 'banned'
 *                           Valeur par défaut : 'active'
 *                           Permet de suspendre un compte sans le supprimer.
 *
 *   ── Vérification email ──────────────────────────────────────────────
 *   email_verified_at     → date de vérification de l'email (null = non vérifié)
 *
 *   ── Horodatage ──────────────────────────────────────────────────────
 *   created_at            → date de création du compte
 *   updated_at            → date de dernière modification
 *
 * ⚠️  INSTRUCTIONS D'UTILISATION :
 *
 *   Option A — Nouveau projet (recommandé) :
 *     Supprimer la migration 'create_users_table' générée par Laravel
 *     et utiliser uniquement celle-ci.
 *
 *   Option B — Projet existant :
 *     Utiliser la migration d'altération (000003_update_users_table_for_auth_kit.php)
 *     qui ajoute uniquement les colonnes manquantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {

            // ── Clé primaire ──────────────────────────────────────────
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            // ── Identité ──────────────────────────────────────────────
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // ── Contact ───────────────────────────────────────────────
            $table->string('email', 191)->unique();

            // Numéro de téléphone — format international recommandé (+221XXXXXXXXX)
            // Nullable car optionnel à l'inscription
            $table->string('phone', 30)->nullable();

            // ── Vérification email ────────────────────────────────────
            // null = email non encore vérifié
            $table->timestamp('email_verified_at')->nullable();

            // ── Authentification ──────────────────────────────────────
            $table->string('password');

            // Flag mode 'admin' : l'utilisateur doit changer son mot de passe temporaire
            // Passe à false dès que l'utilisateur met à jour son mot de passe
            $table->boolean('must_change_password')->default(false);

            // Token "Se souvenir de moi" (utilisé par le guard web, conservé par convention)
            $table->rememberToken();

            // ── Statut du compte ──────────────────────────────────────
            // active  → compte normal, accès complet
            // inactive → compte désactivé temporairement (ex: inactivité)
            // banned  → compte banni, accès bloqué
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active')->index();

            // ── Horodatage ────────────────────────────────────────────
            $table->timestamps(); // created_at + updated_at (gérés automatiquement par Eloquent)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
