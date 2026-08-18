<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use EstebanSmolak19\CrudServiceGenerator\Services\FileExistenceChecker;
use Illuminate\Console\Command;

class CrudServiceGeneratorCommand extends Command
{
    public $signature = 'make:service {name? : Le nom du service (ex: UserService)}';

    public $description = 'Génère une classe de service et ses composants associés via un menu interactif';

    public function __construct(private ICommandService $service)
    {
        parent::__construct();
    }

    /**
     * Exécute la commande de la console.
     * Cette méthode orchestre l'interaction utilisateur, la vérification
     * des conflits de fichiers, puis délègue la génération.
     * @return int Code de statut de la commande (0 pour succès, 1 pour échec).
     */
    public function handle()
    {
        // Définition des modes de génération disponibles
        $options = ['service', 'controllers', 'CRUD', 'tous'];

        $selection = $this->choice(
            'Quels modes choisissez-vous ?',
            $options,
            0
        );

        // Compile toutes les variables nécessaires (chemins, namespaces, options)
        // à partir du choix effectué
        $state = $this->gatherState($selection);

        // Initialisation de la vérification des fichiers
        $checker = new FileExistenceChecker($this);

        // Vérifie si le fichier de service existe déjà
        $fails = $checker
            ->check('service', $state['path'], $state['className'])
            ->when($state['controller'], function ($checker) use ($state) {
                $checker->check('contrôleur', $state['controllerPath'], $state['controllerName']);
            })
            ->fails();

        // Si au moins un fichier existe, on stoppe tout pour éviter d'écraser le travail
        if ($fails) {
            return Command::FAILURE;
        }

        // Lance la création des fichiers
        return $this->service->generate($this, $state);
    }

    /**
     * Rassemble et prépare toutes les métadonnées (chemins, namespaces, flags)
     * nécessaires à la génération des stubs.
     * @param string $selection Le mode choisi par l'utilisateur ('service', 'controllers', etc.)
     * @return array<string, mixed> Tableau associatif contenant l'état de configuration complet.
     */
    private function gatherState(string $selection): array
    {
        // Demande ou récupère le nom du service via le paramètre de la commande
        $input = $this->service->getServiceName($this);

        // Détermination des drapeaux booléens selon le choix interactif
        $isAll = $selection === 'tous';
        $isControllerOnly = $selection === 'controllers';
        $isCrudOnly = $selection === 'CRUD';

        // Calcul des modes finaux (si "tous" est coché, tout passe à true)
        $controller = $isAll || $isControllerOnly;
        $crud = $isAll || $isCrudOnly;

        // Récupération de la configuration et formatage des noms de base
        $configPath = config($this->service->getConfigName() . '.path', 'app/Services');
        $className = basename($input);

        // On ne génère le nom du contrôleur que si le mode l'exige
        $controllerName = $controller ? $this->service->getControllerName($this) : '';

        // On ne demande le nom de la route QUE si on est en mode complet ("tous")
        $routeName = $isAll ? $this->service->getRouteName($this) : '';

        $isAuthenticated = $isAll ? $this->service->isAuthenticatedRoute($this) : false;

        return [
            // Informations générales du service
            'input'               => $input,
            'className'           => $className,
            'namespace'           => $this->service->determineNamespace($input, $configPath),
            'path'                => base_path($configPath . "/{$input}.php"),
            'serviceNamespace'    => $this->service->determineNamespace($input, $configPath),
            'baseNamespace'       => 'EstebanSmolak19\\CrudServiceGenerator\\CrudServiceBase',

            // Flags de génération
            'crud'                => $crud,
            'controller'          => $controller,
            'all_mode'            => $isAll,

            // Configuration spécifique (suffixes, typage strict, etc.)
            'suffix'              => config($this->service->getConfigName() . '.method_suffix', 'Async'),
            'useStrict'           => config($this->service->getConfigName() . '.strict_types', true),

            // Configuration du Modèle (interaction CLI déclenchée si CRUD est actif)
            'model'               => $crud ? $this->service->interactModelCli($this) : null,
            'modelNamespace'      => 'App\\Models',

            // Configuration du Contrôleur
            'controllerName'          => $controllerName,
            'controllerNamespace'     => 'App\\Http\\Controllers',
            'controllerPath'          => app_path("Http/Controllers/{$controllerName}.php"),
            'baseControllerNamespace' => 'EstebanSmolak19\\CrudServiceGenerator\\Controllers\\CrudControllerBase',

            // Configuration des Routes
            'routeName'           => strtolower($routeName),
            'isAuthenticated'   => $isAuthenticated
        ];
    }
}