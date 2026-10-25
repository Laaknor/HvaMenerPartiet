<?php

namespace App\Console\Commands;

use App\Analyse\Embedder;
use App\Enums\Digitaliseringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Utlosning;
use App\Flyt\Prosessflyt;
use App\Models\Tema;
use Illuminate\Console\Command;

class LagEmbeddingsKommando extends Command
{
    use VelgerProgrammer;

    protected $signature = 'program:embeddings
        {program?* : Programnøkkel eller parti}
        {--alle : Alle godkjente programmer}
        {--tving : Lag embeddings på nytt selv om de finnes}
        {--synkront : Kjør her og nå i stedet for i køen}';

    protected $description = 'Lager embeddings for tekstbitene i godkjente programmer, og for alle temaer';

    public function handle(Embedder $embedder, Prosessflyt $flyt): int
    {
        foreach ($this->valgteProgrammer()->filter(fn ($p) => $p->digitalisering_status === Digitaliseringsstatus::Godkjent) as $program) {
            if ($this->option('synkront')) {
                $antall = $embedder->forProgram($program, (bool) $this->option('tving'));
                $this->info("{$program->nokkel}: {$antall} embeddings");
            } else {
                $flyt->start(Prosessteg::Embeddings, $program, utlostAv: Utlosning::Kommando, tving: (bool) $this->option('tving'));
                $this->line("Lagt i kø: {$program->nokkel}");
            }
        }

        $temaer = 0;
        foreach (Tema::all() as $tema) {
            $temaer += (int) $embedder->forTema($tema, (bool) $this->option('tving'));
        }
        $this->info("{$temaer} temaer fikk ny embedding.");

        return self::SUCCESS;
    }
}
