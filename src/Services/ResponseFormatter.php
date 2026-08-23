<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Traits\Conditionable;
use Closure;

class ResponseFormatter
{
    use Conditionable;

    protected mixed $result = null;

    public function __construct(
        protected mixed $data,
        protected Closure $mapper,
        protected int $perPage
    ) {}

    public function format(): mixed
    {
        //Résolution de la requête : si c'est un Builder, on exécute la requête
        $this->when($this->data instanceof Builder || $this->data instanceof QueryBuilder, function ($self) {
            $self->data = $self->perPage > 0
                ? $self->data->paginate(min($self->perPage, config('crud-service-generator.pagination.max_per_page', 100)))
                : $self->data->get();
        });

        //Formatage selon le type de donnée final
        $this
            ->when(is_scalar($this->data) || is_null($this->data), function ($self) {
                $self->result = ['affected_rows' => $self->data];
            })
            ->when($this->data instanceof LengthAwarePaginator, function ($self) {
                $self->result = $self->data->setCollection(
                    $self->data->getCollection()->map($self->mapper)
                );
            })
            ->when($this->data instanceof SupportCollection, function ($self) {
                $self->result = $self->data->map($self->mapper);
            })
            ->unless($this->result !== null, function ($self) {
                $self->result = ($self->mapper)($self->data);
            });

        return $this->result;
    }
}