<?php

use Illuminate\Support\Facades\Route;
use S3Tech\AuthKit\Http\Controllers\AuthController;
use S3Tech\AuthKit\Http\Controllers\ProfileController;
use S3Tech\AuthKit\Http\Controllers\PasswordResetController;
use S3Tech\AuthKit\Http\Controllers\RolePermissionController;
use S3Tech\AuthKit\Http\Controllers\ActivityLogController;

$prefix     = config('auth-kit.route_prefix', 'api');
$middleware = config('auth-kit.route_middleware', ['api']);

Route::prefix($prefix)->middleware($middleware)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Routes publiques (non authentifiées)
    |--------------------------------------------------------------------------
    */

    /**
     * Inscription en mode 'self' uniquement.
     * En mode 'admin', cette route est désactivée — la création passe
     * par POST /users (route protégée avec permission 'create-user').
     */
    if (config('auth-kit.registration.mode', 'self') === 'self') {
        Route::post('auth/register', [AuthController::class, 'register']);
    }

    // Connexion
    Route::post('auth/login', [AuthController::class, 'login']);

    /*
     * Flux OTP — Mot de passe oublié (3 étapes) :
     *   1. Envoyer l'OTP
     *   2. Vérifier l'OTP → obtenir reset_token
     *   3. Réinitialiser le mot de passe avec reset_token
     */
    Route::post('auth/forgot-password', [PasswordResetController::class, 'sendOtp']);
    Route::post('auth/verify-otp',      [PasswordResetController::class, 'verifyOtp']);
    Route::post('auth/reset-password',  [PasswordResetController::class, 'resetPassword']);

    /*
    |--------------------------------------------------------------------------
    | Routes protégées (auth:sanctum requis)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // ── Authentification ──────────────────────────────────

        // Récupérer l'utilisateur connecté (avec rôles + permissions)
        Route::get('auth/me', [AuthController::class, 'me']);

        /**
         * Déconnexion.
         * Trois modes via le body JSON :
         *   { "mode": "current" }              → révoque la session en cours (défaut)
         *   { "mode": "specific", "token_id": 12 } → révoque un token précis
         *   { "mode": "all" }                  → révoque toutes les sessions
         */
        Route::post('auth/logout', [AuthController::class, 'logout']);

        /**
         * Lister toutes les sessions actives de l'utilisateur connecté.
         * Utile pour afficher "Appareils connectés" dans l'UI.
         */
        Route::get('auth/sessions', [AuthController::class, 'sessions']);

        // ── Profil propre (utilisateur connecté) ─────────────

        /**
         * Mettre à jour son propre profil.
         * Seuls les champs déclarés dans config('auth-kit.profile.editable_fields')
         * sont acceptés. Tous les champs sont optionnels (PATCH-like).
         * Le mot de passe ne peut PAS être modifié ici.
         */
        Route::put('profile', [ProfileController::class, 'updateOwnProfile']);

        /**
         * Changer son propre mot de passe.
         * Requiert l'ancien mot de passe pour confirmer l'identité.
         */
        Route::put('profile/password', [ProfileController::class, 'updateOwnPassword']);

        // ── Administration des utilisateurs ───────────────────

        /**
         * Créer un utilisateur (mode 'admin' uniquement).
         * Requiert la permission 'create-user'.
         * Un mot de passe temporaire est généré et envoyé par email.
         */
        Route::post('users', [AuthController::class, 'adminCreateUser'])
            ->middleware('can:' . config('auth-kit.permissions.create_user', 'create-user'));

        /**
         * Modifier le profil d'un autre utilisateur.
         * Requiert la permission 'update-user'.
         * Le mot de passe NE PEUT PAS être modifié par cette route.
         */
        Route::put('users/{user}', [ProfileController::class, 'updateUserProfile'])
            ->middleware('can:' . config('auth-kit.permissions.update_other_user', 'update-user'));

        // ── Logs d'activité ───────────────────────────────────

        /**
         * Consulter ses propres logs d'activité.
         */
        Route::get('activity-logs/me', [ActivityLogController::class, 'myLogs']);

        /**
         * Consulter les logs d'un utilisateur (admin seulement).
         * Requiert la permission 'manage-roles' (ou créer une permission dédiée).
         */
        Route::get('activity-logs/user/{user}', [ActivityLogController::class, 'userLogs'])
            ->middleware('can:' . config('auth-kit.permissions.manage_roles, manage-roles'));

        /**
         * Consulter tous les logs (admin seulement).
         */
        Route::get('activity-logs', [ActivityLogController::class, 'allLogs'])
            ->middleware('can:' . config('auth-kit.permissions.manage_roles', 'manage-roles'));

        // ── Rôles & Permissions (Spatie) ──────────────────────

        Route::middleware('can:' . config('auth-kit.permissions.manage_roles', 'manage-roles'))
            ->group(function () {

                // Rôles
                Route::get('roles',              [RolePermissionController::class, 'listRoles']);
                Route::post('roles',             [RolePermissionController::class, 'createRole']);
                Route::delete('roles/{role}',    [RolePermissionController::class, 'deleteRole']);

                // Permissions
                Route::get('permissions',        [RolePermissionController::class, 'listPermissions']);
                Route::post('permissions',       [RolePermissionController::class, 'createPermission']);

                // Assignation rôle ↔ permission
                Route::post('roles/{role}/permissions',           [RolePermissionController::class, 'assignPermissionToRole']);
                Route::delete('roles/{role}/permissions/{perm}',  [RolePermissionController::class, 'revokePermissionFromRole']);

                // Assignation utilisateur ↔ rôle
                Route::post('users/{user}/roles',                 [RolePermissionController::class, 'assignRoleToUser']);
                Route::delete('users/{user}/roles/{role}',        [RolePermissionController::class, 'revokeRoleFromUser']);
            });
    });
});
