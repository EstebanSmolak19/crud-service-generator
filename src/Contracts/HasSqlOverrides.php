<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

/**
 * Ce contrat permet à un service d'indiquer qu'il souhaite
 * déporter une partie de sa logique métier vers la couche SQL.
 */
interface HasSqlOverrides
{
    /**
     * Retourne le nom de la procédure SQL pour la création d'un record.
     * Si null, le package utilisera Eloquent par défaut.
     */
    public function getCreateProcedureName(): ?string;

    /**
     * Retourne le nom de la procédure SQL pour la mise à jour d'un record.
     */
    public function getUpdateProcedureName(): ?string;

    /**
     * Retourne le nom de la vue SQL pour la récupération des données (all/index).
     */
    public function getSqlViewName(): ?string;

    /**
     * Retourne le mapping des codes d'erreurs SQL personnalisés.
     * Exemple : ['ERR_STOCK' => 'Le stock est épuisé']
     */
    public function getSqlErrorMappings(): array;

    /**
     * Retourne le nom de la procédure SQL pour la suppression.
     */
    public function getDeleteProcedureName(): ?string;
}
