<?php

namespace App\Flyt;

use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Enums\Kjoringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Temastatus;
use App\Enums\Utlosning;
use App\Models\Program;
use App\Models\Prosesskjoring;
use App\Models\Standpunkt;
use App\Models\Tekstbit;
use App\Models\Tema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Starter neste steg i flyten av seg selv når det er ledig kapasitet.
 *
 * Kapasiteten er delt i to grupper: «import» (innlesing og digitalisering)
 * og «ollama» (embeddings og klassifisering). I hver gruppe startes bare så
 * mange jobber som det er plass til, så køen aldri fylles med tusenvis av
 * jobber, og nye programmer kommer raskt fram.
 *
 * Gjennomgangen av den digitaliserte strukturen er manuell: et program går
 * ikke videre til embeddings før det er godkjent i admin.
 *
 * En kjøring som har feilet, startes ikke automatisk igjen før noe er endret
 * (ny fil, ny innlesing eller ny godkjenning), eller den startes på nytt i admin.
 *
 * Gjeldende programmer går alltid først. Historiske programmer (fra tidligere
 * stortingsperioder) får bare kapasitet i en gruppe når ingen gjeldende
 * programmer venter på noe steg i den gruppen, og de nyeste tas først.
 */
class Prosessplanlegger
{
    private const AUTOMATISK = 'flyt:automatisk';

    private const LAAS = 'flyt:planlegg';

    public function __construct(private Prosessflyt $flyt) {}

    public function erAutomatisk(): bool
    {
        return (bool) Cache::get(self::AUTOMATISK, config('hvamenerpartiet.flyt.automatisk', true));
    }

    public function settAutomatisk(bool $pa): void
    {
        Cache::forever(self::AUTOMATISK, $pa);
    }

    /** Kalles når en jobb er ferdig, så ledig kapasitet tas i bruk med en gang. */
    public function etterJobb(): void
    {
        if ($this->erAutomatisk()) {
            $this->planlegg();
        }
    }

    public function kapasitet(string $gruppe): int
    {
        return max(0, (int) config("hvamenerpartiet.flyt.kapasitet.{$gruppe}", 1));
    }

    public function aktive(string $gruppe): int
    {
        return Prosesskjoring::query()->aktive()->whereIn('steg', Prosessteg::iGruppe($gruppe))->count();
    }

    public function ledig(string $gruppe): int
    {
        return max(0, $this->kapasitet($gruppe) - $this->aktive($gruppe));
    }

    /**
     * Starter så mange jobber som det er ledig kapasitet til.
     *
     * @return array<string, int> antall startet per steg
     */
    public function planlegg(): array
    {
        $startet = array_fill_keys(array_map(fn (Prosessteg $s) => $s->value, Prosessteg::cases()), 0);

        // Bare én planlegging om gangen, ellers kan to jobber som blir ferdige
        // samtidig starte mer enn kapasiteten.
        $laas = Cache::lock(self::LAAS, 60);
        if (! $laas->get()) {
            return $startet;
        }

        try {
            $this->ryddOpp();

            foreach (['import', 'ollama'] as $gruppe) {
                $ledig = $this->ledig($gruppe);
                foreach (Prosessteg::iGruppe($gruppe) as $steg) {
                    if ($ledig <= 0) {
                        break;
                    }
                    foreach ($this->kandidater($steg, $ledig) as [$program, $tema, $kildetype]) {
                        try {
                            $this->flyt->start($steg, $program, $tema, $kildetype, Utlosning::Automatisk);
                        } catch (Throwable $e) {
                            // Med synkron kø (lokalt og i tester) kjører jobben her.
                            // Feilen står på kjøringen, så planleggingen fortsetter.
                            report($e);
                        }
                        $startet[$steg->value]++;
                        $ledig--;
                    }
                }
            }

            $this->merkFerdigeTemaer();
        } finally {
            $laas->release();
        }

        return $startet;
    }

    /**
     * Hva som venter på hvert steg, for oversikten i admin.
     *
     * @return array<string, int>
     */
    public function venter(): array
    {
        $resultat = [];
        foreach (Prosessteg::cases() as $steg) {
            $resultat[$steg->value] = count($this->kandidater($steg, PHP_INT_MAX));
        }

        return $resultat;
    }

