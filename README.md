# 🔐 Laravel Auth Kit — v2

Package Laravel **plug-and-play** pour les projets API.  
Installez-le et tout est prêt : authentification, profil, réinitialisation OTP, rôles, et logs d'activité.

---

## 📦 Fonctionnalités

| Fonctionnalité | Détail |
|---|---|
| **Authentification** | Sanctum — login, logout multi-mode, `/me` |
| **Sessions** | Liste des appareils connectés, révocation ciblée |
| **Inscription** | Mode `self` (autonome) **ou** mode `admin` (habilité + mot de passe temporaire par email) |
| **Profil** | Champs optionnels, liste configurable, statut non modifiable |
| **Mot de passe** | Changement (propre) / modification par admin **interdite** |
| **Réinitialisation** | Flux OTP 3 étapes (email → code → reset) |
| **Rôles & Permissions** | Spatie Laravel Permission — CRUD complet |
| **Logs d'activité** | IP, appareil, OS, navigateur, géolocalisation GPS |

---

## 🚀 Installation

### 1. Ajouter le package

```bash
composer require s3tech/laravel-auth-kit
```

En développement local (path repository) :
```json
"repositories": [{ "type": "path", "url": "../laravel-auth-kit" }],
"require": { "s3tech/laravel-auth-kit": "*" }
```

---

### 2. Publier la configuration

```bash
php artisan vendor:publish --tag=auth-kit-config
```

---

### 3. Publier et exécuter les migrations

```bash
# Sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Spatie
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Auth Kit
php artisan vendor:publish --tag=auth-kit-migrations

php artisan migrate
```

---

### 4. Configurer le modèle User

```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use S3Tech\AuthKit\Traits\HasAuthKit;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, HasAuthKit;

    protected $fillable = ['name', 'email', 'password', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];
}
```

---

### 5. Guard Sanctum pour Spatie

Dans `config/permission.php` :
```php
'guard_name' => 'sanctum',
```

Dans `config/auth.php`, s'assurer que le guard existe :
```php
'guards' => [
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
],
```

---

### 6. Créer le seeder de rôles initiaux

```php
// database/seeders/RoleSeeder.php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Rôles
Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'sanctum']);
Role::firstOrCreate(['name' => 'manager',     'guard_name' => 'sanctum']);
Role::firstOrCreate(['name' => 'user',        'guard_name' => 'sanctum']);

// Permissions
$permissions = ['manage-roles', 'create-user', 'update-user'];
foreach ($permissions as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
}

// Assigner toutes les permissions au super-admin
Role::findByName('super-admin', 'sanctum')->givePermissionTo($permissions);
```

---

## ⚙️ Configuration (`config/auth-kit.php`)

```php
return [
    'route_prefix'     => 'api',
    'route_middleware' => ['api'],
    'user_model'       => \App\Models\User::class,

    'token' => ['name' => 'auth_token'],

    'otp' => [
        'length'     => 6,
        'expires_in' => 10,  // minutes
    ],

    'password_rules' => ['min:8'],

    'registration' => [
        'mode'         => 'self',   // 'self' | 'admin'  — mutuellement exclusifs
        'default_role' => 'user',
    ],

    'profile' => [
        // Champs modifiables via PUT /profile et PUT /users/{user}
        // Ajouter 'phone', 'avatar'… selon votre table users
        'editable_fields' => ['name', 'email'],
    ],

    'activity_log' => [
        'enabled'        => true,
        'retention_days' => 90,     // null = indéfini
        'log_events'     => [
            'register'           => true,
            'login'              => true,
            'logout'             => true,
            'login_failed'       => true,
            'profile_updated'    => true,
            'password_changed'   => true,
            'password_reset'     => true,
            'otp_requested'      => true,
            'admin_created_user' => true,
            'role_assigned'      => true,
            'role_revoked'       => true,
        ],
    ],

    'permissions' => [
        'manage_roles'      => 'manage-roles',
        'create_user'       => 'create-user',
        'update_other_user' => 'update-user',
    ],
];
```

---

## 🛣️ Routes disponibles

### Publiques

| Méthode | Endpoint | Description | Condition |
|---|---|---|---|
| `POST` | `/api/auth/register` | Inscription autonome | Mode `self` uniquement |
| `POST` | `/api/auth/login` | Connexion | — |
| `POST` | `/api/auth/forgot-password` | Envoyer OTP | — |
| `POST` | `/api/auth/verify-otp` | Vérifier OTP → reset_token | — |
| `POST` | `/api/auth/reset-password` | Réinitialiser le mot de passe | — |

### Protégées (`auth:sanctum`)

