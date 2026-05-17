<?php

namespace EstebanSmolak19\CrudServiceGenerator\Controllers\Dashboard;

use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('crud-service-generator::index');
    }
}