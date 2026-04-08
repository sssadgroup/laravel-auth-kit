<?php

namespace S3Tech\AuthKit\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * @param  array{en:string,fr:string}  $translations
     * @param  array<string,mixed>  $data
     */
    public static function success(Request $request, array $translations, array $data = [], int $status = 200): JsonResponse
    {
        $locale = self::resolveLocale($request);

        return response()->json(array_merge([
            'message' => $translations[$locale] ?? $translations['en'],
            'locale' => $locale,
            'message_translations' => $translations,
        ], $data), $status);
    }

    /**
     * @param  array{en:string,fr:string}  $translations
     * @param  array<string,mixed>  $data
     */
    public static function error(Request $request, array $translations, array $data = [], int $status = 422): JsonResponse
    {
        $locale = self::resolveLocale($request);

        return response()->json(array_merge([
            'error' => $translations[$locale] ?? $translations['en'],
            'locale' => $locale,
            'error_translations' => $translations,
        ], $data), $status);
    }

    public static function resolveLocale(Request $request): string
    {
        $defaultLocale = config('auth-kit.messages.default_locale', 'en');
        $supported = config('auth-kit.messages.supported_locales', ['en', 'fr']);

        $candidate = $request->header('X-Auth-Kit-Locale')
            ?? $request->query('lang')
            ?? $request->getPreferredLanguage($supported)
            ?? $defaultLocale;

        $locale = strtolower(substr((string) $candidate, 0, 2));

        return in_array($locale, $supported, true) ? $locale : $defaultLocale;
    }
}
