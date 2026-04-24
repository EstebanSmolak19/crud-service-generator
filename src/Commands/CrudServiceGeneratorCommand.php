<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use Illuminate\Console\Command;

class CrudServiceGeneratorCommand extends Command
{
    public $signature = 'make:service {name?} {--crud}';
    public $description = 'Génère une classe de service avec ou sans CRUD';

    public function handle(): int
    {
        $input = $this->getServiceName();
        $crud = $this->option('crud');
        $suffix = config($this->getConfigName() . '.method_suffix', 'Async');
        $useStrict = config($this->getConfigName() . '.strict_types', true);
        $configPath = config($this->getConfigName() . '.path', 'app/Services');
        $className = basename($input);
        $namespace = $this->determineNamespace($input, $configPath);
        $path = base_path($configPath . "/{$input}.php");

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (file_exists($path)) {
            $this->error("Le service {$className} existe déjà !");
            return Command::FAILURE;
        }

        $idConfig = $this->getIdConfiguration($crud);
        $idType = $idConfig['type'];
        $variableNameIdentifiant = $idConfig['variable'];
        $baseNamespace = 'EstebanSmolak19\\CrudServiceGenerator\\CrudServiceBase';
        $modelNamespace = 'App\\Models';
        $model = null;

        if ($crud) {
            $model = $this->ask('Quel est le modèle associé ? (Ex. User)');
            $modelPath = app_path('Models/' . $model . '.php');

            if (!file_exists($modelPath)) {
                if ($this->confirm("Le modèle {$model} n'existe pas. Voulez-vous le créer ?")) {
                    $this->call('make:model', ['name' => $model]);
                }
            }
        }

        $stubPath = $crud
            ? __DIR__ . '/../stubs/CrudService.stub'
            : __DIR__ . '/../stubs/Service.stub';

        if (!file_exists($stubPath)) {
            $this->error("Fichier stub introuvable !");
            return Command::FAILURE;
        }

        $content = file_get_contents($stubPath);

        if ($useStrict) {
            $content = str_replace('<?php', "<?php\n\ndeclare(strict_types=1);", $content);
        }

        $content = str_replace(
            ['{{ class }}', '{{ namespace }}', '{{ idType }}', '{{ variableNameIdentifiant }}', '{{ suffix }}', '{{ baseNamespace }}', '{{ modelNamespace }}', '{{ model }}'],
            [$className, $namespace, $idType, $variableNameIdentifiant, $suffix, $baseNamespace, $modelNamespace, $model ?? ''],
            $content
        );

        file_put_contents($path, $content);
        $this->info("Service {$className} généré avec succès !");

        return Command::SUCCESS;
    }

    private function getServiceName(): string
    {
        $name = $this->argument('name');

        while (!$name) {
            $name = $this->ask('Quel est le nom de votre service ? (Ex. UserService)');

            if (!$name) {
                $this->warn('Le nom du service est obligatoire');
            }
        }

        return $name;
    }

    private function getIdConfiguration(bool $isCrud): array
    {
        if (!$isCrud) {
            return ['type' => 'int', 'variable' => '$id'];
        }

        $idChoice = $this->choice(
            "Quel type d'identifiant utilisez-vous ?",
            ['int', 'uuid'],
            0
        );

        return [
            'type'     => ($idChoice === 'uuid') ? 'string' : 'int',
            'variable' => ($idChoice === 'uuid') ? '$uuid' : '$id',
        ];
    }

    private function determineNamespace(string $input, string $configPath): string
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

    private function getConfigName(): string
    {
        return 'crud-service-generator';
    }
}