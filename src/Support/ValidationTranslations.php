<?php

namespace S3Tech\AuthKit\Support;

class ValidationTranslations
{
    /**
     * @return array<string, string>
     */
    public static function messages(string $locale): array
    {
        return match ($locale) {
            'fr' => [
                'required' => 'Le champ :attribute est obligatoire.',
                'string' => 'Le champ :attribute doit être une chaîne de caractères.',
                'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
                'unique' => 'Cette valeur pour :attribute est déjà utilisée.',
                'confirmed' => 'La confirmation de :attribute ne correspond pas.',
                'max.string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
                'min.string' => 'Le champ :attribute doit contenir au moins :min caractères.',
                'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
                'in' => 'La valeur sélectionnée pour :attribute est invalide. Valeurs autorisées : :values.',
                'nullable' => 'Le champ :attribute peut être nul.',
                'sometimes' => 'Le champ :attribute est invalide.',
            ],
            default => [
                'required' => 'The :attribute field is required.',
                'string' => 'The :attribute field must be a string.',
                'email' => 'The :attribute field must be a valid email address.',
                'unique' => 'The selected :attribute has already been taken.',
                'confirmed' => 'The :attribute confirmation does not match.',
                'max.string' => 'The :attribute field must not be greater than :max characters.',
                'min.string' => 'The :attribute field must be at least :min characters.',
                'exists' => 'The selected :attribute is invalid.',
                'in' => 'The selected :attribute is invalid. Allowed values: :values.',
                'nullable' => 'The :attribute field may be null.',
                'sometimes' => 'The :attribute field is invalid.',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $locale): array
    {
        return match ($locale) {
            'fr' => [
                'first_name' => 'prénom',
                'last_name' => 'nom',
                'email' => 'adresse e-mail',
                'password' => 'mot de passe',
                'password_confirmation' => 'confirmation du mot de passe',
                'current_password' => 'mot de passe actuel',
                'new_password' => 'nouveau mot de passe',
                'new_password_confirmation' => 'confirmation du nouveau mot de passe',
                'phone' => 'numéro de téléphone',
                'role' => 'rôle',
                'permission' => 'permission',
                'status' => 'statut',
                'otp' => 'code OTP',
                'reset_token' => 'jeton de réinitialisation',
                'token_id' => 'identifiant de session',
                'mode' => 'mode',
            ],
            default => [
                'first_name' => 'first name',
                'last_name' => 'last name',
                'email' => 'email address',
                'password' => 'password',
                'password_confirmation' => 'password confirmation',
                'current_password' => 'current password',
                'new_password' => 'new password',
                'new_password_confirmation' => 'new password confirmation',
                'phone' => 'phone number',
                'role' => 'role',
                'permission' => 'permission',
                'status' => 'status',
                'otp' => 'OTP code',
                'reset_token' => 'reset token',
                'token_id' => 'session identifier',
                'mode' => 'mode',
            ],
        };
    }
}
