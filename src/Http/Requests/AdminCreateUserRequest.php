<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'utilisateur par un admin (mode 'admin').
 *
 * Pas de mot de passe dans le body — il est généré automatiquement par le système.
 *
 * Champs :
 *   - first_name  : prénom (obligatoire)
 *   - last_name   : nom de famille (obligatoire)
 *   - email       : email unique (obligatoire)
 *   - phone       : numéro de téléphone (optionnel)
 *   - role        : rôle Spatie à assigner (optionnel — doit exister en base)
 */
class AdminCreateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'sometimes|nullable|string|max:30',
            'role'       => 'sometimes|string|exists:roles,name',
        ];
    }
}
