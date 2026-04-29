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
     * Affiche les informations d'aide personnalisées
     */
    public function helpOption(Command $command): void;

    /**
     * Détermine le Namespace du fichier généré
     */
    public function determineNamespace(string $input, string $configPath): string;

    /**
     * Détermine le nom du service que l'on souhaite créer.
     */
    public function getServiceName(Command $command): string;

    /**
     * Détermine s'il s'agit d'un Id de type Int (id) ou de type string (uuid)
     */
    public function getIdConfiguration(Command $command, bool $isCrud): array;

    /**
     * Récupère le nom de la configuration du package
     */
    public function getConfigName(): string;
}
