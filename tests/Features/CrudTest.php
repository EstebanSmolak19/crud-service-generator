<?php

use EstebanSmolak19\CrudServiceGenerator\Services\CrudServiceBase;
use EstebanSmolak19\CrudServiceGenerator\Contracts\HasSqlOverrides;
use EstebanSmolak19\CrudServiceGenerator\Contracts\ServiceAttributeContract;
use EstebanSmolak19\CrudServiceGenerator\Services\ServiceProxy;
use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $fillable = ['name', 'status'];

    public $timestamps = false;
}

class TestResource extends JsonResource
{
    // Ressource basique
}

class BadService extends CrudServiceBase
{
    // Oubli volontaire de "protected array $fillable = [];" pour tester l'erreur
}

class StandardService extends CrudServiceBase
{
    protected array $fillable = ['name', 'status'];
    protected string $ressource = TestResource::class;
    public bool $audit = false;
    public array $orderBy = ['name' => 'ASC'];
    public string $primaryKey = 'id';
}

class SqlService extends CrudServiceBase implements HasSqlOverrides
{
    protected array $fillable = ['name'];
    protected string $ressource = TestResource::class;
    public string $primaryKey = 'id';
    public bool $audit = false;

    // Simulation des méthodes SQL
    public function getSqlViewName(): ?string { return 'test_models'; }
    public function getCreateProcedureName(): ?string { return 'create_proc'; }
    public function getUpdateProcedureName(): ?string { return 'update_proc'; }
    public function getDeleteProcedureName(): ?string { return 'delete_proc'; }

    public function viewExists(?string $view): bool { return true; }
    public function procedureExists(?string $procedure): bool { return true; }
    public function columnExists(string $table, string $column): bool { return true; }

    public function executeCreateProcedure(string $proc, array $data): mixed {
        return TestModel::create($data);
    }
    public function executeUpdateProcedure(string $proc, mixed $id, array $data): void {
        TestModel::where('id', $id)->update($data);
    }
}


#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class RequireAdmin implements ServiceAttributeContract
{
    public function handle(object $service, string $method, array &$params): void
    {
        // On vérifie une fausse clé 'is_admin' dans les data pour le test
        $data = $params[0] ?? [];
        if (!isset($data['is_admin']) || $data['is_admin'] !== true) {
            abort(403, 'Accès non autorisé');
        }
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class MutateData implements ServiceAttributeContract
{
    public function handle(object $service, string $method, array &$params): void
    {
        // Modifie le tableau de données par référence avant exécution
        $dataIndex = isset($params[1]) ? 1 : 0;
        $params[$dataIndex]['name'] = 'Mutated by Attribute';
    }
}

class AttributeTestService extends StandardService
{
    #[RequireAdmin]
    public function create(array $data): mixed
    {
        return parent::create($data);
    }

    #[MutateData]
    public function update(mixed $id, array $data)
    {
        return parent::update($id, $data);
    }
}

#[RequireAdmin]
class ClassLevelProtectedService extends StandardService
{
    // Toutes les méthodes héritent de RequireAdmin via le Proxy
}

beforeEach(function () {
    config(['crud-service-generator.database.table_name_log' => 'logs_table']);
    config(['crud-service-generator.pagination.param_name' => 'per_page']);
    config(['crud-service-generator.pagination.default_per_page' => 15]);

    Schema::dropIfExists('test_models');
    Schema::create('test_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('active');
    });

    Schema::dropIfExists('logs_table');
    Schema::create('logs_table', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('event');
        $table->string('auditable_type');
        $table->unsignedBigInteger('auditable_id');
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->timestamps();
    });

    Auth::shouldReceive('id')->andReturn(1);
});

describe('Configuration et Initialisation', function () {
    it('déclenche une exception si fillable n est pas déclaré', function () {
        expect(fn() => new BadService(new TestModel()))
            ->toThrow(LogicException::class, "Tu as oublié de déclarer 'protected array \$fillable");
    });

    it('retourne correctement les propriétés de configuration', function () {
        $service = new StandardService(new TestModel());

        expect($service->getResourceFields())->toBe(['name', 'status'])
            ->and($service->getRessource())->toBe(TestResource::class)
            ->and($service->permissions())->toBeArray()->toBeEmpty()
            ->and($service->getPerPage())->toBe(15);

        request()->merge(['per_page' => 50]);
        expect($service->getPerPage())->toBe(50);
    });
});

