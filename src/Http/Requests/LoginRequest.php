<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la connexion.
 */
class LoginRequest extends FormRequest
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
