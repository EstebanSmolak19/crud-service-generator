<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

abstract class CrudServiceBase
{
    protected Model $model;
    protected string $ressource;

    /**
     * Le constructeur de la classe de base
     */
    public function __construct(Model $model, string $ressource)
    {
        $this->model = $model;
        $this->ressource = $ressource;
    }

    private function responseFormat(mixed $data): mixed
    {
        return $data instanceof Collection
            ? $this->ressource::collection($data)
            : new $this->ressource($data);
    }

    /**
     * Récupère tous les éléments (Anciennement getAllAsync)
     */
    public function all(): Collection
    {
        return $this->responseFormat($this->model->all());
    }

    /**
     * Récupère un élément par son identifiant
     */
    public function find(mixed $id): Model
    {
        return $this->responseFormat($this->model->findOrFail($id));
    }

    /**
     * Créer un élément
     */
    public function create(array $data): Model
    {
        $record = $this->model->create($data);
        return $this->responseFormat($record);
    }

    /**
     * Met à jour un élément
     */
    public function update(mixed $id, array $data): Model
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
}