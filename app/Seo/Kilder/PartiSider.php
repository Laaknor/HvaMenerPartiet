<?php

namespace App\Seo\Kilder;

use App\Models\Parti;
use App\Seo\Side;
use App\Seo\Sitemapkilde;

/** Én side per aktivt parti med alle temaene. */
class PartiSider implements Sitemapkilde
{
    public function sider(): iterable
    {
        $partier = Parti::query()
            ->where(fn ($q) => $q->whereNull('aktiv_til')->orWhere('aktiv_til', '>=', now()))
            ->orderBy('navn')
            ->get();

        foreach ($partier as $parti) {
            yield new Side(
                url: route('parti', $parti),
                tittel: "Hva mener {$parti->navn}?",
                beskrivelse: "Standpunktene til {$parti->navn} i alle temaene, med ordrett sitat fra programmet.",
                sistEndret: $parti->updated_at,
                endringsfrekvens: 'weekly',
                prioritet: 0.7,
            );
        }
    }
}
