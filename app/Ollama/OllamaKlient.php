<?php

namespace App\Ollama;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tynn klient for Ollamas HTTP-API. Brukes bare fra bakgrunnsjobber.
 */
class OllamaKlient
{
    public function __construct(
        private string $url,
        private string $modell,
        private string $embeddingmodell,
        private int $tidsavbrudd,
        private int $kontekstlengde,
    ) {}

    public static function fraKonfig(): self
    {
        $k = config('hvamenerpartiet.ollama');

        return new self($k['url'], $k['modell'], $k['embeddingmodell'], $k['tidsavbrudd'], $k['kontekstlengde']);
    }

    public function modell(): string
    {
        return $this->modell;
    }

    public function embeddingmodell(): string
    {
        return $this->embeddingmodell;
    }

    /**
     * @param  list<string>  $tekster
     * @return list<list<float>>
     */
    public function embed(array $tekster): array
    {
        if ($tekster === []) {
            return [];
        }

        $svar = $this->http()->post('/api/embed', [
            'model' => $this->embeddingmodell,
            'input' => array_values($tekster),
            'truncate' => true,
        ]);

        if (! $svar->successful()) {
            throw new RuntimeException("Ollama embed feilet ({$svar->status()}): ".$svar->body());
        }

        $embeddings = $svar->json('embeddings');
        if (! is_array($embeddings) || count($embeddings) !== count($tekster)) {
            throw new RuntimeException('Ollama returnerte feil antall embeddings.');
        }

        return $embeddings;
    }

    /**
     * Chat med strukturert svar. Ollamas format-parameter tvinger svaret til å
     * følge JSON-skjemaet.
     *
     * @param  list<array{role: string, content: string}>  $meldinger
     * @param  array<string, mixed>  $skjema
     * @param  array<string, mixed>  $valg
     * @return array<string, mixed>
     */
    public function chatJson(array $meldinger, array $skjema, array $valg = []): array
    {
        $svar = $this->http()->post('/api/chat', [
            'model' => $this->modell,
            'messages' => $meldinger,
            'stream' => false,
            'format' => $skjema,
            'options' => array_merge([
                'temperature' => 0,
                'num_ctx' => $this->kontekstlengde,
            ], $valg),
        ]);

        if (! $svar->successful()) {
            throw new RuntimeException("Ollama chat feilet ({$svar->status()}): ".$svar->body());
        }

        $innhold = (string) $svar->json('message.content');
        $data = json_decode($innhold, true);

        if (! is_array($data)) {
            throw new UgyldigSvar('Ollama svarte ikke med gyldig JSON: '.mb_substr($innhold, 0, 500));
        }

        return $data;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->url, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->tidsavbrudd)
            ->connectTimeout(10);
    }
}
