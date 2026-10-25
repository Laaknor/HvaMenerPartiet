<?php

namespace App\Analyse;

use App\Enums\Kildetype;
use App\Enums\Sikkerhet;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use App\Models\Avsnitt;
use App\Models\Program;
use App\Models\Setning;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Ollama\OllamaKlient;
use App\Ollama\UgyldigSvar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Setter standpunkt for ett tema i ett program (eller én del av et
 * kombinert dokument).
 *
 * Modellen får bare de relevante avsnittene, med setnings-ID-er, og svarer
 * med hvilke setninger som støtter standpunktet. Koden kontrollerer at
 * ID-ene finnes og hører til riktig program, og utdraget bygges fra
 * setningene i databasen. Partinavnet sendes aldri til modellen.
 */
class Klassifiserer
{
    public const PROMPT_VERSJON = 'v1-2026-10';

    public function __construct(
        private OllamaKlient $ollama,
        private Gjenfinner $gjenfinner,
    ) {}

    public function klassifiser(Tema $tema, Program $program, Kildetype $kildetype): Standpunkt
    {
        $treff = $this->gjenfinner->finn($tema, $program, $kildetype);

        if ($treff === []) {
            return $this->lagre($tema, $program, $kildetype, [
                'verdi' => Standpunktverdi::IkkeOmtalt,
                'sikkerhet' => Sikkerhet::Middels,
                'begrunnelse' => 'Ingen avsnitt i programmet handler om temaet (ingen treff over terskelen for gjenfinning).',
                'uenighet' => false,
                'kjoringer' => [],
                'merknader' => ['Satt uten LLM-kall fordi gjenfinningen ikke fant relevante avsnitt.'],
                'modell' => null,
                'kilder' => collect(),
            ]);
        }

        $avsnitt = $this->avsnittFraTreff($program, $treff);
        $setninger = $avsnitt->flatMap->setninger->keyBy('nokkel');
        $meldinger = $this->meldinger($tema, $program, $kildetype, $avsnitt);

        $kjoringer = [];
        $antall = max(1, (int) config('hvamenerpartiet.analyse.kjoringer', 2));
        for ($i = 0; $i < $antall; $i++) {
            $kjoringer[] = $this->kjor($meldinger, $setninger, $i);
        }

        return $this->lagre($tema, $program, $kildetype, $this->samle($kjoringer, $setninger) + [
            'modell' => $this->ollama->modell(),
        ]);
    }

    /**
     * Avsnittene modellen får se, i dokumentrekkefølge og uten duplikater.
     *
     * @param  list<Treff>  $treff
     * @return Collection<int, Avsnitt>
     */
    private function avsnittFraTreff(Program $program, array $treff): Collection
    {
        $intervaller = [];
        foreach ($treff as $t) {
            $intervaller[] = [$t->bit->fra_avsnitt_id, $t->bit->til_avsnitt_id];
        }

        $rekkefolger = Avsnitt::query()
            ->whereIn('id', collect($intervaller)->flatten()->unique())
            ->pluck('rekkefolge', 'id');

        return Avsnitt::query()
            ->where('program_id', $program->id)
            ->where(function ($q) use ($intervaller, $rekkefolger) {
                foreach ($intervaller as [$fra, $til]) {
                    $q->orWhereBetween('rekkefolge', [$rekkefolger[$fra], $rekkefolger[$til]]);
                }
            })
            ->with(['setninger', 'kapittel.overordnet.overordnet'])
            ->orderBy('rekkefolge')
            ->get();
    }

