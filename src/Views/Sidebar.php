<?php

namespace EstebanSmolak19\CrudServiceGenerator\Views;

use EstebanSmolak19\CrudServiceGenerator\Constants\SidebarMenu;
use Illuminate\View\Component;

class Sidebar extends Component
{
    public array $menu;

    public function __construct()
    {
        $this->menu = SidebarMenu::all();
    }

    public function render()
    {
        return view('crud-service-generator::components.sidebar');
    }
}