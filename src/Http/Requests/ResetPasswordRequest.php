<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la réinitialisation du mot de passe (étape 3 du flux OTP).
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $passwordRules = config('auth-kit.password_rules', ['min:8']);

        return [
            'email'       => 'required|email',
            'reset_token' => 'required|string',
            'password'    => array_merge(['required', 'confirmed'], $passwordRules),
        ];
    }
}
