<?php

namespace App\Http\Middleware;

use App\Sidecache\Sidecache as Cache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Svarer offentlige sider fra cachen i 24 timer. Ligger i web-gruppen, så nye
 * sider tas med automatisk. En rute kan holdes utenfor med
 * ->withoutMiddleware(\App\Http\Middleware\Sidecache::class).
 */
class Sidecache
{
    public function __construct(private readonly Cache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cache->gjelder($request)) {
            return $next($request);
        }

        if ($lagret = $this->cache->hent($request)) {
            $response = $this->cache->svar($request, $lagret);
            $response->headers->set('X-Sidecache', 'treff');

            return $response;
        }

        $response = $next($request);

        if (! $this->cache->kanLagres($response)) {
            return $response;
        }

        $svar = $this->cache->svar($request, $this->cache->lagre($request, $response));
        $svar->headers->set('X-Sidecache', 'lagret');

        // Cookies og andre headere fra siden (f.eks. økten) beholdes.
        foreach ($response->headers->getCookies() as $cookie) {
            $svar->headers->setCookie($cookie);
        }

        return $svar;
    }
}
