<?php

namespace App\Seo\Kilder;

use App\Enums\Digitaliseringsstatus;
use App\Models\Kapittel;
use App\Seo\Side;
use App\Seo\Sitemapkilde;

/** Hvert hovedkapittel i de godkjente programmene. */
class KapittelSider implements Sitemapkilde
{
    public function sider(): iterable
    {
        $kapitler = Kapittel::query()
            ->whereNull('overordnet_kapittel_id')
            ->whereHas('program', fn ($q) => $q->where('digitalisering_status', Digitaliseringsstatus::Godkjent))
            ->with('program.parti')
            ->orderBy('program_id')
            ->orderBy('rekkefolge')
            ->get();

        foreach ($kapitler as $kapittel) {
            $program = $kapittel->program;
            yield new Side(
                url: route('program.kapittel', [$program->parti, $program->slug, $kapittel->slug]),
                tittel: "{$program->parti->navn}, {$program->tittel}: {$kapittel->tittel}",
                sistEndret: $program->godkjent ?? $program->updated_at,
                endringsfrekvens: 'monthly',
                prioritet: 0.3,
            );
        }
    }
}
