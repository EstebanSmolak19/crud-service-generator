<?php

namespace EstebanSmolak19\CrudServiceGenerator;

use Illuminate\Database\Eloquent\Model;

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
     * Récupère tous les éléments
     */
    public function getAllAsync()
    {
        return $this->model->all();
    }

    /**
     * Récupère un élément par son identifiant
     */
    public function getById(mixed $id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Créer un élément
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Met à jour un élément
     */
    public function update(mixed $id, array $data)
    {
        $record = $this->model->findOrFail($id);
        $record->update($data);

        return $record;
    }

    /**
     * Supprime un élément
     */
    public function delete(mixed $id)
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}