<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use Illuminate\Console\Command;

class CrudServiceGeneratorCommand extends Command
{
    public $signature = 'make:service
        {name? : Le nom du service (ex: UserService)}
        {--crud : Génère un service avec méthodes CRUD}
        {--controller : Génère un contrôleur API simple}
        {--all : Génère la totale (Service CRUD + Controller + Route + Resource)}';

    public $description = 'Génère une classe de service et ses composants associés';

    public function __construct(private ICommandService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $optionsCount = ($this->option('crud') ? 1 : 0) + ($this->option('controller') ? 1 : 0) + ($this->option('all') ? 1 : 0);

        if ($optionsCount > 1) {
            $this->error("Veuillez choisir une seule option parmi --crud, --controller ou --all.");
            return Command::FAILURE;
        }

        $state = $this->gatherState();

        if ($state['controller'] && file_exists($state['controllerPath'])) {
            $this->error("Le contrôleur {$state['controllerName']} existe déjà !");
            return Command::FAILURE;
        }

        return $this->service->generate($this, $state);
    }

    private function gatherState(): array
    {
        $input = $this->service->getServiceName($this);

        $isAll = $this->option('all');
        $isControllerOnly = $this->option('controller');
        $isCrudOnly = $this->option('crud');

        $controller = $isAll || $isControllerOnly;
        $crud = $isAll || $isCrudOnly;

        $configPath = config($this->service->getConfigName() . '.path', 'app/Services');
        $className = basename($input);

        $controllerName = $controller ? $this->service->getControllerName($this) : '';

        // On ne demande le nom de la route QUE si on est en mode --all
        $routeName = $isAll ? $this->service->getRouteName($this) : '';

        return [
            'input' => $input,
            'className' => $className,
            'namespace' => $this->service->determineNamespace($input, $configPath),
            'path' => base_path($configPath . "/{$input}.php"),
            'crud' => $crud,
            'controller' => $controller,
            'all_mode' => $isAll, // L'élément manquant qui causait l'erreur
            'suffix' => config($this->service->getConfigName() . '.method_suffix', 'Async'),
            'useStrict' => config($this->service->getConfigName() . '.strict_types', true),
            'model' => $crud ? $this->service->interactModelCli($this) : null,
            'baseNamespace' => 'EstebanSmolak19\\CrudServiceGenerator\\CrudServiceBase',
            'modelNamespace' => 'App\\Models',

            'controllerName'      => $controllerName,
            'controllerNamespace' => 'App\\Http\\Controllers',
            'controllerPath'      => app_path("Http/Controllers/{$controllerName}.php"),
            'serviceNamespace'    => $this->service->determineNamespace($input, $configPath),
            'baseControllerNamespace' => 'EstebanSmolak19\\CrudServiceGenerator\\Controllers\\CrudControllerBase',
            'routeName'           => strtolower($routeName),
        ];
    }
}