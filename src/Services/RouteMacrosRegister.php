<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Routing\Router;

class RouteMacrosRegister
{
    public static function register(Router $router): void
    {
        $router->macro('serviceCrudResource', function (string $name, string $controller) use ($router) {
            $router->post("{$name}/bulk-update", [$controller, 'bulkUpdate'])->name("{$name}.bulk-update");
            $router->post("{$name}/bulk-delete", [$controller, 'bulkDelete'])->name("{$name}.bulk-delete");
            $router->apiResource($name, $controller);
        });
    }
}