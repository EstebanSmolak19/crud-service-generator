<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class FrontendModelGenerator
 *
 * Génère des types TypeScript propres et typés pour le frontend
 * à partir de l'introspection de la base de données Laravel.
 */
class FrontendModelGenerator
{
    /**
     * Cache des modèles déjà générés pour éviter les boucles récursives infinies.
     * @var array<string>
     */
    private array $generatedCache = [];

    /**
     * Cache local des tables et clés étrangères pour éviter les requêtes SQL en double.
     */
    private ?array $cachedTables = null;
    private array $cachedForeignKeys = [];

    public function __construct(private CommandService $service) {}

    /**
     * Point d'entrée principal pour générer le fichier TypeScript du modèle.
     * @param string $modelName Le nom du modèle (ex: Wallet)
     * @param array|null $schema Le schéma optionnel (extrait de la BDD si null)
     * @return void
     */
    public function generate(string $modelName, ?array $schema = null): void
    {
        if (in_array($modelName, $this->generatedCache, true)) {
            return;
        }
        $this->generatedCache[] = $modelName;

        $schema = $schema ?? $this->extractSchemaFromDatabase($modelName);
        if (empty($schema)) {
            return;
        }

        $this->generateRelations($schema);

        $content = $this->buildFileContent($modelName, $schema);
        $this->saveFile($modelName, $content);
    }

    /**
     * Génère récursivement les fichiers des modèles liés détectés.
     * @param array $schema
     * @return void
     */
    private function generateRelations(array $schema): void
    {
        foreach ($schema as $definition) {
            if ($definition['is_relation'] ?? false) {
                $relatedModel = $definition['type'];
                if (!file_exists($this->resolvePath($relatedModel))) {
                    $this->generate($relatedModel);
                }
            }
        }
    }

    /**
     * Assemble le contenu complet du fichier TypeScript.
     * @param string $modelName
     * @param array $schema
     * @return string
     */
    private function buildFileContent(string $modelName, array $schema): string
    {
        $stub = file_get_contents(__DIR__ . '/../stubs/frontend-model.stub');
        $imports = $this->buildImports($schema, $modelName);
        $properties = $this->buildProperties($schema);

        return str_replace(
            ['{{imports}}', '{{modelName}}', '{{properties}}'],
            [$imports ? $imports . "\n" : '', $modelName, $properties],
            $stub
        );
    }

    /**
     * Génère les instructions d'importation pour les relations externes.
     * @param array $schema
     * @param string $modelName
     * @return string
     */
    private function buildImports(array $schema, string $modelName): string
    {
        return collect($schema)
            ->filter(fn($d) => $d['is_relation'] ?? false)
            ->pluck('type')
            ->unique()
            ->reject(fn($m) => $m === $modelName)
            ->map(fn($m) => "import { {$m} } from './{$m}';")
            ->implode("\n");
    }

    /**
     * Convertit le schéma brut en lignes de propriétés TypeScript.
     * @param array $schema
     * @return string
     */
    private function buildProperties(array $schema): string
    {
        return collect($schema)->map(function ($definition, $field) {
            $tsType = $this->resolveTypeScriptType($definition);
            $nullable = ($definition['nullable'] ?? false) ? '?' : '';

            return "    {$field}{$nullable}: {$tsType};";
        })->implode("\n");
    }

