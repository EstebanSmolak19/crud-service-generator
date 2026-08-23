<?php

use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

beforeEach(function () {
    $folders = [
        app_path('Services'),
        app_path('Models'),
        app_path('Http/Controllers'),
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

/**
 * TESTS DES SERVICES SIMPLES (Mode: 'service')
 */
describe('Génération de Services Simples', function () {

    it("fonctionne via l'interaction ask() sans argument", function () {
        $this->artisan('make:service')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'service')
            ->expectsQuestion('Quel est le nom de votre service ? (Ex. UserService)', 'AskService')
            ->expectsOutput('✅ Composants générés avec succès !')
            ->assertExitCode(0);

        expect(File::exists(app_path('Services/AskService.php')))->toBeTrue();
    });

    it("fonctionne via l'argument direct", function () {
        $this->artisan('make:service DirectService')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'service')
            ->expectsOutput('✅ Composants générés avec succès !')
            ->assertExitCode(0);

        expect(File::exists(app_path('Services/DirectService.php')))->toBeTrue();
    });

    it('bloque la génération si le service existe déjà (FileExistenceChecker)', function () {
        $existingName = 'ExistingService';

        // On crée le conflit physique
        File::put(app_path("Services/{$existingName}.php"), '<?php');

        $this->artisan("make:service {$existingName}")
            ->expectsQuestion('Quels modes choisissez-vous ?', 'service')
            // Remarque : Ton FileExistenceChecker met des crochets autour du nom : [ExistingService]
            ->expectsOutput("Le service [{$existingName}] existe déjà !")
            ->assertExitCode(1); // Ton handle() retourne Command::FAILURE
    });

    it('vérifie le contenu du service généré', function () {
        $this->artisan('make:service UserService')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'service');

        $content = File::get(app_path('Services/UserService.php'));

        expect($content)
            ->toContain('namespace App\Services;')
            ->toContain('class UserService')
            ->not->toContain('{{ class }}');
    });
});

/**
 * TESTS DES SERVICES CRUD (Mode: 'CRUD')
 */
describe('Génération de Services CRUD', function () {

    it("génère un CRUD et crée le modèle s'il n'existe pas", function () {
        $this->artisan('make:service CrudService')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'CRUD')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'MissingModel')
            ->expectsOutput("Le modèle MissingModel n'existe pas, création en cours...")
            ->expectsOutput('✅ Composants générés avec succès !')
            ->assertExitCode(0);

        expect(File::exists(app_path('Services/CrudService.php')))->toBeTrue()
            ->and(File::exists(app_path('Models/MissingModel.php')))->toBeTrue();
    });

    it('utilise un modèle déjà existant sans le recréer', function () {
        $modelName = 'User';
        File::put(app_path("Models/{$modelName}.php"), "<?php namespace App\Models; class {$modelName} {}");

        $this->artisan('make:service ExistingCrudService')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'CRUD')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', $modelName)
            ->doesntExpectOutput("Le modèle {$modelName} n'existe pas, création en cours...")
            ->assertExitCode(0);

        expect(File::exists(app_path('Services/ExistingCrudService.php')))->toBeTrue();
    });
});

/**
 * TESTS DES CONTROLLERS (Mode: 'controllers')
 */
describe('Génération de Services avec Controller', function () {

    it('génère un service et un contrôleur simple (sans route ni modèle)', function () {
        $serviceName = 'ControllerService';
        $controllerName = 'TestController';

        $this->artisan("make:service {$serviceName}")
            ->expectsQuestion('Quels modes choisissez-vous ?', 'controllers')
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $controllerName)
            ->expectsOutput('✅ Composants générés avec succès !')
            ->assertExitCode(0);

        // Vérification des fichiers
        expect(File::exists(app_path("Services/{$serviceName}.php")))->toBeTrue()
            ->and(File::exists(app_path("Http/Controllers/{$controllerName}.php")))->toBeTrue();

        // Vérification cruciale : PAS de routes ni modèle générés
        expect(File::exists(base_path('routes/service_generator.php')))->toBeFalse();
    });

    it("redemande le nom du contrôleur s'il est vide", function () {
        $serviceName = 'RetryControllerService';
        $validControllerName = 'ValidController';

        $this->artisan("make:service {$serviceName}")
            ->expectsQuestion('Quels modes choisissez-vous ?', 'controllers')
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', '') // Vide
            ->expectsOutput('Le nom du controller est obligatoire')
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $validControllerName)
            ->assertExitCode(0);

        expect(File::exists(app_path("Http/Controllers/{$validControllerName}.php")))->toBeTrue();
    });
});

