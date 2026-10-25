<?php

namespace App\Feilsoking;

use App\Analyse\Klassifiserer;
use App\Analyse\Treff;
use App\Enums\Prosessteg;
use App\Models\Program;
use App\Models\Prosesskjoring;
use App\Models\Setning;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Models\Tilbakemelding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Setter sammen feilsøkingspakken for et standpunkt, en kjøring eller en
 * tilbakemelding. Bare feltene som er listet her kommer med. Ingen hele
 * modeller, ingen konfigurasjon utover analyseinnstillingene, og ingen
 * brukerdata utover det som trengs (teksten i en tilbakemelding).
 */
class Pakkebygger
{
    public const VERSJON = 1;

    public function __construct(
        private Klassifiserer $klassifiserer,
        private Sensurerer $sensurerer,
    ) {}

    public static function stotter(Model $modell): bool
    {
        return $modell instanceof Standpunkt || $modell instanceof Prosesskjoring || $modell instanceof Tilbakemelding;
    }

    /** @return array<string, mixed> */
    public function bygg(Model $modell): array
    {
        $pakke = match (true) {
            $modell instanceof Standpunkt => $this->forStandpunkt($modell),
            $modell instanceof Prosesskjoring => $this->forKjoring($modell),
            $modell instanceof Tilbakemelding => $this->forTilbakemelding($modell),
            default => throw new InvalidArgumentException('Kan ikke lage feilsøkingspakke for '.$modell::class),
        };

        return $this->sensurerer->rens([
            'versjon' => self::VERSJON,
            'laget' => now()->toIso8601String(),
            'miljo' => $this->miljo(),
            ...$pakke,
        ]);
    }

    /** @return array<string, mixed> */
    private function forStandpunkt(Standpunkt $standpunkt): array
    {
        $standpunkt->loadMissing(['tema', 'program.parti', 'kildesetninger']);

        return [
            'type' => 'standpunkt',
            'tittel' => "Standpunkt: {$standpunkt->tema->navn} · {$standpunkt->program->nokkel} · {$standpunkt->kildetype->etikett()}",
            ...$this->standpunktdeler($standpunkt),
            'jobblogg' => $this->jobblogg(Prosessteg::Klassifisering, $standpunkt->program, $standpunkt->tema, $standpunkt),
            'feilede_jobber' => $this->feiledeJobber($standpunkt->program, $standpunkt->tema),
        ];
    }

