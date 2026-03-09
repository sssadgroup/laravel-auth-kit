<?php

namespace S3Tech\AuthKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use S3Tech\AuthKit\Http\Requests\ResetPasswordRequest;
use S3Tech\AuthKit\Mail\OtpMail;
use S3Tech\AuthKit\Models\PasswordResetOtp;
use S3Tech\AuthKit\Services\ActivityLogService;
use Illuminate\Support\Facades\Mail;

/**
 * PasswordResetController
 *
 * Gère le flux de réinitialisation de mot de passe en 3 étapes via OTP :
 *
 *   Étape 1 — POST /auth/forgot-password
 *             → Génère un OTP (hashé en base), l'envoie par email
 *
 *   Étape 2 — POST /auth/verify-otp
 *             → Vérifie l'OTP, retourne un reset_token (hashé en base)
 *
 *   Étape 3 — POST /auth/reset-password
 *             → Vérifie le reset_token, change le mot de passe,
 *               révoque tous les tokens Sanctum
 *
 * Sécurité :
 *   - L'OTP et le reset_token sont hashés avant d'être stockés en base
 *   - L'email retourne toujours le même message (anti-énumération d'utilisateurs)
 *   - L'OTP expire après N minutes (configurable)
 *   - Le reset_token est à usage unique (marqué 'used' après utilisation)
 */
class PasswordResetController extends Controller
{
    public function __construct(protected ActivityLogService $logger) {}

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 1 : Envoyer l'OTP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Générer et envoyer un OTP par email.
     *
     * POST /api/auth/forgot-password
     * Body : { email }
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $userModel = config('auth-kit.user_model');
        $user      = $userModel::where('email', $request->email)->first();

        // Anti-énumération : toujours retourner 200, même si l'email n'existe pas
        if (! $user) {
            return response()->json([
                'message' => 'Si cet email existe, un code de vérification a été envoyé.',
            ]);
        }

        // Supprimer tout OTP précédent pour cet email
        PasswordResetOtp::where('email', $request->email)->delete();

        // Générer un OTP numérique de la longueur configurée
        $length = config('auth-kit.otp.length', 6);
        $otp    = str_pad((string) random_int(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);

        PasswordResetOtp::create([
            'email'      => $request->email,
            'otp'        => Hash::make($otp),           // Stocké hashé
            'reset_token' => null,                       // Généré à l'étape 2
            'expires_at' => now()->addMinutes(config('auth-kit.otp.expires_in', 10)),
            'used'       => false,
        ]);

        Mail::to($request->email)->send(new OtpMail($otp));

        $this->logger->log($request, 'otp_requested', $user->id, 'auth', $user, [
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Si cet email existe, un code de vérification a été envoyé.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 2 : Vérifier l'OTP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vérifier le code OTP et retourner un reset_token signé.
     *
     * POST /api/auth/verify-otp
     * Body : { email, otp }
     *
     * Réponse succès : { reset_token }
     * → Ce reset_token sera utilisé à l'étape 3 pour changer le mot de passe.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $record = PasswordResetOtp::where('email', $request->email)
            ->where('used', false)
            ->first();

        // Vérifier existence, correspondance du hash et expiration
        if (! $record || ! Hash::check($request->otp, $record->otp) || $record->isExpired()) {
            return response()->json(['message' => 'Code OTP invalide ou expiré.'], 422);
        }

        // Générer un reset_token aléatoire (hashé en base, envoyé en clair)
        $resetToken = Str::random(64);

        $record->update([
            'reset_token' => Hash::make($resetToken),
            // 'used' reste false : l'OTP est vérifié mais le reset n'est pas encore fait
        ]);

        return response()->json([
            'message'     => 'Code OTP vérifié.',
            'reset_token' => $resetToken,  // À transmettre à l'étape 3
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 3 : Réinitialiser le mot de passe
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Réinitialiser le mot de passe avec le reset_token obtenu à l'étape 2.
     *
     * POST /api/auth/reset-password
     * Body : { email, reset_token, password, password_confirmation }
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $record = PasswordResetOtp::where('email', $request->email)
            ->where('used', false)
            ->first();

        // Vérifier existence, reset_token, et expiration
        if (
            ! $record
            || ! $record->reset_token
            || ! Hash::check($request->reset_token, $record->reset_token)
            || $record->isExpired()
        ) {
            return response()->json(['message' => 'Token de réinitialisation invalide ou expiré.'], 422);
        }

        $userModel = config('auth-kit.user_model');
        $user      = $userModel::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        // Mettre à jour le mot de passe
        $user->update(['password' => Hash::make($request->password)]);

        // Révoquer toutes les sessions actives
        $user->tokens()->delete();

        // Marquer l'enregistrement OTP comme utilisé (usage unique)
        $record->update(['used' => true]);

        $this->logger->log($request, 'password_reset', $user->id, 'update', $user);

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès. Veuillez vous reconnecter.',
        ]);
    }
}
