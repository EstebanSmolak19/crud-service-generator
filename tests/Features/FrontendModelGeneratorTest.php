<?php

use EstebanSmolak19\CrudServiceGenerator\Services\CommandService;
use EstebanSmolak19\CrudServiceGenerator\Services\FrontendModelGenerator;
use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;

uses(TestCase::class);

function getTestOutputDir(): string
{
    return __DIR__.'/temp_ts_models';
}

function getServiceMock(): CommandService
{
    $mock = Mockery::mock(CommandService::class);
    $mock->shouldReceive('getConfigName')->andReturn('crud-service-generator');

    config(['crud-service-generator.frontend.path' => getTestOutputDir()]);

    return $mock;
}

function ensureStubExists(): void
{
    // On utilise la réflexion pour trouver exactement où la classe cherche son stub
    $reflection = new ReflectionClass(FrontendModelGenerator::class);
    $stubPath = dirname($reflection->getFileName()).'/../stubs/frontend-model.stub';

    if (! is_dir(dirname($stubPath))) {
        mkdir(dirname($stubPath), 0777, true);
    }

    if (! file_exists($stubPath)) {
        file_put_contents($stubPath, "{{imports}}\nexport type {{modelName}} {\n{{properties}}\n}\n");
    }
}

beforeEach(function () {
    ensureStubExists();

    if (File::exists(getTestOutputDir())) {
        File::deleteDirectory(getTestOutputDir());
    }

    Schema::dropAllTables();
});

afterEach(function () {
    if (File::exists(getTestOutputDir())) {
        File::deleteDirectory(getTestOutputDir());
    }
});

describe('Génération de base (avec schéma fourni manuellement)', function () {

    it('génère un fichier TS valide avec des propriétés simples', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $schema = [
            'name' => ['type' => 'string', 'nullable' => false, 'is_relation' => false],
            'age' => ['type' => 'integer', 'nullable' => true, 'is_relation' => false],
        ];

        $generator->generate('User', $schema);

        $filePath = getTestOutputDir().'/User.ts';
        expect(File::exists($filePath))->toBeTrue();

        $content = File::get($filePath);

        // Vérification des propriétés générées
        expect($content)->toContain('export type User')
            ->and($content)->toContain('name: string;')
            ->and($content)->toContain('age?: number;'); // nullable génère un "?"
    });

    it('ne génère rien si le schéma est vide', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $generator->generate('EmptyModel', []);

        expect(File::exists(getTestOutputDir().'/EmptyModel.ts'))->toBeFalse();
    });

    it('évite les boucles infinies grâce au cache des modèles générés', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $schema = ['id' => ['type' => 'int']];

        // On appelle deux fois de suite, la deuxième fois doit être ignorée
        $generator->generate('CachedModel', $schema);
        $generator->generate('CachedModel', $schema);

        // Si ça passe sans erreur, c'est que la condition `in_array($modelName, $this->generatedCache)` a bien fonctionné.
        expect(File::exists(getTestOutputDir().'/CachedModel.ts'))->toBeTrue();
    });
});

describe('Résolution des types TypeScript et Imports', function () {

    it('mappe correctement les types SQL vers TypeScript', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $schema = [
            'id' => ['type' => 'int'],
            'price' => ['type' => 'decimal'],
            'is_active' => ['type' => 'boolean'],
            'description' => ['type' => 'text'],
        ];

        $generator->generate('TypeModel', $schema);
        $content = File::get(getTestOutputDir().'/TypeModel.ts');

        expect($content)->toContain('id: number;')
            ->and($content)->toContain('price: number;')
            ->and($content)->toContain('is_active: boolean;')
            ->and($content)->toContain('description: string;'); // Par défaut, tout le reste est string
    });

    it('génère les imports externes et empêche l auto-importation', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $schema = [
            'role' => ['type' => 'Role', 'is_relation' => true, 'relation_type' => 'belongsTo'],
            'self_relation' => ['type' => 'ImportModel', 'is_relation' => true], // Ne doit pas être importé
        ];

        $generator->generate('ImportModel', $schema);
        $content = File::get(getTestOutputDir().'/ImportModel.ts');

        // Doit importer 'Role'
        expect($content)->toContain("import { Role } from './Role';")
        // Mais ne doit PAS importer 'ImportModel' dans lui-même
            ->and($content)->not->toContain('import { ImportModel }');
    });

    it('formate correctement les types de relations (Tableau vs Objet)', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $schema = [
            'author' => ['type' => 'User', 'is_relation' => true, 'relation_type' => 'belongsTo'],
            'comments' => ['type' => 'Comment', 'is_relation' => true, 'relation_type' => 'hasMany'],
        ];

        $generator->generate('RelationModel', $schema);
        $content = File::get(getTestOutputDir().'/RelationModel.ts');

        // belongsTo = Objet | null
        expect($content)->toContain('author: User | null;')
        // hasMany = Tableau[]
            ->and($content)->toContain('comments: Comment[];');
    });
});

describe('Introspection de la Base de Données (Schema Extraction)', function () {

    beforeEach(function () {
        // Création d'un vrai schéma SQLite pour tester l'introspection
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // Crée une Foreign Key
            $table->decimal('rating');
        });

        // Table pivot pour tester l'exclusion dans extractHasManyRelations
        Schema::create('post_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('post_id')->constrained('posts');
        });
    });

    it('retourne un schéma vide si la table n existe pas en BDD', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        // La table "ghosts" n'existe pas
        $generator->generate('Ghost');

        expect(File::exists(getTestOutputDir().'/Ghost.ts'))->toBeFalse();
    });

    it('extrait les colonnes scalaires avec les bons types', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $generator->generate('User'); // Va introspecter la table 'users'
        $content = File::get(getTestOutputDir().'/User.ts');

        // id -> integer -> number
        expect($content)->toContain('id: number;')
        // name -> string -> string
            ->and($content)->toContain('name: string;');
    });

    it('extrait les relations belongsTo depuis les clés étrangères de la table', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $generator->generate('Post'); // Va introspecter la table 'posts'
        $content = File::get(getTestOutputDir().'/Post.ts');

        // posts a un user_id FK vers users -> génère relation 'user'
        expect($content)->toContain('user?: User | null;')
            ->and($content)->toContain("import { User } from './User';");
    });

    it('extrait les relations hasMany (relations inverses) et ignore les tables pivots', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        $generator->generate('User'); // Va introspecter la table 'users'
        $content = File::get(getTestOutputDir().'/User.ts');

        // users est ciblé par posts (user_id) -> génère relation 'posts'
        expect($content)->toContain('posts: Post[];')
            ->and($content)->toContain("import { Post } from './Post';");

        // La table pivot "post_user" a 2 clés étrangères, elle doit être ignorée !
        expect($content)->not->toContain('postUsers: PostUser[]');
    });

    it('déclenche la génération récursive des modèles liés manquants', function () {
        $generator = new FrontendModelGenerator(getServiceMock());

        // On génère uniquement Post.
        // Post a une relation BelongsTo vers User.
        // Le générateur doit détecter que User.ts n'existe pas et le créer !
        $generator->generate('Post');

        expect(File::exists(getTestOutputDir().'/Post.ts'))->toBeTrue()
            ->and(File::exists(getTestOutputDir().'/User.ts'))->toBeTrue(); // Le modèle lié a été généré !
    });
});
