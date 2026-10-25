<?php

namespace App\Http\Controllers\Offentlig;

use App\Http\Controllers\Controller;
use App\Sok\Temasok;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Søk på tema. Søket slår bare opp publiserte temaer, aldri en LLM. Peker
 * søket entydig på ett tema (navn eller synonym), sendes brukeren rett dit.
 */
class SokController extends Controller
{
    public function __invoke(Request $request, Temasok $sok): View|RedirectResponse
    {
        $sokeord = trim(mb_substr((string) $request->query('q', ''), 0, 200));

        if ($sokeord === '') {
            return redirect()->route('forside');
        }

        if ($tema = $sok->eksakt($sokeord)) {
            return redirect()->route('tema', ['tema' => $tema, 'sok' => $sokeord]);
        }

        $treff = $sok->sok($sokeord);

        return view('offentlig.sok', [
            'sokeord' => $sokeord,
            'treff' => $treff,
            'naermeste' => $treff->isEmpty() ? $sok->naermeste($sokeord) : collect(),
            'forslag' => $sok->forslag(),
        ]);
    }
}
