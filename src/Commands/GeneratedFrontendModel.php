<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Services\FrontendModelGenerator;
use Illuminate\Console\Command;

class GeneratedFrontendModel extends Command
{
    protected $signature = 'front:model {model : Le nom du modèle (ex: Wallet)}';

    protected $description = "Génère l'interface TypeScript pour le frontend à partir de la base de données";

    public function handle(FrontendModelGenerator $frontendGenerator): int
    {
        $modelName = $this->argument('model');

        $this->info("Génération du type TypeScript pour [{$modelName}]...");

        $frontendGenerator->generate($modelName);

        $this->info('Fichier TypeScript généré avec succès !');

        return Command::SUCCESS;
    }
}
