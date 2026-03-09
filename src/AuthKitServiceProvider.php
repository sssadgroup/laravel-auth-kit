<?php

namespace S3Tech\AuthKit;

use Illuminate\Support\ServiceProvider;

/**
 * AuthKitServiceProvider
 *
 * Point d'entrée du package.
 * - Fusionne la config par défaut avec celle de l'application
 * - Publie config, migrations, vues et stub User model
 * - Charge les routes automatiquement
 *
 * Commandes de publication disponibles :
 *   php artisan vendor:publish --tag=auth-kit-config        → config/auth-kit.php
 *   php artisan vendor:publish --tag=auth-kit-migrations    → database/migrations/
 *   php artisan vendor:publish --tag=auth-kit-views         → resources/views/vendor/auth-kit/
 *   php artisan vendor:publish --tag=auth-kit-user-model    → app/Models/User.php
 */
class AuthKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fusionner la config du package avec celle éventuellement publiée par l'app
        $this->mergeConfigFrom(
            __DIR__ . '/Config/auth-kit.php',
            'auth-kit'
        );
    }

    public function boot(): void
    {
        // ── Config ──────────────────────────────────────────────
        $this->publishes([
            __DIR__ . '/Config/auth-kit.php' => config_path('auth-kit.php'),
        ], 'auth-kit-config');

        // ── Migrations ──────────────────────────────────────────
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'auth-kit-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // ── Vues (templates email) ───────────────────────────────
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/auth-kit'),
        ], 'auth-kit-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'auth-kit');

        // ── Stub User model ───────────────────────────────────────
        // Publie un modèle User pré-configuré avec tous les traits requis
        // (HasApiTokens, HasRoles, HasAuthKit) et toutes les colonnes définies.
        // ⚠️  Écrase app/Models/User.php — à utiliser sur un nouveau projet.
        $this->publishes([
            __DIR__ . '/../stubs/User.php' => app_path('Models/User.php'),
        ], 'auth-kit-user-model');

        // ── Routes ──────────────────────────────────────────────
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}
