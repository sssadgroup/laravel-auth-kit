<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'inscription en mode 'self'.
 *
 * Champs :
 *   - first_name              : prénom (obligatoire)
 *   - last_name               : nom de famille (obligatoire)
 *   - email                   : email unique (obligatoire)
 *   - password                : mot de passe avec confirmation (obligatoire)
 *   - phone                   : numéro de téléphone (optionnel)
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $passwordRules = config('auth-kit.password_rules', ['min:8']);

        return [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'password'   => array_merge(['required', 'confirmed'], $passwordRules),
            // Optionnel — format international recommandé mais non imposé
            'phone'      => 'sometimes|nullable|string|max:30',
        ];
    }
}
