<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class CommandService implements ICommandService
{
    protected function getExcludedColumns(): array
    {
        $basic = ['id', 'created_at', 'updated_at', 'deleted_at'];
        return array_merge($basic, config('crud-service-generator.models.excluded_columns', []));
    }

    public function getServiceName(Command $command): string
    {
        $name = $command->argument('name');

        while (true) {
            if (!$name) {
                $name = $command->ask('Quel est le nom de votre service ? (Ex. UserService)');
            }

            if (!$name) {
                $command->warn('Le nom du service est obligatoire.');
                continue;
            }

            $configPath = config($this->getConfigName() . '.path', 'app/Services');
            $path = base_path($configPath . "/{$name}.php");

            if (file_exists($path)) {
                $command->error("Le service {$name} existe déjà !");
                $name = null;
                continue;
            }

            return $name;
        }
    }

    public function generate(Command $command, array $state): int
    {
        // 1. Service
        $this->generateFileFromStub(
            $state['path'],
            $state['crud'] ? __DIR__ . '/../stubs/CrudService.stub' : __DIR__ . '/../stubs/Service.stub',
            $state
        );

        // 2. Controller
        if ($state['controller']) {
            // Sélection du stub : Controller.stub pour --all, ControllerSimple.stub pour --controller
            $controllerStub = ($state['all_mode'] ?? false)
                ? __DIR__ . '/../stubs/Controller.stub'
                : __DIR__ . '/../stubs/ControllerSimple.stub';

            $this->generateFileFromStub($state['controllerPath'], $controllerStub, $state);

            // Routes : UNIQUEMENT si --all est utilisé
            if ($state['all_mode'] ?? false) {
                $this->registerRoute($command, $state);
            }
        }

        $command->info("✅ Composants générés avec succès !");
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
                }
            }
        }

        $content = str_replace(
            [
                '{{ class }}', '{{ className }}', '{{ namespace }}', '{{ suffix }}',
                '{{ baseNamespace }}', '{{ modelNamespace }}', '{{ model }}', '{{ fields }}',
                '{{ controllerName }}', '{{ controllerNamespace }}', '{{ serviceNamespace }}',
                '{{ baseControllerNamespace }}', '{{ serviceClass }}'
            ],
            [
                $state['className'], $state['className'], $state['namespace'], $state['suffix'],
                $state['baseNamespace'], $state['modelNamespace'], $state['model'] ?? '', trim($fields),
                $state['controllerName'], $state['controllerNamespace'], $state['serviceNamespace'],
                $state['baseControllerNamespace'], $state['className']
            ],
            $content
        );

        file_put_contents($path, $content);
    }

    public function interactModelCli(Command $command): string
    {
        $model = $command->ask('Quel est le modèle associé ? (Ex. User)');
        while (!$model) {
            $model = $command->ask('Le nom du modèle est obligatoire :');
        }

        $modelPath = app_path('Models/' . $model . '.php');
        if (!file_exists($modelPath)) {
            $command->info("Le modèle {$model} n'existe pas, création en cours...");
            $command->call('make:model', ['name' => $model]);
        }

        return $model;
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

    public function getControllerName(Command $command): string
    {
        do {
            $name = $command->ask('Quel est le nom de votre controller ? (Ex. UserController)');
            if(!$name) $command->warn('Le nom du controller est obligatoire');
        } while(!$name);
        return $name;
    }

    public function getRouteName(Command $command): string
    {
        do {
            $routeName = $command->ask('Quel est le nom de la route associée ? (Ex. users)');
            if(!$routeName) $command->warn('Le nom de la route est obligatoire');
        } while(!$routeName);
        return $routeName;
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
        if (!str_contains($currentContent, $controllerFQN)) {
            file_put_contents($routePath, $routeLine, FILE_APPEND);
        }
    }

    public function getConfigName(): string
    {
        return 'crud-service-generator';
    }
}