describe('CRUD Standard (Eloquent) et Audit', function () {
    it('crée un enregistrement avec Eloquent (sans audit)', function () {
        $service = new StandardService(new TestModel());
        $record = $service->create(['name' => 'Test Create']);

        expect($record->name)->toBe('Test Create')
            ->and(DB::table('logs_table')->count())->toBe(0);
    });

    it('crée un enregistrement et trace l audit', function () {
        $service = new StandardService(new TestModel());
        $service->audit = true;

        $record = $service->create(['name' => 'Test Audit']);

        $log = DB::table('logs_table')->first();
        expect($log)->not->toBeNull()
            ->and($log->event)->toBe('create')
            ->and($log->auditable_id)->toBe($record->id);
    });

    it('récupère tous les enregistrements (all)', function () {
        TestModel::create(['name' => 'Item 1']);
        $service = new StandardService(new TestModel());

        $result = $service->all();
        expect($result)->toBeInstanceOf(EloquentBuilder::class)
            ->and($result->count())->toBe(1);
    });

    it('récupère un enregistrement (find)', function () {
        $model = TestModel::create(['name' => 'Find Me']);
        $service = new StandardService(new TestModel());

        $result = $service->find($model->id);
        expect($result->id)->toBe($model->id);
    });

    it('met à jour un enregistrement et trace l audit', function () {
        $model = TestModel::create(['name' => 'Old Name']);
        $service = new StandardService(new TestModel());
        $service->audit = true;

        $service->update($model->id, ['name' => 'New Name']);

        $log = DB::table('logs_table')->where('event', 'update')->first();
        expect($log)->not->toBeNull()
            ->and($log->old_values)->toContain('Old Name')
            ->and($log->new_values)->toContain('New Name');
    });

    it('supprime un enregistrement et trace l audit', function () {
        $model = TestModel::create(['name' => 'To Delete']);
        $service = new StandardService(new TestModel());
        $service->audit = true;

        $service->destroy($model->id);

        expect(TestModel::find($model->id))->toBeNull();
        $log = DB::table('logs_table')->where('event', 'delete')->first();
        expect($log)->not->toBeNull();
    });
});

describe('Opérations en masse (Bulk)', function () {
    it('effectue un bulkUpdate (mise à jour de masse)', function () {
        $m1 = TestModel::create(['name' => 'A']);
        $m2 = TestModel::create(['name' => 'B']);

        $service = new StandardService(new TestModel());
        $count = $service->bulkUpdate([$m1->id, $m2->id], ['status' => 'inactive']);

        expect($count)->toBe(2)
            ->and(TestModel::where('status', 'inactive')->count())->toBe(2);
    });

    it('effectue un bulkDelete (suppression de masse)', function () {
        $m1 = TestModel::create(['name' => 'A']);
        $m2 = TestModel::create(['name' => 'B']);

        $service = new StandardService(new TestModel());
        $count = $service->bulkDelete([$m1->id, $m2->id]);

        expect($count)->toBe(2)
            ->and(TestModel::count())->toBe(0);
    });

    it('effectue un bulkUpdate et trace l audit pour chaque élément mis à jour', function () {
        $m1 = TestModel::create(['name' => 'Bulk Update 1', 'status' => 'active']);
        $m2 = TestModel::create(['name' => 'Bulk Update 2', 'status' => 'active']);

        //Initialisation du service avec l'audit activé
        $service = new StandardService(new TestModel());
        $service->audit = true;

        $count = $service->bulkUpdate([$m1->id, $m2->id], ['status' => 'inactive']);

        expect($count)->toBe(2)
            ->and(TestModel::where('status', 'inactive')->count())->toBe(2); // Les éléments sont bien mis à jour

        //Vérification cruciale : on s'assure qu'il y a bien 2 logs distincts de mise à jour
        $logsCount = DB::table('logs_table')->where('event', 'update')->count();
        expect($logsCount)->toBe(2);
    });

    it('effectue un bulkDelete et trace l audit pour chaque élément supprimé', function () {
        $m1 = TestModel::create(['name' => 'Bulk Delete 1']);
        $m2 = TestModel::create(['name' => 'Bulk Delete 2']);

        //Initialisation du service avec l'audit activé
        $service = new StandardService(new TestModel());
        $service->audit = true;

        //Exécution du bulkDelete
        $count = $service->bulkDelete([$m1->id, $m2->id]);

        expect($count)->toBe(2)
            ->and(TestModel::count())->toBe(0); // Les éléments sont bien supprimés

        //Vérification cruciale : on s'assure qu'il y a bien 2 logs distincts de suppression
        $logsCount = DB::table('logs_table')->where('event', 'delete')->count();
        expect($logsCount)->toBe(2);
    });
});

