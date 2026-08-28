<?php

use EstebanSmolak19\CrudServiceGenerator\Resources\BaseResource;
use EstebanSmolak19\CrudServiceGenerator\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

uses(TestCase::class);


class ResourceTestModel extends Model
{
    protected $guarded = [];

    // Surcharge pour simuler des attributs sans avoir besoin d'une vraie BDD
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        // Assigne explicitement les attributs
        $this->setRawAttributes($attributes, true);
    }
}


describe('Logique standard (Fillable)', function () {

    it('filtre les données selon le tableau $fillable fourni', function () {
        $model = new ResourceTestModel([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret',
        ]);

        // On ne veut que 'id' et 'name'
        $resource = new BaseResource($model, ['id', 'name']);

        // On passe directement une nouvelle Request ici
        $result = $resource->toArray(new Request());

        expect($result)->toBe([
            'id' => 1,
            'name' => 'John Doe',
        ])->not->toHaveKey('email')
          ->not->toHaveKey('password');
    });

    it('retourne null pour les colonnes demandées dans $fillable mais absentes du modèle', function () {
        $model = new ResourceTestModel([
            'name' => 'John Doe',
        ]);

        $resource = new BaseResource($model, ['name', 'age']);
        $result = $resource->toArray(new Request());

        expect($result)->toBe([
            'name' => 'John Doe',
            'age' => null, // 'age' n'existe pas, donc fallback à null
        ]);
    });

    it('retourne tous les attributs si $fillable est un tableau vide', function () {
        $attributes = [
            'id' => 10,
            'title' => 'Mon Super Titre',
            'status' => 'active',
        ];
        $model = new ResourceTestModel($attributes);

        $resource = new BaseResource($model, []);
        $result = $resource->toArray(new Request());

        expect($result)->toBe($attributes);
    });

    it('utilise la méthode native parent::toArray si $fillable est strictement égal à [""]', function () {
        $attributes = ['id' => 99, 'role' => 'admin'];
        $model = new ResourceTestModel($attributes);

        $resource = new BaseResource($model, ['']);
        $result = $resource->toArray(new Request());

        // Doit renvoyer tous les attributs transformés par défaut par JsonResource
        expect($result)->toMatchArray($attributes);
    });

    it('récupère correctement les attributs depuis un objet standard (stdClass)', function () {
        $obj = (object) [
            'id' => 42,
            'name' => 'Test Object'
        ];

        // Sans $fillable, il doit utiliser un cast (array) en fallback car getAttributes() n'existe pas sur stdClass
        $resource = new BaseResource($obj, []);
        $result = $resource->toArray(new Request());

        expect($result)->toBe(['id' => 42, 'name' => 'Test Object']);
    });
});

describe('Logique Vue SQL (fromSqlView = true)', function () {

    it('retourne toutes les données et supprime fromSqlView sur un Modèle Eloquent', function () {
        $model = new ResourceTestModel([
            'id' => 5,
            'name' => 'SQL Name',
            'virtual_column' => 'Calculated',
        ]);

        // On simule l'injection de la propriété dynamique depuis le service
        $model->fromSqlView = true;

        // Même si on donne un $fillable restrictif, il doit être ignoré
        $resource = new BaseResource($model, ['id']);
        $result = $resource->toArray(new Request());

        expect($result)->toBe([
            'id' => 5,
            'name' => 'SQL Name',
            'virtual_column' => 'Calculated',
        ])->not->toHaveKey('fromSqlView');
    });

    it('retourne toutes les données et supprime fromSqlView sur un objet standard (stdClass)', function () {
        $obj = (object) [
            'id' => 12,
            'total_amount' => 150.50,
            'fromSqlView' => true,
        ];

        $resource = new BaseResource($obj, ['id']);
        $result = $resource->toArray(new Request());

        expect($result)->toBe([
            'id' => 12,
            'total_amount' => 150.50,
        ])->not->toHaveKey('fromSqlView');
    });

    it('gère correctement le cas où la ressource est directement un tableau (Array) avec la clé fromSqlView', function () {
        // Dans certains cas rares, DB::table() ou des casts pourraient passer un array
        $arrayData = [
            'id' => 77,
            'fromSqlView' => true,
        ];

        // On le cast en objet car JsonResource s'attend généralement à manipuler des objets via $this->resource->...
        // Si on passait un array pur, $this->resource->fromSqlView lèverait une erreur PHP
        $obj = (object) $arrayData;

        $resource = new BaseResource($obj);
        $result = $resource->toArray(new Request());

        expect($result)->toBe(['id' => 77])
          ->not->toHaveKey('fromSqlView');
    });
});