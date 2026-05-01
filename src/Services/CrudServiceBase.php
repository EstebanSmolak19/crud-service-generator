<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

abstract class CrudServiceBase
{
    protected Model $model;

    /**
     * Le constructeur de la classe de base
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Récupère tous les éléments (Anciennement getAllAsync)
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * Récupère un élément par son identifiant
     */
    public function getById(mixed $id): Model
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Créer un élément
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Met à jour un élément
     */
    public function update(mixed $id, array $data): Model
    {
        $record = $this->getById($id);
        $record->update($data);

        return $record;
    }

    /**
     * Supprime un élément (Renommé pour correspondre au contrôleur destroy)
     */
    public function destroy(mixed $id): bool
    {
        $record = $this->getById($id);

        return $record->delete();
    }
}