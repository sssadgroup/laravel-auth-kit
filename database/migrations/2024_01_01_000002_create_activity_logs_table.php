<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : auth_kit_activity_logs
 *
 * Journalise toutes les actions des utilisateurs avec :
 *   - Contexte réseau (IP, résolution proxy)
 *   - Contexte appareil (type, navigateur, OS, User-Agent brut)
 *   - Géolocalisation (pays, région, ville, coordonnées GPS)
 *   - Action sémantique : verbe CRUD (create, read, update, delete, login…)
 *   - Ressource cible : modèle Eloquent + ID de la ressource impactée
 *   - Métadonnées libres (JSON)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_kit_activity_logs', function (Blueprint $table) {
            $table->id();

            // ── Acteur ────────────────────────────────────────────
            // Utilisateur qui a réalisé l'action — nullable si email inconnu
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── Événement ─────────────────────────────────────────
            // Identifiant technique de l'événement (login, register, profile_updated…)
            $table->string('event', 64)->index();

            // ── Action sémantique (verbe CRUD) ────────────────────
            // Valeurs : 'create' | 'read' | 'update' | 'delete' | 'login' | 'logout' | 'auth'
            // Permet de filtrer tous les "create" ou tous les "update" toutes ressources confondues.
            $table->string('action', 20)->nullable()->index();

            // ── Ressource cible (polymorphisme manuel) ────────────
            // Nom complet du modèle Eloquent impacté (ex: App\Models\User, App\Models\Post)
            // nullable quand l'action ne cible pas une ressource précise (ex: login)
            $table->string('subject_type')->nullable()->index();

            // ID de la ressource impactée (ex: l'id de l'utilisateur modifié)
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            // ── Réseau ────────────────────────────────────────────
            $table->string('ip_address', 45); // 45 chars pour IPv6

            // ── Appareil ──────────────────────────────────────────
            // 'desktop' | 'mobile' | 'tablet' | 'api_client'
            $table->string('device_type', 20)->default('desktop');
            // Libellé lisible : "Windows 10 — Chrome"
            $table->string('device_name')->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            // User-Agent brut complet pour analyse approfondie si nécessaire
            $table->text('user_agent')->nullable();

            // ── Géolocalisation ───────────────────────────────────
            $table->string('country', 100)->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // ── Métadonnées libres ────────────────────────────────
            // Données spécifiques à l'événement (ex: champs modifiés, email tenté…)
            $table->json('metadata')->nullable();

            // Pas de updated_at (les logs sont immuables)
            $table->timestamp('created_at')->useCurrent()->index();

            // Index composite pour requêtes fréquentes : "toutes les actions sur ce modèle"
            $table->index(['subject_type', 'subject_id'], 'idx_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_kit_activity_logs');
    }
};
