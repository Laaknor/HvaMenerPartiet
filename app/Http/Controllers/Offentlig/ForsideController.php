<?php

namespace App\Http\Controllers\Offentlig;

use App\Http\Controllers\Controller;
use App\Models\Tema;
use App\Models\Temabesok;
use App\Sok\Temasok;
use App\Visning\Standpunkttabell;
use App\Visning\Temaoversikt;
use Illuminate\View\View;

class ForsideController extends Controller
{
    /** Antall mest besøkte temaer i oversikten. */
    public const POPULARE = 4;

    /** Antall tilfeldige temaer i oversikten. */
    public const TILFELDIGE = 3;

    /**
     * Så mange kandidater sendes med for de tilfeldige radene. Siden caches,
     * så JavaScript trekker de tre som vises blant disse ved hver visning.
     */
    private const KANDIDATER = 15;

    public function __invoke(Temasok $sok, Temaoversikt $oversikt): View
    {
        $temaer = $sok->publiserte();

        // De mest besøkte siste 30 dager. Mangler det besøk, fylles det opp med tilfeldige.
        $rekkefolge = Temabesok::mestBesokte()->flip();
        $populare = $temaer
            ->filter(fn (Tema $t) => $rekkefolge->has($t->id))
            ->sortBy(fn (Tema $t) => $rekkefolge[$t->id])
            ->take(self::POPULARE)
            ->values();

        $kandidater = $temaer
            ->reject(fn (Tema $t) => $populare->contains('id', $t->id))
            ->shuffle()
            ->take(self::KANDIDATER + self::POPULARE - $populare->count())
            ->values();

        $tilfeldigeSynlige = self::TILFELDIGE + self::POPULARE - $populare->count();
        $grupper = $populare->mapWithKeys(fn (Tema $t) => [$t->id => 'populær'])
            ->union($kandidater->mapWithKeys(fn (Tema $t) => [$t->id => 'tilfeldig']))
            ->all();

        $tabell = Standpunkttabell::for(
            $populare->concat($kandidater),
            $oversikt,
            $grupper,
            $kandidater->slice($tilfeldigeSynlige)->pluck('id')->all(),
        );

        return view('offentlig.forside', [
            'tabell' => $tabell,
            'antallTemaer' => $temaer->count(),
            'tilfeldige' => $tilfeldigeSynlige,
            'harPopulare' => $populare->isNotEmpty(),
            'forslag' => $sok->forslag(),
        ]);
    }
}
