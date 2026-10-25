<?php

use App\Http\Controllers\Offentlig\ForsideController;
use App\Http\Controllers\Offentlig\ProgramController;
use App\Http\Controllers\Offentlig\SokController;
use App\Http\Controllers\Offentlig\TemaController;
use App\Http\Controllers\Offentlig\TilbakemeldingController;
use App\Http\Controllers\SeoController;
use App\Seo\Kilder\KapittelSider;
use App\Seo\Kilder\ProgramSider;
use App\Seo\Kilder\TemaSider;
use Illuminate\Support\Facades\Route;

// Offentlige sider. GET-ruter her kommer automatisk med i sitemap.xml og
// llms.txt og caches i 24 timer. Ruter med parametere tas med i sitemap når
// de oppgir en kilde: ->sitemap(TemaSider::class). Se README.
Route::get('/', ForsideController::class)
    ->name('forside')
    ->sitemap(tittel: 'Forside', beskrivelse: config('seo.beskrivelse'), prioritet: 1.0);

Route::get('sok', SokController::class)->name('sok')->utenforSitemap();

Route::get('tema', [TemaController::class, 'index'])
    ->name('temaer')
    ->sitemap(tittel: 'Alle temaer', beskrivelse: 'Alle temaer som er analysert og kontrollert', prioritet: 0.9);
Route::get('tema/{tema:slug}', [TemaController::class, 'vis'])->name('tema')->sitemap(TemaSider::class);

Route::get('program', [ProgramController::class, 'index'])
    ->name('programmer')
    ->sitemap(tittel: 'Partiprogrammene', beskrivelse: 'Arbeids- og prinsipprogrammene som standpunktene bygger på, digitalisert med lenke til hver setning');
Route::get('program/{parti:slug}/{program}', [ProgramController::class, 'vis'])->name('program')->sitemap(ProgramSider::class);
Route::get('program/{parti:slug}/{program}/{kapittel}', [ProgramController::class, 'kapittel'])
    ->name('program.kapittel')
    ->sitemap(KapittelSider::class);

Route::view('om', 'offentlig.om')
    ->name('om')
    ->sitemap(tittel: 'Om og metode', beskrivelse: 'Hvordan standpunktene lages og kontrolleres, skalaen og kjente svakheter');

Route::middleware('throttle:10,1')->group(function () {
    Route::post('tema/foresla', [TilbakemeldingController::class, 'foreslaTema'])->name('tema.foresla');
    Route::post('tema/{tema:slug}/meld-fra', [TilbakemeldingController::class, 'meldFra'])->name('tema.meld-fra');
});

// For søkemotorer, LLM-er og sikkerhetsforskere
Route::controller(SeoController::class)->group(function () {
    Route::get('robots.txt', 'robots')->utenforSitemap();
    Route::get('sitemap.xml', 'sitemap')->utenforSitemap();
    Route::get('llms.txt', 'llms')->utenforSitemap();
    Route::get('llms-full.txt', 'llmsFull')->utenforSitemap();
    Route::get('.well-known/security.txt', 'securityTxt')->utenforSitemap();
    Route::permanentRedirect('security.txt', '/.well-known/security.txt')->utenforSitemap();
});
