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
        private ?string $embeddingUrl = null,
        private string $embeddingApi = 'ollama',
    ) {}

    public static function fraKonfig(): self
    {
        $k = config('hvamenerpartiet.ollama');

        return new self(
            $k['url'], $k['modell'], $k['embeddingmodell'], $k['tidsavbrudd'], $k['kontekstlengde'],
            $k['embedding_url'] ?? null, $k['embedding_api'] ?? 'ollama',
        );
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

        $openai = $this->embeddingApi === 'openai';
        $sti = $openai ? '/v1/embeddings' : '/api/embed';
        $innhold = ['model' => $this->embeddingmodell, 'input' => array_values($tekster)];
        if (! $openai) {
            $innhold['truncate'] = true;
        }

        $svar = $this->http($this->embeddingUrl ?: $this->url)->post($sti, $innhold);

        if (! $svar->successful()) {
            $this->kastEmbeddingfeil($svar->status(), $svar->body(), $sti);
        }

        $embeddings = $openai
            ? collect((array) $svar->json('data'))->sortBy('index')->pluck('embedding')->values()->all()
            : $svar->json('embeddings');

        if (! is_array($embeddings) || count($embeddings) !== count($tekster)) {
            throw new RuntimeException('Embeddingserveren returnerte feil antall embeddings.');
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

    /**
     * Feil som skyldes oppsettet av serveren, gir en forklaring og ingen nye
     * forsøk. Andre feil kastes som vanlig, så jobben prøver igjen.
     */
    private function kastEmbeddingfeil(int $status, string $kropp, string $sti): never
    {
        $server = rtrim($this->embeddingUrl ?: $this->url, '/');

        if (str_contains($kropp, '--embeddings') || $status === 501) {
            throw new Konfigurasjonsfeil(
                "Serveren på {$server} støtter ikke embeddings ({$status}: ".mb_substr($kropp, 0, 200).'). '
                .'Det ser ut som llama.cpp-server. Start den med --embeddings og en embeddingmodell og sett '
                .'OLLAMA_EMBEDDING_API=openai, eller pek OLLAMA_EMBEDDING_URL til en Ollama-server med '
                ."«{$this->embeddingmodell}» installert."
            );
        }

        if ($status === 404) {
            throw new Konfigurasjonsfeil(
                "Fant ikke {$sti} eller modellen «{$this->embeddingmodell}» på {$server} (404: ".mb_substr($kropp, 0, 200).'). '
                .'Sjekk OLLAMA_EMBEDDING_URL, OLLAMA_EMBEDDING_API og at modellen er lastet ned (ollama pull).'
            );
        }

        throw new RuntimeException("Embedding feilet på {$server} ({$status}): ".mb_substr($kropp, 0, 500));
    }

    private function http(?string $url = null): PendingRequest
    {
        return Http::baseUrl(rtrim($url ?? $this->url, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->tidsavbrudd)
            ->connectTimeout(10);
    }
}
