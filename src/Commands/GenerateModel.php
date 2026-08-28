<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Console\Command;

class GenerateModel extends Command
{
    public $signature = 'generate:model';

    public $description = 'Génère tous les models en fonction des tables existantes dans la base de donnée';

    public function __construct(private IModelService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->task('Génération des modèles et des relations', function () {
            $this->service->generateModels();
            return true;
        });

        $this->newLine();
        $this->components->info('🎉 Tous les modèles ont été générés et synchronisés avec succès !');

        return Command::SUCCESS;
    }
}
