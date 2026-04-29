<?php

namespace EstebanSmolak19\CrudServiceGenerator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use EstebanSmolak19\CrudServiceGenerator\Commands\CrudServiceGeneratorCommand;

class CrudServiceGeneratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('crud-service-generator')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_crud_service_generator_table')
            ->hasCommand(CrudServiceGeneratorCommand::class);
    }

    public function packageBooted()
    {
        $routesPath = base_path('routes/service_generator.php');
        if(file_exists($routesPath)){
            $this->loadRoutesFrom($routesPath);
        }
    }
}
