<?php

namespace App\Console\Commands;

use App\Analyse\Embedder;
use App\Analyse\Klassifiserer;
use App\Enums\Prosessteg;
use App\Enums\Temastatus;
use App\Enums\Utlosning;
use App\Flyt\Prosessflyt;
use App\Models\Program;
use App\Models\Tema;
use Illuminate\Console\Command;
use Throwable;

/**
 * Klassifiserer temaer mot alle godkjente programmer: én jobb per tema,
 * program og kildetype. Arbeidsprogram og prinsipprogram (og delene av et
 * kombinert dokument) klassifiseres hver for seg.
 */
class KlassifiserTemaer extends Command
{
    protected $signature = 'tema:klassifiser
        {tema?* : Tema-slugger, for eksempel nato eu (standard: alle godkjente)}
        {--parti=* : Bare disse partiene}
        {--synkront : Kjør her og nå i stedet for i køen}';

    protected $description = 'Setter standpunkt per parti for temaene, med Ollama';

    public function handle(Embedder $embedder, Klassifiserer $klassifiserer, Prosessflyt $flyt): int
    {
        $temaer = Tema::query()
            ->when($this->argument('tema'), fn ($q, $slugger) => $q->whereIn('slug', $slugger))
            ->when(! $this->argument('tema'), fn ($q) => $q->whereIn('status', [Temastatus::Godkjent, Temastatus::Analysert, Temastatus::Publisert]))
            ->get();

        $programmer = Program::query()
            ->with('parti')
            ->gjeldende()
            ->godkjent()
            ->when($this->option('parti'), fn ($q, $partier) => $q->whereHas('parti', fn ($q) => $q->whereIn('slug', $partier)))
            ->get();

        if ($temaer->isEmpty() || $programmer->isEmpty()) {
            $this->warn('Fant ingen temaer eller ingen godkjente programmer.');

            return self::FAILURE;
        }

        foreach ($temaer as $tema) {
            $embedder->forTema($tema);
        }

        $antall = 0;
        $feil = 0;
        $bar = $this->option('synkront') ? $this->output->createProgressBar($temaer->count() * $programmer->sum(fn ($p) => count($p->kildetyper()))) : null;

        foreach ($temaer as $tema) {
            foreach ($programmer as $program) {
                foreach ($program->kildetyper() as $kildetype) {
                    $antall++;
                    if (! $this->option('synkront')) {
                        $flyt->start(Prosessteg::Klassifisering, $program, $tema, $kildetype, Utlosning::Kommando);

                        continue;
                    }

                    try {
                        $klassifiserer->klassifiser($tema, $program, $kildetype);
                    } catch (Throwable $e) {
                        $feil++;
                        $this->newLine();
                        $this->error("{$tema->slug} / {$program->nokkel} / {$kildetype->value}: {$e->getMessage()}");
                    }
                    $bar?->advance();
                }
            }

            if ($this->option('synkront') && $tema->status === Temastatus::Godkjent) {
                $tema->update(['status' => Temastatus::Analysert]);
            }
        }

        $bar?->finish();
        $this->newLine();
        $this->info($this->option('synkront')
            ? "Ferdig: {$antall} klassifiseringer, {$feil} feil."
            : "La {$antall} klassifiseringsjobber i køen «klassifisering». Følg med i Horizon.");

        return $feil ? self::FAILURE : self::SUCCESS;
    }
}
