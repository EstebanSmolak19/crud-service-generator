<?php

namespace EstebanSmolak19\CrudServiceGenerator\Attributes;

use Attribute;
use EstebanSmolak19\CrudServiceGenerator\Contracts\ServiceAttributeContract;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsAuthenticated implements ServiceAttributeContract
{
    /**
     * Vérifie si l'utilisateur est connecté.
     */
    public function handle(object $service, string $method, array &$params): void
    {
        if (! Auth::check()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => config('crud-service-generator.messages.unauthorized', 'Non autorisé.'),
                'error' => str_replace(
                    ':method',
                    $method,
                    config(
                        'crud-service-generator.messages.unauthorized_detail',
                        'Authentification requise pour accéder à la méthode : :method'
                    )
                ),
            ], 401));
        }
    }
}
