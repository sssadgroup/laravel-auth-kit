<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use S3Tech\AuthKit\Traits\HasAuthKit;

/**
 * Modèle User — Exemple d'implémentation pour laravel-auth-kit
 *
 * Copier ce fichier dans app/Models/User.php de ton projet Laravel
 * et remplacer le modèle User existant.
 *
 * Traits requis :
 *   HasApiTokens  → Sanctum : gestion des tokens API
 *   HasRoles      → Spatie  : gestion des rôles et permissions
 *   HasAuthKit    → Package : helpers revokeAllTokens(), isA(), mustChangePassword()
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COLONNES DE LA TABLE users
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @property int         $id
 * @property string      $first_name
 * @property string      $last_name
 * @property string      $email
 * @property string|null $phone
 * @property string|null $email_verified_at
 * @property string      $password
 * @property bool        $must_change_password
 * @property string      $status              'active' | 'inactive' | 'banned'
 * @property string|null $remember_token
 * @property string      $created_at
 * @property string      $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;   // Sanctum — tokens API
    use HasRoles;       // Spatie  — rôles et permissions
    use HasAuthKit;     // Package — helpers auth-kit
    use HasFactory;
    use Notifiable;

    // ─────────────────────────────────────────────────────────────────────────
    // Configuration Eloquent
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Champs assignables en masse.
     * Le mot de passe et must_change_password sont inclus car ils sont
     * mis à jour programmatiquement par le package (jamais directement par l'user).
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'must_change_password',
        'status',
    ];

    /**
     * Champs masqués dans les sérialisations JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts automatiques des colonnes.
     */
    protected $casts = [
        'email_verified_at'    => 'datetime',
        'must_change_password' => 'boolean',
        // Note : `status` est un ENUM — pas besoin de cast, retourné en string
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes utilitaires
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scope : uniquement les comptes actifs.
     *
     * Usage : User::active()->get()
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope : uniquement les comptes inactifs.
     *
     * Usage : User::inactive()->get()
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope : uniquement les comptes bannis.
     *
     * Usage : User::banned()->get()
     */
    public function scopeBanned($query)
    {
        return $query->where('status', 'banned');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accesseurs
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourner le nom complet (prénom + nom).
     *
     * Usage : $user->full_name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Vérifier si le compte est actif.
     *
     * Usage : $user->isActive()
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Vérifier si le compte est banni.
     *
     * Usage : $user->isBanned()
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }
}
