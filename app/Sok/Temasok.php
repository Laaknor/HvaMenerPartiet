<?php

namespace App\Sok;

use App\Enums\Temastatus;
use App\Models\Tema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Søk i de publiserte temaene. Treffer bare temaer som allerede er analysert
 * og kontrollert, og gjør aldri LLM-kall. Temalisten er liten (noen hundre),
 * så søket gjøres i PHP på navn og synonymer, likt i MySQL og SQLite.
 */
class Temasok
{
    /** Laveste likhet (0–1) for at et tema regnes som «nærmeste treff». */
    private const TERSKEL_NAERMESTE = 0.55;

    /** @var Collection<int, Tema>|null */
    private ?Collection $temaer = null;

    /** @return Collection<int, Tema> */
    public function publiserte(): Collection
    {
        return $this->temaer ??= Tema::query()
            ->where('status', Temastatus::Publisert)
            ->orderBy('navn')
            ->get();
    }

    /** Temaet søket peker entydig på: likt navn, slug eller synonym. */
    public function eksakt(string $sok): ?Tema
    {
        $sok = self::normaliser($sok);
        if ($sok === '') {
            return null;
        }

        return $this->publiserte()->first(
            fn (Tema $tema) => in_array($sok, $this->nokler($tema), true),
        );
    }

    /**
     * Temaer der navn eller synonym inneholder søkeordet, eller søkeordet
     * inneholder dem. Beste treff først.
     *
     * @return Collection<int, Tema>
     */
    public function sok(string $sok): Collection
    {
        $sok = self::normaliser($sok);
        if ($sok === '') {
            return collect();
        }

        return $this->publiserte()
            ->map(fn (Tema $tema) => [$tema, $this->poeng($tema, $sok)])
            ->filter(fn (array $rad) => $rad[1] > 0)
            ->sortByDesc(fn (array $rad) => $rad[1])
            ->map(fn (array $rad) => $rad[0])
            ->values();
    }

    /**
     * Nærmeste temaer når søket ikke ga treff, for eksempel ved skrivefeil
     * («eiendomskatt»).
     *
     * @return Collection<int, Tema>
     */
    public function naermeste(string $sok, int $antall = 5): Collection
    {
        $sok = self::normaliser($sok);
        if ($sok === '') {
            return collect();
        }

        return $this->publiserte()
            ->map(fn (Tema $tema) => [$tema, max(array_map(fn (string $n) => self::likhet($sok, $n), $this->nokler($tema)))])
            ->filter(fn (array $rad) => $rad[1] >= self::TERSKEL_NAERMESTE)
            ->sortByDesc(fn (array $rad) => $rad[1])
            ->take($antall)
            ->map(fn (array $rad) => $rad[0])
            ->values();
    }

    /** Navn og synonymer for autofullføring, uten duplikater. @return list<string> */
    public function forslag(): array
    {
        return $this->publiserte()
            ->flatMap(fn (Tema $tema) => [$tema->navn, ...($tema->synonymer ?? [])])
            ->unique(fn (string $s) => self::normaliser($s))
            ->sort(fn ($a, $b) => strcoll(mb_strtolower($a), mb_strtolower($b)))
            ->values()
            ->all();
    }

    public static function normaliser(string $tekst): string
    {
        $tekst = mb_strtolower(trim($tekst));
        $tekst = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $tekst);

        return trim(preg_replace('/\s+/u', ' ', $tekst));
    }

    /** @return list<string> */
    private function nokler(Tema $tema): array
    {
        return array_values(array_unique(array_filter(array_map(
            self::normaliser(...),
            [$tema->navn, str_replace('-', ' ', $tema->slug), ...($tema->synonymer ?? [])],
        ))));
    }

    private function poeng(Tema $tema, string $sok): int
    {
        $poeng = 0;
        $navn = self::normaliser($tema->navn);

        foreach ($this->nokler($tema) as $i => $nokkel) {
            $erNavn = $nokkel === $navn;
            $p = match (true) {
                $nokkel === $sok => 100,
                Str::startsWith($nokkel, $sok) || Str::startsWith($sok, $nokkel) => 60,
                self::inneholderOrd($nokkel, $sok) || self::inneholderOrd($sok, $nokkel) => 40,
                mb_strlen($sok) >= 4 && str_contains($nokkel, $sok) => 25,
                default => 0,
            };
            $poeng = max($poeng, $p + ($p > 0 && $erNavn ? 5 : 0));
        }

        return $poeng;
    }

    /** Om $ord finnes som helt ord i $tekst. */
    private static function inneholderOrd(string $tekst, string $ord): bool
    {
        return mb_strlen($ord) >= 2 && str_contains(" {$tekst} ", " {$ord} ");
    }

    /** Likhet mellom 0 og 1 ut fra redigeringsavstand. */
    private static function likhet(string $a, string $b): float
    {
        $lengde = max(mb_strlen($a), mb_strlen($b));
        if ($lengde === 0) {
            return 0.0;
        }

        // levenshtein() teller bytes, så æ, ø og å byttes med ett tegn først.
        $a = strtr($a, ['æ' => '{', 'ø' => '|', 'å' => '}']);
        $b = strtr($b, ['æ' => '{', 'ø' => '|', 'å' => '}']);
        $avstand = levenshtein($a, $b);

        // Søkeordet kan være begynnelsen av et lengre navn («eiendom»).
        if (mb_strlen($a) >= 4 && str_starts_with($b, $a)) {
            return 0.8;
        }

        return 1 - $avstand / $lengde;
    }
}
