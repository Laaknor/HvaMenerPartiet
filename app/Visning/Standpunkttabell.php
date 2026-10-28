<?php

namespace App\Visning;

use App\Models\Parti;
use App\Models\Tema;
use Illuminate\Support\Collection;

/**
 * Oversiktstabellen: én rad per tema og én kolonne per parti, med den samlede
 * merkelappen i hver celle. Bygges med de samme reglene som temasiden.
 */
final readonly class Standpunkttabell
{
    /**
     * @param  Collection<int, Parti>  $partier
     * @param  list<array{tema: Tema, celler: array<int, ?Partikort>, gruppe: ?string, skjult: bool}>  $rader
     */
    public function __construct(
        public Collection $partier,
        public array $rader,
    ) {}

    /**
     * @param  Collection<int, Tema>  $temaer
     * @param  array<int, string>  $grupper  Valgfri merking av rader, tema-id => gruppenavn
     * @param  list<int>  $skjulte  Rader som er skjult til JavaScript eventuelt viser dem
     */
    public static function for(Collection $temaer, Temaoversikt $oversikt, array $grupper = [], array $skjulte = []): self
    {
        $alle = $oversikt->forMange($temaer);
        $partier = $oversikt->partier();

        $rader = [];
        foreach ($temaer as $tema) {
            $kort = $alle[$tema->id]['kort']->keyBy(fn (Partikort $k) => $k->parti->id);
            $rader[] = [
                'tema' => $tema,
                'celler' => $partier->mapWithKeys(fn (Parti $p) => [$p->id => $kort->get($p->id)])->all(),
                'gruppe' => $grupper[$tema->id] ?? null,
                'skjult' => in_array($tema->id, $skjulte, true),
            ];
        }

        return new self($partier, $rader);
    }

    /** Kort tekst i cellen. Fullt navn står i title og for skjermlesere. */
    public static function celletekst(?Partikort $kort): string
    {
        return match ($kort?->verdi?->value) {
            null => $kort ? '–' : '',
            'for' => 'For',
            'delvis_for' => 'Delvis',
            'mot' => 'Mot',
            'delt_uklart' => 'Uklart',
            'ikke_omtalt' => '–',
            default => '',
        };
    }
}