    /** @return array<string, mixed> */
    private function forKjoring(Prosesskjoring $kjoring): array
    {
        $kjoring->loadMissing(['program.parti', 'tema']);

        $standpunkt = $kjoring->steg === Prosessteg::Klassifisering && $kjoring->program && $kjoring->tema && $kjoring->kildetype
            ? Standpunkt::query()->where([
                'tema_id' => $kjoring->tema_id, 'program_id' => $kjoring->program_id, 'kildetype' => $kjoring->kildetype,
            ])->with(['tema', 'program.parti', 'kildesetninger'])->first()
            : null;

        return [
            'type' => 'kjoring',
            'tittel' => "Kjøring #{$kjoring->id}: {$kjoring->steg->etikett()} · ".($kjoring->beskrivelse() ?: 'hele flyten'),
            'kjoring' => $this->kjoringsrad($kjoring),
            'program' => $kjoring->program ? $this->program($kjoring->program) : null,
            ...($standpunkt ? $this->standpunktdeler($standpunkt) : []),
            'jobblogg' => $kjoring->program
                ? $this->jobblogg($kjoring->steg, $kjoring->program, $kjoring->tema, $standpunkt)
                : [],
            'feilede_jobber' => $kjoring->program ? $this->feiledeJobber($kjoring->program, $kjoring->tema) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function forTilbakemelding(Tilbakemelding $tilbakemelding): array
    {
        $tilbakemelding->loadMissing(['tema', 'parti', 'standpunkt.tema', 'standpunkt.program.parti', 'standpunkt.kildesetninger']);
        $standpunkt = $tilbakemelding->standpunkt;

        return [
            'type' => 'tilbakemelding',
            'tittel' => "Tilbakemelding #{$tilbakemelding->id}: {$tilbakemelding->tema->navn} · {$tilbakemelding->parti->navn}",
            'tilbakemelding' => [
                'id' => $tilbakemelding->id,
                'mottatt' => $tilbakemelding->created_at?->toIso8601String(),
                'tema' => $tilbakemelding->tema->navn,
                'parti' => $tilbakemelding->parti->navn,
                'tekst' => $tilbakemelding->tekst,
                'behandlet' => $tilbakemelding->behandlet,
            ],
            ...($standpunkt ? $this->standpunktdeler($standpunkt) : []),
            'jobblogg' => $standpunkt ? $this->jobblogg(Prosessteg::Klassifisering, $standpunkt->program, $standpunkt->tema, $standpunkt) : [],
            'feilede_jobber' => $standpunkt ? $this->feiledeJobber($standpunkt->program, $standpunkt->tema) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function standpunktdeler(Standpunkt $standpunkt): array
    {
        return [
            'tema' => $this->tema($standpunkt->tema),
            'program' => $this->program($standpunkt->program),
            'standpunkt' => [
                'id' => $standpunkt->id,
                'kildetype' => $standpunkt->kildetype->value,
                'verdi' => $standpunkt->verdi->value,
                'sikkerhet' => $standpunkt->sikkerhet->value,
                'status' => $standpunkt->status->value,
                'uenighet' => $standpunkt->uenighet,
                'begrunnelse' => $standpunkt->begrunnelse,
                'merknader' => $standpunkt->merknader ?? [],
                'modell' => $standpunkt->modell,
                'prompt_versjon' => $standpunkt->prompt_versjon,
                'oppdatert' => $standpunkt->updated_at?->toIso8601String(),
            ],
            'kildesetninger' => $standpunkt->kildesetninger->map(fn (Setning $s) => [
                'nokkel' => $s->nokkel,
                'tekst' => $s->tekst,
            ])->values()->all(),
            // Modellsvarene slik de ble lagret: gyldige svar tolket, ugyldige
            // med feil og råsvar.
            'modellsvar' => $standpunkt->kjoringer ?? [],
            'prompt' => $this->prompt($standpunkt),
        ];
    }

    /**
     * Prompten bygges på nytt fra dagens data, uten å kalle modellen. Den kan
     * avvike fra den som ble brukt om programmet, temaet eller prompten er
     * endret siden.
     *
     * @return array<string, mixed>
     */
    private function prompt(Standpunkt $standpunkt): array
    {
        $del = [
            'brukt_versjon' => $standpunkt->prompt_versjon,
            'gjeldende_versjon' => Klassifiserer::PROMPT_VERSJON,
            'merknad' => 'Bygget på nytt fra dagens data da pakken ble laget. Kan avvike fra prompten som ble brukt hvis programmet, temaet eller prompten er endret siden.',
        ];

        try {
            $forhandsvisning = $this->klassifiserer->forhandsvis($standpunkt->tema, $standpunkt->program, $standpunkt->kildetype);
        } catch (Throwable $e) {
            return $del + ['feil' => $e->getMessage()];
        }

        if ($forhandsvisning === null) {
            return $del + ['meldinger' => [], 'treff' => [], 'feil' => 'Gjenfinningen finner ingen avsnitt over terskelen, så det blir ikke noe LLM-kall.'];
        }

        return $del + [
            'meldinger' => $forhandsvisning['meldinger'],
            'referanser' => $forhandsvisning['referanser'],
            'treff' => array_map(fn (Treff $t) => [
                'tekstbit_id' => $t->bit->id,
                'overskriftssti' => $t->bit->overskriftssti,
                'likhet' => round($t->likhet, 4),
                'nokkelord' => $t->nokkelord,
            ], $forhandsvisning['treff']),
        ];
    }

    /** @return array<string, mixed> */
    private function tema(Tema $tema): array
    {
        return [
            'id' => $tema->id,
            'navn' => $tema->navn,
            'slug' => $tema->slug,
            'pastand' => $tema->pastand,
            'beskrivelse' => $tema->beskrivelse,
            'synonymer' => $tema->synonymer ?? [],
            'har_embedding' => $tema->embedding !== null,
            'embeddingmodell' => $tema->embeddingmodell,
        ];
    }

    /** @return array<string, mixed> */
    private function program(Program $program): array
    {
        $program->loadMissing('parti');

        return [
            'id' => $program->id,
            'nokkel' => $program->nokkel,
            'tittel' => $program->tittel,
            'parti' => $program->parti?->navn,
            'type' => $program->type?->value,
            'deling' => $program->deling?->value,
            'periode' => $program->periode,
            'gjeldende' => $program->gjeldende,
            'kildefil' => $program->kildefil,
            'digitalisering_status' => $program->digitalisering_status?->value,
            'digitalisering_merknad' => $program->digitalisering_merknad,
            'antall_sider' => $program->antall_sider,
        ];
    }

    /**
     * De siste kjøringene for samme mål, nyeste først.
     *
     * @return list<array<string, mixed>>
     */
    private function jobblogg(Prosessteg $steg, Program $program, ?Tema $tema, ?Standpunkt $standpunkt): array
    {
        $kjoringer = Prosesskjoring::query()
            ->forMal($steg, $program, $tema, $standpunkt?->kildetype)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $kjoringer->map(fn (Prosesskjoring $k) => $this->kjoringsrad($k))->all();
    }

    /** @return array<string, mixed> */
    private function kjoringsrad(Prosesskjoring $kjoring): array
    {
        return [
            'id' => $kjoring->id,
            'steg' => $kjoring->steg->value,
            'gjelder' => $kjoring->beskrivelse(),
            'status' => $kjoring->status->value,
            'utlost_av' => $kjoring->utlost_av?->value,
            'forsok' => $kjoring->forsok,
            'lagt_i_ko' => $kjoring->created_at?->toIso8601String(),
            'startet' => $kjoring->startet?->toIso8601String(),
            'ferdig' => $kjoring->ferdig?->toIso8601String(),
            'varighet' => $kjoring->varighet(),
            'melding' => $kjoring->melding,
        ];
    }

    /**
     * Feilede jobber fra køen for samme program (og tema), funnet via
     * Horizon-merkene i jobben. Bare unntaket tas med, ikke hele jobben, og
     * stakksporet kortes ned.
     *
     * @return list<array<string, mixed>>
     */
    private function feiledeJobber(Program $program, ?Tema $tema): array
    {
        $tabell = config('queue.failed.table', 'failed_jobs');
        if (! Schema::hasTable($tabell)) {
            return [];
        }

        $jobber = DB::table($tabell)
            ->where('payload', 'like', '%'.addcslashes(json_encode('program:'.$program->nokkel), '%_').'%')
            ->when($tema, fn ($q) => $q->where('payload', 'like', '%'.addcslashes(json_encode('tema:'.$tema->slug), '%_').'%'))
            ->orderByDesc('id')
            ->limit(5)
            ->get(['uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(function ($rad) {
                $payload = json_decode($rad->payload, true) ?: [];

                return [
                    'uuid' => $rad->uuid,
                    'ko' => $rad->queue,
                    'jobb' => $payload['displayName'] ?? null,
                    'feilet' => (string) $rad->failed_at,
                    'unntak' => implode("\n", array_slice(explode("\n", (string) $rad->exception), 0, 25)),
                ];
            })
            ->all();

        return $jobber;
    }

    /** @return array<string, mixed> */
    private function miljo(): array
    {
        return [
            'app_env' => config('app.env'),
            'deployment' => config('sidecache.deployment'),
            'modell' => config('hvamenerpartiet.ollama.modell'),
            'embeddingmodell' => config('hvamenerpartiet.ollama.embeddingmodell'),
            'kontekstlengde' => config('hvamenerpartiet.ollama.kontekstlengde'),
            'tenk' => config('hvamenerpartiet.ollama.tenk'),
            'analyse' => config('hvamenerpartiet.analyse'),
        ];
    }
}
