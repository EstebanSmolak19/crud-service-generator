<?php

namespace EstebanSmolak19\CrudServiceGenerator\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;


interface IFillableContract
{
    /**
     * Récupère les éléments à injecter dans la ressource
     * @return array Les élements.
     */
    public function getResourceFields(): array;

    /**
     * Récupère la ressource. Par défaut c'est une ressource générique (BaseResource::class).
     * Si l'on souhaite une ressource custom, alors il faut overide la variable $resource (Ex. UserResource::class).
     * @return String La ressource.
     */
    public function getRessource(): string;

    /**
     * Récupère le nombre d'éléments par page de la pagination.
     * Par défaut, la configuration se trouve dans le fichier de config.
     * La priorité est Service > Config. Il faut overide la variable $perPage dans le service.
     * @return integer Le nombre par page.
     */
    public function getPerPage(): int;

    /**
     * Applique un filtre croissant ou décroissant
     * sur un champ que l'utilisateur choisis dans le code en paramètre
     * avec un overide de $orderBy = ['champs' => "ASC" ou "DESC"]
     * @return Builder
     */
    public function applySorting(Builder|QueryBuilder $query): Builder|QueryBuilder;

}