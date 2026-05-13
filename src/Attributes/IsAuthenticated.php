<?php

namespace EstebanSmolak19\CrudServiceGenerator\Attributes;

use Attribute;
use EstebanSmolak19\CrudServiceGenerator\Contracts\ServiceAttributeContract;
use Illuminate\Support\Facades\Auth;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsAuthenticated implements ServiceAttributeContract
{
    /**
     * Vérifie si l'utilisateur est connecté.
     */
    public function handle(object $service, string $method, array &$params): void
    {
        if (!Auth::check()) {
            // On bloque tout avant d'arriver au service
            abort(401, "Authentification requise pour accéder à la méthode : {$method}");
        }
    }
}