    /**
     * Mappe un type de données brut vers un type TypeScript valide.
     * @param array $definition
     * @return string
     */
    private function resolveTypeScriptType(array $definition): string
    {
        if ($definition['is_relation'] ?? false) {
            $relatedModel = $definition['type'];
            return match ($definition['relation_type'] ?? 'belongsTo') {
                'hasMany', 'belongsToMany' => "{$relatedModel}[]",
                default => "{$relatedModel} | null",
            };
        }

        return match (strtolower($definition['type'] ?? 'string')) {
            'int', 'integer', 'float', 'double', 'decimal' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }

    /**
     * Résout le chemin d'accès absolu du fichier TS pour un modèle donné.
     * @param string $modelName
     * @return string
     */
    private function resolvePath(string $modelName): string
    {
        $dir = config($this->service->getConfigName() . '.frontend.path', base_path('resources/js/types'));
        return "{$dir}/{$modelName}.ts";
    }

    /**
     * Écrit le contenu généré dans le fichier du disque.
     * @param string $modelName
     * @param string $content
     * @return void
     */
    private function saveFile(string $modelName, string $content): void
    {
        $path = $this->resolvePath($modelName);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
    }

    /**
     * Extrait globalement les colonnes et relations d'une table de la BDD.
     * @param string $modelName
     * @return array<string, mixed>
     */
    private function extractSchemaFromDatabase(string $modelName): array
    {
        $tableName = Str::snake(Str::pluralStudly($modelName));

        if (!Schema::hasTable($tableName)) {
            return [];
        }

        return array_merge(
            $this->extractScalarColumns($tableName),
            $this->extractBelongsToRelations($tableName),
            $this->extractHasManyRelations($tableName)
        );
    }

    /**
     * Récupère les clés étrangères d'une table avec mise en cache mémoire.
     */
    private function getCachedForeignKeys(string $tableName): array
    {
        if (!isset($this->cachedForeignKeys[$tableName])) {
            $this->cachedForeignKeys[$tableName] = Schema::getForeignKeys($tableName);
        }
        return $this->cachedForeignKeys[$tableName];
    }

    /**
     * Récupère toutes les tables avec mise en cache mémoire.
     */
    private function getCachedTables(): array
    {
        if ($this->cachedTables === null) {
            $this->cachedTables = Schema::getTables();
        }
        return $this->cachedTables;
    }

    /**
     * Extrait les colonnes scalaires de la table.
     * @param string $tableName
     * @return array<string, array<string, mixed>>
     */
    private function extractScalarColumns(string $tableName): array
    {
        $schema = [];

        foreach (Schema::getColumnListing($tableName) as $column) {
            $schema[$column] = [
                'type' => Schema::getColumnType($tableName, $column),
                'nullable' => false,
                'is_relation' => false,
            ];
        }

        return $schema;
    }

    /**
     * Extrait les relations de type BelongsTo basées sur les clés étrangères.
     * @param string $tableName
     * @return array<string, array<string, mixed>>
     */
    private function extractBelongsToRelations(string $tableName): array
    {
        $schema = [];

        foreach ($this->getCachedForeignKeys($tableName) as $fk) {
            $localColumn = $fk['columns'][0];
            $foreignTable = $fk['foreign_table'];

            $relationName = Str::camel(preg_replace('/(_id|id_)/i', '', $localColumn));
            $relatedModel = Str::studly(Str::singular($foreignTable));

            $schema[$relationName] = [
                'type' => $relatedModel,
                'is_relation' => true,
                'relation_type' => 'belongsTo',
                'nullable' => true,
            ];
        }

        return $schema;
    }

    /**
     * Extrait les relations de type HasMany.
     * @param string $tableName
     * @return array<string, array<string, mixed>>
     */
    private function extractHasManyRelations(string $tableName): array
    {
        $schema = [];

        foreach ($this->getCachedTables() as $otherTable) {
            $otherName = $otherTable['name'];
            if ($otherName === $tableName) {
                continue;
            }

            $otherFks = $this->getCachedForeignKeys($otherName);
            if (count($otherFks) === 2) {
                continue;
            }

            foreach ($otherFks as $fk) {
                if ($fk['foreign_table'] === $tableName) {
                    $relationName = Str::camel(Str::plural(Str::singular($otherName)));
                    $relatedModel = Str::studly(Str::singular($otherName));

                    $schema[$relationName] = [
                        'type' => $relatedModel,
                        'is_relation' => true,
                        'relation_type' => 'hasMany',
                        'nullable' => false,
                    ];
                }
            }
        }

        return $schema;
    }
}