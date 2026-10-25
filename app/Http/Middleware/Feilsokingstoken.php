<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-et for feilsøkingspakker er av til FEILSOKING_TOKEN er satt, og svarer
 * da 404 som om det ikke fantes. Tokenet sammenlignes i konstant tid.
 */
class Feilsokingstoken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('hvamenerpartiet.feilsoking.token');

        if (mb_strlen($token) < 32) {
            abort(404);
        }

        if (! is_string($request->bearerToken()) || ! hash_equals($token, $request->bearerToken())) {
            abort(401, 'Mangler eller feil token.');
        }

        return $next($request);
    }
}
