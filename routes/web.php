<?php

use EstebanSmolak19\CrudServiceGenerator\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;


Route::middleware(['web'])->controller(DashboardController::class)->prefix('/crud-dashboard')->group(function() {
    Route::get('/', 'index');
});