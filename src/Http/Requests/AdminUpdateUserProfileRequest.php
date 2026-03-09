<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la mise à jour du profil d'un autre utilisateur par un admin.
 *
 * ⚠️  RÈGLE MÉTIER : Le mot de passe est explicitement interdit dans ce formulaire.
 *     Un admin ne peut pas modifier le mot de passe d'un autre utilisateur.
 *     Cette contrainte est appliquée ici ET dans le contrôleur (double sécurité).
 */
class AdminUpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // L'ID de l'utilisateur cible est dans l'URL : /users/{user}
        $targetUserId = $this->route('user');
        $allowed      = config('auth-kit.profile.editable_fields', ['name', 'email']);

        $allRules = [
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => "sometimes|email|unique:users,email,{$targetUserId}",
            'phone'      => 'sometimes|nullable|string|max:30',
        ];

        return array_intersect_key($allRules, array_flip($allowed));
    }

    /**
     * S'assurer que 'password' n'est jamais présent dans les données validées,
     * même si quelqu'un l'envoie explicitement dans le body.
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        // Double sécurité : retirer le mot de passe même s'il a passé la validation
        unset($data['password'], $data['password_confirmation'], $data['current_password']);

        return $data;
    }
}
