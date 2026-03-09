<?php

namespace S3Tech\AuthKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use S3Tech\AuthKit\Http\Requests\UpdateProfileRequest;
use S3Tech\AuthKit\Http\Requests\UpdatePasswordRequest;
use S3Tech\AuthKit\Http\Requests\AdminUpdateUserProfileRequest;
use S3Tech\AuthKit\Http\Resources\UserResource;
use S3Tech\AuthKit\Services\ActivityLogService;

/**
 * ProfileController
 *
 * Gère :
 *   - La modification du profil de l'utilisateur connecté (PUT /profile)
 *   - Le changement de mot de passe de l'utilisateur connecté (PUT /profile/password)
 *   - La modification du profil d'un autre utilisateur par un admin (PUT /users/{user})
 *
 * Règles métier importantes :
 *   • Seuls les champs déclarés dans config('auth-kit.profile.editable_fields') sont modifiables.
 *   • Tous les champs sont optionnels (comportement PATCH-like malgré la méthode PUT).
 *   • Le statut ('status', 'is_active', etc.) N'EST PAS modifiable par l'utilisateur lui-même.
 *   • Le mot de passe ne peut être modifié que via PUT /profile/password (par l'utilisateur lui-même).
 *   • Un admin habilité (permission 'update-user') peut modifier le profil d'un autre utilisateur
 *     MAIS ne peut pas modifier son mot de passe.
 */
class ProfileController extends Controller
{
    public function __construct(protected ActivityLogService $logger) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PROFIL DE L'UTILISATEUR CONNECTÉ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mettre à jour son propre profil.
     *
     * Seuls les champs listés dans config('auth-kit.profile.editable_fields') sont acceptés.
     * Tous sont optionnels : seuls les champs présents dans le body seront mis à jour.
     *
     * PUT /api/profile
     * Body (tous optionnels) : { name?, email? }
     *   → Les champs disponibles dépendent de 'auth-kit.profile.editable_fields'
     */
    public function updateOwnProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        // Ne mettre à jour que les champs validés et présents dans la requête
        $data = $this->filterEditableFields($request->validated());

        if (empty($data)) {
            return response()->json(['message' => 'Aucun champ à mettre à jour.'], 422);
        }

        $user->update($data);

        $this->logger->log($request, 'profile_updated', $user->id, 'update', $user, [
            'updated_fields' => array_keys($data),
        ]);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user'    => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Changer son propre mot de passe.
     *
     * Requiert l'ancien mot de passe pour confirmation d'identité.
     * Après le changement, tous les tokens sont révoqués → reconnexion obligatoire.
     *
     * PUT /api/profile/password
     * Body : { current_password, new_password, new_password_confirmation }
     */
    public function updateOwnPassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'ancien mot de passe est correct
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors'  => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->update([
            'password'              => Hash::make($request->new_password),
            'must_change_password'  => false, // Réinitialiser le flag si présent
        ]);

        // Révoquer tous les tokens → l'utilisateur doit se reconnecter
        $user->tokens()->delete();

        $this->logger->log($request, 'password_changed', $user->id, 'update', $user);

        return response()->json([
            'message' => 'Mot de passe modifié. Veuillez vous reconnecter.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODIFICATION PAR UN ADMIN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Modifier le profil d'un autre utilisateur (action admin).
     *
     * ⚠️  RÈGLE MÉTIER : Le mot de passe ne peut PAS être modifié par cette route.
     *     Un admin ne peut pas changer le mot de passe d'un autre utilisateur.
     *     Le champ 'password' est explicitement rejeté même s'il est envoyé.
     *
     * Requiert la permission 'update-user' (vérifié dans les routes).
     *
     * PUT /api/users/{user}
     * Body (tous optionnels) : { name?, email? }
     */
    public function updateUserProfile(AdminUpdateUserProfileRequest $request, mixed $user): JsonResponse
    {
        // Résoudre le modèle User dynamiquement (le type-hint serait lié à App\Models\User)
        $userModel  = config('auth-kit.user_model');
        $targetUser = $userModel::findOrFail($user);

        // Filtrer les champs éditables — le password est exclu à la source (voir Request)
        $data = $this->filterEditableFields($request->validated());

        if (empty($data)) {
            return response()->json(['message' => 'Aucun champ à mettre à jour.'], 422);
        }

        $targetUser->update($data);

        $this->logger->log($request, 'profile_updated', $request->user()->id, 'update', $targetUser, [
            'updated_fields' => array_keys($data),
        ]);

        return response()->json([
            'message' => 'Profil utilisateur mis à jour.',
            'user'    => new UserResource($targetUser->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filtrer les données validées pour ne garder que les champs éditables
     * déclarés dans la configuration.
     * Garantit qu'aucun champ non autorisé (status, role, password…)
     * ne peut être mis à jour, même si la validation laissait passer quelque chose.
     */
    private function filterEditableFields(array $validated): array
    {
        $allowed = config('auth-kit.profile.editable_fields', ['name', 'email']);

        return array_intersect_key($validated, array_flip($allowed));
    }
}
