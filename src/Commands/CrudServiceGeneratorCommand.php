<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Services\CommandService;
use Illuminate\Console\Command;

class CrudServiceGeneratorCommand extends Command
{
    // Ajout de l'option --controller dans la signature
    public $signature = 'make:service {name?} {--crud} {--controller} {--h}';
    public $description = 'Génère une classe de service avec ou sans CRUD et son contrôleur associé';

    public function __construct(private CommandService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('h')) {
            $this->service->helpOption($this);
            return Command::SUCCESS;
        }

        $state = $this->gatherState();

        if (file_exists($state['path'])) {
            $this->error("Le service {$state['className']} existe déjà !");
            return Command::FAILURE;
        }

        return $this->service->generate($this, $state);
    }

    private function gatherState(): array
    {
        $input = $this->service->getServiceName($this);
        $controller = $this->option('controller');
        $crud = $this->option('crud') || $controller;
        $configPath = config($this->service->getConfigName() . '.path', 'app/Services');

        $idConfig = $this->service->getIdConfiguration($this, $crud);

        $className = basename($input);
        $controllerName = $className . 'Controller';

        return [
            'input' => $input,
            'className' => $className,
            'namespace' => $this->service->determineNamespace($input, $configPath),
            'path' => base_path($configPath . "/{$input}.php"),
            'crud' => $crud,
            'controller' => $controller,
            'suffix' => config($this->service->getConfigName() . '.method_suffix', 'Async'),
            'useStrict' => config($this->service->getConfigName() . '.strict_types', true),
            'idType' => $idConfig['type'],
            'variableNameIdentifiant' => $idConfig['variable'],
            'model' => $crud ? $this->service->interactModelCli($this) : null,
            'baseNamespace' => 'EstebanSmolak19\\CrudServiceGenerator\\CrudServiceBase',
            'modelNamespace' => 'App\\Models',

            // Données pour le Controller
            'controllerName'      => $controllerName,
            'controllerNamespace' => 'App\\Http\\Controllers',
            'controllerPath'      => app_path("Http/Controllers/{$controllerName}.php"),
            'serviceNamespace'    => $this->service->determineNamespace($input, $configPath),
            'baseControllerNamespace' => 'EstebanSmolak19\\CrudServiceGenerator\\Controllers\\CrudControllerBase',
            'routeName'           => strtolower($className),
        ];
    }
}