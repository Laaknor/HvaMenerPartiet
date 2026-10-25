<?php

namespace App\Digitalisering;

use App\Enums\Digitaliseringsstatus;
use App\Models\Program;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Leser inn programfilen fra docs/Partiprogram (eller laster den ned) og
 * lagrer en kopi med tidspunkt og SHA-256. Hver versjon som er digitalisert
 * beholdes, slik at hvert utdrag kan spores tilbake.
 */
class Programhenter
{
    /**
     * Leser inn kildefilen fra docs/Partiprogram. Dette er den vanlige veien.
     *
     * @return bool om filen er ny eller endret siden forrige innlesing
     */
    public function lesInn(Program $program): bool
    {
        if (! $program->kildefil) {
            throw new RuntimeException("Programmet {$program->nokkel} har ingen kildefil i docs/Partiprogram.");
        }

        if (! $program->kildefilFinnes()) {
            throw new RuntimeException("Finner ikke docs/Partiprogram/{$program->kildefil}.");
        }

        return $this->importerFil($program, $program->kildefilSti());
    }

    /** Laster ned fra kilde-URL-en. Bare til bruk når filen ikke ligger i docs/. */
    public function hent(Program $program): bool
    {
        if (! $program->kilde_url) {
            throw new RuntimeException("Programmet {$program->nokkel} mangler kilde-URL.");
        }

        $svar = Http::withHeaders(['User-Agent' => 'HvaMenerPartiet/1.0 (+https://github.com/laaknor/hvamenerpartiet)'])
            ->timeout(180)
            ->retry(3, 2000, throw: false)
            ->get($program->kilde_url);

        if (! $svar->successful()) {
            throw new RuntimeException("Nedlasting av {$program->kilde_url} feilet med status {$svar->status()}.");
        }

        $innhold = $svar->body();
        if (! str_starts_with($innhold, '%PDF')) {
            throw new RuntimeException("Filen fra {$program->kilde_url} er ikke en PDF.");
        }

        return $this->lagreFil($program, $innhold);
    }

    /** Importerer en fil som er lastet ned manuelt. */
    public function importerFil(Program $program, string $sti): bool
    {
        if (! is_file($sti)) {
            throw new RuntimeException("Finner ikke filen {$sti}.");
        }

        $innhold = file_get_contents($sti);
        if (! str_starts_with($innhold, '%PDF')) {
            throw new RuntimeException("{$sti} er ikke en PDF.");
        }

        return $this->lagreFil($program, $innhold);
    }

    private function lagreFil(Program $program, string $innhold): bool
    {
        $hash = hash('sha256', $innhold);
        $endret = $hash !== $program->filhash;

        $disk = Storage::disk(config('hvamenerpartiet.digitalisering.disk'));
        $sti = sprintf('programmer/%s/%s-%s-%s.pdf', $program->parti->slug, $program->slug, $program->sprak, substr($hash, 0, 12));
        if (! $disk->exists($sti)) {
            $disk->put($sti, $innhold);
        }

        $program->fill([
            'filsti' => $sti,
            'filhash' => $hash,
            'lastet_ned' => now(),
        ]);

        if ($endret) {
            $program->digitalisering_status = Digitaliseringsstatus::LastetNed;
            $program->digitalisering_merknad = $program->getOriginal('filhash')
                ? 'Ny versjon av filen. Må digitaliseres og kontrolleres på nytt.'
                : null;
        }

        $program->save();

        return $endret;
    }
}
