<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Resources\BaseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use LogicException;
use ReflectionClass;

abstract class CrudServiceBase
{
    protected Model $model;
    protected string $ressource = BaseResource::class;
    protected array $fillable;
    protected int $perPage;

    protected array $orderBy = ['created_at' => 'DESC'];

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

    public function applySorting(Builder $query): Builder
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
        if ($data instanceof Builder) {
            $finalPerPage = $this->getPerPage();

            if ($finalPerPage > 0) {
                $max = config('crud-service-generator.pagination.max_per_page', 100);
                $data = $data->paginate(min((int)$finalPerPage, $max));
            } else {
                $data = $data->get();
            }
        }

        if ($data instanceof LengthAwarePaginator) {
            return $data->setCollection(
                $data->getCollection()->map(function($item) {
                    return new $this->ressource($item, $this->getResourceFields());
                })
            );
        }

        if ($data instanceof EloquentCollection || $data instanceof SupportCollection) {
            return $data->map(function($item) {
                return new $this->ressource($item, $this->getResourceFields());
            });
        }

        return new $this->ressource($data, $this->getResourceFields());
    }

    public function all(): mixed
    {
        return $this->model->query();
    }

    public function find(mixed $id): mixed
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        return $this->model->create($data);
    }

    public function update(mixed $id, array $data): mixed
    {
        $record = $this->model->findOrFail($id);
        $record->update($data);

        return $record;
    }

    public function destroy(mixed $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
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
}