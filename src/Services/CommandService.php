<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use Illuminate\Console\Command;

class CommandService implements ICommandService
{
    public function generate(Command $command, array $state): int
    {
        if (!file_exists(dirname($state['path']))) {
            mkdir(dirname($state['path']), 0755, true);
        }

        $stubPath = $state['crud']
            ? __DIR__ . '/../stubs/CrudService.stub'
            : __DIR__ . '/../stubs/Service.stub';

        if (!file_exists($stubPath)) {
            $command->error("Fichier stub introuvable !");
            return Command::FAILURE;
        }

        $content = file_get_contents($stubPath);

        if ($state['useStrict']) {
            $content = str_replace('<?php', "<?php\n\ndeclare(strict_types=1);", $content);
        }

        $content = str_replace(
            ['{{ class }}', '{{ namespace }}', '{{ idType }}', '{{ variableNameIdentifiant }}', '{{ suffix }}', '{{ baseNamespace }}', '{{ modelNamespace }}', '{{ model }}'],
            [
                $state['className'],
                $state['namespace'],
                $state['idType'],
                $state['variableNameIdentifiant'],
                $state['suffix'],
                $state['baseNamespace'],
                $state['modelNamespace'],
                $state['model'] ?? ''
            ],
            $content
        );

        file_put_contents($state['path'], $content);
        $command->info("Service {$state['className']} généré avec succès !");

        return Command::SUCCESS;
    }

    public function interactModelCli(Command $command): String
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
        $command->newLine(); // Saute une ligne
        $command->info(" Aide du Générateur de Service ");
        $command->line("-------------------------------");
        $command->line("Utilisez <comment>--crud</comment> pour inclure la logique de base de données.");
        $command->line("Utilisez <comment>--sync</comment> pour désactiver le suffixe Async.");
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

            if (!$name) {
                $command->warn('Le nom du service est obligatoire');
            }
        }

        return $name;
    }

    public function getIdConfiguration(Command $command, bool $isCrud): array
    {
        if (!$isCrud) {
            return ['type' => 'int', 'variable' => '$id'];
        }

        $idChoice = $command->choice(
            "Quel type d'identifiant utilisez-vous ?",
            ['int', 'uuid'],
            0
        );

        return [
            'type'     => ($idChoice === 'uuid') ? 'string' : 'int',
            'variable' => ($idChoice === 'uuid') ? '$uuid' : '$id',
        ];
    }

    public function getConfigName(): string
    {
        return 'crud-service-generator';
    }
}