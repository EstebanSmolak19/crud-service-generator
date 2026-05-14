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
        // Détecter si on vient d'une Vue SQL (Priorité n°1)
        if ($this->resource->fromSqlView ?? false) {
            // Si c'est une vue, on récupère tous les attributs sans filtrer par $fillable
            // On convertit en array au cas où c'est une stdClass
            $data = is_array($this->resource)
                ? $this->resource
                : (method_exists($this->resource, 'getAttributes')
                    ? $this->resource->getAttributes()
                    : (array) $this->resource);

            unset($data['fromSqlView']); // On retire la variable 'fromSqlView'

            return $data;
        }

        // Sinon, on suit la logique standard (Fillable)
        if ($this->fillable === ['']) {
            return parent::toArray($request);
        }

        $data = [];
        $columns = ! empty($this->fillable)
            ? $this->fillable
            : array_keys(method_exists($this->resource, 'getAttributes') ? $this->resource->getAttributes() : (array) $this->resource);

        foreach ($columns as $column) {
            $data[$column] = $this->resource->{$column} ?? null;
        }

        return $data;
    }
}
