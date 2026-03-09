<?php

namespace S3Tech\AuthKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityLog
 *
 * Enregistre chaque action notable d'un utilisateur avec contexte complet :
 *   - Informations sur l'appareil (type, navigateur, OS, User-Agent brut)
 *   - Adresse IP résolue
 *   - Géolocalisation (pays, région, ville, coordonnées GPS)
 *   - Type d'événement et métadonnées libres (JSON)
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $event             Identifiant technique de l'événement
 * @property string|null $action            Verbe CRUD : 'create'|'read'|'update'|'delete'|'login'|'logout'|'auth'
 * @property string|null $subject_type      Classe Eloquent de la ressource cible (ex: App\Models\User)
 * @property int|null    $subject_id        ID de la ressource cible
 * @property string      $ip_address
 * @property string      $device_type       'desktop' | 'mobile' | 'tablet' | 'api_client'
 * @property string|null $device_name       Libellé lisible (ex: "Windows 10 — Chrome")
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $user_agent
 * @property string|null $country
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $city
 * @property float|null  $latitude
 * @property float|null  $longitude
 * @property array|null  $metadata
 */
class ActivityLog extends Model
{
    protected $table = 'auth_kit_activity_logs';

    // Les logs ne sont jamais modifiés après création
    public $timestamps = true;
    const UPDATED_AT   = null; // Désactiver updated_at pour gagner de la place

    protected $fillable = [
        'user_id',
        'event',
        'action',
        'subject_type',
        'subject_id',
        'ip_address',
        'device_type',
        'device_name',
        'browser',
        'os',
        'user_agent',
        'country',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'metadata',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * L'utilisateur qui a effectué l'action.
     * Peut être null pour les tentatives de connexion avec email inexistant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth-kit.user_model'), 'user_id');
    }
}
