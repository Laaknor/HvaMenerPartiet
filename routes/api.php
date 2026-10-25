<?php

use App\Http\Controllers\FeilsokingController;
use App\Http\Middleware\Feilsokingstoken;
use Illuminate\Support\Facades\Route;

// Feilsøkingspakker fra «Send til feilsøking» i admin. Kun lesing, med
// token fra FEILSOKING_TOKEN. Av når tokenet ikke er satt.
Route::middleware([Feilsokingstoken::class, 'throttle:60,1'])
    ->prefix('feilsoking')
    ->group(function () {
        Route::get('/', [FeilsokingController::class, 'index'])->name('api.feilsoking');
        Route::get('{sak:referanse}', [FeilsokingController::class, 'vis'])->name('api.feilsoking.vis');
    });
