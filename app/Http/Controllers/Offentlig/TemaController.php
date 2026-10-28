<?php

namespace App\Http\Controllers\Offentlig;

use App\Enums\Temastatus;
use App\Http\Controllers\Controller;
use App\Models\Tema;
use App\Sok\Temasok;
use App\Visning\Standpunkttabell;
use App\Visning\Temaoversikt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemaController extends Controller
{
    public function index(Temasok $sok, Temaoversikt $oversikt): View
    {
        return view('offentlig.temaer', [
            'tabell' => Standpunkttabell::for($sok->publiserte(), $oversikt),
            'forslag' => $sok->forslag(),
        ]);
    }

    public function vis(Request $request, Tema $tema, Temaoversikt $oversikt, Temasok $sok): View
    {
        abort_unless($tema->status === Temastatus::Publisert, 404);

        $visning = in_array($request->query('visning'), Temaoversikt::VISNINGER, true)
            ? $request->query('visning')
            : Temaoversikt::BEGGE;

        ['kort' => $kort, 'ikkeVurdert' => $ikkeVurdert] = $oversikt->for($tema, $visning);

        // Relaterte temaer: samme overordnede tema først, ellers resten av listen.
        $andre = $sok->publiserte()
            ->reject(fn (Tema $t) => $t->id === $tema->id)
            ->sortBy(fn (Tema $t) => [
                $tema->overordnet_tema_id && $t->overordnet_tema_id === $tema->overordnet_tema_id ? 0 : 1,
                $t->id === $tema->overordnet_tema_id || $t->overordnet_tema_id === $tema->id ? 0 : 1,
            ])
            ->take(8);

        return view('offentlig.tema', [
            'tema' => $tema,
            'kort' => $kort,
            'ikkeVurdert' => $ikkeVurdert,
            'grupper' => Temaoversikt::grupper($kort),
            'visning' => $visning,
            'andre' => $andre,
            'forslag' => $sok->forslag(),
            // Kom brukeren hit fra søket, spores søket i Matomo.
            'sokeord' => trim(mb_substr((string) $request->query('sok', ''), 0, 200)) ?: null,
        ]);
    }
}
