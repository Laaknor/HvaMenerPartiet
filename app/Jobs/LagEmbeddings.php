<?php

namespace App\Jobs;

use App\Analyse\Embedder;
use App\Flyt\SporesSomKjoring;
use App\Models\Program;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Embeddings for tekstbitene i ett godkjent program. */
class LagEmbeddings implements ShouldQueue
{
    use Queueable, SporesSomKjoring;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout = 900;

    public function __construct(public Program $program, public bool $tving = false)
    {
        $this->onQueue('embeddings');
    }

    public function handle(Embedder $embedder): void
    {
        $this->sporet(fn () => $embedder->forProgram($this->program, $this->tving).' nye embeddings.');
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['program:'.$this->program->nokkel];
    }
}
