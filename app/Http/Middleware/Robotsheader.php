<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ber søkemotorer og LLM-er la være å indeksere admin, Horizon og tekniske
 * stier, og hele siden utenfor produksjon.
 */
class Robotsheader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('seo.indekser') || Str::is(config('seo.ikke_indekser', []), $request->path())) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
