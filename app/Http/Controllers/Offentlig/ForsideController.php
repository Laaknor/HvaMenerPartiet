<?php

namespace App\Http\Controllers\Offentlig;

use App\Enums\Digitaliseringsstatus;
use App\Http\Controllers\Controller;
use App\Models\Parti;
use App\Sok\Temasok;
use Illuminate\View\View;

class ForsideController extends Controller
{
    public function __invoke(Temasok $sok): View
    {
        return view('offentlig.forside', [
            'temaer' => $sok->publiserte(),
            'forslag' => $sok->forslag(),
            'partier' => Parti::query()
                ->with(['programmer' => fn ($q) => $q->gjeldende()->where('digitalisering_status', Digitaliseringsstatus::Godkjent)])
                ->orderBy('navn')
                ->get(),
        ]);
    }
}
