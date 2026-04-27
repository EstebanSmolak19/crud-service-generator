<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Services\CommandService;
use Illuminate\Console\Command;

class CrudServiceGeneratorCommand extends Command
{
    public $signature = 'make:service {name?} {--crud} {--h}';
    public $description = 'Génère une classe de service avec ou sans CRUD';

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
        $crud = $this->option('crud');
        $configPath = config($this->service->getConfigName() . '.path', 'app/Services');

        $idConfig = $this->service->getIdConfiguration($this, $crud);

        return [
            'input' => $input,
            'className' => basename($input),
            'namespace' => $this->service->determineNamespace($input, $configPath),
            'path' => base_path($configPath . "/{$input}.php"),
            'crud' => $crud,
            'suffix' => config($this->service->getConfigName() . '.method_suffix', 'Async'),
            'useStrict' => config($this->service->getConfigName() . '.strict_types', true),
            'idType' => $idConfig['type'],
            'variableNameIdentifiant' => $idConfig['variable'],
            'model' => $crud ? $this->service->interactModelCli($this) : null,
            'baseNamespace' => 'EstebanSmolak19\\CrudServiceGenerator\\CrudServiceBase',
            'modelNamespace' => 'App\\Models',
        ];
    }
}