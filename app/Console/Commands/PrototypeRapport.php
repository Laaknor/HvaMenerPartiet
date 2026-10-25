<?php

namespace App\Console\Commands;

use App\Analyse\Kildelenke;
use App\Analyse\Visningsregel;
use App\Enums\Kildetype;
use App\Models\Parti;
use App\Models\Standpunkt;
use App\Models\Tema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rapport for fase 1: viser standpunkt per parti og tema slik partikortet
 * ville vist det, med ordrette utdrag og en tom kolonne for manuell
 * vurdering. Kriteriet for å gå videre er at resultatene stemmer med den
 * manuelle vurderingen.
 */
class PrototypeRapport extends Command
{
    protected $signature = 'prototype:rapport {tema?* : Tema-slugger (standard: alle)} {--fil= : Lagre Markdown hit (standard: storage/app/private/rapporter)}';

    protected $description = 'Lager en rapport over standpunktene for manuell kontroll';

    public function handle(Visningsregel $regel): int
    {
        $temaer = Tema::query()
            ->when($this->argument('tema'), fn ($q, $s) => $q->whereIn('slug', $s))
            ->orderBy('id')
            ->get();

        $partier = Parti::query()->with(['programmer' => fn ($q) => $q->gjeldende()])->orderBy('id')->get();

        $md = ['# Prototype: standpunkt per parti', '', 'Laget '.now()->format('d.m.Y H:i').'. Alle standpunkt er maskinelle til de er godkjent.', ''];

        foreach ($temaer as $tema) {
            $standpunkter = Standpunkt::query()
                ->where('tema_id', $tema->id)
                ->with(['program.parti', 'kildesetninger.avsnitt.hovedkapittel'])
                ->get();

            $rader = [];
            foreach ($partier as $parti) {
                $egne = $standpunkter->filter(fn (Standpunkt $s) => $s->program->parti_id === $parti->id);
                $arbeid = $egne->first(fn (Standpunkt $s) => $s->kildetype === Kildetype::Arbeidsprogram);
                $prinsipp = $egne->first(fn (Standpunkt $s) => $s->kildetype === Kildetype::Prinsipprogram);

                if (! $arbeid && ! $prinsipp) {
                    continue;
                }

                $visning = $regel->vis($arbeid, $prinsipp, $parti->harPrinsipprogram());
                $hoved = $visning->hovedstandpunkt;

                $merkelapp = $visning->hovedverdi->etikett();
                if ($visning->avvik) {
                    $merkelapp .= ' / '.$visning->prinsippstandpunkt->verdi->etikett().' (prinsipp)';
                } elseif ($visning->merknad) {
                    $merkelapp .= " ({$visning->merknad})";
                }

                $rader[] = [
                    'sortering' => $visning->hovedverdi->sortering(),
                    'parti' => $parti->forkortelse,
                    'merkelapp' => $merkelapp,
                    'sikkerhet' => $hoved?->sikkerhet->etikett() ?? '–',
                    'prinsipp' => $parti->harSamletProgram() ? 'Samlet program' : $visning->prinsipielleSyn(),
                    'utdrag' => $hoved?->utdrag() ?: '–',
                    'prinsipputdrag' => $visning->hovedkilde === Kildetype::Prinsipprogram ? '' : ($visning->prinsippstandpunkt?->utdrag() ?? ''),
                    'lenke' => $hoved ? Kildelenke::for($hoved->utdragssetninger()) : null,
                    'side' => $hoved ? Kildelenke::side($hoved->utdragssetninger()) : null,
                    'flagg' => implode(', ', array_filter([
                        $hoved?->uenighet ? 'uenige kjøringer' : null,
                        $visning->merknad === Visningsregel::MERKNAD_AVVIK ? 'avvik' : null,
                        $hoved?->status->value,
                    ])),
                    'begrunnelse' => $hoved?->begrunnelse,
                ];
            }

            usort($rader, fn ($a, $b) => $a['sortering'] <=> $b['sortering']);

            $this->newLine();
            $this->info("{$tema->navn} – «{$tema->pastand}»");
            $this->table(
                ['Parti', 'Standpunkt', 'Sikkerhet', 'Prinsipielt syn', 'Utdrag'],
                array_map(fn ($r) => [$r['parti'], $r['merkelapp'], $r['sikkerhet'], $r['prinsipp'], mb_strimwidth($r['utdrag'], 0, 90, '…')], $rader),
            );

            $md[] = "## {$tema->navn}";
            $md[] = '';
            $md[] = "Påstand: «{$tema->pastand}»";
            $md[] = '';
            $md[] = '| Parti | Standpunkt | Sikkerhet | Prinsipielt syn | Utdrag | Kilde | Begrunnelse (modell) | Merknader | Manuell vurdering |';
            $md[] = '| --- | --- | --- | --- | --- | --- | --- | --- | --- |';
            foreach ($rader as $r) {
                $utdrag = $r['utdrag'].($r['prinsipputdrag'] ? '<br>*Prinsipielt:* '.$r['prinsipputdrag'] : '');
                $kilde = $r['lenke'] ? "`{$r['lenke']}`".($r['side'] ? " (s. {$r['side']})" : '') : '–';
                $md[] = '| '.implode(' | ', array_map([$this, 'celle'], [
                    $r['parti'], $r['merkelapp'], $r['sikkerhet'], $r['prinsipp'], $utdrag, $kilde, $r['begrunnelse'] ?? '', $r['flagg'], '',
                ])).' |';
            }
            $md[] = '';
        }

        $fil = $this->option('fil') ?: Storage::disk('local')->path('rapporter/prototype-'.now()->format('Ymd-His').'.md');
        @mkdir(dirname($fil), 0775, true);
        file_put_contents($fil, implode("\n", $md));
        $this->newLine();
        $this->info("Rapport lagret i {$fil}");

        return self::SUCCESS;
    }

    private function celle(?string $tekst): string
    {
        return str_replace(['|', "\n"], ['\\|', '<br>'], (string) $tekst);
    }
}
