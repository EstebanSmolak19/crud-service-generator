<?php

namespace EstebanSmolak19\CrudServiceGenerator\Middlewares;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware qui force Laravel à renvoyer une réponse JSON.
*/
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // On force le header "Accept" à "application/json" avant de passer à Sanctum
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}