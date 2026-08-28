<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InitCommand extends Command
{
    protected $signature = 'package:init';

    protected $description = 'Installe et configure le package Crud Service Generator';

    public function handle(): int
    {
        $this->components->info('🚀 Initialisation du générateur de services CRUD...');

        //Publication de la config en mode silencieux
        $this->components->task('Publication du fichier de configuration', function () {
            $this->call('vendor:publish', [
                '--tag' => 'crud-service-generator-config',
                '--quiet' => true,
            ]);
            return true;
        });

        //Publication des migrations
        $this->components->task('Publication des migrations', function () {
            $this->call('vendor:publish', [
                '--tag' => 'crud-service-generator-migrations',
                '--quiet' => true,
            ]);
            return true;
        });

        $useUuids = $this->confirm('Est-ce que votre application utilise des UUIDs pour vos modèles/utilisateurs ?', false);

        //Mise à jour de la configuration
        $this->components->task('Mise à jour de la configuration (UUIDs)', function () use ($useUuids) {
            $configPath = config_path('crud-service-generator.php');

            if (File::exists($configPath)) {
                $configContent = File::get($configPath);
                $replacement = $useUuids ? "'use_uuids' => true," : "'use_uuids' => false,";
                $configContent = preg_replace("/'use_uuids' => (true|false),/", $replacement, $configContent);
                File::put($configPath, $configContent);
            }
            return true;
        });

        $this->newLine();
        $this->components->info('🎉 Package installé et configuré avec succès !');
        return Command::SUCCESS;
    }
}