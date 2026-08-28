<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use Illuminate\Console\Command;

class HelpCommand extends Command
{
    public $signature = 'p:help';

    public $description = 'Affiche le guide complet d\'utilisation du package';

    public function handle(): int
    {
        $this->helpOption($this);

        return Command::SUCCESS;
    }

    public function helpOption(Command $command): void
    {
        $command->line('');
        $command->line('<fg=white;bg=blue;options=bold>  🚀 CRUD SERVICE GENERATOR - DOCUMENTATION  </>');
        $command->line('');

        $command->comment('Commandes disponibles :');
        $command->line('  <info>php artisan make:service {name?}</info>    Génère un service via un menu interactif');
        $command->line('  <info>php artisan make:attribute</info>     Crée un attribut de service personnalisé');
        $command->line('  <info>php artisan generate:model</info>     Synchronise tous les modèles avec la BDD');
        $command->line('  <info>php artisan front:model {model}</info>  Génère l\'interface TypeScript pour le front');
        $command->line('  <info>php artisan config:apply</info>       Applique le fichier de configuration');
        $command->line('');

        $command->comment('Modes interactifs (pour make:service) :');
        $command->line('  <info>service</info>      Génère uniquement la classe de service');
        $command->line('  <info>controllers</info>  Génère le service et son contrôleur API');
        $command->line('  <info>CRUD</info>         Génère le service, le modèle et les méthodes CRUD');
        $command->line('  <info>tous</info>         Le pack complet : Service, Modèle, Contrôleur et Routes');
        $command->line('');

        $command->line('------------------------------------------------------------------');
        $command->line('<fg=gray>Version : 1.0.4 | Créé par EstebanSmolak19</>');
        $command->line('');
    }
}
