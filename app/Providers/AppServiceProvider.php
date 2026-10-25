<?php

namespace App\Providers;

use App\Ollama\OllamaKlient;
use App\Sidecache\Sidecache;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaKlient::class, fn () => OllamaKlient::fraKonfig());
        $this->app->singleton(Sidecache::class);

        // ->sitemap(...) og ->utenforSitemap() på ruter. Se App\Seo\Sideregister.
        Route::macro('sitemap', function (?string $kilde = null, ?string $tittel = null, ?string $beskrivelse = null, ?string $endringsfrekvens = null, ?float $prioritet = null) {
            /** @var Route $this */
            $this->action['sitemap'] = array_filter(
                compact('kilde', 'tittel', 'beskrivelse', 'endringsfrekvens', 'prioritet'),
                fn ($verdi) => $verdi !== null,
            );

            return $this;
        });

        Route::macro('utenforSitemap', function () {
            /** @var Route $this */
            $this->action['sitemap'] = false;

            return $this;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Kanoniske lenker, sitemap og llms.txt skal bruke https også bak en proxy.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Ny deployment tømmer sidecachen via «php artisan optimize» (og optimize:clear).
        $this->optimizes(optimize: 'sidecache:tom', clear: 'sidecache:tom', key: 'sidecache');
    }
}
