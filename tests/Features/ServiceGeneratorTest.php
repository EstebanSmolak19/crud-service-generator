<?php

use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

/**
 * TESTS DES SERVICES SIMPLES
 */
describe('Génération de Services Simples', function () {

    beforeEach(function () {
        // Nettoyage du dossier Services avant chaque test
        if (File::isDirectory(app_path('Services'))) {
            File::deleteDirectory(app_path('Services'));
        }
    });

    it("fonctionne via l'interaction ask()", function() {
        $this->artisan('make:service')
             ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', 'AskService')
             ->assertExitCode(0);

        expect(File::exists(app_path('Services/AskService.php')))->toBeTrue();
    });

    it("fonctionne via l'argument direct", function() {
        $this->artisan('make:service DirectService')
             ->assertExitCode(0);

        expect(File::exists(app_path('Services/DirectService.php')))->toBeTrue();
    });

    it("redemande un nom si le service existe déjà", function() {
        $existingName = 'ExistingService';
        $newName = 'NewService';

        // On crée le conflit
        File::ensureDirectoryExists(app_path('Services'));
        File::put(app_path("Services/{$existingName}.php"), "<?php");

        $this->artisan('make:service')
             ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', $existingName)
             ->expectsOutput("Le service {$existingName} existe déjà !")
             ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', $newName)
             ->assertExitCode(0);

        expect(File::exists(app_path("Services/{$newName}.php")))->toBeTrue();
    });

    it("vérifie le contenu du service généré", function () {
        $this->artisan('make:service UserService');

        $content = File::get(app_path('Services/UserService.php'));

        // On vérifie les points critiques
        expect($content)
            ->toContain('namespace App\Services;')
            ->toContain('class UserService')
            ->not->toContain('{{ class }}');
    });
});

/**
 * TESTS DES SERVICES CRUD
 */
describe('Génération de Services CRUD', function () {

    beforeEach(function () {
        // Nettoyage Services et Models
        foreach ([app_path('Services'), app_path('Models')] as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
            File::ensureDirectoryExists($path);
        }
    });

    it("fonctionne via l'interaction ask() et sans model existant", function() {
        $this->artisan('make:service --crud')
            ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', 'AskService')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'Ask')
            ->expectsOutput("Le modèle Ask n'existe pas, création en cours...")
            ->expectsOutput("✅ Composants générés avec succès !")
            ->assertExitCode(0);

        expect(File::exists(app_path('Services/AskService.php')))->toBeTrue()
            ->and(File::exists(app_path('Models/Ask.php')))->toBeTrue();
    });

    it("fonctionne avec un modèle déjà existant", function() {
        $modelName = 'User';
        $modelPath = app_path("Models/{$modelName}.php");

        // Simulation du modèle existant
        File::put($modelPath, "<?php namespace App\Models; class {$modelName} {}");

        $this->artisan('make:service --crud')
            ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', 'CrudService')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', $modelName)
            ->doesntExpectOutput("Le modèle {$modelName} n'existe pas, création en cours...")
            ->expectsOutput("✅ Composants générés avec succès !")
            ->assertExitCode(0);

        expect(File::exists(app_path('Services/CrudService.php')))->toBeTrue();
    });

    it("fonctionne avec le nom passé directement en argument", function() {
        $serviceName = 'DirectCrudService';
        $modelName = 'Post';

        File::put(app_path("Models/{$modelName}.php"), "<?php");

        $this->artisan("make:service {$serviceName} --crud")
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', $modelName)
            ->expectsOutput("✅ Composants générés avec succès !")
            ->assertExitCode(0);

        expect(File::exists(app_path("Services/{$serviceName}.php")))->toBeTrue();
    });

    it("redemande un nom si le service existe déjà en mode CRUD", function() {
        $existingName = 'ConflictService';
        $validName = 'FinalService';
        $modelName = 'User';

        File::put(app_path("Services/{$existingName}.php"), "<?php");
        File::put(app_path("Models/{$modelName}.php"), "<?php");

        $this->artisan('make:service --crud')
             ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', $existingName)
             ->expectsOutput("Le service {$existingName} existe déjà !")
             ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', $validName)
             ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', $modelName)
             ->expectsOutput("✅ Composants générés avec succès !")
             ->assertExitCode(0);

        expect(File::exists(app_path("Services/{$validName}.php")))->toBeTrue();
    });

    it("vérifie le contenu du service CRUD généré", function () {
        $this->artisan('make:service UserService --crud')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'User')
            ->expectsOutput("Le modèle User n'existe pas, création en cours...")
            ->assertExitCode(0);

        $content = File::get(app_path('Services/UserService.php'));

        // On vérifie les points critiques du stub CRUD
        expect($content)
            ->toContain('namespace App\Services;')
            ->toContain('class UserService extends CrudServiceBase')
            ->toContain('implements IFillableContract')
            ->toContain('User $model')
            ->not->toContain('{{ class }}');
    });
});

/**
 * TESTS DES CONTROLLERS
 */
