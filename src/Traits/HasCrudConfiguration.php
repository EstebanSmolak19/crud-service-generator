<?php

namespace EstebanSmolak19\CrudServiceGenerator\Traits;

use EstebanSmolak19\CrudServiceGenerator\Resources\BaseResource;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasCrudConfiguration
 * * Ce trait centralise toutes les options de configuration du service CRUD.
 * Il permet à l'utilisateur de personnaliser le comportement (tri, pagination, audit)
 * directement dans sa classe de service.
 */
trait HasCrudConfiguration
{
    /**
     * L'instance du modèle Eloquent lié au service.
     */
    protected Model $model;

    /**
     * La clé primaire utilisée dans la vue SQL ou le modèle.
     * Par défaut 'id'.
     */
    protected string $primaryKey = 'id';

    /**
     * La classe Resource utilisée pour transformer les données en sortie (API).
     * Par défaut, utilise la BaseResource du package.
     *
     * * @var string|BaseResource
     */
    protected string $ressource = BaseResource::class;

    /**
     * Liste des colonnes autorisées dans la réponse.
     * Si vide, les champs seront extraits dynamiquement.
     */
    protected array $fillable;

    /**
     * Nombre d'éléments affichés par page.
     */
    protected int $perPage;

    /**
     * Active ou désactive le journal d'audit (Logs) pour ce service.
     * Si true, chaque action (create, update, delete) sera enregistrée en base (table du package).
     */
    protected bool $audit = false;

    /**
     * Configuration du tri par défaut des résultats.
     * Format : ['nom_colonne' => 'ASC' ou 'DESC']
     *
     * * @var array<string, string>
     */
    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Nom de la procédure SQL pour la création d'un enregistrement.
     * Si rempli, ignore Eloquent pour le CREATE.
     */
    protected ?string $sqlCreateProcedure = null;

    /**
     * Nom de la procédure SQL pour la mise à jour d'un enregistrement.
     */
    protected ?string $sqlUpdateProcedure = null;

    /**
     * Nom de la procédure SQL pour la suppression (ID en paramètre).
     */
    protected ?string $sqlDeleteProcedure = null;

    /**
     * Nom de la vue SQL utilisée pour les listes et la récupération (Lecture seule).
     */
    protected ?string $sqlViewName = null;

    /**
     * Mapping des codes d'erreurs SQL vers des messages lisibles.
     * Exemple : ['ERR_INSUFFICIENT_FUNDS' => 'Solde insuffisant.']
     *
     * @var array<string, string>
     */
    protected array $sqlErrorMappings = [];
}
