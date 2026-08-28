<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ServiceAttributeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ReflectionClass;
use ReflectionMethod;

class ServiceProxy
{
    protected $service;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function __call($name, $arguments)
    {
        $reflection = new ReflectionClass($this->service);

        // Récupération unique des attributs natifs (Classe + Méthode via #[...])
        $attributes = array_merge(
            $reflection->getAttributes(ServiceAttributeContract::class, \ReflectionAttribute::IS_INSTANCEOF),
            $reflection->hasMethod($name)
                ? (new ReflectionMethod($this->service, $name))->getAttributes(ServiceAttributeContract::class, \ReflectionAttribute::IS_INSTANCEOF)
                : []
        );

        // Exécution de la logique des attributs
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $instance->handle($this->service, $name, $arguments);
        }

        // Appel de la méthode réelle du service
        $result = call_user_func_array([$this->service, $name], $arguments);

        // Formatage de sortie et tri
        if ($result instanceof Builder || $result instanceof QueryBuilder) {
            $result = $this->service->applySorting($result);
        }

        return $this->service->responseFormat($result);
    }
}