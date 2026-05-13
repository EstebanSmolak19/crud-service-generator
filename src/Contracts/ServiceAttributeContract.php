<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

interface ServiceAttributeContract
{
    /**
     * @param object $service L'instance du service en cours
     * @param string $method La méthode appelée (create, update, ou méthode perso)
     * @param array $params Les arguments passés à la méthode
     */
    public function handle(object $service, string $method, array &$params): void;
}