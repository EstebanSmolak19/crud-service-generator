<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ModelService implements IModelService
{
    public array $whitelist {
        get {
            $basic = [
                'cache', 'cache_locks', 'crud_service_generator_table', 'failed_jobs',
                'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'sessions'
            ];
            return array_merge($basic, config('crud-service-generator.models.whitelist', []));
        }
    }

    public array $excludedColumns {
        get {
            $basic = ['id', 'created_at', 'updated_at', 'deleted_at'];
            return array_merge($basic, config('crud-service-generator.models.excluded_columns', []));
        }
    }

    public function searchTable(): array
    {
        $db_connexion = config('database.default');
        $db_name = config("database.connections.{$db_connexion}.database");
        $tables = Schema::getTables();

        return array_filter($tables, function($table) use ($db_name) {
            $isGoodSchema = !isset($table['schema']) || $table['schema'] === $db_name;
            return $isGoodSchema && !in_array($table['name'], $this->whitelist);
        });
    }

    public function generateModels(): void
    {
        $allTables = $this->searchTable();
        $directory = app_path("Models");

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        foreach ($allTables as $table) {
            $tableName = $table['name'];
            $className = Str::studly(Str::singular($tableName));

            $primaryKey = $this->getPrimaryKey($tableName);
            $primaryKeyLine = "";

            // Si la clé n'est pas "id", on génère la ligne de code pour le modèle
            if ($primaryKey !== 'id') {
                $primaryKeyLine = "\n    protected \$primaryKey = '{$primaryKey}';";
            }

            $allColumns = $this->getTableColumns($tableName);
            $fillableColumns = array_diff($allColumns, $this->excludedColumns);
            $fillableString = "\n        '" . implode("',\n        '", $fillableColumns) . "',\n    ";

            $imports = [];
            $relationsContent = $this->generateAllRelations($tableName, $allTables, $imports);

            $stubPath = __DIR__ . '/../stubs/Model.stub';
            if (!File::exists($stubPath)) continue;

            $content = File::get($stubPath);
            $useString = implode("\n", array_unique($imports));

            $content = str_replace(
                ['{{ namespace }}', '{{ imports }}', '{{ class }}', '{{ table }}', '{{ primaryKey }}', '{{ fillable }}', '{{ relations }}'],
                ["App\\Models", $useString, $className, $tableName, $primaryKeyLine, $fillableString, $relationsContent],
                $content
            );

            File::put("{$directory}/{$className}.php", $content);
        }
    }

    private function generateAllRelations(string $currentTable, array $allTables, array &$imports): string
    {
        $content = "";

        //BELONGSTO
        foreach (Schema::getForeignKeys($currentTable) as $fk) {
            $localColumn = $fk['columns'][0];
            $foreignTable = $fk['foreign_table'];

            $methodName = Str::camel(preg_replace('/(_id|id_)/i', '', $localColumn));
            $relatedModel = Str::studly(Str::singular($foreignTable));

            // Ajout des imports
            $imports[] = "use Illuminate\Database\Eloquent\Relations\BelongsTo;";

            $content .= "\n    public function {$methodName}(): BelongsTo\n";
            $content .= "    {\n";
            $content .= "        return \$this->belongsTo({$relatedModel}::class, '{$localColumn}');\n";
            $content .= "    }\n";
        }

        //HASMANY
        foreach ($allTables as $otherTable) {
            $otherTableName = $otherTable['name'];
            if ($otherTableName === $currentTable) continue;

            foreach (Schema::getForeignKeys($otherTableName) as $fk) {
                if ($fk['foreign_table'] === $currentTable) {
                    $foreignColumn = $fk['columns'][0];

                    $methodName = Str::camel(Str::plural(Str::singular($otherTableName)));
                    $relatedModel = Str::studly(Str::singular($otherTableName));

                    // Ajout des imports
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