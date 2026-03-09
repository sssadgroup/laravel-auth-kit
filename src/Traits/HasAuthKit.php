<?php

namespace S3Tech\AuthKit\Traits;

/**
 * HasAuthKit
 *
 * Trait à ajouter sur le modèle App\Models\User pour la compatibilité complète
 * avec le package laravel-auth-kit.
 *
 * Prérequis — Le modèle User doit également utiliser :
 *   use Laravel\Sanctum\HasApiTokens;
 *   use Spatie\Permission\Traits\HasRoles;
 *
 * Usage dans User.php :
 *   use HasApiTokens, HasRoles, HasAuthKit;
 */
trait HasAuthKit
{
    /**
     * Révoquer tous les tokens Sanctum de cet utilisateur.
     * Utile pour forcer la déconnexion de toutes les sessions.
     */
    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    /**
     * Vérifier si l'utilisateur possède un rôle donné.
     * Alias lisible de la méthode Spatie hasRole().
     */
    public function isA(string $role): bool
    {
        return $this->hasRole($role);
    }

    /**
     * Vérifier si l'utilisateur possède au moins un des rôles listés.
     */
    public function isAny(array $roles): bool
    {
        return $this->hasAnyRole($roles);
    }

    /**
     * Vérifier si l'utilisateur doit changer son mot de passe.
     * Utilisé en mode 'admin' pour les mots de passe temporaires.
     */
    public function mustChangePassword(): bool
    {
        return (bool) ($this->must_change_password ?? false);
    }
}
