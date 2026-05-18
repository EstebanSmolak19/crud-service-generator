<?php

namespace EstebanSmolak19\CrudServiceGenerator\Controllers\Dashboard;

use EstebanSmolak19\CrudServiceGenerator\Services\Dashboard\ScannerService;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __construct(private ScannerService $service) {}

    public function index()
    {
        return view('crud-service-generator::index');
    }
}
