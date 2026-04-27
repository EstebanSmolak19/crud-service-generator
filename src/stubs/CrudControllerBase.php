<?php

namespace EstebanSmolak19\CrudServiceGenerator\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CrudControllerBase extends Controller
{
    protected $service;

    public function __construct($service)
    {
        $this->service = $service;
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

    public function show($id)
    {
        return response()->json($this->service->find($id));
    }

    public function update(Request $request, $id)
    {
        $data = $this->service->update($id, $request->all());
        return response()->json($data);
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }
}