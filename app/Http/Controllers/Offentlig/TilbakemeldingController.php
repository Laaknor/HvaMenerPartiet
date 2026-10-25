<?php

namespace App\Http\Controllers\Offentlig;

use App\Enums\Temastatus;
use App\Http\Controllers\Controller;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Models\Temaforslag;
use App\Models\Tilbakemelding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** «Foreslå tema» og «Meld fra om feil». Begge havner i admin. */
class TilbakemeldingController extends Controller
{
    public function foreslaTema(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tema' => ['required', 'string', 'min:2', 'max:200'],
            // Skjult felt som bare roboter fyller ut.
            'nettside' => ['prohibited'],
        ]);

        Temaforslag::registrer($data['tema']);

        return back()->with('status', "Takk! «{$data['tema']}» er foreslått som nytt tema.");
    }

    public function meldFra(Request $request, Tema $tema): RedirectResponse
    {
        abort_unless($tema->status === Temastatus::Publisert, 404);

        $data = $request->validate([
            'parti_id' => ['required', 'integer', 'exists:partier,id'],
            'standpunkt_id' => ['nullable', 'integer'],
            'tekst' => ['required', 'string', 'min:5', 'max:2000'],
            'nettside' => ['prohibited'],
        ]);

        // Standpunktet må høre til temaet, ellers lagres meldingen uten det.
        $standpunkt = isset($data['standpunkt_id'])
            ? Standpunkt::where('tema_id', $tema->id)->find($data['standpunkt_id'])
            : null;

        Tilbakemelding::create([
            'tema_id' => $tema->id,
            'parti_id' => $data['parti_id'],
            'standpunkt_id' => $standpunkt?->id,
            'tekst' => $data['tekst'],
        ]);

        return redirect()
            ->to(route('tema', $tema).'#parti-'.$data['parti_id'])
            ->with('status', 'Takk for at du meldte fra! Vi ser på det før standpunktet eventuelt endres.');
    }
}
