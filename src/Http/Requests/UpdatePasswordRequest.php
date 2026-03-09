<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du changement de mot de passe de l'utilisateur connecté.
 * Requiert l'ancien mot de passe (vérifié dans le contrôleur, pas ici).
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $passwordRules = config('auth-kit.password_rules', ['min:8']);

        return [
            'current_password' => 'required|string',
            'new_password'     => array_merge(['required', 'confirmed'], $passwordRules),
        ];
    }
}
