<?php

namespace App\Jobs;

use App\Analyse\Klassifiserer;
use App\Enums\Kildetype;
use App\Models\Program;
use App\Models\Tema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Én jobb per tema, program og kildetype. Køen «klassifisering» kjøres av en
 * Horizon-supervisor med så mange prosesser som Ollama-serveren tåler.
 */
class KlassifiserStandpunkt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 900;

    public function __construct(public Tema $tema, public Program $program, public Kildetype $kildetype)
    {
        $this->onQueue('klassifisering');
    }

    public function handle(Klassifiserer $klassifiserer): void
    {
        $klassifiserer->klassifiser($this->tema, $this->program, $this->kildetype);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['tema:'.$this->tema->slug, 'program:'.$this->program->nokkel, $this->kildetype->value];
    }
}
