<?php

namespace S3Tech\AuthKit\Http\Requests;

/**
 * Validation de la connexion.
 */
class LoginRequest extends AuthKitFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }
}
