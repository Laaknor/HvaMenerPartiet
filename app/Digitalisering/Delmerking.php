<?php

namespace App\Digitalisering;

use App\Enums\Programdel;
use App\Models\Avsnitt;
use App\Models\Kapittel;
use App\Models\Program;
use App\Models\Tekstbit;
use InvalidArgumentException;

/**
 * Merker kapitler i et kombinert dokument som prinsipp- eller handlingsdel,
 * når overskriftene ikke var tydelige nok. Underkapitler følger med.
 */
class Delmerking
{
    /**
     * @param  list<string>  $slugger  kapittel-slugger, for eksempel kap-1 og kap-2
     * @return int antall kapitler som ble merket
     */
    public function merk(Program $program, Programdel $del, array $slugger): int
    {
        if (! $program->erKombinert()) {
            throw new InvalidArgumentException('Bare kombinerte dokumenter deles i prinsipp- og handlingsdel.');
        }

        $antall = 0;
        foreach ($slugger as $slug) {
            $ider = Kapittel::query()
                ->where('program_id', $program->id)
                ->where(fn ($q) => $q->where('slug', $slug)->orWhere('slug', 'like', $slug.'-%'))
                ->pluck('id');

            Kapittel::query()->whereIn('id', $ider)->update(['del' => $del]);
            Avsnitt::query()->whereIn('kapittel_id', $ider)->update(['del' => $del]);
            Tekstbit::query()->whereIn('kapittel_id', $ider)->update(['del' => $del]);
            $antall += $ider->count();
        }

        $this->oppdaterMerknad($program);

        return $antall;
    }

    /** Fjerner merknaden om umerkede kapitler når alle er merket. */
    private function oppdaterMerknad(Program $program): void
    {
        $umerket = Kapittel::query()->where('program_id', $program->id)->where('niva', 1)->whereNull('del')->count();
        $linjer = array_filter(explode("\n", (string) $program->digitalisering_merknad), fn ($l) => ! str_contains($l, 'mangler prinsipp- eller handlingsdel'));
        if ($umerket > 0) {
            $linjer[] = "{$umerket} hovedkapitler mangler prinsipp- eller handlingsdel og må merkes manuelt.";
        }

        $program->update(['digitalisering_merknad' => $linjer ? implode("\n", $linjer) : null]);
    }
}
