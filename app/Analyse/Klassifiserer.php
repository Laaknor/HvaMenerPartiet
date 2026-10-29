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
    public const PROMPT_VERSJON = 'v4-2026-10';

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

        ['setninger' => $setninger, 'referanser' => $referanser, 'meldinger' => $meldinger] = $this->forbered($tema, $program, $kildetype, $treff);

        $kjoringer = [];
        $antall = max(1, (int) config('hvamenerpartiet.analyse.kjoringer', 2));
        for ($i = 0; $i < $antall; $i++) {
            $kjoringer[] = $this->kjor($meldinger, $setninger, $referanser, $i);
        }

        return $this->lagre($tema, $program, $kildetype, $this->samle($kjoringer, $setninger) + [
            'modell' => $this->ollama->modell(),
        ]);
    }

    /**
     * Prompten modellen ville fått nå, uten å kalle modellen. Brukes i
     * feilsøkingspakken. Null betyr at gjenfinningen ikke fant noe, og at
     * standpunktet settes uten LLM-kall.
     *
     * @return array{treff: list<Treff>, referanser: array<string, string>, meldinger: list<array{role: string, content: string}>}|null
     */
    public function forhandsvis(Tema $tema, Program $program, Kildetype $kildetype): ?array
    {
        $treff = $this->gjenfinner->finn($tema, $program, $kildetype);

        if ($treff === []) {
            return null;
        }

        $forberedt = $this->forbered($tema, $program, $kildetype, $treff);

        return ['treff' => $treff, 'referanser' => $forberedt['referanser'], 'meldinger' => $forberedt['meldinger']];
    }

    /**
     * @param  list<Treff>  $treff
     * @return array{setninger: Collection<string, Setning>, referanser: array<string, string>, meldinger: list<array{role: string, content: string}>}
     */
    private function forbered(Tema $tema, Program $program, Kildetype $kildetype, array $treff): array
    {
        $avsnitt = $this->avsnittFraTreff($program, $treff);
        $setninger = $avsnitt->flatMap->setninger->keyBy('nokkel');

        return [
            'setninger' => $setninger,
            'referanser' => self::referanser($setninger),
            'meldinger' => $this->meldinger($tema, $program, $kildetype, $avsnitt),
        ];
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
- Hver setning har en kort ID i hakeparentes, som [S3]. I "kildesetninger" oppgir du ID-ene til de setningene som best begrunner standpunktet, for eksempel ["S3", "S4"]. Høyst fem, og bare ID-er som står i teksten.
- Er standpunktet noe annet enn "ikke_omtalt", skal du alltid oppgi minst én ID.
- En tekst som sier at noe er viktig, nødvendig eller en forutsetning, støtter påstanden selv om den ikke gjentar påstanden ordrett.
- En tekst kan også gå imot påstanden uten å nevne den. Vil teksten ha noe som ikke kan gjelde samtidig med påstanden, som et alternativ, en erstatning eller å avskaffe ordningen, er standpunktet "mot". For eksempel går «Skolen bør styres av kommunene» imot påstanden «Staten bør styre skolen».
- At teksten tar tydelig stilling, eller ligner påstanden i ordlyd, betyr ikke at den er enig. Avgjør retningen: ville noen som mener det teksten sier, svart ja eller nei på påstanden?
- Teksten må handle om selve temaet. Et nærliggende, men annet tema tar ikke stilling til påstanden, selv om ordene ligner. For eksempel handler skatt på formue ikke om skatt på bolig, og våpen handler ikke om energi.
- Ikke utled et standpunkt fra generelle holdninger, som at skattene er for høye eller at fred er viktig, når teksten ikke nevner temaet.
- Svar først på "handler_om_temaet": true bare hvis minst én setning handler om selve temaet. Er svaret false, skal standpunktet være "ikke_omtalt".
- Skriv så i "tekstens_syn" med egne ord hva teksten vil, uten å se på påstanden. Sammenlign deretter med påstanden og velg standpunkt.

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
        $referanser = array_flip(self::referanser($avsnitt->flatMap->setninger->keyBy('nokkel')));

        $utdrag = $avsnitt->groupBy('kapittel_id')->map(function (Collection $avsnittene) use ($anonymiser, $referanser) {
            /** @var Avsnitt $forste */
            $forste = $avsnittene->first();
            $linjer = ['### '.$anonymiser($forste->kapittel->overskriftssti()).($forste->side ? " (side {$forste->side})" : '')];
            foreach ($avsnittene as $a) {
                foreach ($a->setninger as $s) {
                    $linjer[] = "[{$referanser[$s->nokkel]}] ".($s->er_punkt ? '• ' : '').$anonymiser($s->tekst);
                }
                $linjer[] = '';
            }

            return implode("\n", $linjer);
        })->implode("\n");

        $bruker = "Tema: {$tema->navn}\n";
        if ($tema->beskrivelse) {
            $bruker .= "Beskrivelse: {$tema->beskrivelse}\n";
        }
        $bruker .= "Påstand: «{$tema->pastand}»\n";
        if ($forvekslinger = $tema->forvekslingsord()) {
            $bruker .= 'Ikke det samme som: '.implode(', ', $forvekslinger).". Tekst som bare handler om dette, tar ikke stilling til påstanden.\n";
        }
        $bruker .= "\n";
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
                // Først, så modellen tar stilling til om teksten handler om
                // temaet før den velger standpunkt.
                'handler_om_temaet' => ['type' => 'boolean'],
                // Hva teksten vil, før det sammenlignes med påstanden. Uten
                // dette steget leste modellen «Statsoverhodet bør være
                // folkevalgt» som «for» monarki: teksten var tydelig og
                // lignet påstanden, men gikk i motsatt retning.
                'tekstens_syn' => ['type' => 'string'],
                'standpunkt' => ['type' => 'string', 'enum' => Standpunktverdi::verdier()],
                'sikkerhet' => ['type' => 'string', 'enum' => ['høy', 'middels', 'lav']],
                'kildesetninger' => ['type' => 'array', 'items' => ['type' => 'string']],
                'begrunnelse' => ['type' => 'string'],
            ],
            'required' => ['handler_om_temaet', 'tekstens_syn', 'standpunkt', 'sikkerhet', 'kildesetninger', 'begrunnelse'],
        ];
    }

    /**
     * Korte ID-er (S1, S2, ...) i stedet for de faste ID-ene. Lange ID-er som
     * «sp-kp2025-k12-a4-s3» er vanskelige å gjengi riktig for mindre modeller.
     *
     * @param  Collection<string, Setning>  $setninger
     * @return array<string, string> kort ID => fast ID
     */
    public static function referanser(Collection $setninger): array
    {
        $referanser = [];
        foreach ($setninger->keys()->values() as $i => $nokkel) {
            $referanser['S'.($i + 1)] = $nokkel;
        }

        return $referanser;
    }

    /**
     * Gjør modellens ID om til en fast setnings-ID. Tåler «S3», «[S3]», «s3»,
     * «3» og den faste ID-en.
     *
     * @param  Collection<string, Setning>  $setninger
     * @param  array<string, string>  $referanser
     */
    public static function tolkReferanse(mixed $id, Collection $setninger, array $referanser): ?string
    {
        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $id = trim((string) $id, " \t\n\r\"'[]()«»");
        if ($setninger->has($id)) {
            return $id;
        }
        if (preg_match('/^s?\s*(\d+)$/i', $id, $m)) {
            return $referanser['S'.(int) $m[1]] ?? null;
        }

        return null;
    }

    /**
     * Én kjøring. Første kjøring er deterministisk; de neste bruker litt
     * temperatur og et annet frø, så uenighet kan oppdages. Mangler svaret
     * gyldige kildesetninger, får modellen ett nytt forsøk med beskjed om det.
     *
     * @param  list<array{role: string, content: string}>  $meldinger
     * @param  Collection<string, Setning>  $setninger
     * @param  array<string, string>  $referanser
     * @return array<string, mixed>
     */
    private function kjor(array $meldinger, Collection $setninger, array $referanser, int $nr): array
    {
        $valg = ['temperature' => $nr === 0 ? 0 : 0.4, 'seed' => 1000 + $nr];

        $kjoring = $this->sporModellen($meldinger, $setninger, $referanser, $valg);

        if (! $kjoring['gyldig'] && ($kjoring['feil'] ?? '') === 'Ingen gyldige kildesetninger') {
            $meldinger[] = ['role' => 'assistant', 'content' => json_encode($kjoring['svar'] ?? [], JSON_UNESCAPED_UNICODE)];
            $meldinger[] = ['role' => 'user', 'content' => 'Svaret mangler gyldige ID-er i "kildesetninger". Svar på nytt med de samme feltene, og oppgi ID-ene (som "S3") til setningene i teksten som begrunner standpunktet.'];
            $nytt = $this->sporModellen($meldinger, $setninger, $referanser, $valg);
            $nytt['nytt_forsok'] = true;
            $kjoring = $nytt;
        }

        unset($kjoring['svar']);

        return $kjoring;
    }

    /**
     * @param  list<array{role: string, content: string}>  $meldinger
     * @param  Collection<string, Setning>  $setninger
     * @param  array<string, string>  $referanser
     * @param  array<string, mixed>  $valg
     * @return array<string, mixed>
     */
    private function sporModellen(array $meldinger, Collection $setninger, array $referanser, array $valg): array
    {
        try {
            $svar = $this->ollama->chatJson($meldinger, self::svarskjema(), $valg);
        } catch (UgyldigSvar $e) {
            return ['gyldig' => false, 'feil' => $e->getMessage()];
        }

        $verdi = Standpunktverdi::tryFrom(mb_strtolower(trim((string) ($svar['standpunkt'] ?? ''))));
        if ($verdi === null) {
            return ['gyldig' => false, 'feil' => 'Ukjent standpunkt', 'svar' => $svar, 'rasvar' => $svar];
        }

        // Sier modellen selv at teksten ikke handler om temaet, kan den ikke
        // samtidig ha et standpunkt. Mangler feltet, brukes standpunktet.
        $overstyrt = false;
        if ($verdi->omtaler() && ($svar['handler_om_temaet'] ?? null) === false) {
            $verdi = Standpunktverdi::IkkeOmtalt;
            $overstyrt = true;
        }

        $oppgitt = array_values((array) ($svar['kildesetninger'] ?? []));
        $gyldige = [];
        $ugyldige = [];
        foreach ($oppgitt as $id) {
            $nokkel = self::tolkReferanse($id, $setninger, $referanser);
            $nokkel !== null ? $gyldige[] = $nokkel : $ugyldige[] = is_scalar($id) ? (string) $id : json_encode($id);
        }
        $gyldige = array_values(array_unique($gyldige));

        if ($verdi === Standpunktverdi::IkkeOmtalt) {
            $gyldige = [];
        }

        $kjoring = [
            'gyldig' => true,
            'handler_om_temaet' => is_bool($svar['handler_om_temaet'] ?? null) ? $svar['handler_om_temaet'] : null,
            'tekstens_syn' => trim((string) ($svar['tekstens_syn'] ?? '')),
            'verdi' => $verdi->value,
            'sikkerhet' => Sikkerhet::fraModell((string) ($svar['sikkerhet'] ?? 'lav'))->value,
            'kildesetninger' => $gyldige,
            'ugyldige_kilder' => $ugyldige,
            'begrunnelse' => trim((string) ($svar['begrunnelse'] ?? '')),
            'svar' => $svar,
        ];

        if ($overstyrt) {
            $kjoring['merknad'] = 'Modellen svarte at teksten ikke handler om temaet, men oppga «'.mb_strtolower(trim((string) $svar['standpunkt'])).'». Satt til «ikke_omtalt».';
        }

        // Et standpunkt uten gyldige kilder kan ikke vises med ordrett sitat.
        if ($verdi->omtaler() && $gyldige === []) {
            $kjoring['gyldig'] = false;
            $kjoring['feil'] = 'Ingen gyldige kildesetninger';
            $kjoring['rasvar'] = $svar;
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

        $overstyrte = count(array_filter($gyldige, fn ($k) => isset($k['merknad'])));
        if ($overstyrte) {
            $merknader[] = "{$overstyrte} av ".count($kjoringer).' kjøringer oppga standpunkt, men svarte at teksten ikke handler om temaet. Satt til «Ikke omtalt».';
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
