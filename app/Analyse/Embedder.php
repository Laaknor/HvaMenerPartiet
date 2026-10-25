<?php

namespace App\Analyse;

use App\Models\Program;
use App\Models\Tekstbit;
use App\Models\Tema;
use App\Ollama\OllamaKlient;

/** Lager embeddings for tekstbiter og temaer med embeddingmodellen i Ollama. */
class Embedder
{
    private const BATCH = 16;

    public function __construct(private OllamaKlient $ollama) {}

    /** @return int antall nye embeddings */
    public function forProgram(Program $program, bool $tving = false): int
    {
        $modell = $this->ollama->embeddingmodell();
        $antall = 0;

        Tekstbit::query()
            ->where('program_id', $program->id)
            ->when(! $tving, fn ($q) => $q->where(fn ($q) => $q->whereNull('embedding')->orWhere('embeddingmodell', '!=', $modell)))
            ->orderBy('id')
            ->chunkById(self::BATCH, function ($biter) use ($modell, &$antall) {
                $vektorer = $this->ollama->embed($biter->map->embeddingtekst()->all());
                foreach ($biter->values() as $i => $bit) {
                    $bit->forceFill([
                        'embedding' => Vektor::pakk($vektorer[$i]),
                        'embeddingmodell' => $modell,
                    ])->save();
                    $antall++;
                }
            });

        return $antall;
    }

    public function forTema(Tema $tema, bool $tving = false): bool
    {
        $modell = $this->ollama->embeddingmodell();
        if (! $tving && $tema->embedding && $tema->embeddingmodell === $modell) {
            return false;
        }

        [$vektor] = $this->ollama->embed([$tema->embeddingtekst()]);
        $tema->forceFill([
            'embedding' => Vektor::pakk($vektor),
            'embeddingmodell' => $modell,
        ])->save();

        return true;
    }
}
