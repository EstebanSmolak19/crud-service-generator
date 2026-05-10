<?php

use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use EstebanSmolak19\CrudServiceGenerator\Services\ModelService;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

describe('Commande generate:model (Mocked Logic)', function () {

    beforeEach(function () {
        File::deleteDirectory(app_path('Models'));
        File::ensureDirectoryExists(app_path('Models/Base'));

        if (File::exists(base_path('.vscode/settings.json'))) {
            File::delete(base_path('.vscode/settings.json'));
        }
    });

    it("génère un modèle complet avec les bonnes colonnes (fillable) et clé primaire", function () {
            config(['crud-service-generator.models.whitelist' => ['ignored_table']]);
            config(['crud-service-generator.models.excluded_columns' => ['created_at']]);

            $this->partialMock(ModelService::class, function ($mock) {
                $mock->shouldReceive('searchTable')
                    ->andReturn([['name' => 'users']]);

                $mock->shouldReceive('getTableColumns')
                    ->with('users')
                    ->andReturn(['uuid', 'name', 'email', 'password', 'created_at']);

                $mock->shouldReceive('getPrimaryKey')
                    ->with('users')
                    ->andReturn('uuid');

                $mock->shouldReceive('getTableForeignKeys')->andReturn([]);
            });

            //Action
            $this->artisan('generate:model')->assertExitCode(0);

            //Assertions de structure
            expect(File::exists(app_path('Models/IgnoredTable.php')))->toBeFalse()
                ->and(File::exists(app_path('Models/User.php')))->toBeTrue();

            //Assertions de contenu
            $baseContent = File::get(app_path('Models/Base/User.php'));

            expect($baseContent)
                // Vérifie que la clé primaire personnalisée est bien là
                ->toContain("protected \$primaryKey = 'uuid';")

                // Vérifie que les colonnes sont dans le $fillable
                ->toContain("'name'")
                ->toContain("'email'")
                ->toContain("'password'")

                // Vérifie que 'created_at' est bien exclu selon la config d'exclusion
                ->not->toContain("'created_at'");
        });
});