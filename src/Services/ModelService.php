<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelService implements IModelService
{
    /**
     * Cache local pour stocker les métadonnées de la base de données
     * et éviter les requêtes SQL en boucle.
     */
    private array $schemaCache = [
        'foreignKeys' => [],
        'columns'     => [],
        'indexes'     => [],
    ];

    /**
     * Liste des tables à exclure lors de la génération des modèles.
     */
    public array $whitelist {
        get {
            return array_merge(config('crud-service-generator.models.whitelist', []));
        }
    }

    /**
     * Liste des colonnes à exclure systématiquement de la propriété $fillable
     * des modèles générés (ex: id, created_at, updated_at).
     */
    public array $excludedColumns {
        get {
            return array_merge(config('crud-service-generator.models.excluded_columns', []));
        }
    }

    /**
     * Récupère et filtre la liste des tables de la base de données actuelle.
     *
     * @return array Liste des tables valides pour la génération.
     */
    public function searchTable(): array
    {
        $db_connexion = config('database.default');
        $db_name = config("database.connections.{$db_connexion}.database");
        $tables = Schema::getTables();

        return array_filter($tables, function ($table) use ($db_name) {
            $isGoodSchema = ! isset($table['schema']) || $table['schema'] === $db_name;

            return $isGoodSchema && ! in_array($table['name'], $this->whitelist);
        });
    }

    /**
     * Point d'entrée principal. Précharge toutes les métadonnées en mémoire,
     * crée les dossiers, et lance la génération en un éclair.
     */
    public function generateModels(): void
    {
        $allTables = $this->searchTable();
        $this->ensureDirectoriesExist();

        // 🚀 Pré-chargement global de toutes les métadonnées (Évite les requêtes en boucle N²)
        foreach ($allTables as $table) {
            $tableName = $table['name'];
            $this->schemaCache['foreignKeys'][$tableName] = Schema::getForeignKeys($tableName);
            $this->schemaCache['columns'][$tableName] = Schema::getColumnListing($tableName);
            $this->schemaCache['indexes'][$tableName] = Schema::getIndexes($tableName);
        }

        foreach ($allTables as $table) {
            $this->generateSingleModel($table, $allTables);
        }

    }


    /**
     * S'assure que les répertoires de destination pour les modèles existent.
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            app_path('Models'),
            app_path('Generated/Models'),
        ];

        foreach ($directories as $directory) {
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0777, true);
            }
        }
    }

    /**
     * Génération d'un modèle pour une table spécifique.
     */
    private function generateSingleModel(array $table, array $allTables): void
    {
        $tableName = $table['name'];
        $className = Str::studly(Str::singular($tableName));

        $stubPath = __DIR__.'/../stubs/Model.stub';
        if (! File::exists($stubPath)) {
            return;
        }

        $data = $this->prepareModelData($tableName, $allTables);
        $stubContent = File::get($stubPath);

        $this->writeBaseModel($className, $tableName, $data, $stubContent);
        $this->writeUserModel($className);
    }

    /**
     * Prépare toutes les variables dynamiques (clés primaires, colonnes, imports, relations, traits).
     */
    private function prepareModelData(string $tableName, array $allTables): array
    {
        $primaryKey = $this->getPrimaryKey($tableName);
        $allColumns = $this->getTableColumns($tableName);
        $fillableColumns = array_diff($allColumns, $this->excludedColumns);

        $imports = [];
        $traits = [];

        if (config('crud-service-generator.use_uuids', false)) {
            $imports[] = "use Illuminate\Database\Eloquent\Concerns\HasUuids;";
            $traits[] = "use HasUuids;";
        }

        $relations = $this->generateAllRelations($tableName, $allTables, $imports);
        $traitsString = !empty($traits) ? "\n    " . implode("\n    ", $traits) . "\n" : '';

        return [
            'primaryKeyLine' => ($primaryKey !== 'id') ? "\n    protected \$primaryKey = '{$primaryKey}';" : '',
            'fillableString' => "\n        '".implode("',\n        '", $fillableColumns)."',\n    ",
            'useString'      => implode("\n", array_unique($imports)),
            'traits'         => $traitsString,
            'relations'      => $relations,
        ];
    }

    /**
     * Écrit le fichier du modèle de base dans le dossier Generated.
     */
    private function writeBaseModel(string $className, string $tableName, array $data, string $stubContent): void
    {
        $content = str_replace(
            ['{{ namespace }}', '{{ imports }}', '{{ class }}', '{{ traits }}', '{{ table }}', '{{ primaryKey }}', '{{ fillable }}', '{{ relations }}'],
            ['App\\Generated\\Models', $data['useString'], $className, $data['traits'], $tableName, $data['primaryKeyLine'], $data['fillableString'], $data['relations']],
            $stubContent
        );

        $content = str_replace("class {$className}", "abstract class {$className}", $content);

        File::put(app_path("Generated/Models/{$className}.php"), $content);
    }

    /**
     * Écrit le fichier du modèle utilisateur s'il n'existe pas déjà.
     */
    private function writeUserModel(string $className): void
    {
        $path = app_path("Models/{$className}.php");

        if (! File::exists($path)) {
            $content = "<?php\n\nnamespace App\Models;\n\nuse App\Generated\Models\\{$className} as Generated{$className};\n\nclass {$className} extends Generated{$className}\n{\n    // Ajoutez votre logique personnalisée ici\n}\n";
            File::put($path, $content);
        }
    }

    /**
     * Chef d'orchestre de la génération des relations.
     */
    private function generateAllRelations(string $currentTable, array $allTables, array &$imports): string
    {
        $content = '';

        $content .= $this->generateBelongsTo($currentTable, $imports);
        $content .= $this->generateHasMany($currentTable, $allTables, $imports);
        $content .= $this->generateBelongsToMany($currentTable, $allTables, $imports);

        return $content;
    }

    /**
     * Génère les méthodes de relation BelongsTo.
     */
    private function generateBelongsTo(string $currentTable, array &$imports): string
    {
        $content = '';

        foreach ($this->getTableForeignKeys($currentTable) as $fk) {
            $localColumn = $fk['columns'][0];
            $foreignTable = $fk['foreign_table'];

            $methodName = Str::camel(preg_replace('/(_id|id_)/i', '', $localColumn));
            $relatedModel = '\\App\\Models\\'.Str::studly(Str::singular($foreignTable));

            $imports[] = "use Illuminate\Database\Eloquent\Relations\BelongsTo;";

            $content .= "\n    public function {$methodName}(): BelongsTo\n";
            $content .= "    {\n";
            $content .= "        return \$this->belongsTo({$relatedModel}::class, '{$localColumn}');\n";
            $content .= "    }\n";
        }

        return $content;
    }

    /**
     * Génère les méthodes de relation HasMany.
     */
    private function generateHasMany(string $currentTable, array $allTables, array &$imports): string
    {
        $content = '';

        foreach ($allTables as $otherTable) {
            $otherTableName = $otherTable['name'];

            if ($otherTableName === $currentTable) {
                continue;
            }

            $otherTableFks = $this->getTableForeignKeys($otherTableName);

            if (count($otherTableFks) === 2) {
                continue;
            }

            foreach ($otherTableFks as $fk) {
                if ($fk['foreign_table'] === $currentTable) {
                    $foreignColumn = $fk['columns'][0];

                    $methodName = Str::camel(Str::plural(Str::singular($otherTableName)));
                    $relatedModel = '\\App\\Models\\'.Str::studly(Str::singular($otherTableName));

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

    /**
     * Génère les méthodes de relation BelongsToMany.
     */
    private function generateBelongsToMany(string $currentTable, array $allTables, array &$imports): string
    {
        $content = '';

        foreach ($allTables as $pivotTable) {
            $pivotTableName = $pivotTable['name'];

            if ($pivotTableName === $currentTable) {
                continue;
            }

            $fks = $this->getTableForeignKeys($pivotTableName);

            if (count($fks) === 2) {
                $fk1 = $fks[0];
                $fk2 = $fks[1];

                $pointsToCurrent = $fk1['foreign_table'] === $currentTable || $fk2['foreign_table'] === $currentTable;
                $pointsToDifferentTables = $fk1['foreign_table'] !== $fk2['foreign_table'];

                if ($pointsToCurrent && $pointsToDifferentTables) {
                    $relatedFk = $fk1['foreign_table'] === $currentTable ? $fk2 : $fk1;
                    $localFk = $fk1['foreign_table'] === $currentTable ? $fk1 : $fk2;

                    $relatedTableName = $relatedFk['foreign_table'];

                    $methodName = Str::camel(Str::plural($relatedTableName));
                    $relatedModel = '\\App\\Models\\'.Str::studly(Str::singular($relatedTableName));

                    $imports[] = "use Illuminate\Database\Eloquent\Relations\BelongsToMany;";

                    $content .= "\n    public function {$methodName}(): BelongsToMany\n";
                    $content .= "    {\n";
                    $content .= "        return \$this->belongsToMany({$relatedModel}::class, '{$pivotTableName}', '{$localFk['columns'][0]}', '{$relatedFk['columns'][0]}');\n";
                    $content .= "    }\n";
                }
            }
        }

        return $content;
    }

    /**
     * Récupère les colonnes depuis le cache mémoire.
     */
    public function getTableColumns(string $table): array
    {
        return $this->schemaCache['columns'][$table] ?? Schema::getColumnListing($table);
    }

    /**
     * Récupère les clés étrangères depuis le cache mémoire.
     */
    public function getTableForeignKeys(string $table): array
    {
        return $this->schemaCache['foreignKeys'][$table] ?? Schema::getForeignKeys($table);
    }

    /**
     * Identifie la clé primaire depuis le cache mémoire.
     */
    public function getPrimaryKey(string $table): string
    {
        $indexes = $this->schemaCache['indexes'][$table] ?? Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['primary']) {
                return $index['columns'][0];
            }
        }

        return 'id';
    }
}