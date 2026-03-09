<?php

namespace S3Tech\AuthKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource
 *
 * Transforme le modèle User en réponse JSON standardisée.
 *
 * Champs exposés :
 *   id                    → identifiant unique
 *   first_name            → prénom
 *   last_name             → nom de famille
 *   full_name             → prénom + nom (calculé)
 *   email                 → adresse email
 *   phone                 → numéro de téléphone (nullable)
 *   status                → statut du compte : 'active' | 'inactive' | 'banned'
 *   roles                 → liste des rôles Spatie (lazy-loaded)
 *   permissions           → toutes les permissions (lazy-loaded)
 *   must_change_password  → flag mot de passe temporaire (mode admin)
 *   created_at            → date de création du compte (ISO 8601)
 *   updated_at            → date de dernière modification (ISO 8601)
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identité ──────────────────────────────────────────────
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            // Nom complet calculé — pratique pour l'affichage dans les clients API
            'full_name'  => trim("{$this->first_name} {$this->last_name}"),

            // ── Contact ───────────────────────────────────────────────
            'email'      => $this->email,
            // Null si non renseigné à l'inscription
            'phone'      => $this->phone,

            // ── Statut du compte ──────────────────────────────────────
            // 'active' | 'inactive' | 'banned'
            'status'     => $this->status,

            // ── Rôles & Permissions (Spatie) ──────────────────────────
            // Inclus uniquement si chargés via ->load('roles')
            'roles'      => $this->whenLoaded('roles',
                fn () => $this->getRoleNames()
            ),
            // Toutes les permissions (directes + via rôles) — via ->load('permissions')
            'permissions' => $this->whenLoaded('permissions',
                fn () => $this->getAllPermissions()->pluck('name')
            ),

            // ── Sécurité ──────────────────────────────────────────────
            // true = l'utilisateur doit changer son mot de passe temporaire (mode admin)
            'must_change_password' => (bool) ($this->must_change_password ?? false),

            // ── Horodatage ────────────────────────────────────────────
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
