<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Console\Command;

class ApplyConfigCommand extends Command
{
    public $signature = 'config:apply';
    public $description = 'Applique le fichier de configuration du package.';

    public function __construct(private IModelService $service)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Configuration de l\'environnement en cours...');
        $this->service->hideBaseModelsInVsCode();
        $this->info('L\'environnement a été mis à jour avec succès !');
        return Command::SUCCESS;
    }
}