<?php

namespace App\Jobs;

use App\Analyse\Embedder;
use App\Models\Program;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Embeddings for tekstbitene i ett godkjent program. */
class LagEmbeddings implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout = 900;

    public function __construct(public Program $program, public bool $tving = false)
    {
        $this->onQueue('embeddings');
    }

    public function handle(Embedder $embedder): void
    {
        $embedder->forProgram($this->program, $this->tving);
    }
}
