<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

interface IModelService
{
    /**
     * Recherches toutes les bases de la base de donnée.
     */
    public function searchTable(): array;

    /**
     * Créer les models en fonction d'une liste de table
     */
    public function generateModels(): void;

    /**
     * Récupère les champs d'une table
     * @param string $table la table
     * @return array tous les champs de la table.
     */
    public function getTableColumns(string $table): array;

    /**
     * Récupère les relations d'une table.
     * @param string $table une table
     * @return array toutes les relations de la table.
     */
    public function getTableForeignKeys(string $table): array;
}