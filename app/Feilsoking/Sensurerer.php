<?php

namespace App\Feilsoking;

/**
 * Siste sikring før en pakke lagres eller sendes: verdier fra miljøet som ser
 * ut som hemmeligheter (passord, nøkler, token), e-postadresser og
 * innloggingsinfo i URL-er byttes ut med [skjult]. Pakken bygges uansett bare
 * fra en fast liste med felt, så dette fanger det som kan ha sneket seg inn
 * via feilmeldinger.
 */
class Sensurerer
{
    public const SKJULT = '[skjult]';

    /** @var list<string>|null */
    private ?array $hemmeligheter = null;

    public function rens(mixed $verdi): mixed
    {
        if (is_array($verdi)) {
            return array_map(fn ($v) => $this->rens($v), $verdi);
        }

        return is_string($verdi) ? $this->renTekst($verdi) : $verdi;
    }

    public function renTekst(string $tekst): string
    {
        foreach ($this->hemmeligheter() as $hemmelighet) {
            $tekst = str_replace($hemmelighet, self::SKJULT, $tekst);
        }

        $tekst = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', self::SKJULT, $tekst);
        // https://bruker:passord@vert → https://[skjult]@vert
        $tekst = preg_replace('#(?<=://)[^/\s:@]+:[^/\s@]+(?=@)#', self::SKJULT, $tekst);
        $tekst = preg_replace('/(Bearer|token)\s+[A-Za-z0-9._~+\/=-]{12,}/i', '$1 '.self::SKJULT, $tekst);
        // GitHub-token, også uten «Bearer» foran.
        $tekst = preg_replace('/\b(gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/', self::SKJULT, $tekst);

        return $tekst;
    }

    /** @return list<string> lengste først, så en lang verdi ikke blir halvveis skjult */
    private function hemmeligheter(): array
    {
        if ($this->hemmeligheter !== null) {
            return $this->hemmeligheter;
        }

        $verdier = [
            config('app.key'),
            config('hvamenerpartiet.feilsoking.token'),
            config('hvamenerpartiet.feilsoking.github_token'),
            config('database.connections.mysql.password'),
            config('database.redis.default.password'),
        ];

        foreach ([...$_ENV, ...$_SERVER] as $navn => $verdi) {
            if (is_string($navn) && is_string($verdi) && preg_match('/KEY|SECRET|PASSWORD|PASS|TOKEN|CREDENTIAL|DSN|PRIVATE/i', $navn)) {
                $verdier[] = $verdi;
            }
        }

        $verdier = array_values(array_unique(array_filter($verdier, fn ($v) => is_string($v) && mb_strlen($v) >= 6)));
        usort($verdier, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $this->hemmeligheter = $verdier;
    }
}
