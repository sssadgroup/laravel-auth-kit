<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : altération de la table users existante pour auth-kit
 *
 * À utiliser UNIQUEMENT si ta table users existe déjà (projet en cours).
 * Pour un nouveau projet, utilise plutôt :
 *   2024_01_01_000000_create_users_table.php
 *
 * Colonnes ajoutées :
 *   first_name            → prénom (obligatoire)
 *   last_name             → nom de famille (obligatoire)
 *   phone                 → numéro de téléphone (optionnel)
 *   must_change_password  → flag mot de passe temporaire (mode admin)
 *   status                → statut du compte : 'active' | 'inactive' | 'banned'
 *
 * La colonne `name` par défaut de Laravel est conservée nullable
 * pour ne pas casser les projets existants.
 *
 * ⚠️  Si tu as déjà des données dans `name`, tu peux les migrer avec :
 *   UPDATE users
 *     SET first_name = TRIM(SUBSTRING_INDEX(name, ' ', 1)),
 *         last_name  = TRIM(SUBSTRING(name, LOCATE(' ', name) + 1))
 *   WHERE name IS NOT NULL;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // ── Identité ──────────────────────────────────────────────
            $table->string('first_name', 100)->after('id');
            $table->string('last_name', 100)->after('first_name');

            // Rendre `name` nullable (colonne par défaut Laravel) pour compatibilité
            // Tu peux la supprimer plus tard : $table->dropColumn('name');
            $table->string('name')->nullable()->change();

            // ── Contact ───────────────────────────────────────────────
            // Numéro de téléphone — format international recommandé (+221XXXXXXXXX)
            $table->string('phone', 30)->nullable()->after('email');

            // ── Authentification ──────────────────────────────────────
            // Flag mode 'admin' : forcer le changement de mot de passe temporaire
            $table->boolean('must_change_password')->default(false)->after('password');

            // ── Statut du compte ──────────────────────────────────────
            // active   → compte normal (défaut)
            // inactive → compte désactivé temporairement
            // banned   → compte banni
            $table->enum('status', ['active', 'inactive', 'banned'])
                ->default('active')
                ->index()
                ->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone', 'must_change_password', 'status']);

            // Restaurer `name` comme non-nullable
            $table->string('name')->nullable(false)->change();
        });
    }
};
