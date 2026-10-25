<?php

namespace Tests\Feature;

use App\Ollama\Konfigurasjonsfeil;
use App\Ollama\OllamaKlient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OllamaKlientTest extends TestCase
{
    private function klient(array $konfig = []): OllamaKlient
    {
        config(['hvamenerpartiet.ollama' => array_merge(config('hvamenerpartiet.ollama'), [
            'url' => 'http://tolkning:11434',
            'embedding_url' => 'http://tolkning:11434',
            'embedding_api' => 'ollama',
        ], $konfig)]);

        return OllamaKlient::fraKonfig();
    }

    public function test_embeddings_kan_ga_til_egen_server_med_ollama_api(): void
    {
        Http::fake(['http://embed:11434/api/embed' => Http::response(['embeddings' => [[1, 0], [0, 1]]])]);

        $v = $this->klient(['embedding_url' => 'http://embed:11434'])->embed(['a', 'b']);

        $this->assertSame([[1, 0], [0, 1]], $v);
        Http::assertSent(fn (Request $r) => $r->url() === 'http://embed:11434/api/embed' && $r['model'] === config('hvamenerpartiet.ollama.embeddingmodell'));
    }

    public function test_openai_api_for_llama_cpp_server(): void
    {
        Http::fake(['http://llama:8080/v1/embeddings' => Http::response(['data' => [
            ['index' => 1, 'embedding' => [0, 1]],
            ['index' => 0, 'embedding' => [1, 0]],
        ]])]);

        $v = $this->klient(['embedding_url' => 'http://llama:8080', 'embedding_api' => 'openai'])->embed(['a', 'b']);

        $this->assertSame([[1, 0], [0, 1]], $v);
    }

    public function test_server_uten_embeddings_gir_forklaring_og_ingen_nye_forsok(): void
    {
        Http::fake(['*' => Http::response(['error' => 'This server does not support embeddings. Start it with `--embeddings`'], 501)]);

        try {
            $this->klient()->embed(['a']);
            $this->fail('Forventet Konfigurasjonsfeil');
        } catch (Konfigurasjonsfeil $e) {
            $this->assertStringContainsString('--embeddings', $e->getMessage());
            $this->assertStringContainsString('OLLAMA_EMBEDDING_URL', $e->getMessage());
        }
    }

    public function test_andre_feil_kastes_som_vanlig_sa_jobben_prover_igjen(): void
    {
        Http::fake(['*' => Http::response('opptatt', 503)]);

        $this->expectException(RuntimeException::class);
        try {
            $this->klient()->embed(['a']);
        } catch (Konfigurasjonsfeil $e) {
            $this->fail('Midlertidige feil skal ikke stoppe nye forsøk');
        }
    }
}
