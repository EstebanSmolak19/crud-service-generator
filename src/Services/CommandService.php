<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class CommandService implements ICommandService
{
    /**
     * Liste des colonnes à ne pas afficher dans la Resource API
     */
    protected function getExcludedColumns(): array
    {
        $basic = ['id', 'created_at', 'updated_at', 'deleted_at'];
        return array_merge($basic, config('crud-service-generator.models.excluded_columns', []));
    }

    public function generate(Command $command, array $state): int
    {
        $this->generateFileFromStub(
            $state['path'],
            $state['crud'] ? __DIR__ . '/../stubs/CrudService.stub' : __DIR__ . '/../stubs/Service.stub',
            $state
        );

        if ($state['crud']) {
            $resourceName = ($state['model'] ?? $state['className']) . 'Resource';
            $resourcePath = app_path("Http/Resources/{$resourceName}.php");

            $resourceState = $state;
            $resourceState['className'] = $resourceName;
            $resourceState['namespace'] = 'App\Http\Resources';

            $this->generateFileFromStub($resourcePath, __DIR__ . '/../stubs/Resource.stub', $resourceState);
        }

        if ($state['controller']) {
            $this->generateFileFromStub($state['controllerPath'], __DIR__ . '/../stubs/Controller.stub', $state);
            $this->registerRoute($command, $state);
        }

        return Command::SUCCESS;
    }

    private function generateFileFromStub(string $path, string $stubPath, array $state): void
    {
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (!file_exists($stubPath)) return;

        $content = file_get_contents($stubPath);

        if ($state['useStrict']) {
            $content = str_replace('<?php', "<?php\n\ndeclare(strict_types=1);", $content);
        }

        $fields = "";
        if (isset($state['model'])) {
            $modelClass = "App\\Models\\" . $state['model'];

            if (class_exists($modelClass)) {
                $modelInstance = new $modelClass();
                $tableName = $modelInstance->getTable();

                if (Schema::hasTable($tableName)) {
                    $allColumns = Schema::getColumnListing($tableName);
                    $excluded = $this->getExcludedColumns();

                    foreach ($allColumns as $col) {
                        if (!in_array($col, $excluded)) {
                            $fields .= "            '{$col}' => \$this->{$col},\n";
                        }
                    }
                } else {
                    $fields = "            // Table '{$tableName}' détectée mais introuvable dans la base.";
                }
            } else {
                $fields = "            // Modèle '{$modelClass}' introuvable. Impossible de détecter la table.";
            }
        }

        $content = str_replace(
            [
                '{{ class }}',
                '{{ className }}',
                '{{ namespace }}',
                '{{ idType }}',
                '{{ variableNameIdentifiant }}',
                '{{ suffix }}',
                '{{ baseNamespace }}',
                '{{ modelNamespace }}',
                '{{ model }}',
                '{{ resource }}',
                '{{ fields }}',
                '{{ controllerName }}',
                '{{ controllerNamespace }}',
                '{{ serviceNamespace }}',
                '{{ baseControllerNamespace }}',
                '{{ serviceClass }}'
            ],
            [
                $state['className'],
                $state['className'],
                $state['namespace'],
                $state['idType'],
                $state['variableNameIdentifiant'],
                $state['suffix'],
                $state['baseNamespace'],
                $state['modelNamespace'],
                $state['model'] ?? '',
                ($state['model'] ?? $state['className']) . 'Resource',
                trim($fields),
                $state['controllerName'],
                $state['controllerNamespace'],
                $state['serviceNamespace'],
                $state['baseControllerNamespace'],
                $state['className']
            ],
            $content
        );

        file_put_contents($path, $content);
    }

    public function interactModelCli(Command $command): string
    {
        $model = $command->ask('Quel est le modèle associé ? (Ex. User)');
        $modelPath = app_path('Models/' . $model . '.php');

        if (!file_exists($modelPath)) {
            if ($command->confirm("Le modèle {$model} n'existe pas. Voulez-vous le créer ?")) {
                $command->call('make:model', ['name' => $model]);
            }
        }

        return $model;
    }

    public function helpOption(Command $command): void
    {
        $command->newLine();
        $command->info(" 🛠️  Aide du Générateur de Service & CRUD ");
        $command->line("------------------------------------------------------------------");
        $command->comment("Usage :");
        $command->line("  php artisan make:service {name} [options]");
        $command->newLine();
        $command->comment("Options disponibles :");
        $command->line("  <info>--crud</info>        Génère Service, Model et Resource API.");
        $command->line("  <info>--controller</info>  Génère le Contrôleur et enregistre la route.");
        $command->line("  <info>--strict</info>      Active le mode strict PHP.");
        $command->newLine();
    }

    public function determineNamespace(string $input, string $configPath): string
    {
        $subDir = dirname($input);
        $baseNamespace = str_replace('/', '\\', ucfirst($configPath));
        $baseNamespace = preg_replace('/^app\\\/i', 'App\\', $baseNamespace);
        $namespace = $baseNamespace;

        if ($subDir !== '.') {
            $namespace .= "\\" . str_replace(['/', '|', ':'], "\\", $subDir);
        }

        return $namespace;
    }

    public function getServiceName(Command $command): string
    {
        $name = $command->argument('name');
        while (!$name) {
            $name = $command->ask('Quel est le nom de votre service ? (Ex. UserService)');
            if (!$name) $command->warn('Le nom du service est obligatoire');
        }
        return $name;
    }

    public function getIdConfiguration(Command $command, bool $isCrud): array
    {
        if (!$isCrud) return ['type' => 'int', 'variable' => '$id'];
        $idChoice = $command->choice("Type d'identifiant ?", ['int', 'uuid'], 0);
        return [
            'type'     => ($idChoice === 'uuid') ? 'string' : 'int',
            'variable' => ($idChoice === 'uuid') ? '$uuid' : '$id',
        ];
    }

    private function registerRoute(Command $command, array $state): void
    {
        $routePath = base_path('routes/service_generator.php');

        if (!file_exists($routePath)) {
            file_put_contents($routePath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n");
        }

        $slug = Str::plural(Str::kebab($state['routeName']));
        $controllerFQN = "\\" . $state['controllerNamespace'] . "\\" . $state['controllerName'];
        $routeLine = "Route::apiResource('{$slug}', {$controllerFQN}::class);\n";

        $currentContent = file_get_contents($routePath);
        if (str_contains($currentContent, $controllerFQN)) {
            return;
        }

        file_put_contents($routePath, $routeLine, FILE_APPEND);
    }

    public function getConfigName(): string
    {
        return 'crud-service-generator';
    }
}