describe('Génération de Services avec Controller', function () {

    beforeEach(function () {
        // Nettoyage Services et Controllers
        foreach ([app_path('Services'), app_path('Http/Controllers')] as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
            File::ensureDirectoryExists($path);
        }

        // On s'assure que le fichier de routes temporaire n'existe pas
        if (File::exists(base_path('routes/service_generator.php'))) {
            File::delete(base_path('routes/service_generator.php'));
        }
    });

    it("génère un service et un contrôleur simple", function () {
        $serviceName = 'ControllerService';
        $controllerName = 'TestController';

        $this->artisan("make:service {$serviceName} --controller")
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $controllerName)
            ->expectsOutput("✅ Composants générés avec succès !")
            ->assertExitCode(0);

        // Vérification des fichiers
        expect(File::exists(app_path("Services/{$serviceName}.php")))->toBeTrue()
            ->and(File::exists(app_path("Http/Controllers/{$controllerName}.php")))->toBeTrue();

        // Vérification cruciale : PAS de routes générées (car pas de --all)
        expect(File::exists(base_path('routes/service_generator.php')))->toBeFalse();
    });

    it("redemande le nom du contrôleur s'il est vide", function () {
        $serviceName = 'RetryControllerService';
        $validControllerName = 'ValidController';

        $this->artisan("make:service {$serviceName} --controller")
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', '') // Vide
            ->expectsOutput('Le nom du controller est obligatoire') // Message de ton CommandService
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $validControllerName)
            ->assertExitCode(0);

        expect(File::exists(app_path("Http/Controllers/{$validControllerName}.php")))->toBeTrue();
    });

    it("redemande le nom du service même avec l'option --controller", function () {
        $existingService = 'DuplicateService';
        $newService = 'UniqueService';
        $controllerName = 'UniqueController';

        // Créer le doublon
        File::ensureDirectoryExists(app_path('Services'));
        File::put(app_path("Services/{$existingService}.php"), "<?php");

        $this->artisan('make:service --controller')
            ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', $existingService)
            ->expectsOutput("Le service {$existingService} existe déjà !")
            ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', $newService)
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $controllerName)
            ->assertExitCode(0);

        expect(File::exists(app_path("Services/{$newService}.php")))->toBeTrue()
            ->and(File::exists(app_path("Http/Controllers/{$controllerName}.php")))->toBeTrue();
    });

    it("vérifie le contenu du controller généré", function () {
        $serviceName = 'ControllerService';
        $controllerName = 'TestController';

        $this->artisan("make:service {$serviceName} --controller")
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $controllerName)
            ->expectsOutput("✅ Composants générés avec succès !")
            ->assertExitCode(0);

        $content = File::get(app_path("Http/Controllers/{$controllerName}.php"));

        expect($content)
            ->toContain('namespace App\Http\Controllers;')
            ->toContain("class $controllerName")
            ->toContain("extends Controller")
            ->toContain("private $serviceName")
            ->not->toContain('{{ class }}')
            ->not->toContain('CrudControllerBase');
    });
});

/**
 * TESTS DU MODE COMPLET (--all)
 */
describe('Génération Complète (--all)', function () {

    beforeEach(function () {
        $folders = [
            app_path('Services'),
            app_path('Models'),
            app_path('Http/Controllers'),
            base_path('routes')
        ];

        foreach ($folders as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
            File::ensureDirectoryExists($path);
        }

        if (File::exists(base_path('routes/service_generator.php'))) {
            File::delete(base_path('routes/service_generator.php'));
        }
    });

    it("génère l'ensemble des composants avec l'option --all", function () {
        $serviceName = 'PostService';
        $controllerName = 'PostController';
        $routeName = 'posts';
        $modelName = 'Post';

        $this->artisan("make:service {$serviceName} --all")
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $controllerName)
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', $routeName)
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', $modelName)
            ->expectsOutput("Le modèle {$modelName} n'existe pas, création en cours...")
            ->expectsOutput("✅ Composants générés avec succès !")
            ->assertExitCode(0);

        //Vérification des fichiers physiques
        expect(File::exists(app_path("Services/{$serviceName}.php")))->toBeTrue()
            ->and(File::exists(app_path("Http/Controllers/{$controllerName}.php")))->toBeTrue()
            ->and(File::exists(app_path("Models/{$modelName}.php")))->toBeTrue()
            ->and(File::exists(base_path('routes/service_generator.php')))->toBeTrue();

        //Vérification du contenu du Controller (doit être un CRUD)
        $controllerContent = File::get(app_path("Http/Controllers/{$controllerName}.php"));
        expect($controllerContent)->toContain('extends CrudControllerBase');

        //Vérification de l'enregistrement de la Route
        $routeContent = File::get(base_path('routes/service_generator.php'));
        expect($routeContent)
            ->toContain("use Illuminate\Support\Facades\Route;")
            ->toContain("Route::apiResource('posts', \App\Http\Controllers\PostController::class);");
    });

    it("redemande le nom de la route si elle est vide en mode --all", function () {
        $serviceName = 'RouteRetryService';

        $this->artisan("make:service {$serviceName} --all")
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', 'RetryController')
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', '') // Vide la première fois
            ->expectsOutput('Le nom de la route est obligatoire') // Ton message de validation
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', 'valid-route')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'User')
            ->assertExitCode(0);

        $routeContent = File::get(base_path('routes/service_generator.php'));
        expect($routeContent)->toContain("'valid-routes'"); // Pluralisé par Str::plural
    });

    it("n'écrase pas le fichier de routes existant mais ajoute à la fin", function () {
        // Pré-création du fichier de routes avec une route existante
        File::ensureDirectoryExists(base_path('routes'));
        $initialContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::get('test', fn() => 'ok');\n";
        File::put(base_path('routes/service_generator.php'), $initialContent);

        $this->artisan("make:service FirstService --all")
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', 'FirstController')
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', 'first')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'User')
            ->assertExitCode(0);

        $content = File::get(base_path('routes/service_generator.php'));

        // On vérifie que l'ancien contenu est toujours là
        expect($content)->toContain("Route::get('test'");

        // On vérifie le nouveau contenu.
        expect($content)->toContain("Route::apiResource('firsts'");
    });
});