| Méthode | Endpoint | Description |
|---|---|---|
| `GET` | `/api/auth/me` | Utilisateur connecté |
| `POST` | `/api/auth/logout` | Déconnexion (voir modes ci-dessous) |
| `GET` | `/api/auth/sessions` | Liste des sessions actives |
| `PUT` | `/api/profile` | Modifier son propre profil |
| `PUT` | `/api/profile/password` | Changer son mot de passe |
| `POST` | `/api/users` | Créer un utilisateur (permission `create-user`) |
| `PUT` | `/api/users/{user}` | Modifier le profil d'un user (permission `update-user`) |
| `GET` | `/api/activity-logs/me` | Mes logs d'activité |
| `GET` | `/api/activity-logs/user/{user}` | Logs d'un user (permission `manage-roles`) |
| `GET` | `/api/activity-logs` | Tous les logs (permission `manage-roles`) |

### Administration rôles (permission `manage-roles`)

| Méthode | Endpoint | Description |
|---|---|---|
| `GET/POST` | `/api/roles` | Lister / Créer un rôle |
| `DELETE` | `/api/roles/{role}` | Supprimer un rôle |
| `GET/POST` | `/api/permissions` | Lister / Créer une permission |
| `POST` | `/api/roles/{role}/permissions` | Assigner permission → rôle |
| `DELETE` | `/api/roles/{role}/permissions/{perm}` | Révoquer permission d'un rôle |
| `POST` | `/api/users/{user}/roles` | Assigner rôle → utilisateur |
| `DELETE` | `/api/users/{user}/roles/{role}` | Révoquer rôle d'un utilisateur |

---

## 🔌 Modes de déconnexion

```json
// Session courante uniquement (défaut)
{ "mode": "current" }

// Une session précise (visible via GET /auth/sessions)
{ "mode": "specific", "token_id": 12 }

// Toutes les sessions (déconnexion totale)
{ "mode": "all" }
```

---

## 👤 Modes d'inscription

### Mode `self` (défaut)
L'utilisateur s'inscrit via `POST /api/auth/register` avec son propre mot de passe.
La route est publique.

### Mode `admin`
- `POST /api/auth/register` est **désactivée**
- Seul un utilisateur avec la permission `create-user` peut créer des comptes via `POST /api/users`
- Un **mot de passe temporaire** est généré et envoyé par email
- L'utilisateur devra changer son mot de passe à la première connexion (`must_change_password = true`)

---

## 📊 Logs d'activité

### Données collectées par action

```json
{
  "id": 42,
  "user_id": 7,
  "event": "login",
  "ip_address": "41.82.12.45",
  "device_type": "mobile",
  "device_name": "Android — Chrome",
  "browser": "Chrome",
  "os": "Android",
  "country": "Senegal",
  "country_code": "SN",
  "region": "Dakar",
  "city": "Dakar",
  "latitude": 14.6928,
  "longitude": -17.4467,
  "metadata": null,
  "created_at": "2024-11-20T14:32:11.000000Z"
}
```

### Filtres disponibles (query string)

```
GET /api/activity-logs?event=login&from=2024-01-01&to=2024-12-31&per_page=50
```

---

## 🏗️ Arborescence du package

```
laravel-auth-kit/
├── composer.json
├── README.md
├── database/
│   └── migrations/
│       ├── ..._create_password_reset_otps_table.php
│       └── ..._create_activity_logs_table.php
├── resources/
│   └── views/emails/
│       ├── otp.blade.php                    Email code OTP
│       └── temporary-password.blade.php     Email mot de passe temporaire (mode admin)
└── src/
    ├── AuthKitServiceProvider.php
    ├── Config/
    │   └── auth-kit.php
    ├── Http/
    │   ├── Controllers/
    │   │   ├── AuthController.php           login, register, logout, me, sessions, adminCreateUser
    │   │   ├── ProfileController.php        updateOwnProfile, updateOwnPassword, updateUserProfile
    │   │   ├── PasswordResetController.php  sendOtp, verifyOtp, resetPassword
    │   │   ├── RolePermissionController.php CRUD rôles/permissions + assignations
    │   │   └── ActivityLogController.php    myLogs, userLogs, allLogs
    │   ├── Requests/
    │   │   ├── LoginRequest.php
    │   │   ├── RegisterRequest.php
    │   │   ├── AdminCreateUserRequest.php
    │   │   ├── UpdateProfileRequest.php
    │   │   ├── AdminUpdateUserProfileRequest.php
    │   │   ├── UpdatePasswordRequest.php
    │   │   └── ResetPasswordRequest.php
    │   └── Resources/
    │       └── UserResource.php
    ├── Mail/
    │   ├── OtpMail.php
    │   └── TemporaryPasswordMail.php
    ├── Models/
    │   ├── PasswordResetOtp.php
    │   └── ActivityLog.php
    ├── Routes/
    │   └── api.php
    ├── Services/
    │   └── ActivityLogService.php           Cœur du système de logs
    └── Traits/
        └── HasAuthKit.php                   À ajouter sur App\Models\User
```

---

## 📄 Licence

MIT
