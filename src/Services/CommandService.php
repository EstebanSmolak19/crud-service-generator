<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CommandService implements ICommandService
{
    public function generate(Command $command, array $state): int
    {
        $this->generateFileFromStub(
            $state['path'],
            $state['crud'] ? __DIR__ . '/../stubs/CrudService.stub' : __DIR__ . '/../stubs/Service.stub',
            $state
        );
        $command->info("Service {$state['className']} généré avec succès !");

        //Génération du Controller (si l'option est présente)
        if ($state['controller']) {
            if (file_exists($state['controllerPath'])) {
                $command->warn("Le contrôleur {$state['controllerName']} existe déjà !");

            } else {
                $this->generateFileFromStub(
                    $state['controllerPath'],
                    __DIR__ . '/../stubs/Controller.stub',
                    $state
                );
                $command->info("Contrôleur {$state['controllerName']} généré avec succès !");

                $slug = Str::plural($state['routeName']);
                $this->registerRoute($command, $state);
                $command->line("<comment>Route suggérée :</comment> Route::apiResource('{$slug}', \\{$state['controllerNamespace']}\\{$state['controllerName']}::class);");
            }
        }

        return Command::SUCCESS;
    }

    private function generateFileFromStub(string $path, string $stubPath, array $state): void
    {
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $content = file_get_contents($stubPath);

        if ($state['useStrict']) {
            $content = str_replace('<?php', "<?php\n\ndeclare(strict_types=1);", $content);
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
                '{{ controllerName }}',
                '{{ controllerNamespace }}',
                '{{ serviceNamespace }}',
                '{{ baseControllerNamespace }}',
                '{{ serviceClass }}',
                '{{ controllerNamespace }}'
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
                $state['controllerName'],
                $state['controllerNamespace'],
                $state['serviceNamespace'],
                $state['baseControllerNamespace'],
                $state['className'],
                $state['controllerNamespace']
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
        $command->info(" Aide du Générateur de Service ");
        $command->line("-------------------------------");
        $command->line("Utilisez <comment>--crud</comment> pour inclure la logique de base de données.");
        $command->line("Utilisez <comment>--controller</comment> pour générer le contrôleur CRUD associé.");
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

        //Si le fichier n'existe pas, on le crée avec l'en-tête PHP
        if (!file_exists($routePath)) {
            file_put_contents($routePath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n");
            $command->info("Fichier de routes créé : routes/service_generator.php");
        }

        //Préparer la ligne (ex: Route::apiResource('users', UserController::class);)
        $slug = Str::plural($state['routeName']);
        $controllerFQN = "\\" . $state['controllerNamespace'] . "\\" . $state['controllerName'];

        $routeLine = "Route::apiResource('{$slug}', {$controllerFQN}::class);\n";

        //Vérifier si la route n'existe pas déjà pour éviter les doublons
        $currentContent = file_get_contents($routePath);
        if (str_contains($currentContent, $controllerFQN)) {
            $command->warn("La route pour {$state['controllerName']} semble déjà exister.");
            return;
        }

        //Ajouter la ligne à la fin du fichier
        file_put_contents($routePath, $routeLine, FILE_APPEND);
        $command->info("Route ajoutée avec succès !");
    }

    public function getConfigName(): string
    {
        return 'crud-service-generator';
    }
}