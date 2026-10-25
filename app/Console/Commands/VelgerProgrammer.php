<?php

namespace App\Console\Commands;

use App\Models\Program;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Felles valg av programmer for kommandoene. Argumentet kan være en
 * programnøkkel («sv-ap2025») eller en partiforkortelse («sv»).
 */
trait VelgerProgrammer
{
    /** @return Collection<int, Program> */
    protected function valgteProgrammer(string $argument = 'program'): Collection
    {
        $valg = (array) $this->argument($argument);

        if ($valg === [] && ! $this->option('alle')) {
            throw new RuntimeException('Oppgi programnøkkel eller parti, eller bruk --alle.');
        }

        return Program::query()
            ->with('parti')
            ->gjeldende()
            ->when($valg !== [], fn ($q) => $q->where(function ($q) use ($valg) {
                $q->whereIn('nokkel', $valg)->orWhereHas('parti', fn ($q) => $q->whereIn('slug', $valg));
            }))
            ->orderBy('parti_id')
            ->orderBy('type')
            ->get();
    }
}
