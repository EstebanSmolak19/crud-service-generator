<?php

use EstebanSmolak19\CrudServiceGenerator\Services\ModelService;
use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

describe('ModelService', function () {

    beforeEach(function () {
        File::deleteDirectory(app_path('Models'));
        File::deleteDirectory(app_path('Generated'));

        File::ensureDirectoryExists(app_path('Models'));
        File::ensureDirectoryExists(app_path('Generated/Models'));

        if (File::exists(base_path('.vscode/settings.json'))) {
            File::delete(base_path('.vscode/settings.json'));
        }
    });

    it('génère un modèle de base et un modèle utilisateur avec la bonne clé primaire personnalisée', function () {
        config(['crud-service-generator.models.whitelist' => []]);
        config(['crud-service-generator.models.excluded_columns' => ['ignored_column']]);

        $this->partialMock(ModelService::class, function ($mock) {
            $mock->shouldReceive('searchTable')->andReturn([['name' => 'entities']]);
            $mock->shouldReceive('getTableColumns')->with('entities')->andReturn(['custom_id', 'field_1', 'ignored_column']);
            $mock->shouldReceive('getPrimaryKey')->with('entities')->andReturn('custom_id');
            $mock->shouldReceive('getTableForeignKeys')->andReturn([]);
        });

        $this->artisan('generate:model')->assertExitCode(0);

        // Vérification des fichiers créés
        expect(File::exists(app_path('Models/Entity.php')))->toBeTrue()
            ->and(File::exists(app_path('Generated/Models/Entity.php')))->toBeTrue();

        $generatedContent = File::get(app_path('Generated/Models/Entity.php'));

        expect($generatedContent)
            ->toContain("protected \$primaryKey = 'custom_id';")
            ->toContain("'field_1'")
            ->not->toContain("'ignored_column'")
            ->toContain('abstract class Entity extends Model');
    });

    it('génère une relation BelongsTo', function () {
        $this->partialMock(ModelService::class, function ($mock) {
            $mock->shouldReceive('searchTable')->andReturn([['name' => 'child_tables']]);
            $mock->shouldReceive('getTableColumns')->andReturn(['id', 'parent_table_id']);
            $mock->shouldReceive('getPrimaryKey')->andReturn('id');

            // child_tables pointe vers parent_tables
            $mock->shouldReceive('getTableForeignKeys')->with('child_tables')->andReturn([
                [
                    'columns' => ['parent_table_id'],
                    'foreign_table' => 'parent_tables',
                ]
            ]);
        });

        $this->artisan('generate:model')->assertExitCode(0);

        $content = File::get(app_path('Generated/Models/ChildTable.php'));

        expect($content)
            ->toContain('use Illuminate\Database\Eloquent\Relations\BelongsTo;')
            ->toContain('public function parentTable(): BelongsTo')
            ->toContain("\$this->belongsTo(\App\Models\ParentTable::class, 'parent_table_id');");
    });

    it('génère une relation HasMany', function () {
        $this->partialMock(ModelService::class, function ($mock) {
            $mock->shouldReceive('searchTable')->andReturn([
                ['name' => 'parent_tables'],
                ['name' => 'child_tables']
            ]);
            $mock->shouldReceive('getTableColumns')->andReturn(['id']);
            $mock->shouldReceive('getPrimaryKey')->andReturn('id');

            $mock->shouldReceive('getTableForeignKeys')->with('parent_tables')->andReturn([]);
            $mock->shouldReceive('getTableForeignKeys')->with('child_tables')->andReturn([
                [
                    'columns' => ['parent_table_id'],
                    'foreign_table' => 'parent_tables',
                ]
            ]);
        });

        $this->artisan('generate:model')->assertExitCode(0);

        $content = File::get(app_path('Generated/Models/ParentTable.php'));

        expect($content)
            ->toContain('use Illuminate\Database\Eloquent\Relations\HasMany;')
            ->toContain('public function childTables(): HasMany')
            ->toContain("\$this->hasMany(\App\Models\ChildTable::class, 'parent_table_id');");
    });

    it('détecte une table pivot et génère le BelongsToMany croisé sans HasMany', function () {
        $this->partialMock(ModelService::class, function ($mock) {
            $mock->shouldReceive('searchTable')->andReturn([
                ['name' => 'table_alphas'],
                ['name' => 'table_betas'],
                ['name' => 'alpha_beta'], // La table pivot
            ]);
            $mock->shouldReceive('getTableColumns')->andReturn(['id']);
            $mock->shouldReceive('getPrimaryKey')->andReturn('id');

            $mock->shouldReceive('getTableForeignKeys')->with('table_alphas')->andReturn([]);
            $mock->shouldReceive('getTableForeignKeys')->with('table_betas')->andReturn([]);

            // La table pivot possède 2 clés étrangères pointant vers Alpha et Beta
            $mock->shouldReceive('getTableForeignKeys')->with('alpha_beta')->andReturn([
                ['columns' => ['alpha_id'], 'foreign_table' => 'table_alphas'],
                ['columns' => ['beta_id'], 'foreign_table' => 'table_betas'],
            ]);
        });

        $this->artisan('generate:model')->assertExitCode(0);

        $alphaContent = File::get(app_path('Generated/Models/TableAlpha.php'));
        $betaContent = File::get(app_path('Generated/Models/TableBeta.php'));

        // Vérification de Alpha -> Betas
        expect($alphaContent)
            ->toContain('use Illuminate\Database\Eloquent\Relations\BelongsToMany;')
            ->toContain('public function tableBetas(): BelongsToMany')
            ->toContain("\$this->belongsToMany(\App\Models\TableBeta::class, 'alpha_beta', 'alpha_id', 'beta_id');")
            ->not->toContain('alphaBetas(): HasMany'); // Pivot ignoré

        // Vérification de Beta -> Alphas
        expect($betaContent)
            ->toContain('public function tableAlphas(): BelongsToMany')
            ->toContain("\$this->belongsToMany(\App\Models\TableAlpha::class, 'alpha_beta', 'beta_id', 'alpha_id');");
    });

    it('respecte le modèle utilisateur existant', function () {
        File::put(app_path('Models/GenericModel.php'), "<?php // CLASS CUSTOM");

        $this->partialMock(ModelService::class, function ($mock) {
            $mock->shouldReceive('searchTable')->andReturn([['name' => 'generic_models']]);
            $mock->shouldReceive('getTableColumns')->andReturn(['id']);
            $mock->shouldReceive('getPrimaryKey')->andReturn('id');
            $mock->shouldReceive('getTableForeignKeys')->andReturn([]);
        });

        $this->artisan('generate:model')->assertExitCode(0);

        expect(File::get(app_path('Models/GenericModel.php')))->toContain('// CLASS CUSTOM');
    });

    it('gère l\'exclusion de la configuration VSCode', function () {
        config(['crud-service-generator.models.hide_base_models_in_vscode' => true]);

        $this->partialMock(ModelService::class, function ($mock) {
            $mock->shouldReceive('searchTable')->andReturn([]);
        });

        $this->artisan('generate:model')->assertExitCode(0);

        $settings = json_decode(File::get(base_path('.vscode/settings.json')), true);

        expect($settings['files.exclude']['app/Models/Base'])->toBeTrue();
    });
});