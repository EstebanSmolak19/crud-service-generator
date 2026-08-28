<?php

use EstebanSmolak19\CrudServiceGenerator\Services\RouteRegister;
use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

function getTempRoutePath(): string {
    return __DIR__ . '/temp_api.php';
}

function getMockState(): array {
    return [
        'routeName' => 'UserProfile', // Devra être transformé en 'user-profiles'
        'controllerNamespace' => 'App\Http\Controllers\Api',
        'controllerName' => 'UserProfileController'
    ];
}


beforeEach(function () {
    if (File::exists(getTempRoutePath())) {
        File::delete(getTempRoutePath());
    }
});

afterEach(function () {
    if (File::exists(getTempRoutePath())) {
        File::delete(getTempRoutePath());
    }
});

describe('Initialisation du fichier de routes', function () {

    it('crée le fichier de routes avec les marqueurs de base si le fichier n existe pas', function () {
        new RouteRegister(getTempRoutePath());

        expect(File::exists(getTempRoutePath()))->toBeTrue();

        $content = File::get(getTempRoutePath());
        expect($content)->toContain('// <public_routes>')
            ->and($content)->toContain('// <protected_routes>')
            ->and($content)->toContain('Route::middleware([ForceJsonResponse::class, \'auth:sanctum\'])');
    });

    it('conserve le contenu existant si le fichier de routes existe déjà', function () {
        File::put(getTempRoutePath(), '<?php // Mon contenu personnalisé');

        new RouteRegister(getTempRoutePath());

        $content = File::get(getTempRoutePath());
        expect($content)->toContain('Mon contenu personnalisé')
            ->and($content)->not->toContain('// <public_routes>');
    });
});

describe('Préparation et Vérification d\'existence', function () {

    it('prépare correctement le slug pluriel kebab-case et le FQN du contrôleur', function () {
        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState())
                 ->registerPublic()
                 ->save();

        $content = File::get(getTempRoutePath());

        expect($content)->toContain("Route::serviceCrudResource('user-profiles'");
        expect($content)->toContain("\App\Http\Controllers\Api\UserProfileController::class");
    });

    it('retourne false si la route n a jamais été déclarée', function () {
        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState());

        expect($register->routeExists())->toBeFalse();
    });

    it('retourne true si le controleur est déjà présent dans le fichier', function () {
        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState())->registerPublic()->save();

        $checkRegister = new RouteRegister(getTempRoutePath());
        $checkRegister->prepare(getMockState());

        expect($checkRegister->routeExists())->toBeTrue();
    });
});

describe('Injection des routes Publiques', function () {

    it('injecte la route publique juste avant le marqueur <public_routes>', function () {
        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState())->registerPublic()->save();

        $content = File::get(getTempRoutePath());
        $expectedLine = "Route::serviceCrudResource('user-profiles', \App\Http\Controllers\Api\UserProfileController::class);\n// <public_routes>";

        expect($content)->toContain($expectedLine);
    });

    it('ajoute la route publique à la fin du fichier si le marqueur <public_routes> est introuvable', function () {
        File::put(getTempRoutePath(), "<?php\n// Custom file\n");

        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState())->registerPublic()->save();

        $content = File::get(getTempRoutePath());
        $expectedLine = "Route::serviceCrudResource('user-profiles', \App\Http\Controllers\Api\UserProfileController::class);\n";

        expect($content)->toEndWith($expectedLine);
    });
});

describe('Injection des routes Protégées', function () {

    it('injecte la route protégée juste avant le marqueur <protected_routes>', function () {
        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState())->registerProtected()->save();

        $content = File::get(getTempRoutePath());
        $expectedLine = "    Route::serviceCrudResource('user-profiles', \App\Http\Controllers\Api\UserProfileController::class);\n    // <protected_routes>";

        expect($content)->toContain($expectedLine);
    });

    it('crée un groupe middleware sanctum complet si le marqueur <protected_routes> est introuvable', function () {
        File::put(getTempRoutePath(), "<?php\n// Custom file\n");

        $register = new RouteRegister(getTempRoutePath());
        $register->prepare(getMockState())->registerProtected()->save();

        $content = File::get(getTempRoutePath());

        expect($content)->toContain("Route::middleware('auth:sanctum')->group(function () {")
            ->and($content)->toContain("    Route::serviceCrudResource('user-profiles', \App\Http\Controllers\Api\UserProfileController::class);")
            ->and($content)->toContain("});");
    });
});

describe('Chaînage (Conditionable)', function () {

    it('permet de chainer les méthodes grace au trait Conditionable', function () {
        $register = new RouteRegister(getTempRoutePath());

        $result = $register->prepare(getMockState())
                           ->when(true, fn($r) => $r->registerPublic())
                           ->when(false, fn($r) => $r->registerProtected());

        expect($result)->toBeInstanceOf(RouteRegister::class);

        $result->save();
        $content = File::get(getTempRoutePath());

        expect($content)->toContain('// <public_routes>')
            ->and($content)->toContain('user-profiles')
            ->and($content)->toContain('// <protected_routes>')
            ->and($content)->not->toContain("    Route::serviceCrudResource('user-profiles', \App\Http\Controllers\Api\UserProfileController::class);\n    // <protected_routes>");
    });
});