    /**
     * @param  Collection<int, Avsnitt>  $avsnitt
     * @return list<array{role: string, content: string}>
     */
    public function meldinger(Tema $tema, Program $program, Kildetype $kildetype, Collection $avsnitt): array
    {
        $system = <<<'TEKST'
Du er en nøytral analytiker som leser norske partiprogrammer. Du skal avgjøre hva en programtekst sier om en påstand.

Regler:
- Bruk bare teksten du får. Ikke bruk egen kunnskap om partier eller politikk.
- Vurder bare det teksten faktisk sier, ikke hva partiet «egentlig» mener.
- Hver setning har en ID i hakeparentes. Oppgi ID-ene til de setningene som best begrunner standpunktet, høyst fem, og bare ID-er som står i teksten.

Standpunkt, målt mot påstanden:
- "for": teksten støtter påstanden tydelig.
- "delvis_for": teksten støtter påstanden, men med vilkår, forbehold eller bare delvis.
- "mot": teksten går tydelig imot påstanden.
- "delt_uklart": teksten omtaler temaet, men standpunktet til påstanden kan ikke leses entydig.
- "ikke_omtalt": teksten sier ingenting som tar stilling til påstanden. Da skal listen med kildesetninger være tom.

Sikkerhet: "høy" når teksten er eksplisitt, "middels" når standpunktet må utledes, "lav" når du er usikker.
Begrunnelse: én eller to korte setninger på norsk bokmål som forklarer vurderingen ut fra teksten.
TEKST;

        $programbeskrivelse = match ($kildetype) {
            Kildetype::Arbeidsprogram => 'et partis arbeidsprogram'.($program->periode ? " for stortingsperioden {$program->periode}" : ''),
            Kildetype::Prinsipprogram => 'et partis prinsipprogram (langsiktige verdier og mål)',
        };

        $anonymiser = $this->anonymiserer($program);

        $utdrag = $avsnitt->groupBy('kapittel_id')->map(function (Collection $avsnittene) use ($anonymiser) {
            /** @var Avsnitt $forste */
            $forste = $avsnittene->first();
            $linjer = ['### '.$anonymiser($forste->kapittel->overskriftssti()).($forste->side ? " (side {$forste->side})" : '')];
            foreach ($avsnittene as $a) {
                foreach ($a->setninger as $s) {
                    $linjer[] = "[{$s->nokkel}] ".($s->er_punkt ? '• ' : '').$anonymiser($s->tekst);
                }
                $linjer[] = '';
            }

            return implode("\n", $linjer);
        })->implode("\n");

        $bruker = "Tema: {$tema->navn}\n";
        if ($tema->beskrivelse) {
            $bruker .= "Beskrivelse: {$tema->beskrivelse}\n";
        }
        $bruker .= "Påstand: «{$tema->pastand}»\n\n";
        $bruker .= "Teksten under er utdrag fra {$programbeskrivelse}. Partinavnet er erstattet med «Partiet».\n\n{$utdrag}\n";
        $bruker .= 'Hva er programmets standpunkt til påstanden?';

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $bruker],
        ];
    }

    /**
     * Partinavnet byttes ut i teksten modellen får, så vurderingen blir blind
     * og ikke påvirkes av hva modellen tror den vet om partiet.
     *
     * @return callable(string): string
     */
    private function anonymiserer(Program $program): callable
    {
        $parti = $program->parti;
        $navn = array_filter([$parti->navn, mb_strlen($parti->forkortelse) >= 2 ? $parti->forkortelse : null]);
        usort($navn, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $monstre = [];
        foreach ($navn as $n) {
            $q = preg_quote($n, '/');
            $monstre['/(?<![\p{L}\d])'.$q.'s(?![\p{L}\d])/u'] = 'Partiets';
            $monstre['/(?<![\p{L}\d])'.$q.'(?![\p{L}\d])/u'] = 'Partiet';
        }

        return fn (string $tekst) => preg_replace(array_keys($monstre), array_values($monstre), $tekst);
    }

    /** @return array<string, mixed> */
    public static function svarskjema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standpunkt' => ['type' => 'string', 'enum' => Standpunktverdi::verdier()],
                'sikkerhet' => ['type' => 'string', 'enum' => ['høy', 'middels', 'lav']],
                'kildesetninger' => ['type' => 'array', 'items' => ['type' => 'string']],
                'begrunnelse' => ['type' => 'string'],
            ],
            'required' => ['standpunkt', 'sikkerhet', 'kildesetninger', 'begrunnelse'],
        ];
    }

    /**
     * Én kjøring. Første kjøring er deterministisk; de neste bruker litt
     * temperatur og et annet frø, så uenighet kan oppdages.
     *
     * @param  list<array{role: string, content: string}>  $meldinger
     * @param  Collection<string, Setning>  $setninger
     * @return array<string, mixed>
     */
    private function kjor(array $meldinger, Collection $setninger, int $nr): array
    {
        try {
            $svar = $this->ollama->chatJson($meldinger, self::svarskjema(), [
                'temperature' => $nr === 0 ? 0 : 0.4,
                'seed' => 1000 + $nr,
            ]);
        } catch (UgyldigSvar $e) {
            return ['gyldig' => false, 'feil' => $e->getMessage()];
        }

        $verdi = Standpunktverdi::tryFrom((string) ($svar['standpunkt'] ?? ''));
        if ($verdi === null) {
            return ['gyldig' => false, 'feil' => 'Ukjent standpunkt', 'svar' => $svar];
        }

        $oppgitt = array_values(array_filter((array) ($svar['kildesetninger'] ?? []), 'is_string'));
        $gyldige = array_values(array_filter($oppgitt, fn ($n) => $setninger->has($n)));
        $ugyldige = array_values(array_diff($oppgitt, $gyldige));

        if ($verdi === Standpunktverdi::IkkeOmtalt) {
            $gyldige = [];
        }

        $kjoring = [
            'gyldig' => true,
            'verdi' => $verdi->value,
            'sikkerhet' => Sikkerhet::fraModell((string) ($svar['sikkerhet'] ?? 'lav'))->value,
            'kildesetninger' => $gyldige,
            'ugyldige_kilder' => $ugyldige,
            'begrunnelse' => trim((string) ($svar['begrunnelse'] ?? '')),
        ];

        // Et standpunkt uten gyldige kilder kan ikke vises med ordrett sitat.
        if ($verdi->omtaler() && $gyldige === []) {
            $kjoring['gyldig'] = false;
            $kjoring['feil'] = 'Ingen gyldige kildesetninger';
        }

        return $kjoring;
    }

    /**
     * Slår sammen kjøringene. Enighet gir verdien og laveste sikkerhet;
     * uenighet gir «Delt / uklart» med lav sikkerhet og merkes for manuell
     * gjennomgang.
     *
     * @param  list<array<string, mixed>>  $kjoringer
     * @param  Collection<string, Setning>  $setninger
     * @return array<string, mixed>
     */
    private function samle(array $kjoringer, Collection $setninger): array
    {
        $gyldige = array_values(array_filter($kjoringer, fn ($k) => $k['gyldig']));
        $merknader = [];

        if (count($gyldige) < count($kjoringer)) {
            $merknader[] = (count($kjoringer) - count($gyldige)).' av '.count($kjoringer).' kjøringer ga ugyldig svar.';
        }

        if ($gyldige === []) {
            return [
                'verdi' => Standpunktverdi::DeltUklart,
                'sikkerhet' => Sikkerhet::Lav,
                'begrunnelse' => 'Modellen ga ikke et gyldig svar med kildesetninger. Må vurderes manuelt.',
                'uenighet' => true,
                'kjoringer' => $kjoringer,
                'merknader' => $merknader,
                'kilder' => collect(),
            ];
        }

        $verdier = array_unique(array_column($gyldige, 'verdi'));
        $forste = $gyldige[0];

        if (count($verdier) === 1) {
            return [
                'verdi' => Standpunktverdi::from($forste['verdi']),
                'sikkerhet' => Sikkerhet::lavest(...array_map(fn ($k) => Sikkerhet::from($k['sikkerhet']), $gyldige)),
                'begrunnelse' => $forste['begrunnelse'],
                'uenighet' => false,
                'kjoringer' => $kjoringer,
                'merknader' => $merknader,
                'kilder' => $this->sorter($setninger, $forste['kildesetninger']),
            ];
        }

        $merknader[] = 'Kjøringene var uenige: '.implode(', ', $verdier).'.';

        return [
            'verdi' => Standpunktverdi::DeltUklart,
            'sikkerhet' => Sikkerhet::Lav,
            'begrunnelse' => 'Kjøringene var uenige ('.implode(', ', $verdier).'). Må vurderes manuelt.',
            'uenighet' => true,
            'kjoringer' => $kjoringer,
            'merknader' => $merknader,
            'kilder' => $this->sorter($setninger, array_merge(...array_column($gyldige, 'kildesetninger'))),
        ];
    }

    /**
     * Kildesetningene i dokumentrekkefølge, så utdraget leses naturlig.
     *
     * @param  Collection<string, Setning>  $setninger
     * @param  list<string>  $nokler
     * @return Collection<int, Setning>
     */
    private function sorter(Collection $setninger, array $nokler): Collection
    {
        $rekkefolge = $setninger->keys()->flip();

        return collect(array_unique($nokler))
            ->filter(fn ($n) => $setninger->has($n))
            ->sortBy(fn ($n) => $rekkefolge[$n])
            ->map(fn ($n) => $setninger[$n])
            ->values();
    }

    /** @param array<string, mixed> $data */
    private function lagre(Tema $tema, Program $program, Kildetype $kildetype, array $data): Standpunkt
    {
        return DB::transaction(function () use ($tema, $program, $kildetype, $data) {
            $standpunkt = Standpunkt::query()->firstOrNew([
                'tema_id' => $tema->id,
                'program_id' => $program->id,
                'kildetype' => $kildetype,
            ]);

            $nyeKilder = $data['kilder']->pluck('id')->all();
            $endret = ! $standpunkt->exists
                || $standpunkt->verdi !== $data['verdi']
                || $standpunkt->kildesetninger()->pluck('setninger.id')->all() !== $nyeKilder;

            $standpunkt->fill([
                'verdi' => $data['verdi'],
                'sikkerhet' => $data['sikkerhet'],
                'begrunnelse' => $data['begrunnelse'],
                'uenighet' => $data['uenighet'],
                'kjoringer' => $data['kjoringer'],
                'merknader' => $data['merknader'] ?: null,
                'modell' => $data['modell'],
                'prompt_versjon' => self::PROMPT_VERSJON,
            ]);

            // En ny vurdering som endrer noe, sendes tilbake til godkjenning.
            if ($endret) {
                if ($standpunkt->exists && $standpunkt->status !== Standpunktstatus::Maskinell) {
                    $standpunkt->leggTilMerknad('Ny klassifisering endret standpunkt eller kilder. Må godkjennes på nytt.');
                }
                $standpunkt->status = Standpunktstatus::Maskinell;
                $standpunkt->godkjent_av = null;
                $standpunkt->godkjent_tid = null;
            } elseif (! $standpunkt->status) {
                $standpunkt->status = Standpunktstatus::Maskinell;
            }

            $standpunkt->save();

            $standpunkt->kildesetninger()->sync(
                collect($nyeKilder)->values()->mapWithKeys(fn ($id, $i) => [$id => ['rekkefolge' => $i + 1]])->all()
            );

            return $standpunkt->load('kildesetninger');
        });
    }
}
