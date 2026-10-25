<?php

namespace App\Jobs;

use App\Digitalisering\Digitaliserer;
use App\Flyt\SporesSomKjoring;
use App\Models\Program;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class DigitaliserProgram implements ShouldQueue
{
    use Queueable, SporesSomKjoring;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public Program $program)
    {
        $this->onQueue('import');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('program-'.$this->program->id))->dontRelease()];
    }

    public function handle(Digitaliserer $digitaliserer): void
    {
        $this->sporet(function () use ($digitaliserer) {
            $r = $digitaliserer->digitaliser($this->program);

            return trim("{$r->kapitler} kapitler, {$r->avsnitt} avsnitt, {$r->setninger} setninger. "
                .($this->program->digitalisering_merknad ?? ''));
        });
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['program:'.$this->program->nokkel];
    }
}
