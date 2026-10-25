<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Erstatter Ollama i testene. Embeddings er en enkel ordpose hashet til 64
 * dimensjoner, så tekster med felles ord blir like. Chat-svarene settes av
 * testen.
 */
class FalskOllama
{
    /** @var list<array<string, mixed>|callable> */
    public array $chatsvar = [];

    /** @var list<array<string, mixed>> */
    public array $chatkall = [];

    public int $embedkall = 0;

    public static function start(): self
    {
        $falsk = new self;

        Http::fake([
            '*/api/embed' => function (Request $request) use ($falsk) {
                $falsk->embedkall++;

                return Http::response([
                    'embeddings' => array_map([self::class, 'vektor'], (array) $request['input']),
                ]);
            },
            '*/api/chat' => function (Request $request) use ($falsk) {
                $falsk->chatkall[] = $request->data();
                $svar = array_shift($falsk->chatsvar) ?? ['standpunkt' => 'ikke_omtalt', 'sikkerhet' => 'lav', 'kildesetninger' => [], 'begrunnelse' => ''];
                if (is_callable($svar)) {
                    $svar = $svar($request->data());
                }

                return Http::response(['message' => ['role' => 'assistant', 'content' => is_string($svar) ? $svar : json_encode($svar)]]);
            },
        ]);

        return $falsk;
    }

    /** @return list<float> */
    public static function vektor(string $tekst): array
    {
        $v = array_fill(0, 64, 0.0);
        preg_match_all('/\p{L}{3,}/u', mb_strtolower($tekst), $ord);
        foreach ($ord[0] as $o) {
            $v[crc32(mb_substr($o, 0, 6)) % 64] += 1.0;
        }

        return $v;
    }

    /** Teksten modellen fikk i siste kall. */
    public function sisteBrukermelding(): string
    {
        $kall = end($this->chatkall);

        return $kall ? end($kall['messages'])['content'] : '';
    }
}
