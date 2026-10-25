<?php

namespace App\Analyse;

use App\Models\Avsnitt;
use App\Models\Kapittel;
use App\Models\Program;
use App\Models\Tekstbit;
use Illuminate\Support\Collection;

/**
 * Slår sammen korte avsnitt under samme overskrift til biter på 200–600 ord
 * for gjenfinning. En bit krysser aldri en overskrift eller en programdel.
 */
class Tekstbitbygger
{
    public function bygg(Program $program): int
    {
        $program->tekstbiter()->delete();

        $min = (int) config('hvamenerpartiet.analyse.bit_min_ord', 200);
        $maks = (int) config('hvamenerpartiet.analyse.bit_maks_ord', 600);

        $kapitler = Kapittel::query()->where('program_id', $program->id)->get()->keyBy('id');
        $stier = $kapitler->map(fn (Kapittel $k) => $this->sti($k, $kapitler));

        $antall = 0;

        Avsnitt::query()
            ->where('program_id', $program->id)
            ->orderBy('rekkefolge')
            ->get()
            ->groupBy('kapittel_id')
            ->each(function (Collection $avsnittene, int $kapittelId) use ($program, $stier, $min, $maks, &$antall) {
                $bit = [];
                $ord = 0;

                $lagre = function () use (&$bit, &$ord, $program, $stier, $kapittelId, &$antall) {
                    if ($bit === []) {
                        return;
                    }
                    Tekstbit::create([
                        'program_id' => $program->id,
                        'kapittel_id' => $kapittelId,
                        'fra_avsnitt_id' => $bit[0]->id,
                        'til_avsnitt_id' => end($bit)->id,
                        'del' => $bit[0]->del,
                        'overskriftssti' => mb_substr($stier[$kapittelId], 0, 1000),
                        'tekst' => collect($bit)->pluck('tekst')->implode("\n\n"),
                        'antall_ord' => $ord,
                    ]);
                    $antall++;
                    $bit = [];
                    $ord = 0;
                };

                foreach ($avsnittene as $avsnitt) {
                    /** @var Avsnitt $avsnitt */
                    $avsnittOrd = self::tellOrd($avsnitt->tekst);

                    if ($bit !== [] && ($ord + $avsnittOrd > $maks || $ord >= $min)) {
                        $lagre();
                    }

                    $bit[] = $avsnitt;
                    $ord += $avsnittOrd;
                }

                $lagre();
            });

        return $antall;
    }

    public static function tellOrd(string $tekst): int
    {
        return count(preg_split('/\s+/u', trim($tekst), -1, PREG_SPLIT_NO_EMPTY));
    }

    /** @param Collection<int, Kapittel> $kapitler */
    private function sti(Kapittel $kapittel, Collection $kapitler): string
    {
        $deler = [];
        $k = $kapittel;
        while ($k) {
            array_unshift($deler, $k->tittel);
            $k = $k->overordnet_kapittel_id ? $kapitler->get($k->overordnet_kapittel_id) : null;
        }

        return implode(' > ', $deler);
    }
}