    /** Kjøringer som har stått fast (jobben døde, eller køen ble tømt), regnes som feilet. */
    public function ryddOpp(): int
    {
        $maks = (int) config('hvamenerpartiet.flyt.maks_kjoretid', 1200);

        $kjorer = Prosesskjoring::query()
            ->where('status', Kjoringsstatus::Kjorer)
            ->where('startet', '<', now()->subSeconds($maks))
            ->update([
                'status' => Kjoringsstatus::Feilet,
                'ferdig' => now(),
                'melding' => 'Jobben ble avbrutt (kjørte lenger enn '.intdiv($maks, 60).' minutter uten å bli ferdig).',
            ]);

        // En jobb som har ventet i kø svært lenge, er trolig borte. Kjører
        // Horizon, blir den startet på nytt av planleggeren.
        $iKo = Prosesskjoring::query()
            ->where('status', Kjoringsstatus::IKo)
            ->where('created_at', '<', now()->subSeconds($maks * 6))
            ->update([
                'status' => Kjoringsstatus::Feilet,
                'ferdig' => now(),
                'melding' => 'Jobben ble aldri startet. Sjekk at Horizon kjører.',
            ]);

        return $kjorer + $iKo;
    }

    /**
     * Mål som er klare for steget, i den rekkefølgen de bør kjøres.
     *
     * @return list<array{0: Program, 1: ?Tema, 2: ?Kildetype}>
     */
    public function kandidater(Prosessteg $steg, int $maks): array
    {
        $resultat = $this->kandidaterFor($steg, $maks, gjeldende: true);

        $igjen = $maks - count($resultat);
        if ($igjen > 0 && $this->historiskeSlippesTil($steg)) {
            $resultat = [...$resultat, ...$this->kandidaterFor($steg, $igjen, gjeldende: false)];
        }

        return $resultat;
    }

