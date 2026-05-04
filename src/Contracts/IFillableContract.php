<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

interface IFillableContract
{
    /**
     * Récupère les éléments à injecter dans la ressource
     * @return array Les élements.
     */
    public function getResourceFields(): array;
}