/**
 * TESTS DU MODE COMPLET (Mode: 'tous')
 */
describe('Génération Complète (--all / tous)', function () {

    it("génère l'ensemble des composants avec la route protégée (Sanctum)", function () {
        $serviceName = 'PostService';
        $controllerName = 'PostController';
        $routeName = 'posts';
        $modelName = 'Post';

        $this->artisan("make:service {$serviceName}")
            ->expectsQuestion('Quels modes choisissez-vous ?', 'tous')
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', $controllerName)
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', $routeName)
            // Test de la nouvelle question Sanctum (Oui)
            ->expectsConfirmation("La route a-t-elle besoin d'être protégée par une authentification (Sanctum) ?", 'yes')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', $modelName)
            ->expectsOutput('✅ Composants générés avec succès !')
            ->assertExitCode(0);

        // Vérification des fichiers
        expect(File::exists(app_path("Services/{$serviceName}.php")))->toBeTrue()
            ->and(File::exists(app_path("Http/Controllers/{$controllerName}.php")))->toBeTrue()
            ->and(File::exists(app_path("Models/{$modelName}.php")))->toBeTrue()
            ->and(File::exists(base_path('routes/service_generator.php')))->toBeTrue();

        // Vérification de la Route protégée
        $routeContent = File::get(base_path('routes/service_generator.php'));
        expect($routeContent)
            ->toContain("Route::middleware([ForceJsonResponse::class, 'auth:sanctum'])->group(")
            ->toContain("Route::apiResource('posts', \App\Http\Controllers\PostController::class);");
    });

    it("génère l'ensemble des composants avec la route publique", function () {
        $this->artisan('make:service PublicService')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'tous')
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', 'PublicController')
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', 'public-route')
            // Test de la nouvelle question Sanctum (Non)
            ->expectsConfirmation("La route a-t-elle besoin d'être protégée par une authentification (Sanctum) ?", 'no')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'PublicModel')
            ->assertExitCode(0);

        $routeContent = File::get(base_path('routes/service_generator.php'));

        // On vérifie que la route a été placée dans les routes publiques, sans l'indentation du middleware
        expect($routeContent)
            ->toContain("Route::apiResource('public-routes', \App\Http\Controllers\PublicController::class);") // Pluralisé automatiquement
            ->toContain('// <public_routes>');
    });

    it("n'écrase pas le fichier de routes existant mais ajoute intelligemment grâce au RouteRegister", function () {
        File::ensureDirectoryExists(base_path('routes'));

        // Fichier contenant déjà une route publique
        $initialContent = <<<PHP
        <?php
        // <public_routes>
        Route::apiResource('existings', \App\Http\Controllers\ExistingController::class);
        PHP;
        File::put(base_path('routes/service_generator.php'), $initialContent);

        $this->artisan('make:service FirstService')
            ->expectsQuestion('Quels modes choisissez-vous ?', 'tous')
            ->expectsQuestion('Quel est le nom de votre controller ? (Ex. UserController)', 'FirstController')
            ->expectsQuestion('Quel est le nom de la route associée ? (Ex. users)', 'first')
            ->expectsConfirmation("La route a-t-elle besoin d'être protégée par une authentification (Sanctum) ?", 'no')
            ->expectsQuestion('Quel est le modèle associé ? (Ex. User)', 'User')
            ->assertExitCode(0);

        $content = File::get(base_path('routes/service_generator.php'));

        expect($content)->toContain("Route::apiResource('existings'");
        expect($content)->toContain("Route::apiResource('firsts', \App\Http\Controllers\FirstController::class);");
    });
});
