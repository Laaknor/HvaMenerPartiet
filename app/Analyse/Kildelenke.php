<?php

namespace App\Analyse;

use App\Models\Setning;
use Illuminate\Support\Collection;

/**
 * Lenker til siterte setninger i det digitaliserte programmet, for eksempel
 * /program/sv/arbeidsprogram-2025/kap-4#a12-s3-s5. Lenken åpner kapittelet,
 * ruller til avsnitt 12 og markerer setning 3 til 5.
 */
final class Kildelenke
{
    /** @param Collection<int, Setning> $setninger */
    public static function for(Collection $setninger): ?string
    {
        $forste = $setninger->first();
        if (! $forste) {
            return null;
        }

        $setninger->loadMissing('avsnitt.hovedkapittel', 'program.parti');
        $avsnitt = $forste->avsnitt;
        $program = $forste->program;

        // Markeringen dekker setningene i samme avsnitt som den første.
        $nr = $setninger->filter(fn (Setning $s) => $s->avsnitt_id === $avsnitt->id)->pluck('nr');
        $fra = $nr->min();
        $til = $nr->max();
        $anker = "a{$avsnitt->nr}-s{$fra}".($til !== $fra ? "-s{$til}" : '');

        return "/program/{$program->parti->slug}/{$program->slug}/{$avsnitt->hovedkapittel->slug}#{$anker}";
    }

    /** Sidetall i originalen, med lenke til PDF-en som reserve. */
    public static function side(Collection $setninger): ?int
    {
        return $setninger->first()?->avsnitt?->side;
    }
}
