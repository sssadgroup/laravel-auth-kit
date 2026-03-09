<?php

namespace S3Tech\AuthKit\Services;

use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use S3Tech\AuthKit\Models\ActivityLog;

/**
 * ActivityLogService
 *
 * Service central pour journaliser toutes les actions des utilisateurs.
 *
 * Chaque entrée de log capture :
 *   ┌─────────────────┬──────────────────────────────────────────────────────────┐
 *   │ Champ           │ Description                                              │
 *   ├─────────────────┼──────────────────────────────────────────────────────────┤
 *   │ event           │ Identifiant technique : 'login', 'profile_updated'…      │
 *   │ action          │ Verbe CRUD : 'create'|'read'|'update'|'delete'|          │
 *   │                 │              'login'|'logout'|'auth'                     │
 *   │ subject_type    │ Classe Eloquent cible : 'App\Models\User'                │
 *   │ subject_id      │ ID de la ressource impactée                              │
 *   │ ip_address      │ IP réelle (gestion proxy/Cloudflare)                     │
 *   │ device_*        │ Type, navigateur, OS, User-Agent brut                    │
 *   │ country/city…   │ Géolocalisation via stevebauman/location                 │
 *   │ metadata        │ Données libres spécifiques à l'événement                 │
 *   └─────────────────┴──────────────────────────────────────────────────────────┘
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * EXEMPLES D'UTILISATION
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * // Connexion (pas de ressource cible)
 * $this->logger->log($request, 'login', $user->id, 'login');
 *
 * // Inscription (l'utilisateur créé EST la ressource)
 * $this->logger->log($request, 'register', $user->id, 'create', $user);
 *
 * // Modification de profil (ressource = l'utilisateur modifié)
 * $this->logger->log($request, 'profile_updated', $actor->id, 'update', $targetUser,
 *     ['updated_fields' => ['email']]
 * );
 *
 * // Tentative de connexion échouée (pas d'utilisateur connu)
 * $this->logger->log($request, 'login_failed', null, 'auth', null,
 *     ['email' => 'tentative@email.com']
 * );
 *
 * // Attribution d'un rôle (ressource = l'utilisateur qui reçoit le rôle)
 * $this->logger->log($request, 'role_assigned', $admin->id, 'update', $targetUser,
 *     ['role' => 'manager']
 * );
 */
