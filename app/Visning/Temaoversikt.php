<?php

namespace App\Visning;

use App\Analyse\Visningsregel;
use App\Enums\Kildetype;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use App\Models\Parti;
use App\Models\Standpunkt;
use App\Models\Tema;
use Illuminate\Support\Collection;

/**
 * Bygger partikortene for en temaside. Bare godkjente (eller korrigerte)
 * standpunkter for gjeldende programmer tas med, og arbeidsprogram og
 * prinsipprogram slås sammen med den faste visningsregelen.
 */
class Temaoversikt
{
    public const BEGGE = 'begge';

    public const ARBEIDSPROGRAM = 'arbeidsprogram';

    public const PRINSIPPROGRAM = 'prinsipprogram';

    public const VISNINGER = [self::BEGGE, self::ARBEIDSPROGRAM, self::PRINSIPPROGRAM];

    public function __construct(private readonly Visningsregel $regel) {}

    /**
     * @return array{kort: Collection<int, Partikort>, ikkeVurdert: Collection<int, Parti>}
     */
    public function for(Tema $tema, string $visning = self::BEGGE): array
    {
        $standpunkter = Standpunkt::query()
            ->where('tema_id', $tema->id)
            ->whereIn('status', [Standpunktstatus::Godkjent, Standpunktstatus::Korrigert])
            ->whereHas('program', fn ($q) => $q->gjeldende())
            ->with(['program.parti', 'kildesetninger.avsnitt.hovedkapittel', 'kildesetninger.program.parti'])
            ->get();

        $partier = Parti::query()
            ->with(['programmer' => fn ($q) => $q->gjeldende()])
            ->where(fn ($q) => $q->whereNull('aktiv_til')->orWhere('aktiv_til', '>=', now()))
            ->orderBy('navn')
            ->get();

        $kort = collect();
        $ikkeVurdert = collect();

        foreach ($partier as $parti) {
            $egne = $standpunkter->filter(fn (Standpunkt $s) => $s->program->parti_id === $parti->id);
            $arbeid = $egne->first(fn (Standpunkt $s) => $s->kildetype === Kildetype::Arbeidsprogram);
            $prinsipp = $egne->first(fn (Standpunkt $s) => $s->kildetype === Kildetype::Prinsipprogram);

            $partikort = match ($visning) {
                self::ARBEIDSPROGRAM => $arbeid ? $this->enkelt($parti, $arbeid) : null,
                // Et samlet prinsipp- og handlingsprogram gjelder også for prinsippvisningen.
                self::PRINSIPPROGRAM => $parti->harSamletProgram()
                    ? ($arbeid ? $this->enkelt($parti, $arbeid) : null)
                    : $this->bareprinsipp($parti, $prinsipp),
                default => ($arbeid || $prinsipp) ? $this->samlet($parti, $arbeid, $prinsipp) : null,
            };

            $partikort ? $kort->push($partikort) : $ikkeVurdert->push($parti);
        }

        return [
            'kort' => $kort->sortBy([
                fn (Partikort $a, Partikort $b) => $a->sortering() <=> $b->sortering(),
                fn (Partikort $a, Partikort $b) => strcoll($a->parti->navn, $b->parti->navn),
            ])->values(),
            'ikkeVurdert' => $ikkeVurdert,
        ];
    }

    /** Antall partier per merkelapp, i fast rekkefølge. @return array<string, int> */
    public static function fordeling(Collection $kort): array
    {
        $fordeling = [];
        foreach (Standpunktverdi::cases() as $verdi) {
            $antall = $kort->filter(fn (Partikort $k) => $k->verdi === $verdi)->count();
            if ($antall) {
                $fordeling[$verdi->value] = $antall;
            }
        }

        return $fordeling;
    }

    private function samlet(Parti $parti, ?Standpunkt $arbeid, ?Standpunkt $prinsipp): Partikort
    {
        $harPrinsipp = $parti->harPrinsipprogram();
        $visning = $this->regel->vis($arbeid, $prinsipp, $harPrinsipp);
        $hoved = $visning->hovedstandpunkt;
        $fraPrinsipp = $visning->hovedkilde === Kildetype::Prinsipprogram;

        // Når hovedmerkelappen kommer fra prinsipprogrammet, er linjen «Prinsipielt syn» overflødig.
        $prinsippkilde = ! $fraPrinsipp && $visning->prinsippstandpunkt && $visning->prinsippstandpunkt->verdi->omtaler()
            ? Kildeutdrag::fra($visning->prinsippstandpunkt)
            : null;

        return new Partikort(
            parti: $parti,
            verdi: $visning->hovedverdi,
            kilde: $hoved && $hoved->verdi->omtaler() ? Kildeutdrag::fra($hoved) : null,
            sikkerhet: $hoved?->verdi->omtaler() ? $hoved->sikkerhet : null,
            merknad: $visning->merknad,
            avvikverdi: $visning->avvik ? $visning->prinsippstandpunkt?->verdi : null,
            // Et samlet program har ikke et eget prinsipielt syn ved siden av.
            prinsipieltSyn: $fraPrinsipp || $parti->harSamletProgram() ? null : $visning->prinsipielleSyn(),
            prinsippkilde: $prinsippkilde,
        );
    }

    private function enkelt(Parti $parti, Standpunkt $standpunkt): Partikort
    {
        return new Partikort(
            parti: $parti,
            verdi: $standpunkt->verdi,
            kilde: $standpunkt->verdi->omtaler() ? Kildeutdrag::fra($standpunkt) : null,
            sikkerhet: $standpunkt->verdi->omtaler() ? $standpunkt->sikkerhet : null,
            merknad: null,
            avvikverdi: null,
            prinsipieltSyn: null,
            prinsippkilde: null,
        );
    }

    private function bareprinsipp(Parti $parti, ?Standpunkt $prinsipp): ?Partikort
    {
        // Et parti uten prinsipprogram vises som «Ingen prinsipprogram», ikke «Ikke omtalt».
        if (! $parti->harPrinsipprogram()) {
            return new Partikort($parti, null, null, null, null, null, null, null);
        }

        return $prinsipp ? $this->enkelt($parti, $prinsipp) : null;
    }
}
