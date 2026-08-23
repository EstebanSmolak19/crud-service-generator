<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\HasSqlOverrides;
use EstebanSmolak19\CrudServiceGenerator\Traits\HasCrudConfiguration;
use EstebanSmolak19\CrudServiceGenerator\Traits\InteractsWithSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use ReflectionClass;

abstract class CrudServiceBase
{
    use HasCrudConfiguration, InteractsWithSql, Macroable;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $reflection = new ReflectionClass($this);
        if ($reflection->getProperty('fillable')->getDeclaringClass()->getName() === self::class) {
            throw new LogicException(
                sprintf("Tu as oublié de déclarer 'protected array \$fillable = [];' dans %s.
                Mets un tableau vide pour tout afficher par défaut.", static::class)
            );
        }
    }

    public function writeLog(string $event, $model, ?array $old = null, ?array $new = null): void
    {
        if (! $this->audit) {
            return;
        }
        $tableName = config('crud-service-generator.database.table_name_log');
        DB::table($tableName)->insert([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function applySorting(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        foreach ($this->orderBy as $column => $direction) {
            // On s'assure que la direction est valide (ASC ou DESC)
            $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
            $query->orderBy($column, $dir);
        }

        return $query;
    }

    public function responseFormat(mixed $data): mixed
    {
        return (new ResponseFormatter($data, fn ($item) => $this->mapToResource($item), $this->getPerPage()))->format();
    }

    public function all(): mixed
    {
        if ($this instanceof HasSqlOverrides && $view = $this->getSqlViewName()) {
            if ($this->viewExists($view)) {
                // On renvoie le QueryBuilder pour permettre la pagination
                return DB::table($view);
            }
        }

        return $this->model->query();
    }

    public function find(mixed $id): mixed
    {
        if ($this instanceof HasSqlOverrides && $view = $this->getSqlViewName()) {
            if ($this->viewExists($view)) {

                // Si la colonne $this->primary_key n'existe pas dans la vue SQL.
                if (! $this->columnExists($view, $this->primaryKey)) {
                    throw new LogicException(
                        sprintf(
                            "Structure SQL invalide : La vue '%s' doit contenir la colonne '%s' pour permettre la récupération par ID.
                            Vérifiez la définition de votre vue ou modifiez \$primaryKey dans votre service.",
                            $view,
                            $this->primaryKey
                        )
                    );
                }

                $record = DB::table($view)->where($this->primaryKey, $id)->first();
                if (! $record) {
                    abort(404, 'Enregistrement introuvable dans la vue.');
                }

                return $this->mapToResource($record);
            }
        }

        return $this->model->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        // On vérifie si on doit utiliser une procédure SQL
        if ($this instanceof HasSqlOverrides && $procedure = $this->getCreateProcedureName()) {
            if ($this->procedureExists($procedure)) {
                return $this->executeCreateProcedure($procedure, $data);
            }
        }

        // Sinon, on reste sur le comportement Eloquent classique
        $record = $this->model->create($data);

        if ($this->audit) {
            // On log l'événement 'create' avec les données de l'objet créé
            $this->writeLog('create', $record, null, $record->toArray());
        }

        return $record;
    }

    public function update(mixed $id, array $data)
    {
        // On récupère toujours le record initial
        $record = $this->model->findOrFail($id);
        $oldValues = $this->audit ? $record->getRawOriginal() : null;

        // Procédure SQL ou Eloquent
        if ($this instanceof HasSqlOverrides && $procedure = $this->getUpdateProcedureName()) {
            if ($this->procedureExists($procedure)) {
                $this->executeUpdateProcedure($procedure, $id, $data);
                // On rafraîchit le modèle depuis la BDD car Eloquent
                // ne sait pas ce que la procédure a modifié.
                $record->refresh();
            }
        } else {
            $record->update($data);
        }

        // Gestion de l'Audit
        if ($this->audit) {
            // Eloquent remplit getChanges() automatiquement après update() ou refresh()
            $newValues = $record->getChanges();

            if (! empty($newValues)) {
                $relevantOld = array_intersect_key($oldValues, $newValues);
                $this->writeLog('update', $record, $relevantOld, $newValues);
            }
        }

        return $record;
    }

    public function destroy(mixed $id): bool
    {
        $record = $this->model->findOrFail($id);

        // Audit avant suppression
        if ($this->audit) {
            $this->writeLog('delete', $record, $record->toArray(), null);
        }

        // Détection Procédure SQL
        if ($this instanceof HasSqlOverrides && $procedure = $this->getDeleteProcedureName()) {
            if ($this->procedureExists($procedure)) {
                DB::select("CALL {$procedure}(?)", [$id]);

                return true;
            }
        }

        // Sinon Fallback Eloquent
        return $record->delete();
    }

    /**
     * Met à jour plusieurs enregistrements simultanément.
     *
     * @param  array  $ids  Liste des clés primaires
     * @param  array  $data  Les données à mettre à jour
     * @return int Le nombre de lignes affectées
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $count = 0;
        $hasUpdateProcedure = $this instanceof HasSqlOverrides
            && $this->getUpdateProcedureName()
            && $this->procedureExists($this->getUpdateProcedureName());

        if ($this->audit || $hasUpdateProcedure) {
            $records = $this->model->whereIn($this->primaryKey ?? 'id', $ids)->get();
            foreach ($records as $record) {
                $this->update($record->getKey(), $data);
                $count++;
            }

            return $count;
        }

        return $this->model->whereIn($this->primaryKey ?? 'id', $ids)->update($data);
    }

    /**
     * Supprime plusieurs enregistrements simultanément.
     *
     * @param  array  $ids  Liste des clés primaires
     * @return int Le nombre de lignes supprimées
     */
    public function bulkDelete(array $ids): int
    {
        $count = 0;

        $hasDeleteProcedure = $this instanceof HasSqlOverrides
            && $this->getDeleteProcedureName()
            && $this->procedureExists($this->getDeleteProcedureName());

        // Si audit ou procédure SQL, on délègue à ta méthode destroy() ligne par ligne
        if ($this->audit || $hasDeleteProcedure) {
            $records = $this->model->whereIn($this->primaryKey ?? 'id', $ids)->get();
            foreach ($records as $record) {
                $this->destroy($record->getKey());
                $count++;
            }

            return $count;
        }

        return $this->model->whereIn($this->primaryKey ?? 'id', $ids)->delete();
    }

    /**
     * Helper pour transformer un item (Model ou stdClass) en Resource
     */
    protected function mapToResource(mixed $item): mixed
    {
        // Si l'item vient d'un QueryBuilder (vue SQL), on l'habille en Modèle
        if (! ($item instanceof Model)) {
            $item = $this->model->newInstance((array) $item, true);
            $item->fromSqlView = true;
            $item->makeHidden('fromSqlView');
        }

        return new $this->ressource($item, $this->getResourceFields());
    }

    public function getResourceFields(): array
    {
        return $this->fillable;
    }

    public function getRessource(): string
    {
        return $this->ressource;
    }

    public function getPerPage(): int
    {
        $queryParam = request()->query(config('crud-service-generator.pagination.param_name', 'per_page'));

        if ($queryParam) {
            return (int) $queryParam;
        }

        if (isset($this->perPage)) {
            return $this->perPage;
        }

        return (int) config('crud-service-generator.pagination.default_per_page', 15);
    }

    /**
     * Gère les permissions des services CRUD (all, find, create, update, destroy)
     * Par défaut, tous est en public.
     */
    public function permissions(): array
    {
        return [];
    }
}
