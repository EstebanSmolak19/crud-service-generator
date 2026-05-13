<?php

namespace EstebanSmolak19\CrudServiceGenerator\Commands;

use Illuminate\Console\GeneratorCommand;

class AttributeCommand extends GeneratorCommand
{
    protected $name = 'make:attribute';
    protected $description = 'Créer un attribut de service personnalisé';
    protected $type = 'Attribute';

    protected function getStub()
    {
        return __DIR__ . '/../stubs/Attribute.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Attributes';
    }
}