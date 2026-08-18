<?php

namespace EstebanSmolak19\CrudServiceGenerator\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Traits\Conditionable;

class FileExistenceChecker
{
    use Conditionable;

    private bool $hasErrors = false;

    public function __construct(private Command $command) {}

    /**
     * Vérifie si un fichier existe et affiche une erreur si c'est le cas.
     */
    public function check(string $type, ?string $path, ?string $name): self
    {
        if ($path && file_exists($path)) {
            $this->command->error("Le {$type} [{$name}] existe déjà !");
            $this->hasErrors = true;
        }

        return $this;
    }

    /**
     * Retourne true si au moins un fichier existe déjà.
     */
    public function fails(): bool
    {
        return $this->hasErrors;
    }
}