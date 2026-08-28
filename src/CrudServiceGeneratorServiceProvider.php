<?php

namespace EstebanSmolak19\CrudServiceGenerator;

use EstebanSmolak19\CrudServiceGenerator\Commands\ApplyConfigCommand;
use EstebanSmolak19\CrudServiceGenerator\Commands\AttributeCommand;
use EstebanSmolak19\CrudServiceGenerator\Commands\CrudServiceGeneratorCommand;
use EstebanSmolak19\CrudServiceGenerator\Commands\GeneratedFrontendModel;
use EstebanSmolak19\CrudServiceGenerator\Commands\GenerateModel;
use EstebanSmolak19\CrudServiceGenerator\Commands\HelpCommand;
use EstebanSmolak19\CrudServiceGenerator\Contracts\ICommandService;
use EstebanSmolak19\CrudServiceGenerator\Contracts\IModelService;
use EstebanSmolak19\CrudServiceGenerator\Services\CommandService;
use EstebanSmolak19\CrudServiceGenerator\Services\ModelService;
use EstebanSmolak19\CrudServiceGenerator\Services\RouteMacrosRegister;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CrudServiceGeneratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('crud-service-generator')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_crud_service_generator_table')
            ->hasCommands([
                CrudServiceGeneratorCommand::class,
                GenerateModel::class,
                ApplyConfigCommand::class,
                HelpCommand::class,
                AttributeCommand::class,
                GeneratedFrontendModel::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(IModelService::class, ModelService::class);
        $this->app->bind(ICommandService::class, CommandService::class);
    }

    public function packageBooted(): void
    {
        $router = $this->app['router'];

        // Enregistre les macros.
        RouteMacrosRegister::register($router);

        // Chargement du fichier de routes
        $routesPath = base_path('routes/service_generator.php');
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
    }
}