describe('Surcharges SQL (SQL Overrides)', function () {
    it('jette une exception si la clé primaire n existe pas dans la vue SQL', function () {

        $service = \Mockery::mock(SqlService::class, [new TestModel()])->makePartial();

        $service->shouldReceive('columnExists')->andReturn(false);
        $service->shouldReceive('getSqlViewName')->andReturn('test_models');
        $service->shouldReceive('viewExists')->andReturn(true);

        expect(fn() => $service->find(1))
            ->toThrow(LogicException::class, "Structure SQL invalide");
    });

    it('transforme correctement un objet stdClass (issu d une requête brute) en Resource', function () {
        $service = new StandardService(new TestModel());

        // On simule un retour de requête DB::table()
        $stdClassObject = (object) ['id' => 1, 'name' => 'Raw Object', 'status' => 'active'];

        // On utilise la réflexion pour appeler la méthode protégée mapToResource
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('mapToResource');
        $method->setAccessible(true);

        $resource = $method->invoke($service, $stdClassObject);

        expect($resource)->toBeInstanceOf(TestResource::class)
            ->and($resource->resource)->toBeInstanceOf(TestModel::class)
            ->and($resource->resource->name)->toBe('Raw Object')
            ->and($resource->resource->fromSqlView)->toBeTrue(); // Vérifie que l'attribut a bien été injecté
    });

    it('fait un abort 404 si l enregistrement est introuvable dans la vue SQL', function () {
        // test_models est vide en BDD à cet instant
        $service = new SqlService(new TestModel());

        $hasException = false;
        try {
            $service->find(9999);
        } catch (HttpException $e) {
            $hasException = ($e->getStatusCode() === 404);
        }

        expect($hasException)->toBeTrue();
    });

    it('utilise la vue SQL pour all()', function () {
        $service = new SqlService(new TestModel());
        $result = $service->all();
        expect($result)->toBeInstanceOf(QueryBuilder::class);
    });

    it('utilise la vue SQL pour find() et formate en ressource', function () {
        $model = TestModel::create(['name' => 'SQL Find']);
        $service = new SqlService(new TestModel());

        $result = $service->find($model->id);
        expect($result)->toBeInstanceOf(TestResource::class)
            ->and($result->resource->name)->toBe('SQL Find');
    });

    it('utilise la procédure SQL pour create()', function () {
        $service = new SqlService(new TestModel());
        $record = $service->create(['name' => 'SQL Create']);

        expect($record->name)->toBe('SQL Create');
    });

    it('utilise la procédure SQL pour update()', function () {
        $model = TestModel::create(['name' => 'Old SQL']);
        $service = new SqlService(new TestModel());

        $service->update($model->id, ['name' => 'New SQL']);

        expect(TestModel::find($model->id)->name)->toBe('New SQL');
    });

    it('utilise la procédure SQL brute pour destroy()', function () {
        $model = TestModel::create(['id' => 99, 'name' => 'SQL Delete']);
        $service = new SqlService(new TestModel());

        $sqlExecuted = false;

        try {
            $service->destroy($model->id);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'delete_proc')) {
                $sqlExecuted = true;
            }
        }

        expect($sqlExecuted)->toBeTrue();
    });

    it('ne trace pas d audit si la mise à jour ne contient aucune modification réelle', function () {
        $model = TestModel::create(['name' => 'Identical Name']);
        $service = new StandardService(new TestModel());
        $service->audit = true;

        // On update avec exactement la même valeur
        $service->update($model->id, ['name' => 'Identical Name']);

        // Aucun log d'update ne doit avoir été créé
        $logCount = DB::table('logs_table')->where('event', 'update')->count();
        expect($logCount)->toBe(0);
    });
});

