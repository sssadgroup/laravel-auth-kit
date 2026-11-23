<?php

namespace S3Tech\AuthKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use S3Tech\AuthKit\Services\ActivityLogService;
use S3Tech\AuthKit\Support\ApiResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * RolePermissionController
 *
 * Gère la création, suppression et assignation des rôles et permissions Spatie.
 * Toutes les routes de ce controller sont protégées par la permission 'manage-roles'
 * (définie dans config('auth-kit.permissions.manage_roles')).
 *
 * Le guard utilisé est 'sanctum' pour la compatibilité avec les tokens API.
 */
class RolePermissionController extends Controller
{
    public function __construct(protected ActivityLogService $logger) {}

    // ─────────────────────────────────────────────────────────────────────────
    // RÔLES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lister tous les rôles avec leurs permissions associées.
     * GET /api/roles
     */
    public function listRoles(): JsonResponse
    {
        return response()->json(
            Role::with('permissions')->get()
        );
    }

    /**
     * Créer un nouveau rôle.
     * POST /api/roles
     * Body : { name }
     */
    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'sanctum',
        ]);

        $this->logger->log($request, 'role_assigned', $request->user()->id, 'create', null, [
            'action' => 'role_created',
            'role_name' => $role->name,
        ]);

        return ApiResponse::success($request, [
            'en' => "Role '{$role->name}' created.",
            'fr' => "Rôle '{$role->name}' créé.",
        ], [
            'role' => $role,
        ], 201);
    }

    /**
     * Modifier un rôle.
     * PUT /api/roles/{role}
     * Body : { name }
     */
    public function updateRole(Request $request, $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);

        $request->validate([
            'name' => "required|string|unique:roles,name,{$role->id}",
        ]);

        $oldName = $role->name;

        $role->update([
            'name' => $request->name,
        ]);

        $this->logger->log($request, 'role_updated', $request->user()->id, 'update', $role, [
            'action' => 'role_updated',
            'old_name' => $oldName,
            'new_name' => $role->name,
        ]);

        return ApiResponse::success($request, [
            'en' => "Role '{$oldName}' renamed to '{$role->name}'.",
            'fr' => "Rôle '{$oldName}' modifié en '{$role->name}'.",
        ], [
            'role' => $role,
        ]);
    }

    /**
     * Supprimer un rôle.
     * DELETE /api/roles/{role}
     */
    public function deleteRole(Request $request, $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);

        if ($role->users()->exists()) {
            return ApiResponse::error($request, [
                'en' => "Deletion not allowed: role '{$role->name}' is still assigned to at least one user.",
                'fr' => "Suppression impossible: le rôle '{$role->name}' est encore assigné à au moins un utilisateur.",
            ], [
                'code' => 422,
            ], 422);
        }

        $roleName = $role->name;
        $role->delete();

        $this->logger->log($request, 'role_revoked', $request->user()->id, 'delete', $role, [
            'action' => 'role_deleted',
            'role_name' => $roleName,
        ]);

        return ApiResponse::success($request, [
            'en' => "Role '{$roleName}' deleted.",
            'fr' => "Rôle '{$roleName}' supprimé.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERMISSIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lister toutes les permissions disponibles.
     * GET /api/permissions
     */
    public function listPermissions(): JsonResponse
    {
        return response()->json(Permission::all());
    }

    /**
     * Créer une nouvelle permission.
     * POST /api/permissions
     * Body : { name }
     */
    public function createPermission(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'sanctum',
        ]);

        return ApiResponse::success($request, [
            'en' => "Permission '{$permission->name}' created.",
            'fr' => "Permission '{$permission->name}' créée.",
        ], [
            'permission' => $permission,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RÔLE ↔ PERMISSION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assigner une permission à un rôle.
     * POST /api/roles/{role}/permissions
     * Body : { permission }
     */
    public function assignPermissionToRole(Request $request, $roleId): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $role = Role::findOrFail($roleId);
        $permission = Permission::where('name', $request->permission)
            ->where('guard_name', $role->guard_name)
            ->firstOrFail();

        $role->givePermissionTo($request->permission);

        $this->logger->log($request, 'permission_assigned', $request->user()->id, 'create', $permission, [
            'action' => 'permission_assigned_to_role',
            'role_name' => $role->name,
            'permission' => $request->permission,
        ]);

        return ApiResponse::success($request, [
            'en' => "Permission '{$request->permission}' assigned to role '{$role->fresh()->name}'.",
            'fr' => "Permission '{$request->permission}' assignée au rôle '{$role->fresh()->name}'.",
        ]);
    }

    /**
     * Révoquer une permission d'un rôle.
     * DELETE /api/roles/{role}/permissions/{perm}
     */
    public function revokePermissionFromRole(Request $request, $roleId, string $perm): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::where('name', $perm)
            ->where('guard_name', $role->guard_name)
            ->first();

        if (! $permission) {
            return ApiResponse::error($request, [
                'en' => "Permission '{$perm}' does not exist.",
                'fr' => "La permission '{$perm}' n'existe pas.",
            ], [
                'code' => 404,
            ], 404);
        }

        $role->revokePermissionTo($perm);

        $this->logger->log($request, 'permission_revoked', $request->user()->id, 'delete', $permission, [
            'action' => 'permission_revoked_from_role',
            'role_name' => $role->name,
            'permission' => $perm,
        ]);

        return ApiResponse::success($request, [
            'en' => "Permission '{$perm}' revoked from role '{$role->name}'.",
            'fr' => "Permission '{$perm}' révoquée du rôle '{$role->name}'.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILISATEUR ↔ RÔLE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assigner un rôle à un utilisateur.
     * POST /api/users/{user}/roles
     * Body : { role }
     */
    public function assignRoleToUser(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $userModel = config('auth-kit.user_model');
        $user = $userModel::findOrFail($userId);
        $role = Role::where('name', $request->role)->where('guard_name', 'sanctum')->firstOrFail();

        if ($user->hasRole($role->name)) {
            return ApiResponse::success($request, [
                'en' => "User #{$user->id} already has role '{$role->name}'.",
                'fr' => "L'utilisateur #{$user->id} possède déjà le rôle '{$role->name}'.",
            ]);
        }

        $user->assignRole($role->name);

        $this->logger->log($request, 'role_assigned', $request->user()->id, 'create', $role, [
            'action' => 'role_assigned_to_user',
            'target_user_id' => $user->id,
            'role' => $role->name,
        ]);

        return ApiResponse::success($request, [
            'en' => "Role '{$role->name}' assigned to user #{$user->id}.",
            'fr' => "Rôle '{$role->name}' assigné à l'utilisateur #{$user->id}.",
        ]);
    }

    /**
     * Révoquer un rôle d'un utilisateur.
     * DELETE /api/users/{user}/roles/{role}
     */
    public function revokeRoleFromUser(Request $request, int $userId, string $role): JsonResponse
    {
        $userModel = config('auth-kit.user_model');
        $user = $userModel::findOrFail($userId);
        $roleModel = Role::where('name', $role)->where('guard_name', 'sanctum')->first();

        if (! $roleModel) {
            return ApiResponse::error($request, [
                'en' => "Role '{$role}' does not exist.",
                'fr' => "Le rôle '{$role}' n'existe pas.",
            ], [
                'code' => 404,
            ], 404);
        }

        if (! $user->hasRole($roleModel->name)) {
            return ApiResponse::success($request, [
                'en' => "User #{$user->id} does not have role '{$roleModel->name}'.",
                'fr' => "L'utilisateur #{$user->id} ne possède pas le rôle '{$roleModel->name}'.",
            ]);
        }

        $user->removeRole($roleModel->name);

        $this->logger->log($request, 'role_revoked', $request->user()->id, 'delete', $roleModel, [
            'action' => 'role_revoked_from_user',
            'target_user_id' => $user->id,
            'role' => $roleModel->name,
        ]);

        return ApiResponse::success($request, [
            'en' => "Role '{$roleModel->name}' revoked from user #{$user->id}.",
            'fr' => "Rôle '{$roleModel->name}' révoqué de l'utilisateur #{$user->id}.",
        ]);
    }

    /**
     * Assigner une permission directe à un utilisateur.
     * POST /api/users/{user}/permissions
     * Body : { permission }
     */
    public function assignPermissionToUser(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $userModel = config('auth-kit.user_model');
        $user = $userModel::findOrFail($userId);

        // Éviter le doublon si déjà assignée
        if ($user->hasDirectPermission($request->permission)) {
            return ApiResponse::error($request, [
                'en' => "User already has permission '{$request->permission}'.",
                'fr' => "L'utilisateur possède déjà la permission '{$request->permission}'.",
            ], [
                'code' => 422,
            ], 422);
        }

        $user->givePermissionTo($request->permission);

        $permission = Permission::where('name', $request->permission)
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $this->logger->log($request, 'role_assigned', $request->user()->id, 'create', $permission, [
            'action' => 'permission_assigned_to_user',
            'target_user_id' => $user->id,
            'permission' => $request->permission,
        ]);

        return ApiResponse::success($request, [
            'en' => "Permission '{$request->permission}' assigned to user #{$user->id}.",
            'fr' => "Permission '{$request->permission}' assignée à l'utilisateur #{$user->id}.",
        ]);
    }

    /**
     * Révoquer une permission directe d'un utilisateur.
     * DELETE /api/users/{user}/permissions/{permission}
     */
    public function revokePermissionFromUser(Request $request, int $userId, string $permission): JsonResponse
    {
        $userModel = config('auth-kit.user_model');
        $user = $userModel::findOrFail($userId);

        // Vérifier que la permission existe
        if (! Permission::where('name', $permission)->where('guard_name', 'sanctum')->exists()) {
            return ApiResponse::error($request, [
                'en' => "Permission '{$permission}' does not exist.",
                'fr' => "La permission '{$permission}' n'existe pas.",
            ], [
                'code' => 404,
            ], 404);
        }

        // Vérifier qu'elle est bien assignée directement
        if (! $user->hasDirectPermission($permission)) {
            return ApiResponse::error($request, [
                'en' => "User does not have direct permission '{$permission}'.",
                'fr' => "L'utilisateur ne possède pas la permission directe '{$permission}'.",
            ], [
                'code' => 422,
            ], 422);
        }

        $user->revokePermissionTo($permission);

        $permissionModel = Permission::where('name', $permission)
            ->where('guard_name', 'sanctum')
            ->firstOrFail();

        $this->logger->log($request, 'role_revoked', $request->user()->id, 'delete', $permissionModel, [
            'action' => 'permission_revoked_from_user',
            'target_user_id' => $user->id,
            'permission' => $permission,
        ]);

        return ApiResponse::success($request, [
            'en' => "Permission '{$permission}' revoked from user #{$user->id}.",
            'fr' => "Permission '{$permission}' révoquée de l'utilisateur #{$user->id}.",
        ]);
    }
}
