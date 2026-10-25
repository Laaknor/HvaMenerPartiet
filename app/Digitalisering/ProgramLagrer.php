<?php

namespace App\Digitalisering;

use App\Enums\Standpunktstatus;
use App\Models\Avsnitt;
use App\Models\Kapittel;
use App\Models\Program;
use App\Models\Setning;
use App\Models\Standpunkt;
use Illuminate\Support\Facades\DB;

/**
 * Lagrer et digitalisert dokument med faste ID-er.
 *
 * Første gang får hver setning en ID ut fra posisjonen, for eksempel
 * «sv-ap2025-k4-a12-s3». Ved ny import av samme program beholder en setning
 * både databaseraden og ID-en sin så lenge teksten er uendret, slik at gamle
 * lenker og standpunkter fortsatt virker. Standpunkter som pekte på setninger
 * som er borte, sendes tilbake til godkjenning.
 */
class ProgramLagrer
{
    public function __construct(private Setningsdeler $setningsdeler) {}

    public function lagre(Program $program, Dokument $dokument): Lagringsresultat
    {
        return DB::transaction(fn () => $this->lagreITransaksjon($program, $dokument));
    }

    private function lagreITransaksjon(Program $program, Dokument $dokument): Lagringsresultat
    {
        $resultat = new Lagringsresultat;

        // 1. Del alt i setninger først, så vi kan matche mot forrige import.
        $plan = [];
        foreach ($dokument->kapitler as $ki => $kapittel) {
            foreach ($kapittel->avsnitt as $ai => $avsnitt) {
                $plan[$ki][$ai] = array_map(
                    fn (array $s) => $s + ['hash' => Tekstrens::hash($s['tekst'])],
                    $this->setningsdeler->del($avsnitt->tekst),
                );
            }
        }

        // 2. Finn gamle setninger med samme tekst, i rekkefølge.
        $gamle = Setning::query()
            ->where('setninger.program_id', $program->id)
            ->join('avsnitt', 'avsnitt.id', '=', 'setninger.avsnitt_id')
            ->orderBy('avsnitt.rekkefolge')
            ->orderBy('setninger.nr')
            ->get(['setninger.id', 'setninger.nokkel', 'setninger.tekst_hash']);

        $ledige = [];
        foreach ($gamle as $g) {
            $ledige[$g->tekst_hash][] = $g;
        }

        $treff = []; // "ki.ai.si" => Setning
        foreach ($plan as $ki => $avsnittene) {
            foreach ($avsnittene as $ai => $setningene) {
                foreach ($setningene as $si => $s) {
                    if (! empty($ledige[$s['hash']])) {
                        $treff["$ki.$ai.$si"] = array_shift($ledige[$s['hash']]);
                    }
                }
            }
        }

        // 3. Setninger som ikke finnes lenger: merk berørte standpunkter og slett.
        $fjernes = collect($ledige)->flatten()->pluck('id')->all();
        if ($fjernes !== []) {
            $standpunkter = Standpunkt::query()
                ->whereHas('kildesetninger', fn ($q) => $q->whereIn('setninger.id', $fjernes))
                ->get();
            foreach ($standpunkter as $standpunkt) {
                $standpunkt->status = Standpunktstatus::Maskinell;
                $standpunkt->godkjent_av = null;
                $standpunkt->godkjent_tid = null;
                $standpunkt->leggTilMerknad('Ny import av programmet fjernet en eller flere kildesetninger. Må vurderes på nytt.');
                $standpunkt->save();
            }
            $resultat->berorteStandpunkter = $standpunkter->count();

            foreach (array_chunk($fjernes, 500) as $bit) {
                Setning::query()->whereIn('id', $bit)->delete();
            }
        }
        $resultat->fjernet = count($fjernes);

        // 4. Bygg kapitler og avsnitt på nytt, og flytt beholdte setninger dit.
        $gamleKapitler = Kapittel::query()->where('program_id', $program->id)->pluck('id')->all();
        $program->tekstbiter()->delete();

        $brukteNokler = collect($treff)->map->nokkel->flip()->all();
        $rekkefolge = 0;
        $avsnittsrekkefolge = 0;
        $stabel = []; // nivå => Kapittel
        $teller = []; // overordnet id (eller 'rot') => siste nr
        $avsnittnr = []; // hovedkapittel id => siste nr

        foreach ($dokument->kapitler as $ki => $dokKapittel) {
            $niva = $dokKapittel->niva;
            while ($niva > 1 && ! isset($stabel[$niva - 1])) {
                $niva--;
            }
            $overordnet = $niva > 1 ? $stabel[$niva - 1] : null;
            $tellerNokkel = $overordnet?->id ?? 'rot';

            if ($dokKapittel->innledning) {
                $nr = 0;
            } else {
                $nr = ($teller[$tellerNokkel] ?? 0) + 1;
            }
            $teller[$tellerNokkel] = $nr;

            $kapittel = Kapittel::create([
                'program_id' => $program->id,
                'overordnet_kapittel_id' => $overordnet?->id,
                'nr' => $nr,
                'niva' => $niva,
                'rekkefolge' => ++$rekkefolge,
                'tittel' => mb_substr($dokKapittel->tittel, 0, 500),
                'slug' => $overordnet ? $overordnet->slug.'-'.$nr : 'kap-'.$nr,
                'del' => $dokKapittel->del,
                'side' => $dokKapittel->side,
            ]);
            $resultat->kapitler++;

            $stabel[$niva] = $kapittel;
            foreach (array_keys($stabel) as $n) {
                if ($n > $niva) {
                    unset($stabel[$n]);
                }
            }
            $hovedkapittel = $stabel[1];

            foreach ($dokKapittel->avsnitt as $ai => $dokAvsnitt) {
                $anr = ($avsnittnr[$hovedkapittel->id] ?? 0) + 1;
                $avsnittnr[$hovedkapittel->id] = $anr;

                $avsnitt = Avsnitt::create([
                    'program_id' => $program->id,
                    'kapittel_id' => $kapittel->id,
                    'hovedkapittel_id' => $hovedkapittel->id,
                    'nr' => $anr,
                    'rekkefolge' => ++$avsnittsrekkefolge,
                    'del' => $dokKapittel->del,
                    'side' => $dokAvsnitt->side,
                    'til_side' => $dokAvsnitt->tilSide,
                    'er_liste' => $dokAvsnitt->erListe,
                    'tekst' => $dokAvsnitt->tekst,
                    'tekst_hash' => Tekstrens::hash($dokAvsnitt->tekst),
                ]);
                $resultat->avsnitt++;

                foreach ($plan[$ki][$ai] as $si => $s) {
                    $snr = $si + 1;
                    $gammel = $treff["$ki.$ai.$si"] ?? null;

                    if ($gammel) {
                        Setning::query()->whereKey($gammel->id)->update([
                            'avsnitt_id' => $avsnitt->id,
                            'nr' => $snr,
                            'er_punkt' => $s['er_punkt'],
                        ]);
                        $resultat->beholdt++;
                    } else {
                        $nokkel = $this->ledigNokkel(
                            "{$program->nokkel}-k{$hovedkapittel->nr}-a{$anr}-s{$snr}",
                            $brukteNokler,
                        );
                        Setning::create([
                            'program_id' => $program->id,
                            'avsnitt_id' => $avsnitt->id,
                            'nr' => $snr,
                            'nokkel' => $nokkel,
                            'er_punkt' => $s['er_punkt'],
                            'tekst' => $s['tekst'],
                            'tekst_hash' => $s['hash'],
                        ]);
                        $resultat->nye++;
                    }
                    $resultat->setninger++;
                }
            }
        }

        // 5. Gamle kapitler (og dermed gamle avsnitt) slettes til slutt, når
        //    alle beholdte setninger er flyttet over.
        foreach (array_chunk($gamleKapitler, 500) as $bit) {
            Kapittel::query()->whereIn('id', $bit)->whereNull('overordnet_kapittel_id')->delete();
            Kapittel::query()->whereIn('id', $bit)->delete();
        }

        return $resultat;
    }

    /**
     * En posisjonsbasert ID kan allerede være tatt av en beholdt setning som
     * har flyttet seg. Da legges et løpenummer til. Alle andre setninger i
     * programmet er slettet på dette tidspunktet, så $brukte er komplett.
     *
     * @param  array<string, mixed>  $brukte
     */
    private function ledigNokkel(string $onsket, array &$brukte): string
    {
        $nokkel = $onsket;
        $i = 2;
        while (isset($brukte[$nokkel])) {
            $nokkel = $onsket.'-n'.$i++;
        }
        $brukte[$nokkel] = true;

        return $nokkel;
    }
}
