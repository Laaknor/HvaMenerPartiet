<?php

namespace App\Console\Commands;

use App\Ollama\OllamaKlient;
use Illuminate\Console\Command;
use Throwable;

/** Viser hvilke Ollama-innstillinger appen faktisk bruker, og prøver embeddings og tolkning. */
class SjekkOllama extends Command
{
    protected $signature = 'ollama:sjekk';

    protected $description = 'Sjekker at Ollama-oppsettet virker for embeddings og tolkning';

    public function handle(): int
    {
        $ollama = app(OllamaKlient::class);
        $feil = 0;

        $this->table(['Innstilling', 'Verdi'], [
            ['OLLAMA_URL (tolkning)', $ollama->url()],
            ['OLLAMA_MODELL', $ollama->modell()],
            ['OLLAMA_EMBEDDING_URL', $ollama->embeddingUrl()],
            ['OLLAMA_EMBEDDINGMODELL', $ollama->embeddingmodell()],
            ['OLLAMA_EMBEDDING_API', $ollama->embeddingApi()],
        ]);

        if (app()->configurationIsCached()) {
            $this->warn('Konfigurasjonen er bufret. Endringer i .env virker først etter «php artisan config:clear» (eller config:cache på nytt) og omstart av Horizon.');
        }

        if ($ollama->embeddingApi() === 'ollama') {
            $evner = rescue(fn () => $ollama->evner($ollama->embeddingmodell(), $ollama->embeddingUrl()), null, false);
            if ($evner === null) {
                $this->warn("Fant ikke modellen «{$ollama->embeddingmodell()}» på {$ollama->embeddingUrl()}. Sjekk navnet med «ollama list».");
            } elseif (! in_array('embedding', $evner, true)) {
                $this->error("«{$ollama->embeddingmodell()}» er ikke en embeddingmodell (kan: ".implode(', ', $evner).'). Sett OLLAMA_EMBEDDINGMODELL til for eksempel bge-m3.');
            }
        }

        try {
            $vektor = $ollama->embed(['Norge skal være medlem av NATO.'])[0];
            $this->info('Embeddings virker: '.count($vektor).' dimensjoner.');
        } catch (Throwable $e) {
            $feil++;
            $this->error('Embeddings feilet: '.$e->getMessage());
        }

        try {
            $svar = $ollama->chatJson(
                [['role' => 'user', 'content' => 'Svar med JSON der feltet "ok" er true.']],
                ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']],
            );
            $this->info('Tolkning virker: '.json_encode($svar));
        } catch (Throwable $e) {
            $feil++;
            $this->error('Tolkning feilet: '.$e->getMessage());
        }

        return $feil ? self::FAILURE : self::SUCCESS;
    }
}
