<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

use Illuminate\Console\Command;

interface ICommandService
{
    /**
     * Centralise la génération du fichier à partir du stub et du state
     */
    public function generate(Command $command, array $state): int;

    /**
     * Questions sur la synchronisation avec un model
     */
    public function interactModelCli(Command $command): string;

    /**
     * Détermine le Namespace du fichier généré
     */
    public function determineNamespace(string $input, string $configPath): string;

    /**
     * Détermine le nom du service que l'on souhaite créer.
     */
    public function getServiceName(Command $command): string;

    /**
     * Récupère le nom de la configuration du package
     */
    public function getConfigName(): string;

    /**
     * Retourne le nom du controller que l'on souhaite.
     *
     * @return string Le nom du controller
     */
    public function getControllerName(Command $command): string;

    /**
     * Retourne le nom de la route CRUD.
     */
    public function getRouteName(Command $command): string;
}
