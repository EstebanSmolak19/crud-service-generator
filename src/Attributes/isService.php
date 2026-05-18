<?php

namespace EstebanSmolak19\CrudServiceGenerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IsService
{
    public function __construct() { }
}