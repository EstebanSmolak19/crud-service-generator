<?php

declare(strict_types=1);

namespace EstebanSmolak19\CrudServiceGenerator\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    protected array $fillable;

    public function __construct($resource, array $fillable = [])
    {
        parent::__construct($resource);
        $this->fillable = $fillable;
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];
        // Si aucun champ n'est défini, on peut retourner tous les attributs du modèle
        $columns = !empty($this->fillable) ? $this->fillable : array_keys($this->resource->getAttributes());

        foreach ($columns as $column) {
            $data[$column] = $this->{$column}; // On récupère dynamiquement la valeur
        }

        return $data;
    }
}