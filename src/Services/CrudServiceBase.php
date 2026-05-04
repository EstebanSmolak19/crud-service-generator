<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Resources\BaseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    private function responseFormat(mixed $data): mixed
    {
        if ($data instanceof EloquentCollection || $data instanceof SupportCollection) {
            return $data->map(function($item) {
                return new $this->ressource($item, $this->getResourceFields());
            });
        }

        return new $this->ressource($data, $this->getResourceFields());
    }

    /**
     * Récupère tous les éléments
     */
    public function all(): mixed
    {
        return $this->responseFormat($this->model->all());
    }

    /**
     * Récupère un élément par son identifiant
     */
    public function find(mixed $id): mixed
    {
        return $this->responseFormat($this->model->findOrFail($id));
    }

    /**
     * Créer un élément
     */
    public function create(array $data): mixed
    {
        $record = $this->model->create($data);
        return $this->responseFormat($record);
    }

    /**
     * Met à jour un élément
     */
    public function update(mixed $id, array $data): mixed
    {
        $record = $this->find($id);
        $record->update($data);

        return $this->responseFormat($record);
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