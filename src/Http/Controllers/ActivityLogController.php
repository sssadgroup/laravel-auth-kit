<?php

namespace S3Tech\AuthKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use S3Tech\AuthKit\Models\ActivityLog;

/**
 * ActivityLogController
 *
 * Expose les logs d'activité en lecture seule.
 * Trois endpoints :
 *   - GET /activity-logs/me          → Propres logs de l'utilisateur connecté
 *   - GET /activity-logs/user/{user} → Logs d'un utilisateur (admin)
 *   - GET /activity-logs             → Tous les logs (admin)
 *
 * Filtres disponibles via query string :
 *   ?event=login          → Filtrer par type d'événement
 *   ?from=2024-01-01      → Depuis une date
 *   ?to=2024-12-31        → Jusqu'à une date
 *   ?per_page=20          → Pagination (défaut : 20)
 */
class ActivityLogController extends Controller
{
    /**
     * Retourner les logs de l'utilisateur connecté.
     * GET /api/activity-logs/me
     */
    public function myLogs(Request $request): JsonResponse
    {
        $logs = $this->buildQuery($request)
            ->where('user_id', $request->user()->id)
            ->paginate($request->integer('per_page', 20));

        return response()->json($logs);
    }

    /**
     * Retourner les logs d'un utilisateur spécifique.
     * GET /api/activity-logs/user/{user}
     * Requiert la permission 'manage-roles'.
     */
    public function userLogs(Request $request, int $userId): JsonResponse
    {
        $logs = $this->buildQuery($request)
            ->where('user_id', $userId)
            ->paginate($request->integer('per_page', 20));

        return response()->json($logs);
    }

    /**
     * Retourner tous les logs (toutes actions, tous utilisateurs).
     * GET /api/activity-logs
     * Requiert la permission 'manage-roles'.
     */
    public function allLogs(Request $request): JsonResponse
    {
        $logs = $this->buildQuery($request)
            ->with('user:id,name,email')
            ->paginate($request->integer('per_page', 20));

        return response()->json($logs);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Construire la requête de base avec les filtres communs.
     */
    private function buildQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = ActivityLog::query()->latest();

        // Filtrer par type d'événement (ex: ?event=login)
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Filtrer par plage de dates
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        return $query;
    }
}