    /** Om historiske programmer kan bruke kapasiteten i gruppen til steget nå. */
    public function historiskeSlippesTil(Prosessteg $steg): bool
    {
        if (! config('hvamenerpartiet.flyt.historiske', true)) {
            return false;
        }

        foreach (Prosessteg::iGruppe($steg->kapasitetsgruppe()) as $annet) {
            if ($this->kandidaterFor($annet, 1, gjeldende: true) !== []) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{0: Program, 1: ?Tema, 2: ?Kildetype}> */
    private function kandidaterFor(Prosessteg $steg, int $maks, bool $gjeldende): array
    {
        if ($maks <= 0) {
            return [];
        }

        $siste = $this->sisteKjoringer($steg);
        $ledigMal = fn (Program $p, ?Tema $t = null, $k = null) => ! $this->blokkert($siste->get($this->nokkel($p->id, $t?->id, $k?->value)), $steg, $p);

        $resultat = [];
        $leggTil = function (Program $p, ?Tema $t = null, $k = null) use (&$resultat, $maks, $ledigMal): bool {
            if ($ledigMal($p, $t, $k)) {
                $resultat[] = [$p, $t, $k];
            }

            return count($resultat) < $maks;
        };

        $programmer = Program::query()->with('parti')->where('gjeldende', $gjeldende);
        if (! $gjeldende) {
            // Nyeste periode først.
            $programmer->orderByDesc('vedtatt_ar');
        }
        $programmer->orderBy('parti_id')->orderBy('id');

        switch ($steg) {
            case Prosessteg::Innlesing:
                foreach ($programmer->whereNotNull('kildefil')->get() as $p) {
                    if ($p->kildefilErEndret() && ! $leggTil($p)) {
                        break;
                    }
                }
                break;

            case Prosessteg::Digitalisering:
                foreach ($programmer->where('digitalisering_status', Digitaliseringsstatus::LastetNed)->get() as $p) {
                    if ($p->filFinnes() && ! $leggTil($p)) {
                        break;
                    }
                }
                break;

            case Prosessteg::Embeddings:
                foreach ($this->manglerEmbeddings($programmer->godkjent()->get()) as $p) {
                    if (! $leggTil($p)) {
                        break;
                    }
                }
                break;

            case Prosessteg::Klassifisering:
                $klare = $programmer->godkjent()->get();
                $klare = $klare->diff($this->manglerEmbeddings($klare))
                    ->filter(fn (Program $p) => $p->tekstbiter()->exists());
                if ($klare->isEmpty()) {
                    break;
                }

                $finnes = Standpunkt::query()
                    ->whereIn('program_id', $klare->modelKeys())
                    ->get(['tema_id', 'program_id', 'kildetype'])
                    ->map(fn (Standpunkt $s) => $this->nokkel($s->program_id, $s->tema_id, $s->kildetype->value))
                    ->flip();

                foreach ($this->aktuelleTemaer() as $tema) {
                    foreach ($klare as $p) {
                        foreach ($p->kildetyper() as $k) {
                            if ($finnes->has($this->nokkel($p->id, $tema->id, $k->value))) {
                                continue;
                            }
                            if (! $leggTil($p, $tema, $k)) {
                                break 3;
                            }
                        }
                    }
                }
                break;
        }

        return $resultat;
    }

    /** @return Collection<int, Tema> */
    public function aktuelleTemaer(): Collection
    {
        return Tema::query()
            ->whereIn('status', [Temastatus::Godkjent, Temastatus::Analysert, Temastatus::Publisert])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Program>  $programmer
     * @return Collection<int, Program>
     */
    private function manglerEmbeddings(Collection $programmer): Collection
    {
        if ($programmer->isEmpty()) {
            return $programmer;
        }

        $modell = config('hvamenerpartiet.ollama.embeddingmodell');
        $mangler = Tekstbit::query()
            ->whereIn('program_id', $programmer->modelKeys())
            ->where(fn ($q) => $q->whereNull('embedding')->orWhereNull('embeddingmodell')->orWhere('embeddingmodell', '!=', $modell))
            ->distinct()
            ->pluck('program_id')
            ->flip();

        return $programmer->filter(fn (Program $p) => $mangler->has($p->id))->values();
    }

    /**
     * Den siste kjøringen for hvert mål i steget.
     *
     * @return Collection<string, Prosesskjoring>
     */
    private function sisteKjoringer(Prosessteg $steg): Collection
    {
        $ider = Prosesskjoring::query()
            ->where('steg', $steg)
            ->selectRaw('max(id) as id')
            ->groupBy('program_id', 'tema_id', 'kildetype')
            ->pluck('id');

        return Prosesskjoring::query()
            ->whereIn('id', $ider)
            ->whereIn('status', [Kjoringsstatus::IKo, Kjoringsstatus::Kjorer, Kjoringsstatus::Feilet])
            ->get()
            ->keyBy(fn (Prosesskjoring $k) => $this->nokkel($k->program_id, $k->tema_id, $k->kildetype?->value));
    }

    /** Om forrige kjøring hindrer en ny automatisk start. */
    private function blokkert(?Prosesskjoring $siste, Prosessteg $steg, Program $program): bool
    {
        if (! $siste) {
            return false;
        }

        if ($siste->status->erAktiv()) {
            return true;
        }

        // Feilet: prøv igjen bare hvis noe er endret siden.
        $endret = match ($steg) {
            Prosessteg::Innlesing => $program->kildefilEndretTid(),
            Prosessteg::Digitalisering => $program->lastet_ned?->getTimestamp(),
            Prosessteg::Embeddings => $program->godkjent?->getTimestamp(),
            Prosessteg::Klassifisering => null,
        };

        return $endret === null || $endret <= $siste->created_at->getTimestamp();
    }

    private function nokkel(?int $program, ?int $tema, ?string $kildetype): string
    {
        return ($program ?? '-').'|'.($tema ?? '-').'|'.($kildetype ?? '-');
    }

    /** Et godkjent tema som har standpunkt for alle godkjente programmer, er analysert. */
    private function merkFerdigeTemaer(): void
    {
        $programmer = Program::query()->gjeldende()->godkjent()->get();
        if ($programmer->isEmpty()) {
            return;
        }

        $forventet = $programmer->sum(fn (Program $p) => count($p->kildetyper()));

        Tema::query()->where('status', Temastatus::Godkjent)->get()
            ->filter(fn (Tema $t) => $t->standpunkter()->whereIn('program_id', $programmer->modelKeys())->count() >= $forventet)
            ->each(fn (Tema $t) => $t->update(['status' => Temastatus::Analysert]));
    }
}
