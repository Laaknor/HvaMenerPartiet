<?php

namespace App\Seo;

use App\Analyse\Kildelenke;
use App\Analyse\Visningsregel;
use App\Enums\Kildetype;
use App\Enums\Standpunktstatus;
use App\Enums\Temastatus;
use App\Models\Parti;
use App\Models\Standpunkt;
use App\Models\Tema;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Alle publiserte temaer med partienes godkjente standpunkter som Markdown,
 * for /llms-full.txt. Samme visningsregel som partikortene, og bare
 * standpunkter som er godkjent i admin.
 */
class Standpunkteksport
{
    public function __construct(private readonly Visningsregel $regel) {}

    public function markdown(): string
    {
        $md = [
            '# '.config('seo.navn').': hva partiene mener',
            '',
            '> '.config('seo.beskrivelse'),
            '',
            config('seo.om'),
            '',
            'Skalaen er For, Delvis for, Delt / uklart, Mot og Ikke omtalt, målt mot påstanden for hvert tema. Partier uten godkjent standpunkt på et tema er utelatt.',
            '',
        ];

        $temaer = Tema::query()->where('status', Temastatus::Publisert)->orderBy('navn')->get();
        $partier = Parti::query()->with(['programmer' => fn ($q) => $q->gjeldende()])->orderBy('navn')->get();

        if ($temaer->isEmpty()) {
            $md[] = 'Ingen temaer er publisert ennå.';
        }

        foreach ($temaer as $tema) {
            $standpunkter = Standpunkt::query()
                ->where('tema_id', $tema->id)
                ->whereIn('status', [Standpunktstatus::Godkjent, Standpunktstatus::Korrigert])
                ->whereHas('program', fn ($q) => $q->gjeldende())
                ->with(['program.parti', 'kildesetninger.avsnitt.hovedkapittel'])
                ->get();

            $md[] = "## {$tema->navn}";
            $md[] = '';
            $md[] = "Påstand: «{$tema->pastand}»";
            if ($tema->beskrivelse) {
                $md[] = '';
                $md[] = $tema->beskrivelse;
            }
            $md[] = '';

            $rader = [];
            foreach ($partier as $parti) {
                $egne = $standpunkter->filter(fn (Standpunkt $s) => $s->program->parti_id === $parti->id);
                $arbeid = $egne->first(fn (Standpunkt $s) => $s->kildetype === Kildetype::Arbeidsprogram);
                $prinsipp = $egne->first(fn (Standpunkt $s) => $s->kildetype === Kildetype::Prinsipprogram);
                if (! $arbeid && ! $prinsipp) {
                    continue;
                }

                $visning = $this->regel->vis($arbeid, $prinsipp, $parti->harPrinsipprogram());
                $hoved = $visning->hovedstandpunkt;

                $linje = "- **{$parti->navn} ({$parti->forkortelse})**: {$visning->hovedverdi->etikett()}";
                if ($visning->avvik) {
                    $linje .= ". Prinsipprogrammet: {$visning->prinsippstandpunkt->verdi->etikett()} ({$visning->merknad})";
                } elseif ($visning->merknad) {
                    $linje .= " ({$visning->merknad})";
                }
                if ($hoved && $hoved->utdrag() !== '') {
                    $linje .= "\n  > «{$hoved->utdrag()}»";
                    if ($kilde = $this->kilde($hoved)) {
                        $linje .= "\n  Kilde: {$kilde}";
                    }
                }

                $rader[] = [$visning->hovedverdi->sortering(), $linje];
            }

            usort($rader, fn ($a, $b) => $a[0] <=> $b[0]);
            $md = [...$md, ...array_column($rader, 1), ''];
        }

        return rtrim(implode("\n", $md))."\n";
    }

    private function kilde(Standpunkt $standpunkt): ?string
    {
        $program = $standpunkt->program;
        $setninger = $standpunkt->utdragssetninger();
        $side = Kildelenke::side($setninger);

        $tekst = $program->tittel.($side ? ", s. {$side}" : '');

        // Lenke til setningene i det digitaliserte programmet når den siden
        // finnes, ellers til originalen.
        $lenke = Kildelenke::for($setninger);
        if ($lenke && $this->ruteFinnes(strtok($lenke, '#'))) {
            return "[{$tekst}](".url($lenke).')';
        }

        if ($program->kilde_url) {
            return "[{$tekst}]({$program->kilde_url}".($side ? "#page={$side}" : '').')';
        }

        return $tekst;
    }

    private function ruteFinnes(string $sti): bool
    {
        try {
            Route::getRoutes()->match(Request::create($sti));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
