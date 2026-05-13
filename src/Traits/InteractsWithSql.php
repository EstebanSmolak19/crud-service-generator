<?php

namespace EstebanSmolak19\CrudServiceGenerator\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait InteractsWithSql
{
    public function getCreateProcedureName(): ?string
    {
        return $this->sqlCreateProcedure;
    }

    public function getUpdateProcedureName(): ?string
    {
        return $this->sqlUpdateProcedure;
    }

    public function getSqlViewName(): ?string
    {
        return $this->sqlViewName;
    }

    public function getSqlErrorMappings(): array
    {
        return $this->sqlErrorMappings;
    }

    public function getDeleteProcedureName(): ?string
    {
        return $this->sqlDeleteProcedure;
    }

    /**
     * Vérifie si la vue SQL configurée existe réellement en BDD.
     */
    public function viewExists(?string $name): bool
    {
        if (!$name) return false;

        return Cache::remember("sql_view_exists_{$name}", 3600, function() use ($name) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            $query = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = ?
                AND table_name = ?
                AND table_type = 'VIEW'
            ", [$database, $name]);

            return $query[0]->count > 0;
        });
    }

    /**
     * Idem pour vérifier si une procédure existe.
     */
    public function procedureExists(?string $name): bool
    {
        if (!$name) return false;

        return Cache::remember("sql_proc_exists_{$name}", 3600, function() use ($name) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            $query = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.routines
                WHERE routine_schema = ?
                AND routine_name = ?
                AND routine_type = 'PROCEDURE'
            ", [$database, $name]);

            return $query[0]->count > 0;
        });
    }

    /**
     * Vérifie si une colonne spécifique existe dans la vue ou la table.
     */
    public function columnExists(string $table, string $column): bool
    {
        return Cache::remember("sql_col_exists_{$table}_{$column}", 3600, function() use ($table, $column) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            $query = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.columns
                WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?
            ", [$database, $table, $column]);

            return $query[0]->count > 0;
        });
    }

    /**
     * Exécute la procédure SQL de création et retourne le modèle fraîchement créé.
     */
    public function executeCreateProcedure(string $procedure, array $data): mixed
    {
        //Exécution
        $result = DB::select("CALL {$procedure}(" . implode(',', array_fill(0, count($data), '?')) . ")", array_values($data));

        //On tente de récupérer l'ID depuis le résultat du SELECT de la procédure
        $newId = $result[0]->id ?? null;

        //FALLBACK : Si la procédure n'a rien renvoyé (SELECT id manquant)
        if (!$newId) {
            // On demande à la connexion PDO le dernier ID inséré durant cette session
            $newId = DB::getPdo()->lastInsertId();
        }

        //Si après le fallback on a enfin un ID
        if ($newId && $newId > 0) {
            return $this->find($newId);
        }

        //Si vraiment on ne trouve rien (ex: pas d'auto-incrément)
        // On transforme manuellement le tableau en instance de modèle pour ne pas faire planter l'API
        return $this->model->newInstance($data, true);
    }

    /**
     * Exécute la procédure SQL de mise à jour.
     */
    public function executeUpdateProcedure(string $procedure, mixed $id, array $data): void
    {
        // On crée un tableau plat : l'ID en premier, puis les valeurs du tableau data
        $params = array_merge([$id], array_values($data));

        // On génère dynamiquement les points d'interrogation (?, ?, ?, ...)
        $placeholders = implode(',', array_fill(0, count($params), '?'));

        // Exécution de l'appel
        DB::select("CALL {$procedure}({$placeholders})", $params);
    }
}