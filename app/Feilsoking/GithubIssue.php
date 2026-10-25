<?php

namespace App\Feilsoking;

use App\Models\Feilsokingssak;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lager en GitHub-issue for en feilsøkingssak. Repoet er offentlig, så issuen
 * får bare delene av pakken som er merket offentlige, og en lenke til hele
 * pakken i det token-beskyttede API-et.
 */
class GithubIssue
{
    public function __construct(private Sensurerer $sensurerer) {}

    public static function erSattOpp(): bool
    {
        return filled(config('hvamenerpartiet.feilsoking.github_token')) && filled(config('hvamenerpartiet.feilsoking.github_repo'));
    }

    /** @return int issuenummeret */
    public function opprett(Feilsokingssak $sak): int
    {
        $repo = config('hvamenerpartiet.feilsoking.github_repo');

        $svar = Http::withToken(config('hvamenerpartiet.feilsoking.github_token'))
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15)
            ->post("https://api.github.com/repos/{$repo}/issues", [
                'title' => Str::limit('Feilsøking: '.$sak->tittel(), 240),
                'body' => $this->sensurerer->renTekst($this->tekst($sak)),
                'labels' => array_values(array_filter([config('hvamenerpartiet.feilsoking.github_etikett')])),
            ]);

        if (! $svar->successful() || ! is_int($svar->json('number'))) {
            throw new RuntimeException('GitHub svarte '.$svar->status().': '.Str::limit((string) $svar->json('message', $svar->body()), 200));
        }

        return $svar->json('number');
    }

    public function tekst(Feilsokingssak $sak): string
    {
        $p = $sak->pakke;
        $linjer = [];

        if ($p['notat'] ?? null) {
            $linjer[] = '> '.str_replace("\n", "\n> ", $p['notat']);
            $linjer[] = '';
        }

        if ($tema = $p['tema'] ?? null) {
            $linjer[] = "**Tema:** {$tema['navn']}  ";
            $linjer[] = "**Påstand:** «{$tema['pastand']}»  ";
        }
        if ($program = $p['program'] ?? null) {
            $linjer[] = "**Program:** `{$program['nokkel']}` ({$program['parti']}, {$program['periode']})  ";
        }
        if ($kjoring = $p['kjoring'] ?? null) {
            $linjer[] = "**Kjøring:** #{$kjoring['id']} {$kjoring['steg']}, status `{$kjoring['status']}`, forsøk {$kjoring['forsok']}  ";
        }
        if (($p['type'] ?? null) === 'tilbakemelding') {
            $linjer[] = '**Fra:** «Meld fra om feil» på temasiden. Teksten fra den besøkende står bare i den private pakken.  ';
        }

        if ($s = $p['standpunkt'] ?? null) {
            $linjer[] = '';
            $linjer[] = '### Standpunkt';
            $linjer[] = "`{$s['verdi']}` med `{$s['sikkerhet']}` sikkerhet, status `{$s['status']}`".($s['uenighet'] ? ', **uenighet mellom kjøringene**' : '').'.';
            $linjer[] = '';
            $linjer[] = 'Modell `'.($s['modell'] ?? 'ingen')."`, prompt `{$s['prompt_versjon']}`.";
            if ($s['begrunnelse']) {
                $linjer[] = '';
                $linjer[] = '> '.$s['begrunnelse'];
            }
            foreach ($s['merknader'] as $m) {
                $linjer[] = "- {$m}";
            }
        }

        if ($setninger = $p['kildesetninger']['setninger'] ?? []) {
            $linjer[] = '';
            $linjer[] = '### Kildesetninger';
            foreach ($setninger as $setning) {
                $linjer[] = "- `{$setning['nokkel']}` {$setning['tekst']}";
            }
        }

        if ($kjoringer = $p['modellsvar']['kjoringer'] ?? []) {
            $linjer[] = '';
            $linjer[] = '### Modellsvar';
            $linjer[] = '';
            $linjer[] = '| # | Gyldig | Standpunkt | Sikkerhet | Kilder | Feil |';
            $linjer[] = '| --- | --- | --- | --- | --- | --- |';
            foreach ($kjoringer as $i => $k) {
                $linjer[] = sprintf('| %d | %s | %s | %s | %s | %s |',
                    $i + 1,
                    ($k['gyldig'] ?? false) ? 'ja' : 'nei',
                    $k['verdi'] ?? '–',
                    $k['sikkerhet'] ?? '–',
                    implode(', ', $k['kildesetninger'] ?? []) ?: '–',
                    str_replace('|', '\|', (string) ($k['feil'] ?? '–')),
                );
            }
        }

        $linjer[] = '';
        $linjer[] = '### Full pakke';
        $linjer[] = 'Prompt, jobblogg, feilede jobber og eventuell tekst fra besøkende hentes med token:';
        $linjer[] = '';
        $linjer[] = '```sh';
        $linjer[] = 'curl -H "Authorization: Bearer $FEILSOKING_TOKEN" '.$sak->apiUrl();
        $linjer[] = '```';
        $linjer[] = '';
        $linjer[] = "<sub>Referanse `{$sak->referanse}` · laget {$p['laget']}</sub>";

        return implode("\n", $linjer);
    }
}
