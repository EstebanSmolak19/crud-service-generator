<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ModelService implements IModelService
{
    public array $whitelist {
        get { return array_merge(config('crud-service-generator.models.whitelist', [])); }
    }

    public array $excludedColumns {
        get { return array_merge(config('crud-service-generator.models.excluded_columns', [])); }
    }

    public function searchTable(): array
    {
        $db_connexion = config('database.default');
        $db_name = config("database.connections.{$db_connexion}.database");
        $tables = Schema::getTables();

        return array_filter($tables, function ($table) use ($db_name) {
            $isGoodSchema = !isset($table['schema']) || $table['schema'] === $db_name;
            return $isGoodSchema && !in_array($table['name'], $this->whitelist);
        });
    }

    public function generateModels(): void
    {
        $allTables = $this->searchTable();
        $this->ensureDirectoriesExist();

        foreach ($allTables as $table) {
            $this->generateSingleModel($table, $allTables);
        }

        if (config('crud-service-generator.models.hide_base_models_in_vscode', false)) {
            $this->hideBaseModelsInVsCode();
        }
    }

    /**
     * Masque le dossier Base dans VS Code pour ne pas polluer l'explorateur
     */
    private function hideBaseModelsInVsCode(): void
    {
        $vscodePath = base_path('.vscode');
        $settingsPath = "{$vscodePath}/settings.json";

        //Créer le dossier .vscode s'il n'existe pas
        if (!File::isDirectory($vscodePath)) {
            File::makeDirectory($vscodePath, 0777, true);
        }

        //Lire le contenu actuel s'il existe
        $settings = [];
        if (File::exists($settingsPath)) {
            $settings = json_decode(File::get($settingsPath), true) ?? [];
        }

        // Ajouter l'exclusion du dossier Base
        // On utilise la clé "files.exclude" native de VS Code
        $settings['files.exclude']['app/Models/Base'] = true;

        //Sauvegarder proprement
        File::put(
            $settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Gère la création des dossiers nécessaires
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            app_path("Models"),
            app_path("Models/Base")
        ];

        foreach ($directories as $directory) {
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0777, true);
            }
        }
    }

    /**
     * Logique de génération pour une table spécifique
     */
    private function generateSingleModel(array $table, array $allTables): void
    {
        $tableName = $table['name'];
        $className = Str::studly(Str::singular($tableName));

        $stubPath = __DIR__ . '/../stubs/Model.stub';
        if (!File::exists($stubPath)) return;

        // Préparation des données
        $data = $this->prepareModelData($tableName, $allTables);
        $stubContent = File::get($stubPath);

        // 1. Génération du Base Model (Toujours écrasé)
        $this->writeBaseModel($className, $tableName, $data, $stubContent);

        // 2. Génération du Model Utilisateur (Si inexistant)
        $this->writeUserModel($className);
    }

    /**
     * Prépare toutes les variables nécessaires au stub
     */
    private function prepareModelData(string $tableName, array $allTables): array
    {
        $primaryKey = $this->getPrimaryKey($tableName);
        $allColumns = $this->getTableColumns($tableName);
        $fillableColumns = array_diff($allColumns, $this->excludedColumns);

        $imports = [];
        $relations = $this->generateAllRelations($tableName, $allTables, $imports);

        return [
            'primaryKeyLine' => ($primaryKey !== 'id') ? "\n    protected \$primaryKey = '{$primaryKey}';" : "",
            'fillableString' => "\n        '" . implode("',\n        '", $fillableColumns) . "',\n    ",
            'useString' => implode("\n", array_unique($imports)),
            'relations' => $relations
        ];
    }

    /**
     * Écrit le fichier de base (Base Model)
     */
    private function writeBaseModel(string $className, string $tableName, array $data, string $stubContent): void
    {
        $content = str_replace(
            ['{{ namespace }}', '{{ imports }}', '{{ class }}', '{{ table }}', '{{ primaryKey }}', '{{ fillable }}', '{{ relations }}'],
            ["App\\Models\\Base", $data['useString'], $className, $tableName, $data['primaryKeyLine'], $data['fillableString'], $data['relations']],
            $stubContent
        );

        // Remplacement pour rendre la classe abstraite
        $content = str_replace("class {$className}", "abstract class {$className}", $content);

        File::put(app_path("Models/Base/{$className}.php"), $content);
    }

    /**
     * Écrit le fichier extensible par l'utilisateur
     */
    private function writeUserModel(string $className): void
    {
        $path = app_path("Models/{$className}.php");

        if (!File::exists($path)) {
            $content = "<?php\n\nnamespace App\Models;\n\nuse App\Models\Base\\{$className} as Base{$className};\n\nclass {$className} extends Base{$className}\n{\n    // Ajoutez votre logique personnalisée ici\n}\n";
            File::put($path, $content);
        }
    }

    private function generateAllRelations(string $currentTable, array $allTables, array &$imports): string
    {
        $content = "";

        // BELONGSTO
        foreach (Schema::getForeignKeys($currentTable) as $fk) {
            $localColumn = $fk['columns'][0];
            $foreignTable = $fk['foreign_table'];

            $methodName = Str::camel(preg_replace('/(_id|id_)/i', '', $localColumn));
            $relatedModel = "\\App\\Models\\" . Str::studly(Str::singular($foreignTable));

            $imports[] = "use Illuminate\Database\Eloquent\Relations\BelongsTo;";

            $content .= "\n    public function {$methodName}(): BelongsTo\n";
            $content .= "    {\n";
            $content .= "        return \$this->belongsTo({$relatedModel}::class, '{$localColumn}');\n";
            $content .= "    }\n";
        }

        // HASMANY
        foreach ($allTables as $otherTable) {
            $otherTableName = $otherTable['name'];
            if ($otherTableName === $currentTable) continue;

            foreach (Schema::getForeignKeys($otherTableName) as $fk) {
                if ($fk['foreign_table'] === $currentTable) {
                    $foreignColumn = $fk['columns'][0];

                    $methodName = Str::camel(Str::plural(Str::singular($otherTableName)));
                    $relatedModel = "\\App\\Models\\" . Str::studly(Str::singular($otherTableName));

                    $imports[] = "use Illuminate\Database\Eloquent\Relations\HasMany;";

                    $content .= "\n    public function {$methodName}(): HasMany\n";
                    $content .= "    {\n";
                    $content .= "        return \$this->hasMany({$relatedModel}::class, '{$foreignColumn}');\n";
                    $content .= "    }\n";
                }
            }
        }

        return $content;
    }

    public function getTableColumns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    public function getTableForeignKeys(string $table): array
    {
        return Schema::getForeignKeys($table);
    }

    public function getPrimaryKey(string $table): string
    {
        $primaryKey = Schema::getIndexes($table);

        foreach ($primaryKey as $index) {
            if ($index['primary']) {
                return $index['columns'][0];
            }
        }

        return 'id';
    }
}