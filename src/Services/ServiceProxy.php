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
        // Analyse du service via Reflection
        $reflection = new ReflectionClass($this->service);

        // Récupération des attributs physiques (Classe + Méthode via #[...])
        $attributes = array_merge(
            $reflection->getAttributes(ServiceAttributeContract::class, \ReflectionAttribute::IS_INSTANCEOF),
            $reflection->hasMethod($name)
                ? (new ReflectionMethod($this->service, $name))->getAttributes(ServiceAttributeContract::class, \ReflectionAttribute::IS_INSTANCEOF)
                : []
        );

        // Récupération des attributs déclarés dans la méthode permissions() du service
        if (method_exists($this->service, 'permissions')) {
            $declaredPermissions = $this->service->permissions();

            if (isset($declaredPermissions[$name])) {
                foreach ((array) $declaredPermissions[$name] as $attrClass) {
                    if (class_exists($attrClass)) {
                        $instance = new $attrClass;
                        if ($instance instanceof ServiceAttributeContract) {
                            // Exécution de la logique de l'attribut déclaré
                            $instance->handle($this->service, $name, $arguments);
                        }
                    }
                }
            }
        }

        // Exécution de la logique des attributs physiques (newInstance())
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            // Si handle() jette une exception ou fait un abort(), le code s'arrête ici.
            $instance->handle($this->service, $name, $arguments);
        }

        // Appel de la méthode réelle
        $result = call_user_func_array([$this->service, $name], $arguments);

        // Formatage de sortie
        if ($result instanceof Builder || $result instanceof QueryBuilder) {
            $result = $this->service->applySorting($result);
        }

        return $this->service->responseFormat($result);
    }
}
