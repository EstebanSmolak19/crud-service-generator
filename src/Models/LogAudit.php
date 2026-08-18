<?php

namespace EstebanSmolak19\CrudServiceGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /**
     * Le nom de la table est récupéré dynamiquement depuis la config.
     */
    public function getTable()
    {
        return config('crud-service-generator.database.table_name_log');
    }

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
    ];

    /**
     * Cast automatique des JSON en tableaux PHP.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Relation vers l'utilisateur qui a fait l'action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }

    /**
     * Relation polymorphique vers l'entité auditée (Arret, Ligne, etc.).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
