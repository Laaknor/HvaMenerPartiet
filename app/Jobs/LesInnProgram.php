<?php

namespace App\Jobs;

use App\Digitalisering\Programhenter;
use App\Flyt\SporesSomKjoring;
use App\Models\Program;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Leser inn programfilen fra docs/Partiprogram. */
class LesInnProgram implements ShouldQueue
{
    use Queueable, SporesSomKjoring;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Program $program)
    {
        $this->onQueue('import');
    }

    public function handle(Programhenter $henter): void
    {
        $this->sporet(function () use ($henter) {
            $endret = $henter->lesInn($this->program);

            return $endret
                ? 'Ny fil lest inn ('.substr($this->program->filhash, 0, 12).').'
                : 'Filen er uendret.';
        });
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['program:'.$this->program->nokkel];
    }
}
