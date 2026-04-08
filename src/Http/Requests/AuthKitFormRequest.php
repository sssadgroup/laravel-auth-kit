<?php

namespace S3Tech\AuthKit\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use S3Tech\AuthKit\Support\ApiResponse;
use S3Tech\AuthKit\Support\ValidationTranslations;

abstract class AuthKitFormRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ValidationTranslations::messages(ApiResponse::resolveLocale($this));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ValidationTranslations::attributes(ApiResponse::resolveLocale($this));
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $supportedLocales = config('auth-kit.messages.supported_locales', ['en', 'fr']);
        $currentLocale = ApiResponse::resolveLocale($this);
        $translatedErrors = [];

        foreach ($supportedLocales as $locale) {
            $translatedErrors[$locale] = $this->buildErrorsForLocale($locale);
        }

        throw new HttpResponseException(
            ApiResponse::error($this, [
                'en' => 'The given data was invalid.',
                'fr' => 'Les donnees fournies sont invalides.',
            ], [
                'errors' => $translatedErrors[$currentLocale] ?? $validator->errors()->messages(),
                'errors_translations' => $translatedErrors,
            ], 422)
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildErrorsForLocale(string $locale): array
    {
        $validator = Validator::make(
            $this->validationData(),
            $this->container->call([$this, 'rules']),
            ValidationTranslations::messages($locale),
            ValidationTranslations::attributes($locale)
        );

        $validator->fails();

        return $validator->errors()->messages();
    }
}
