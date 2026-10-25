<?php

namespace App\Seo\Kilder;

use App\Enums\Digitaliseringsstatus;
use App\Models\Program;
use App\Seo\Side;
use App\Seo\Sitemapkilde;

/** Innholdsfortegnelsen for hvert godkjente, digitaliserte program. */
class ProgramSider implements Sitemapkilde
{
    public function sider(): iterable
    {
        $programmer = Program::query()
            ->where('digitalisering_status', Digitaliseringsstatus::Godkjent)
            ->with('parti')
            ->get();

        foreach ($programmer as $program) {
            yield new Side(
                url: route('program', [$program->parti, $program->slug]),
                tittel: "{$program->parti->navn}: {$program->tittel}",
                beskrivelse: 'Hele programmet, digitalisert med lenke til hver setning.',
                sistEndret: $program->godkjent ?? $program->updated_at,
                endringsfrekvens: 'monthly',
                prioritet: 0.5,
            );
        }
    }
}
