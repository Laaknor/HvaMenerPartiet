<?php

namespace App\Visning;

use App\Analyse\Visningsregel;
use App\Enums\Kildetype;
use App\Enums\Standpunktstatus;
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

    /** @var Collection<int, Parti>|null */
    private ?Collection $partier = null;

    public function __construct(private readonly Visningsregel $regel) {}

    /**
     * @return array{kort: Collection<int, Partikort>, ikkeVurdert: Collection<int, Parti>}
     */
    public function for(Tema $tema, string $visning = self::BEGGE): array
    {
        return $this->forMange(collect([$tema]), $visning)[$tema->id];
    }

    /**
     * Partikortene for mange temaer på én gang, med to spørringer i stedet for
     * to per tema. Brukes av oversiktstabellen og partisiden. Med $bare tas
     * bare det ene partiet med.
     *
     * @param  Collection<int, Tema>  $temaer
     * @return array<int, array{kort: Collection<int, Partikort>, ikkeVurdert: Collection<int, Parti>}>
     */
    public function forMange(Collection $temaer, string $visning = self::BEGGE, ?Parti $bare = null): array
    {
        $partier = $this->partier()->when($bare, fn (Collection $p) => $p->where('id', $bare->id))->values();

        $standpunkter = Standpunkt::query()
            ->whereIn('tema_id', $temaer->pluck('id'))
            ->whereIn('status', [Standpunktstatus::Godkjent, Standpunktstatus::Korrigert])
            ->whereHas('program', fn ($q) => $q->gjeldende())
            ->when($bare, fn ($q) => $q->whereHas('program', fn ($p) => $p->where('parti_id', $bare->id)))
            ->with(['program.parti', 'kildesetninger.avsnitt.hovedkapittel', 'kildesetninger.program.parti'])
            ->get()
            ->groupBy('tema_id');

        $resultat = [];
        foreach ($temaer as $tema) {
            $resultat[$tema->id] = $this->bygg($partier, $standpunkter->get($tema->id, collect()), $visning);
        }

        return $resultat;
    }

    /** Partiene som vises, alfabetisk. @return Collection<int, Parti> */
    public function partier(): Collection
    {
        return $this->partier ??= Parti::query()
            ->with(['programmer' => fn ($q) => $q->gjeldende()])
            ->where(fn ($q) => $q->whereNull('aktiv_til')->orWhere('aktiv_til', '>=', now()))
            ->orderBy('navn')
            ->get();
    }

    /**
     * @param  Collection<int, Parti>  $partier
     * @param  Collection<int, Standpunkt>  $standpunkter
     * @return array{kort: Collection<int, Partikort>, ikkeVurdert: Collection<int, Parti>}
     */
    private function bygg(Collection $partier, Collection $standpunkter, string $visning): array
    {
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

    /**
     * Kortene gruppert etter merkelapp, i fast rekkefølge, for «svaret først»
     * og overskriftene i listen. Kort uten merkelapp («Ingen prinsipprogram»)
     * får nøkkelen «ingen».
     *
     * @param  Collection<int, Partikort>  $kort
     * @return array<string, Collection<int, Partikort>>
     */
    public static function grupper(Collection $kort): array
    {
        $grupper = [];
        foreach ($kort as $k) {
            $grupper[$k->verdi?->value ?? 'ingen'][] = $k;
        }

        return array_map(fn (array $k) => collect($k), $grupper);
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
