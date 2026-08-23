<?php

namespace EstebanSmolak19\CrudServiceGenerator\Controllers;

use EstebanSmolak19\CrudServiceGenerator\Enums\BulkActions;
use EstebanSmolak19\CrudServiceGenerator\Services\BulkActionGuard;
use EstebanSmolak19\CrudServiceGenerator\Services\ServiceProxy;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CrudControllerBase extends Controller
{
    protected $realService;
    protected array $bulkActions = []; // Tous est fermé par défaut
    protected ?BulkActionGuard $bulkGuard = null;

    public function __construct($service)
    {
        $this->realService = $service;
    }

    /**
     * On intercepte le $this->service qui n'existe pas
     * On l'injecte dans $this->realService
     * On appel le proxy
     */
    public function __get($name)
    {
        if ($name === 'service') {
            return new ServiceProxy($this->realService);
        }
    }

    public function index()
    {
        return response()->json($this->service->all());
    }

    public function store(Request $request)
    {
        $data = $this->service->create($request->all());

        return response()->json($data, 201);
    }

    public function show(int $id)
    {
        return response()->json($this->service->find($id));
    }

    public function update(Request $request, int $id)
    {
        $data = $this->service->update($id, $request->all());

        return response()->json($data);
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);

        return response()->json(null, 204);
    }

    /**
     * Initialise et retourne le guard pour ce contrôleur
    */
    public function bulk(): BulkActionGuard
    {
        if(!$this->bulkGuard) {
            $this->bulkGuard = new BulkActionGuard($this->bulkActions);
        }
        return $this->bulkGuard;
    }

    public function bulkUpdate(Request $request)
    {
        $this->bulk()->authorize(BulkActions::UPDATE);

        $ids = $request->input('ids', []);
        $data = $request->except('ids');
        $result = $this->service->bulkUpdate($ids, $data);
        return response()->json($result);
    }

    public function bulkDelete(Request $request)
    {
        $this->bulk()->authorize(BulkActions::DELETE);

        $ids = $request->input('ids', []);
        $this->service->bulkDelete($ids);
        return response()->json(null, 204);
    }
}