class ActivityLogService
{
    /**
     * Enregistrer une entrée dans les logs d'activité.
     *
     * @param  Request     $request   Requête HTTP courante (IP + User-Agent)
     * @param  string      $event     Identifiant de l'événement ('login', 'profile_updated'…)
     * @param  int|null    $userId    ID de l'utilisateur qui agit (null si inconnu)
     * @param  string      $action    Verbe CRUD : 'create'|'read'|'update'|'delete'|'login'|'logout'|'auth'
     * @param  object|null $subject   Instance Eloquent de la ressource impactée (ex: $user, $post)
     * @param  array       $metadata  Données libres supplémentaires
     */
    public function log(
        Request $request,
        string  $event,
        ?int    $userId,
        string  $action   = 'auth',
        ?object $subject  = null,
        array   $metadata = []
    ): void {
        // ── Vérifications globales ────────────────────────────────────────────

        if (! config('auth-kit.activity_log.enabled', true)) {
            return;
        }

        if (! config("auth-kit.activity_log.log_events.{$event}", true)) {
            return;
        }

        // ── Collecte du contexte ──────────────────────────────────────────────

        $ip        = $this->resolveIp($request);
        $userAgent = $request->userAgent() ?? 'unknown';
        $device    = $this->parseUserAgent($userAgent);
        $location  = $this->resolveLocation($ip);

        // ── Résolution de la ressource cible (polymorphisme) ──────────────────

        // subject_type = nom de la classe Eloquent (ex: App\Models\User)
        // subject_id   = clé primaire de l'instance
        $subjectType = null;
        $subjectId   = null;

        if ($subject !== null) {
            // Respecter la morphMap Eloquent si définie dans l'application
            $subjectType = method_exists($subject, 'getMorphClass')
                ? $subject->getMorphClass()
                : get_class($subject);

            $subjectId = method_exists($subject, 'getKey')
                ? $subject->getKey()
                : ($subject->id ?? null);
        }

        // ── Persistance ───────────────────────────────────────────────────────

        ActivityLog::create([
            // Acteur
            'user_id'      => $userId,
            // Événement + action sémantique
            'event'        => $event,
            'action'       => $action,
            // Ressource cible
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            // Réseau
            'ip_address'   => $ip,
            // Appareil
            'device_type'  => $device['type'],
            'device_name'  => $device['name'],
            'browser'      => $device['browser'],
            'os'           => $device['os'],
            'user_agent'   => $userAgent,
            // Géolocalisation
            'country'      => $location['country'],
            'country_code' => $location['country_code'],
            'region'       => $location['region'],
            'city'         => $location['city'],
            'latitude'     => $location['latitude'],
            'longitude'    => $location['longitude'],
            // Métadonnées libres
            'metadata'     => ! empty($metadata) ? $metadata : null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — Contexte réseau
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Résoudre l'adresse IP réelle du client.
     * Tient compte des reverse-proxies courants dans l'ordre de priorité.
     */
    private function resolveIp(Request $request): string
    {
        $headers = [
            'CF-Connecting-IP',  // Cloudflare (IP client directe, la plus fiable)
            'X-Real-IP',         // Nginx proxy
            'X-Forwarded-For',   // Standard proxy — peut contenir "ip1, ip2, ip3"
        ];

        foreach ($headers as $header) {
            $value = $request->header($header);
            if ($value) {
                // Prendre la première IP de la chaîne (la plus proche du client)
                return trim(explode(',', $value)[0]);
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — Contexte appareil
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Analyser le User-Agent pour extraire type, navigateur, OS et libellé.
     * Implémentation légère sans dépendance externe.
     *
     * @return array{type: string, name: string, browser: string, os: string}
     */
    private function parseUserAgent(string $ua): array
    {
        $ua = strtolower($ua);

        // ── Type d'appareil ───────────────────────────────────
        $type = 'desktop';
        if (str_contains($ua, 'mobile') || (str_contains($ua, 'android') && str_contains($ua, 'mobile'))) {
            $type = 'mobile';
        } elseif (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            $type = 'tablet';
        } elseif (str_contains($ua, 'postman') || str_contains($ua, 'insomnia') || str_contains($ua, 'curl')) {
            $type = 'api_client';
        }

        // ── Navigateur (ordre important : Edge avant Chrome, Opera avant Chrome) ──
        $browser  = 'Unknown';
        $browsers = [
            'edg'      => 'Microsoft Edge',
            'opr'      => 'Opera',
            'chrome'   => 'Chrome',
            'firefox'  => 'Firefox',
            'safari'   => 'Safari',
            'msie'     => 'Internet Explorer',
            'trident'  => 'Internet Explorer',
            'postman'  => 'Postman',
            'insomnia' => 'Insomnia',
            'curl'     => 'cURL',
        ];

        foreach ($browsers as $key => $name) {
            if (str_contains($ua, $key)) {
                $browser = $name;
                break;
            }
        }

        // ── Système d'exploitation ────────────────────────────
        $os      = 'Unknown';
        $systems = [
            'windows nt 10'  => 'Windows 10/11',
            'windows nt 6.3' => 'Windows 8.1',
            'windows nt 6.1' => 'Windows 7',
            'windows'        => 'Windows',
            'iphone os'      => 'iOS',
            'ipad'           => 'iPadOS',
            'android'        => 'Android',
            'mac os x'       => 'macOS',
            'linux'          => 'Linux',
            'ubuntu'         => 'Ubuntu',
        ];

        foreach ($systems as $key => $name) {
            if (str_contains($ua, $key)) {
                $os = $name;
                break;
            }
        }

        $name = "{$os} — {$browser}";

        return compact('type', 'name', 'browser', 'os');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — Géolocalisation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Résoudre la géolocalisation via stevebauman/location (sans clé API).
     * Retourne des nulls pour les IPs privées/locales ou en cas d'erreur.
     * Ne bloque jamais l'action principale si la géoloc échoue.
     *
     * @return array{country:string|null, country_code:string|null, region:string|null, city:string|null, latitude:float|null, longitude:float|null}
     */
    private function resolveLocation(string $ip): array
    {
        $empty = [
            'country'      => null,
            'country_code' => null,
            'region'       => null,
            'city'         => null,
            'latitude'     => null,
            'longitude'    => null,
        ];

        // Ignorer les IPs privées et réservées (localhost, LAN, etc.)
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        try {
            $position = Location::get($ip);

            if (! $position) {
                return $empty;
            }

            return [
                'country'      => $position->countryName ?? null,
                'country_code' => $position->countryCode ?? null,
                'region'       => $position->regionName  ?? null,
                'city'         => $position->cityName    ?? null,
                'latitude'     => isset($position->latitude)  ? (float) $position->latitude  : null,
                'longitude'    => isset($position->longitude) ? (float) $position->longitude : null,
            ];
        } catch (\Throwable) {
            // Ne jamais faire échouer une action à cause de la géolocalisation
            return $empty;
        }
    }
}
