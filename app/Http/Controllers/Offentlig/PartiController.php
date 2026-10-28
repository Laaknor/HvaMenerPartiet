<?php

namespace App\Http\Controllers\Offentlig;

use App\Enums\Digitaliseringsstatus;
use App\Http\Controllers\Controller;
use App\Models\Parti;
use App\Models\Tema;
use App\Sok\Temasok;
use App\Visning\Partikort;
use App\Visning\Temaoversikt;
use Illuminate\View\View;

/** Partiene, og alt ett parti mener på én side. */
class PartiController extends Controller
{
    public function index(Temaoversikt $oversikt, Temasok $sok): View
    {
        return view('offentlig.partier', [
            'partier' => $oversikt->partier()->load(['programmer' => fn ($q) => $q
                ->gjeldende()
                ->where('digitalisering_status', Digitaliseringsstatus::Godkjent)]),
            'forslag' => $sok->forslag(),
        ]);
    }

    public function vis(Parti $parti, Temaoversikt $oversikt, Temasok $sok): View
    {
        abort_unless($oversikt->partier()->contains('id', $parti->id), 404);

        $temaer = $sok->publiserte();
        $alle = $oversikt->forMange($temaer, Temaoversikt::BEGGE, $parti);

        // Ett kort per tema der partiet er kontrollert, gruppert etter merkelapp.
        $rader = $temaer
            ->map(fn (Tema $tema) => ['tema' => $tema, 'kort' => $alle[$tema->id]['kort']->first()])
            ->filter(fn (array $rad) => $rad['kort'] instanceof Partikort)
            ->sortBy(fn (array $rad) => $rad['kort']->sortering())
            ->groupBy(fn (array $rad) => $rad['kort']->verdi?->value ?? 'ingen');

        return view('offentlig.parti', [
            'parti' => $parti->load(['programmer' => fn ($q) => $q
                ->gjeldende()
                ->where('digitalisering_status', Digitaliseringsstatus::Godkjent)]),
            'grupper' => $rader,
            'ikkeKontrollert' => $temaer->count() - $rader->flatten(1)->count(),
            'forslag' => $sok->forslag(),
        ]);
    }
}
