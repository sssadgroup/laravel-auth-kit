<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la mise à jour du profil de l'utilisateur connecté.
 *
 * Tous les champs sont optionnels (comportement PATCH-like).
 * Le mot de passe est EXCLU intentionnellement — il a sa propre route dédiée.
 * Le champ 'email' utilise l'ignore de l'ID courant pour les règles d'unicité.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $userId  = $this->user()->id;
        $allowed = config('auth-kit.profile.editable_fields', ['name', 'email']);

        $allRules = [
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => "sometimes|email|unique:users,email,{$userId}",
            // Optionnel — null autorisé pour effacer le numéro
            'phone'      => 'sometimes|nullable|string|max:30',
            // Ajouter ici d'autres champs si 'editable_fields' est étendu dans la config
        ];

        // Ne valider que les champs autorisés dans la config
        return array_intersect_key($allRules, array_flip($allowed));
    }
}