describe('Proxy et Attributs de Permission', function () {
    it('bloque l appel à create() et renvoie une erreur 403 via l attribut de méthode', function () {
        $service = new AttributeTestService(new TestModel());
        $proxy = new ServiceProxy($service);

        $hasException = false;
        try {
            // is_admin est à false, l'attribut RequireAdmin va faire un abort(403)
            $proxy->create(['name' => 'Hacker attempt', 'is_admin' => false]);
        } catch (HttpException $e) {
            $hasException = ($e->getStatusCode() === 403);
        }

        expect($hasException)->toBeTrue();
        expect(TestModel::count())->toBe(0); // Rien n'a été créé
    });

    it('autorise l appel à create() si l attribut de méthode est satisfait', function () {
        $service = new AttributeTestService(new TestModel());
        $proxy = new ServiceProxy($service);

        // is_admin est true, on passe
        $proxy->create(['name' => 'Valid Admin Action', 'is_admin' => true]);

        expect(TestModel::count())->toBe(1)
            ->and(TestModel::first()->name)->toBe('Valid Admin Action');
    });

    it('bloque l accès à TOUTES les méthodes si l attribut est défini sur la CLASSE', function () {
        $service = new ClassLevelProtectedService(new TestModel());
        $proxy = new ServiceProxy($service);

        $hasException = false;
        try {
            // all() n'a pas les datas nécessaires, ça bloque direct
            $proxy->all();
        } catch (HttpException $e) {
            $hasException = ($e->getStatusCode() === 403);
        }

        expect($hasException)->toBeTrue();
    });

    it('autorise l accès à une méthode quelconque si l attribut de classe est satisfait', function () {
        $service = new ClassLevelProtectedService(new TestModel());
        $proxy = new ServiceProxy($service);

        $proxy->create(['name' => 'Class Allowed', 'is_admin' => true]);

        expect(TestModel::count())->toBe(1);
    });

    it('modifie les paramètres à la volée (par référence) avant d atteindre le service réel', function () {
        $model = TestModel::create(['name' => 'Old Name']);

        $service = new AttributeTestService(new TestModel());
        $proxy = new ServiceProxy($service);

        // MutateData intercepte l'appel et force 'name' = 'Mutated by Attribute'
        $proxy->update($model->id, ['name' => 'New Name Attempt']);

        expect(TestModel::first()->name)->toBe('Mutated by Attribute');
    });
});

describe('Application du Tri (applySorting)', function () {

    it('applique le tri standard en ASC', function () {
        $service = new StandardService(new TestModel());
        $service->orderBy = ['name' => 'ASC'];

        $query = $service->applySorting(TestModel::query());

        // Note: SQLite utilise des guillemets doubles pour les colonnes dans toSql()
        expect($query->toSql())->toContain('order by "name" asc');
    });

    it('accepte les directions en minuscules et les applique correctement', function () {
        $service = new StandardService(new TestModel());
        $service->orderBy = ['name' => 'asc'];

        $query = $service->applySorting(TestModel::query());

        expect($query->toSql())->toContain('order by "name" asc');
    });

    it('applique le tri en DESC', function () {
        $service = new StandardService(new TestModel());
        $service->orderBy = ['id' => 'DESC'];

        $query = $service->applySorting(TestModel::query());

        expect($query->toSql())->toContain('order by "id" desc');
    });

    it('force le tri en DESC si la direction fournie est invalide (fallback de sécurité)', function () {
        $service = new StandardService(new TestModel());
        // On passe une direction qui n'existe pas en SQL
        $service->orderBy = ['status' => 'NIMPORTE_QUOI'];

        $query = $service->applySorting(TestModel::query());

        // Ton code : strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC'
        // Donc ça doit fallback sur "desc"
        expect($query->toSql())->toContain('order by "status" desc');
    });

    it('applique le tri sur plusieurs colonnes consécutives', function () {
        $service = new StandardService(new TestModel());
        $service->orderBy = [
            'status' => 'ASC',
            'created_at' => 'DESC'
        ];

        $query = $service->applySorting(TestModel::query());

        $sql = $query->toSql();
        expect($sql)->toContain('order by "status" asc, "created_at" desc');
    });

});