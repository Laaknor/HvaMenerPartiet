<?php

namespace App\Jobs;

use App\Digitalisering\Programhenter;
use App\Models\Program;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HentProgram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public Program $program, public bool $digitaliser = true)
    {
        $this->onQueue('import');
    }

    public function handle(Programhenter $henter): void
    {
        $endret = $henter->hent($this->program);

        if ($endret && $this->digitaliser) {
            DigitaliserProgram::dispatch($this->program);
        }
    }
}
