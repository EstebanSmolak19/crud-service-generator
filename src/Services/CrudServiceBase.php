<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Resources\BaseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

abstract class CrudServiceBase
{
    protected Model $model;
    protected string $ressource = BaseResource::class;
    protected array $fillable = []; // Overide dans l'enfant.

    /**
     * Le constructeur de la classe de base
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function responseFormat(mixed $data): mixed
    {
        // Le Builder
        if ($data instanceof Builder) {
            // On va chercher dans l'URL si ?'per_page' = existe
            $perPage = request()->query(config('crud-service-generator.pagination.param_name', 'per_page'));
            $default = config('crud-service-generator.pagination.default_per_page', 5);
            $finalPerPage = $perPage ?: $default;

            if ($finalPerPage) {
                $max = config('crud-service-generator.pagination.max_per_page', 100);
                // On transforme le Builder en Paginator
                $data = $data->paginate(min((int)$finalPerPage, $max));
            } else {
                // Sinon on transforme le Builder en Collection simple
                $data = $data->get();
            }
        }

        // CAS 1 : Gestion de la Pagination (Si c'est devenu un Paginator)
        if ($data instanceof LengthAwarePaginator) {
            return $data->setCollection(
                $data->getCollection()->map(function($item) {
                    return new $this->ressource($item, $this->getResourceFields());
                })
            );
        }

        // CAS 2 : Collections classiques
        if ($data instanceof EloquentCollection || $data instanceof SupportCollection) {
            return $data->map(function($item) {
                return new $this->ressource($item, $this->getResourceFields());
            });
        }

        // CAS 3 : Objet unique
        return new $this->ressource($data, $this->getResourceFields());
    }

    /**
     * Récupère tous les éléments
     */
    public function all(): mixed
    {
        return $this->model->query();
    }

    /**
     * Récupère un élément par son identifiant
     */
    public function find(mixed $id): mixed
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Créer un élément
     */
    public function create(array $data): mixed
    {
        return $this->model->create($data);
    }

    /**
     * Met à jour un élément
     */
    public function update(mixed $id, array $data): mixed
    {
        $record = $this->model->findOrFail($id);
        $record->update($data);

        return $record;
    }

    /**
     * Supprime un élément (Renommé pour correspondre au contrôleur destroy)
     */
    public function destroy(mixed $id): bool
    {
        return (bool) $this->find($id)->delete();
    }

    public function getResourceFields(): array
    {
        return $this->fillable;
    }
}