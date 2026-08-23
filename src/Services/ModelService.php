<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelService implements IModelService
{
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
     * Point d'entrée principal. Déclenche la création des dossiers,
     * itère sur toutes les tables valides et génère les modèles correspondants.
     * Gère également le masquage des fichiers de base dans VSCode si configuré.
     */
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
     * Met à jour le fichier .vscode/settings.json du projet pour masquer ou afficher
     * le dossier `app/Generated/Models` dans l'explorateur de fichiers de l'éditeur.
     */
    public function hideBaseModelsInVsCode(): void
    {
        $settingsPath = base_path('.vscode/settings.json');
        $configValue = config('crud-service-generator.models.hide_base_models_in_vscode', true);

        if (! File::exists($settingsPath)) {
            if (! $configValue) {
                return;
            }
            File::makeDirectory(base_path('.vscode'), 0777, true, true);
            $settings = [];
        } else {
            $settings = json_decode(File::get($settingsPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }
        }

        if ($configValue) {
            $settings['files.exclude']['app/Models/Base'] = true;
        } else {
            if (isset($settings['files.exclude']['app/Models/Base'])) {
                unset($settings['files.exclude']['app/Models/Base']);
            }

            if (isset($settings['files.exclude']) && empty($settings['files.exclude'])) {
                unset($settings['files.exclude']);
            }
        }

        if (! empty($settings) || File::exists($settingsPath)) {
            File::put(
                $settingsPath,
                json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    /**
     * S'assure que les répertoires de destination pour les modèles
     * (Modeles utilisateurs et Modèles générés) existent sur le système de fichiers.
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
     *
     * @param  array  $table  Les métadonnées de la table courante.
     * @param  array  $allTables  L'ensemble des tables
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
     * Prépare toutes les variables dynamiques (clés primaires, colonnes, imports, relations)
     *
     * @param  string  $tableName  Nom de la table dans la base de données.
     * @param  array  $allTables  Ensemble des tables pour l'analyse des relations.
     * @return array Tableau associatif contenant les chaînes formatées pour le stub.
     */
    private function prepareModelData(string $tableName, array $allTables): array
    {
        $primaryKey = $this->getPrimaryKey($tableName);
        $allColumns = $this->getTableColumns($tableName);
        $fillableColumns = array_diff($allColumns, $this->excludedColumns);

        $imports = [];
        $relations = $this->generateAllRelations($tableName, $allTables, $imports);

        return [
            'primaryKeyLine' => ($primaryKey !== 'id') ? "\n    protected \$primaryKey = '{$primaryKey}';" : '',
            'fillableString' => "\n        '".implode("',\n        '", $fillableColumns)."',\n    ",
            'useString' => implode("\n", array_unique($imports)),
            'relations' => $relations,
        ];
    }

    /**
     * Écrit le fichier du modèle de base dans le dossier Generated.
     *
     * @param  string  $className  Nom de la classe (ex: User).
     * @param  string  $tableName  Nom de la table (ex: users).
     * @param  array  $data  Données préparées pour le remplacement.
     * @param  string  $stubContent  Contenu original du fichier stub.
     */
    private function writeBaseModel(string $className, string $tableName, array $data, string $stubContent): void
    {
        $content = str_replace(
            ['{{ namespace }}', '{{ imports }}', '{{ class }}', '{{ table }}', '{{ primaryKey }}', '{{ fillable }}', '{{ relations }}'],
            ['App\\Generated\\Models', $data['useString'], $className, $tableName, $data['primaryKeyLine'], $data['fillableString'], $data['relations']],
            $stubContent
        );

        $content = str_replace("class {$className}", "abstract class {$className}", $content);

        File::put(app_path("Generated/Models/{$className}.php"), $content);
    }

    /**
     * Écrit le fichier du modèle utilisateur dans le dossier app/Models.
     * Ce fichier n'est généré que s'il n'existe pas déjà, pour ne pas écraser
     * la logique personnalisée
     *
     * @param  string  $className  Nom de la classe du modèle.
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
     * Chef d'orchestre de la génération des relations du modèle.
     * Concatène les résultats de BelongsTo, HasMany et BelongsToMany.
     *
     * @param  string  $currentTable  Nom de la table en cours d'analyse.
     * @param  array  $allTables  Ensemble des tables de la BDD.
     * @param  array  &$imports  Tableau passé par référence pour collecter les classes à importer (use).
     * @return string Le code source PHP de toutes les méthodes de relation générées.
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
     * Analyse les clés étrangères physiquement présentes dans la table courante.
     *
     * @param  string  $currentTable  Nom de la table courante.
     * @param  array  &$imports  Tableau de collecte des namespaces à importer.
     * @return string Code source des méthodes BelongsTo générées.
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
     * Recherche dans les autres tables celles qui possèdent une clé étrangère
     * pointant vers la table courante (en excluant les tables pivots pures).
     *
     * @param  string  $currentTable  Nom de la table courante.
     * @param  array  $allTables  Ensemble des tables de la BDD.
     * @param  array  &$imports  Tableau de collecte des namespaces à importer.
     * @return string Code source des méthodes HasMany générées.
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

            // Si l'autre table a exactement 2 clés étrangères, on considère
            // que c'est une table pivot N à N. On ne génère donc pas le HasMany,
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
     * Génère les méthodes de relation BelongsToMany (Relations N à N).
     * Identifie les tables pivots
     *
     * @param  string  $currentTable  Nom de la table courante.
     * @param  array  $allTables  Ensemble des tables de la BDD pour détecter les pivots.
     * @param  array  &$imports  Tableau de collecte des namespaces à importer.
     * @return string Code source des méthodes BelongsToMany générées.
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

            // Une table pivot classique a exactement 2 clés étrangères
            if (count($fks) === 2) {
                $fk1 = $fks[0];
                $fk2 = $fks[1];

                // On vérifie si l'une des clés pointe vers la table courante
                $pointsToCurrent = $fk1['foreign_table'] === $currentTable || $fk2['foreign_table'] === $currentTable;
                // On vérifie que les clés pointent vers deux tables différentes
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
     * Récupère la liste de toutes les colonnes d'une table spécifique.
     *
     * @param  string  $table  Nom de la table.
     * @return array Tableau contenant les noms des colonnes.
     */
    public function getTableColumns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /**
     * Récupère les métadonnées de toutes les clés étrangères d'une table.
     *
     * @param  string  $table  Nom de la table.
     * @return array Tableau détaillant les clés étrangères (colonnes locales et cibles).
     */
    public function getTableForeignKeys(string $table): array
    {
        return Schema::getForeignKeys($table);
    }

    /**
     * Identifie le nom de la colonne agissant comme clé primaire pour une table donnée.
     *
     * @param  string  $table  Nom de la table.
     * @return string Nom de la clé primaire (retourne 'id' par défaut).
     */
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
