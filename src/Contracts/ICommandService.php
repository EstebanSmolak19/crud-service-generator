<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

use Illuminate\Console\Command;

interface ICommandService
{
    /**
     * Centralise la génération du fichier à partir du stub et du state
     *
     * @param Command $command
     * @param array $state
     * @return int
     */
    public function generate(Command $command, array $state): int;

    /**
     * Questions sur la synchronisation avec un model
     *
     * @param Command $command
     * @return string
     */
    public function interactModelCli(Command $command): string;

    /**
     * Affiche les informations d'aide personnalisées
     *
     * @param Command $command
     * @return void
     */
    public function helpOption(Command $command): void;

    /**
     * Détermine le Namespace du fichier généré
     *
     * @param string $input
     * @param string $configPath
     * @return string
     */
    public function determineNamespace(string $input, string $configPath): string;

    /**
     * Détermine le nom du service que l'on souhaite créer.
     *
     * @param Command $command
     * @return string
     */
    public function getServiceName(Command $command): string;

    /**
     * Détermine s'il s'agit d'un Id de type Int (id) ou de type string (uuid)
     *
     * @param Command $command
     * @param bool $isCrud
     * @return array
     */
    public function getIdConfiguration(Command $command, bool $isCrud): array;

    /**
     * Récupère le nom de la configuration du package
     *
     * @return string
     */
    public function getConfigName(): string;
}