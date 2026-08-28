<?php

use EstebanSmolak19\CrudServiceGenerator\Services\ResponseFormatter;
use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

class FormatterTestModel extends Model
{
    protected $table = 'formatter_models';
    protected $guarded = [];
    public $timestamps = false;
}


beforeEach(function () {
    Schema::dropIfExists('formatter_models');
    Schema::create('formatter_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    for ($i = 1; $i <= 5; $i++) {
        FormatterTestModel::create(['name' => "Item {$i}"]);
    }
});


describe('Formatage des types scalaires, null et objets simples', function () {

    it('formate une valeur scalaire (entier, booléen, chaine)', function () {
        $mapper = fn($item) => $item; // Le mapper ne sera pas utilisé pour les scalaires

        expect((new ResponseFormatter(42, $mapper, 15))->format())->toBe(['affected_rows' => 42]);
        expect((new ResponseFormatter(true, $mapper, 15))->format())->toBe(['affected_rows' => true]);
        expect((new ResponseFormatter('success', $mapper, 15))->format())->toBe(['affected_rows' => 'success']);
    });

    it('formate une valeur null', function () {
        $mapper = fn($item) => $item;

        expect((new ResponseFormatter(null, $mapper, 15))->format())->toBe(['affected_rows' => null]);
    });

    it('formate un objet unique (stdClass ou Model) en appliquant le mapper', function () {
        $item = (object) ['name' => 'John Doe'];
        $mapper = fn($obj) => ['mapped_name' => strtoupper($obj->name)];

        $result = (new ResponseFormatter($item, $mapper, 15))->format();

        expect($result)->toBe(['mapped_name' => 'JOHN DOE']);
    });
});

describe('Formatage des Collections et Paginators existants', function () {

    it('formate une SupportCollection en appliquant le mapper sur chaque élément', function () {
        $collection = collect([
            (object) ['id' => 1],
            (object) ['id' => 2]
        ]);
        $mapper = fn($item) => $item->id * 10;

        $result = (new ResponseFormatter($collection, $mapper, 15))->format();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->toArray())->toBe([10, 20]);
    });

    it('formate un LengthAwarePaginator en appliquant le mapper sur sa collection interne', function () {
        $paginator = new LengthAwarePaginator(collect([(object)['id' => 1]]), 1, 15);
        $mapper = fn($item) => ['new_id' => $item->id];

        $result = (new ResponseFormatter($paginator, $mapper, 15))->format();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->items())->toBe([['new_id' => 1]]);
    });
});

describe('Résolution automatique des requêtes (Builders)', function () {

    it('résout un Eloquent Builder avec pagination (perPage > 0)', function () {
        $query = FormatterTestModel::query();
        $mapper = fn($item) => ['mapped' => $item->name];

        $formatter = new ResponseFormatter($query, $mapper, 2); // 2 éléments par page
        $result = $formatter->format();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->count())->toBe(2) // 2 éléments sur la page actuelle
            ->and($result->total())->toBe(5) // 5 éléments au total en BDD
            ->and($result->first())->toHaveKey('mapped'); // Le mapper a bien été appliqué
    });

    it('résout un Eloquent Builder avec un simple get() (perPage = 0)', function () {
        $query = FormatterTestModel::query();
        $mapper = fn($item) => ['mapped' => $item->name];

        $formatter = new ResponseFormatter($query, $mapper, 0); // 0 = pas de pagination
        $result = $formatter->format();

        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->count())->toBe(5); // Récupère tout
    });

    it('résout un DB QueryBuilder brut avec pagination', function () {
        $query = DB::table('formatter_models');
        $mapper = fn($item) => ['mapped_id' => $item->id];

        $formatter = new ResponseFormatter($query, $mapper, 3);
        $result = $formatter->format();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->count())->toBe(3)
            ->and($result->first())->toHaveKey('mapped_id');
    });
});

describe('Sécurités et configurations', function () {

    it('bride la pagination à max_per_page selon la configuration', function () {
        // On force la configuration maximale à 2
        config(['crud-service-generator.pagination.max_per_page' => 2]);

        $query = FormatterTestModel::query();
        $mapper = fn($item) => $item;

        // On demande 100 éléments par page (doit être bridé à 2)
        $formatter = new ResponseFormatter($query, $mapper, 100);
        $result = $formatter->format();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($result->perPage())->toBe(2) // La limite a fonctionné
            ->and($result->count())->toBe(2);
    });
});