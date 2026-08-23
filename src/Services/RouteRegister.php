<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;

class RouteRegister
{
    use Conditionable;

    private string $currentContent;

    private string $controllerFQN;

    private string $slug;

    public function __construct(private string $routePath)
    {
        $this->initializeFileIfNeeded();
        $this->currentContent = file_get_contents($this->routePath);
    }

    /**
     * Initialise les variables nécessaires à la création de la route.
     *
     * @param  array  $state  L'état global généré par la commande.
     */
    public function prepare(array $state): self
    {
        $this->slug = Str::plural(Str::kebab($state['routeName']));
        $this->controllerFQN = '\\'.$state['controllerNamespace'].'\\'.$state['controllerName'];

        return $this;
    }

    /**
     * Vérifie si la route pour ce contrôleur a déjà été déclarée.
     */
    public function routeExists(): bool
    {
        return str_contains($this->currentContent, $this->controllerFQN);
    }

    /**
     * Injecte la route dans le bloc des routes publiques.
     */
    public function registerPublic(): self
    {
        $routeLine = "Route::serviceCrudResource('{$this->slug}', {$this->controllerFQN}::class);\n";

        if (str_contains($this->currentContent, '// <public_routes>')) {
            $this->currentContent = str_replace(
                '// <public_routes>',
                "{$routeLine}// <public_routes>",
                $this->currentContent
            );
        } else {
            $this->currentContent .= $routeLine;
        }

        return $this;
    }

    /**
     * Injecte la route dans le groupe des middlewares protégés (Sanctum).
     */
    public function registerProtected(): self
    {
        $routeLine = "    Route::serviceCrudResource('{$this->slug}', {$this->controllerFQN}::class);\n";

        if (str_contains($this->currentContent, '// <protected_routes>')) {
            $this->currentContent = str_replace(
                '// <protected_routes>',
                "{$routeLine}    // <protected_routes>",
                $this->currentContent
            );
        } else {
            $this->currentContent .= "\nRoute::middleware('auth:sanctum')->group(function () {\n{$routeLine}});\n";
        }

        return $this;
    }

    /**
     * Sauvegarde les modifications dans le fichier de routes.
     */
    public function save(): void
    {
        file_put_contents($this->routePath, $this->currentContent);
    }

    /**
     * Crée le fichier de routes avec les marqueurs si celui-ci n'existe pas.
     */
    private function initializeFileIfNeeded(): void
    {
        if (file_exists($this->routePath)) {
            return;
        }

        $initialContent = <<<PHP
            <?php

            use Illuminate\Support\Facades\Route;
            use EstebanSmolak19\CrudServiceGenerator\Middlewares\ForceJsonResponse;


            // --- Routes Publiques ---
            // <public_routes>


            // --- Routes Protégées (Sanctum) ---
            Route::middleware([ForceJsonResponse::class, 'auth:sanctum'])->group(function () {
                // <protected_routes>
            });

            PHP;
        file_put_contents($this->routePath, $initialContent);
    }
}
