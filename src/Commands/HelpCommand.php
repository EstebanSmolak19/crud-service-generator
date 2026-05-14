<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Console\Command;

class HelpCommand extends Command
{
    public $signature = 'p:help';

    public $description = 'Affiche le guide complet d\'utilisation du package';

    public function __construct(private IModelService $service)
    {
        return parent::__construct();
    }

    public function handle(): int
    {
        $this->helpOption($this);

        return Command::SUCCESS;
    }

    public function helpOption(Command $command): void
    {
        // Titre stylisé
        $command->line('');
        $command->line('<fg=white;bg=blue;options=bold>  🚀 CRUD SERVICE GENERATOR - DOCUMENTATION  </>');
        $command->line('');

        // Usage de base, les commandes
        $command->comment('Usage:');
        $command->line('  <info>php artisan make:service {Name}</info> [options?]');
        $command->line('  <info>php artisan generate:model</info>   Synchronise tous les modèles avec la BDD.');
        $command->line('  <info>php artisan config:apply</info>     Applique les réglages du fichier de configurations');
        $command->line('');

        // Les options
        $command->comment('Listes des options');
        $command->line("  <info>--crud</info>            Génère le <comment>Modèle s'il n'existe pas</comment> ainsi que le Service qui étant d'un <comment>CRUD Service</comment>");
        $command->line('  <info>--controller</info>      Ajoute le <comment>Controller API</comment> et associe le service au controller'); // A faire
        $command->line('  <info>--all</info>             Le pack complet : CRUD + Controller + Routes CRUD.');
        $command->line('');

        $command->line('------------------------------------------------------------------');
        $command->line('<fg=gray>Version : 1.0.4 | Créé par EstebanSmolak19</>');
        $command->line('');
    }
}
