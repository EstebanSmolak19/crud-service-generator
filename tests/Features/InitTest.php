<?php

use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

describe('InitCommand', function () {

    beforeEach(function () {
        // Nettoyage avant chaque test
        $configPath = config_path('crud-service-generator.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        // Nettoyage des migrations de test générées
        $migrationFiles = File::glob(database_path('migrations/*_create_crud_service_generator_table.php'));
        foreach ($migrationFiles as $file) {
            File::delete($file);
        }
    });

    afterEach(function () {
        // Nettoyage après chaque test
        $configPath = config_path('crud-service-generator.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $migrationFiles = File::glob(database_path('migrations/*_create_crud_service_generator_table.php'));
        foreach ($migrationFiles as $file) {
            File::delete($file);
        }
    });

    it('exécute la commande avec succès, publie la config et la migration', function () {
        $this->artisan('package:init')
            ->expectsConfirmation('Est-ce que votre application utilise des UUIDs pour vos modèles/utilisateurs ?', 'no')
            ->assertExitCode(0);

        // Vérification de la présence physique du fichier de configuration
        $configPath = config_path('crud-service-generator.php');
        expect(File::exists($configPath))->toBeTrue();

        $configContent = File::get($configPath);
        expect($configContent)->toContain("'use_uuids' => false");

        // Vérification approfondie de la publication des migrations
        $migrationFiles = File::glob(database_path('migrations/*_create_crud_service_generator_table.php'));

        expect($migrationFiles)
            ->not->toBeEmpty()
            ->and(count($migrationFiles))->toBe(1);

        // Vérification du contenu du fichier de migration publié
        $migrationContent = File::get($migrationFiles[0]);
        expect($migrationContent)
            ->toContain('extends Migration')
            ->toContain('Schema::create');
    });

    it('active correctement use_uuids à true et publie bien la migration', function () {
        $this->artisan('package:init')
            ->expectsConfirmation('Est-ce que votre application utilise des UUIDs pour vos modèles/utilisateurs ?', 'yes')
            ->assertExitCode(0);

        $configPath = config_path('crud-service-generator.php');
        expect(File::exists($configPath))->toBeTrue();

        $configContent = File::get($configPath);
        expect($configContent)->toContain("'use_uuids' => true");

        // Vérification que la migration est également présente lors de ce parcours
        $migrationFiles = File::glob(database_path('migrations/*_create_crud_service_generator_table.php'));
        expect($migrationFiles)->not->toBeEmpty();
    });

    it('gère proprement le cas où la config existe déjà tout en publiant la migration', function () {
        $configPath = config_path('crud-service-generator.php');

        // Simulation d'un fichier de config existant
        File::ensureDirectoryExists(dirname($configPath));
        File::put($configPath, "<?php\n\nreturn [\n    'use_uuids' => false,\n];");

        $this->artisan('package:init')
            ->expectsConfirmation('Est-ce que votre application utilise des UUIDs pour vos modèles/utilisateurs ?', 'yes')
            ->assertExitCode(0);

        // Vérifications de la mise à jour de la config et de la présence de la migration
        expect(File::exists($configPath))->toBeTrue();
        expect(File::get($configPath))->toContain("'use_uuids' => true");

        $migrationFiles = File::glob(database_path('migrations/*_create_crud_service_generator_table.php'));
        expect($migrationFiles)->not->toBeEmpty();
    });
});
