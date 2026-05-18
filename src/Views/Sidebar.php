<?php

namespace EstebanSmolak19\CrudServiceGenerator\Views;

use EstebanSmolak19\CrudServiceGenerator\Constants\SidebarMenu;
use EstebanSmolak19\CrudServiceGenerator\Services\Dashboard\ScannerService;
use Illuminate\View\Component;

class Sidebar extends Component
{
    public array $menu;
    public array $controllers;

    public function __construct(private ScannerService $service)
    {
        $this->menu = SidebarMenu::all();
        $this->controllers = $this->service->getControllers();
    }

    public function render()
    {
        return view('crud-service-generator::components.sidebar');
    }
}