<?php

namespace App\Http\Middleware;

use App\Models\Tema;
use App\Models\Temabesok;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Teller besøk på temasidene, til «mest besøkte» på forsiden. Ligger foran
 * sidecachen i web-gruppen, så besøk som besvares fra cachen også telles.
 * Tellingen skjer etter at svaret er sendt, og roboter telles ikke.
 */
class TellTemabesok
{
    private const ROBOTER = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|monitor|curl|wget|python|headless|lighthouse/i';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET') || ! $request->routeIs('tema') || $response->getStatusCode() >= 400) {
            return;
        }

        // Forhåndslasting fra nettleseren er ikke et besøk.
        if ($request->headers->get('Purpose') === 'prefetch' || $request->headers->get('Sec-Purpose')) {
            return;
        }

        if (preg_match(self::ROBOTER, (string) $request->userAgent()) || ! $request->userAgent()) {
            return;
        }

        $tema = $request->route('tema');
        if (! $tema instanceof Tema) {
            return;
        }

        try {
            Temabesok::tell($tema);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
