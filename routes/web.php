<?php

use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

// Offentlige sider. GET-ruter her kommer automatisk med i sitemap.xml og
// llms.txt og caches i 24 timer. Ruter med parametere tas med i sitemap når
// de oppgir en kilde: ->sitemap(TemaSider::class). Se README.
Route::get('/', function () {
    return view('welcome');
})->name('forside')->sitemap(tittel: 'Forside', beskrivelse: config('seo.beskrivelse'), prioritet: 1.0);

// For søkemotorer, LLM-er og sikkerhetsforskere
Route::controller(SeoController::class)->group(function () {
    Route::get('robots.txt', 'robots')->utenforSitemap();
    Route::get('sitemap.xml', 'sitemap')->utenforSitemap();
    Route::get('llms.txt', 'llms')->utenforSitemap();
    Route::get('llms-full.txt', 'llmsFull')->utenforSitemap();
    Route::get('.well-known/security.txt', 'securityTxt')->utenforSitemap();
    Route::permanentRedirect('security.txt', '/.well-known/security.txt')->utenforSitemap();
});
