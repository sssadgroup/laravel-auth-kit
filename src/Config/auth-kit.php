<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | Préfixe et middleware appliqués à toutes les routes du package.
    | Exemple : 'route_prefix' => 'api/v1'
    */
    'route_prefix'     => 'api',
    'route_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Modèle User
    |--------------------------------------------------------------------------
    | Modèle Eloquent utilisé pour toutes les opérations d'authentification.
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Token Sanctum
    |--------------------------------------------------------------------------
    */
    'token' => [
        'name' => 'auth_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP (One-Time Password)
    |--------------------------------------------------------------------------
    | Paramètres du code temporaire envoyé par email pour la réinitialisation
    | du mot de passe.
    */
    'otp' => [
        'length'     => 6,   // Nombre de chiffres
        'expires_in' => 10,  // Durée de validité en minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Règles de validation du mot de passe
    |--------------------------------------------------------------------------
    | Appliquées à l'inscription, au changement et à la réinitialisation.
    */
    'password_rules' => ['min:8'],

    /*
    |--------------------------------------------------------------------------
    | Inscription (Register)
    |--------------------------------------------------------------------------
    | Deux modes mutuellement exclusifs :
    |
    |   'self'  → L'utilisateur s'inscrit lui-même via POST /auth/register
    |   'admin' → Seul un utilisateur habilité peut créer un compte ;
    |              un mot de passe temporaire est généré et envoyé par email.
    |              La route publique /auth/register est alors désactivée.
    |
    | 'default_role' : rôle Spatie assigné automatiquement à la création.
    |                  null = aucun rôle assigné.
    */
    'registration' => [
        'mode'         => 'self',   // 'self' | 'admin'
        'default_role' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Champs modifiables du profil
    |--------------------------------------------------------------------------
    | Liste des colonnes de la table users que l'utilisateur peut modifier
    | via PUT /profile.
    | Ajoute ici tout champ supplémentaire (ex. 'phone', 'avatar', 'bio'…).
    | Le champ 'password' est intentionnellement absent : il a sa propre route.
    */
    'profile' => [
        'editable_fields' => ['first_name', 'last_name', 'email', 'phone'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs d'activité
    |--------------------------------------------------------------------------
    | Activation globale et liste des événements à journaliser.
    | Chaque événement peut être activé/désactivé indépendamment.
    */
    'activity_log' => [
        'enabled' => true,

        'log_events' => [
            'register'            => true,
            'login'               => true,
            'logout'              => true,
            'login_failed'        => true,
            'profile_updated'     => true,
            'password_changed'    => true,
            'password_reset'      => true,
            'otp_requested'       => true,
            'admin_created_user'  => true,
            'role_assigned'       => true,
            'role_revoked'        => true,
        ],

        /*
         * Durée de rétention des logs en jours.
         * null = conservation indéfinie.
         * Utilise un scheduled command pour purger : php artisan auth-kit:purge-logs
         */
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions requises pour les actions d'administration
    |--------------------------------------------------------------------------
    | Ces noms de permissions doivent correspondre à ceux créés dans Spatie.
    | Permet d'affiner le contrôle au-delà d'un simple rôle 'super-admin'.
    */
    'permissions' => [
        'manage_roles'       => 'manage-roles',
        'create_user'        => 'create-user',      // requis pour le mode 'admin'
        'update_other_user'  => 'update-user',      // modifier le profil d'un autre
    ],

];
