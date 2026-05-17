<?php

namespace EstebanSmolak19\CrudServiceGenerator;

use EstebanSmolak19\CrudServiceGenerator\Views\Sidebar;
use Illuminate\Support\ServiceProvider;

class DashboardProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php'); // Charge les routes du dashboard.

        $this->loadViewsFrom(__DIR__ . '../resources/views', 'crud-service-generator');

        $this->loadViewComponentsAs('app', [
            Sidebar::class
        ]);
    }
}