<?php

namespace EstebanSmolak19\CrudServiceGenerator;

use EstebanSmolak19\CrudServiceGenerator\Commands\ApplyConfigCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use EstebanSmolak19\CrudServiceGenerator\Commands\CrudServiceGeneratorCommand;
use EstebanSmolak19\CrudServiceGenerator\Commands\GenerateModel;
use EstebanSmolak19\CrudServiceGenerator\Commands\HelpCommand;
use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use EstebanSmolak19\CrudServiceGenerator\Services\CommandService;
use EstebanSmolak19\CrudServiceGenerator\Services\ModelService;

class CrudServiceGeneratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('crud-service-generator')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                    CrudServiceGeneratorCommand::class,
                    GenerateModel::class,
                    ApplyConfigCommand::class,
                    HelpCommand::class
                ]);
    }

    public function packageBooted()
    {
        $routesPath = base_path('routes/service_generator.php');
        if(file_exists($routesPath)){
            $this->loadRoutesFrom($routesPath);
        }
    }

    public function register()
    {
        parent::register();

        $this->app->bind(IModelService::class, ModelService::class);
        $this->app->bind(ICommandService::class, CommandService::class);
    }
}
