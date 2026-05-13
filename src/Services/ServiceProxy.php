<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ServiceProxy
{
    protected $service;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function __call($name, $arguments)
    {
        $result = call_user_func_array([$this->service, $name], $arguments);

        if ($result instanceof Builder || $result instanceof QueryBuilder) {
            // On applique le filtre pour toutes les méthodes du service
            $result = $this->service->applySorting($result);
        }
        return $this->service->responseFormat($result);
    }
}