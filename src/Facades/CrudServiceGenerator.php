<?php

namespace EstebanSmolak19\CrudServiceGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EstebanSmolak19\CrudServiceGenerator\CrudServiceGenerator
 */
class CrudServiceGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \EstebanSmolak19\CrudServiceGenerator\CrudServiceGenerator::class;
    }
}
