<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CommandService implements ICommandService
{
    /**
     * Récupère la liste des colonnes à exclure lors de la génération des champs.
     * @var array
     */
    public array $excludedColumns {
        get { return array_merge(config('crud-service-generator.models.excluded_columns', [])); }
    }

    /**
     * Demande ou récupère le nom du service cible.
     * @param Command $command L'instance de la commande en cours d'exécution.
     * @return string Le nom du service saisi par l'utilisateur.
     */
    public function getServiceName(Command $command): string
    {
        $name = $command->argument('name');

        // On boucle tant que l'utilisateur n'a pas fourni de nom valide
        while (!$name) {
            $name = $command->ask('Quel est le nom de votre service ? (Ex. UserService)');

            if (!$name) {
                $command->warn('Le nom du service est obligatoire.');
            }
        }

        return $name;
    }

    /**
     * Génération de tous les composants demandés.
     * @param Command $command L'instance de la commande.
     * @param array $state L'état global de la configuration généré par la commande.
     * @return int Code de retour de la commande (Command::SUCCESS).
     */
    public function generate(Command $command, array $state): int
    {
        // Génération du Service (toujours généré)
        // On choisit le stub selon que le mode CRUD a été demandé ou non
        $this->generateFileFromStub(
            $state['path'],
            $state['crud'] ? __DIR__ . '/../stubs/CrudService.stub' : __DIR__ . '/../stubs/Service.stub',
            $state
        );

        // Génération du Contrôleur si demandé
        if ($state['controller']) {
            // Sélection du stub : complet (avec méthodes CRUD) ou simple
            $controllerStub = ($state['all_mode'] ?? false)
                ? __DIR__ . '/../stubs/Controller.stub'
                : __DIR__ . '/../stubs/ControllerSimple.stub';

            $this->generateFileFromStub($state['controllerPath'], $controllerStub, $state);

            // Déclaration de la Route
            if ($state['all_mode'] ?? false) {
                $this->registerRoute($command, $state);
            }
        }

        $command->info("✅ Composants générés avec succès !");
        return Command::SUCCESS;
    }

    /**
     * Génère un fichier à partir d'un fichier "stub"
     * Remplace les variables dynamiques ({{ class }}, {{ namespace }}...) par les valeurs réelles.
     * @param string $path Chemin absolu où le fichier doit être créé.
     * @param string $stubPath Chemin absolu vers le fichier stub source.
     * @param array $state Données de configuration pour le remplacement.
     * @return void
     */
    private function generateFileFromStub(string $path, string $stubPath, array $state): void
    {
        // Crée les dossiers parents s'ils n'existent pas encore
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (!file_exists($stubPath)) return;

        $content = file_get_contents($stubPath);

        // Ajoute la déclaration de typage strict si configurée
        if ($state['useStrict']) {
            $content = str_replace('<?php', "<?php\n\ndeclare(strict_types=1);", $content);
        }

        // Si on est en mode CRUD, on pré-remplit les champs de la ressource en lisant la base de données
        $fields = "";
        if (isset($state['model'])) {
            $modelClass = "App\\Models\\" . $state['model'];

            // On vérifie que la classe existe et que la table correspondante est migrée
            if (class_exists($modelClass)) {
                $modelInstance = new $modelClass();
                $tableName = $modelInstance->getTable();

                if (Schema::hasTable($tableName)) {
                    $allColumns = Schema::getColumnListing($tableName);
                    foreach ($allColumns as $col) {
                        // On ignore les colonnes configurées (ex: id, created_at, etc.)
                        if (!in_array($col, $this->excludedColumns)) {
                            $fields .= "            '{$col}' => \$this->{$col},\n";
                        }
                    }
                }
            }
        }

        // Remplace toutes les balises du stub par les données de l'état
        $content = str_replace(
            [
                '{{ class }}', '{{ className }}', '{{ namespace }}', '{{ suffix }}',
                '{{ baseNamespace }}', '{{ modelNamespace }}', '{{ model }}', '{{ fields }}',
                '{{ controllerName }}', '{{ controllerNamespace }}', '{{ serviceNamespace }}',
                '{{ baseControllerNamespace }}', '{{ serviceClass }}'
            ],
            [
                $state['className'], $state['className'], $state['namespace'], $state['suffix'],
                $state['baseNamespace'], $state['modelNamespace'], $state['model'] ?? '', trim($fields),
                $state['controllerName'], $state['controllerNamespace'], $state['serviceNamespace'],
                $state['baseControllerNamespace'], $state['className']
            ],
            $content
        );

        // Crée le fichier final
        file_put_contents($path, $content);
    }

    /**
     * Demande à l'utilisateur le nom du modèle associé et le crée s'il n'existe pas.
     * @param Command $command L'instance de la commande.
     * @return string Le nom du modèle.
     */
    public function interactModelCli(Command $command): string
    {
        $model = $command->ask('Quel est le modèle associé ? (Ex. User)');

        while (!$model) {
            $model = $command->ask('Le nom du modèle est obligatoire :');
        }

        $modelPath = app_path('Models/' . $model . '.php');

        // Si le modèle n'existe pas, on appelle la commande native de Laravel pour le créer
        if (!file_exists($modelPath)) {
            $command->info("Le modèle {$model} n'existe pas, création en cours...");
            $command->callSilent('make:model', ['name' => $model]);
        }

        return $model;
    }

    /**
     * Détermine le namespace final d'une classe en fonction de son dossier de destination.
     * @param string $input Le nom saisi (peut inclure des sous-dossiers, ex: Admin/UserService)
     * @param string $configPath Le chemin de base depuis la config (ex: app/Services)
     * @return string Le namespace formaté (ex: App\Services\Admin)
     */
    public function determineNamespace(string $input, string $configPath): string
    {
        $subDir = dirname($input);

        // Convertit le chemin (ex: app/Services) en base de namespace (App\Services)
        $baseNamespace = str_replace('/', '\\', ucfirst($configPath));
        $baseNamespace = preg_replace('/^app\\\/i', 'App\\', $baseNamespace);

        $namespace = $baseNamespace;

        // Si le service est dans un sous-dossier, on l'ajoute au namespace
        if ($subDir !== '.') {
            $namespace .= "\\" . str_replace(['/', '|', ':'], "\\", $subDir);
        }

        return $namespace;
    }

    /**
     * Demande le nom du contrôleur à générer.
     * @param Command $command L'instance de la commande.
     * @return string Le nom du contrôleur.
     */
    public function getControllerName(Command $command): string
    {
        do {
            $name = $command->ask('Quel est le nom de votre controller ? (Ex. UserController)');
            if (!$name) $command->warn('Le nom du controller est obligatoire');
        } while (!$name);

        return $name;
    }

    /**
     * Demande le nom de la route API associée.
     * @param Command $command L'instance de la commande.
     * @return string Le nom de la route.
     */
    public function getRouteName(Command $command): string
    {
        do {
            $routeName = $command->ask('Quel est le nom de la route associée ? (Ex. users)');
            if (!$routeName) $command->warn('Le nom de la route est obligatoire');
        } while (!$routeName);

        return $routeName;
    }

    /**
     * Demande à l'utilisateur si la route doit être protégée par une authentification.
     * @param Command $command l'instance de la commande.
     * @return bool La valeur de la réponse (true pour oui, false pour non)
     */
    public function isAuthenticatedRoute(Command $command): bool
    {
        return $command->confirm("La route a-t-elle besoin d'être protégée par une authentification (Sanctum) ?", true);
    }

    /**
     * Enregistre la route API dans le fichier de routes.
     * Délègue la manipulation du fichier à la classe RouteRegistrar.
     *
     * @param Command $command L'instance de la commande.
     * @param array $state L'état global contenant les infos du contrôleur et de la route.
     * @return void
     */
    private function registerRoute(Command $command, array $state): void
    {
        $routePath = base_path('routes/service_generator.php');

        $registrar = new RouteRegister($routePath);

        $registrar->prepare($state);

        //Si la route existe déjà, on quitte
        if ($registrar->routeExists()) {
            return;
        }

        $isAuth = $state['isAuthenticated'] ?? false;

        $registrar
            ->when($isAuth, function ($reg) {
                $reg->registerProtected();
            })
            ->unless($isAuth, function ($reg) {
                $reg->registerPublic();
            })
            ->save();
    }

    /**
     * Retourne le nom du fichier de configuration du package.
     * @return string
     */
    public function getConfigName(): string
    {
        return 'crud-service-generator';
    }
}