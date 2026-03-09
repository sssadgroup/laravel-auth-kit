<?php

namespace S3Tech\AuthKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use S3Tech\AuthKit\Http\Requests\LoginRequest;
use S3Tech\AuthKit\Http\Requests\RegisterRequest;
use S3Tech\AuthKit\Http\Requests\AdminCreateUserRequest;
use S3Tech\AuthKit\Http\Resources\UserResource;
use S3Tech\AuthKit\Mail\TemporaryPasswordMail;
use S3Tech\AuthKit\Services\ActivityLogService;
use Illuminate\Support\Facades\Mail;

/**
 * AuthController
 *
 * Gère :
 *   - Inscription (mode 'self' ou 'admin')
 *   - Connexion / Déconnexion (session courante, session précise, toutes les sessions)
 *   - Utilisateur connecté (/me)
 *   - Liste des sessions actives (/sessions)
 *
 * Champs utilisateur supportés : first_name, last_name, email, phone (optionnel)
 */
class AuthController extends Controller
{
    public function __construct(protected ActivityLogService $logger) {}

    // ─────────────────────────────────────────────────────────────────────────
    // INSCRIPTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Register (mode 'self')
     * ──────────────────────
     * L'utilisateur s'inscrit lui-même avec ses propres informations.
     * Disponible uniquement si config('auth-kit.registration.mode') === 'self'.
     *
     * Body : { first_name, last_name, email, password, password_confirmation, phone? }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $userModel = config('auth-kit.user_model');

        $user = $userModel::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'phone'      => $request->phone, // optionnel — null si absent
        ]);

        $this->assignDefaultRole($user);

        $token = $user->createToken(config('auth-kit.token.name'))->plainTextToken;

        // Log : action 'create', ressource = le nouvel utilisateur lui-même
        $this->logger->log($request, 'register', $user->id, 'create', $user, [
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Inscription réussie.',
            'user'    => new UserResource($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Admin Create User (mode 'admin')
     * ──────────────────────────────────
     * Un utilisateur habilité (permission 'create-user') crée un compte.
     * Un mot de passe temporaire est généré et envoyé par email.
     *
     * Body : { first_name, last_name, email, phone?, role? }
     * Requiert : permission 'create-user' (vérifié dans les routes)
     */
    public function adminCreateUser(AdminCreateUserRequest $request): JsonResponse
    {
        $userModel = config('auth-kit.user_model');

        $temporaryPassword = Str::random(12);

        $user = $userModel::create([
            'first_name'           => $request->first_name,
            'last_name'            => $request->last_name,
            'email'                => $request->email,
            'password'             => Hash::make($temporaryPassword),
            'phone'                => $request->phone,
            'must_change_password' => true,
        ]);

        if ($request->filled('role')) {
            $user->assignRole($request->role);
        } else {
            $this->assignDefaultRole($user);
        }

        Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaryPassword));

        // Log : action 'create', acteur = admin, ressource = nouvel utilisateur
        $this->logger->log($request, 'admin_created_user', $request->user()->id, 'create', $user, [
            'created_user_email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé. Un mot de passe temporaire a été envoyé par email.',
            'user'    => new UserResource($user),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONNEXION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Login
     * ─────
     * Body : { email, password }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $userModel = config('auth-kit.user_model');
        $user      = $userModel::where('email', $request->email)->first();

        // Vérifier les identifiants
        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Log : tentative échouée — null si email totalement inconnu (anti-énumération)
            $this->logger->log($request, 'login_failed', $user?->id, 'auth', null, [
                'email' => $request->email,
            ]);

            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        // Vérifier que le compte est actif
        // Un compte 'inactive' ou 'banned' se voit refuser l'accès avec un message explicite
        if ($user->status !== 'active') {
            $messages = [
                'inactive' => "Votre compte est désactivé. Contactez l'administrateur.",
                'banned'   => "Votre compte a été suspendu. Contactez l'administrateur.",
            ];

            $this->logger->log($request, 'login_failed', $user->id, 'auth', $user, [
                'reason' => "account_{$user->status}",
            ]);

            return response()->json([
                'message' => $messages[$user->status] ?? 'Compte non autorisé.',
            ], 403);
        }

        $token = $user->createToken(config('auth-kit.token.name'))->plainTextToken;

        // Log : action 'login', ressource = l'utilisateur lui-même
        $this->logger->log($request, 'login', $user->id, 'login', $user);

        return response()->json([
            'message' => 'Connexion réussie.',
            'user'    => new UserResource($user->load('roles', 'permissions')),
            'token'   => $token,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DÉCONNEXION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Logout
     * ──────
     * Trois modes via le champ 'mode' du body :
     *
     *  ┌─────────────┬──────────────────────────────────────────────────────┐
     *  │ mode        │ Comportement                                         │
     *  ├─────────────┼──────────────────────────────────────────────────────┤
     *  │ current     │ Révoque uniquement le token actuel (défaut)          │
     *  │ specific    │ Révoque un token précis (token_id requis)            │
     *  │ all         │ Révoque tous les tokens (déconnexion totale)         │
     *  └─────────────┴──────────────────────────────────────────────────────┘
     *
     * Body : { mode?: 'current'|'specific'|'all', token_id?: int }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->validate([
            'mode'     => 'sometimes|in:current,specific,all',
            'token_id' => 'required_if:mode,specific|integer',
        ]);

        $user = $request->user();
        $mode = $request->input('mode', 'current');

        switch ($mode) {

            case 'specific':
                $tokenId = $request->input('token_id');
                $token   = $user->tokens()->find($tokenId);

                if (! $token) {
                    return response()->json(['message' => 'Session introuvable.'], 404);
                }

                if ($token->tokenable_id !== $user->id) {
                    return response()->json(['message' => 'Action non autorisée.'], 403);
                }

                $token->delete();

                $this->logger->log($request, 'logout', $user->id, 'logout', $user, [
                    'mode'     => 'specific',
                    'token_id' => $tokenId,
                ]);

                return response()->json(['message' => 'Session spécifique révoquée.']);

            case 'all':
                $count = $user->tokens()->count();
                $user->tokens()->delete();

                $this->logger->log($request, 'logout', $user->id, 'logout', $user, [
                    'mode'            => 'all',
                    'sessions_closed' => $count,
                ]);

                return response()->json([
                    'message'         => 'Toutes les sessions ont été révoquées.',
                    'sessions_closed' => $count,
                ]);

            default: // 'current'
                $user->currentAccessToken()->delete();

                $this->logger->log($request, 'logout', $user->id, 'logout', $user, [
                    'mode' => 'current',
                ]);

                return response()->json(['message' => 'Déconnecté avec succès.']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SESSIONS ACTIVES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lister tous les tokens actifs de l'utilisateur connecté.
     * Permet d'afficher "Appareils connectés" dans l'UI.
     *
     * GET /api/auth/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $sessions = $request->user()
            ->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id'           => $token->id,
                'name'         => $token->name,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at'   => $token->created_at->toISOString(),
                'is_current'   => $token->id === $currentTokenId,
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILISATEUR CONNECTÉ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourner le profil complet de l'utilisateur connecté.
     *
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(
            new UserResource($request->user()->load('roles', 'permissions'))
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    private function assignDefaultRole(mixed $user): void
    {
        $defaultRole = config('auth-kit.registration.default_role');
        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }
    }
}
