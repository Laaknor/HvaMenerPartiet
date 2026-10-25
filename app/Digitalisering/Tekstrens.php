<?php

namespace App\Digitalisering;

use Normalizer;

/** Små tekstoperasjoner som brukes under digitaliseringen. */
final class Tekstrens
{
    private const LIGATURER = ['ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬀ' => 'ff', 'ﬃ' => 'ffi', 'ﬄ' => 'ffl', 'ﬅ' => 'st', 'ﬆ' => 'st'];

    /** Ord som tyder på at bindestreken er en ekte bindestrek («helse- og omsorg»). */
    private const ETTER_BINDESTREK = ['og', 'eller', 'til', 'samt', 'enn', 'som'];

    public static function normaliser(string $tekst): string
    {
        $tekst = strtr($tekst, self::LIGATURER);
        if (class_exists(Normalizer::class)) {
            $tekst = Normalizer::normalize($tekst, Normalizer::FORM_C) ?: $tekst;
        }
        $tekst = str_replace(["\u{00A0}", "\u{2009}", "\u{202F}"], ' ', $tekst);

        return trim(preg_replace('/[ \t]+/u', ' ', $tekst));
    }

    /**
     * Slår sammen to linjer. Orddeling over linjeskift («ut-» + «vide») slås
     * sammen, men ikke ekte bindestreker som i «helse- og omsorgstjenester».
     */
    public static function skjot(string $forste, string $andre): string
    {
        $forste = rtrim($forste);
        $andre = ltrim($andre);

        if ($forste === '') {
            return $andre;
        }
        if ($andre === '') {
            return $forste;
        }

        // Myk bindestrek fjernes alltid.
        if (str_ends_with($forste, "\u{00AD}")) {
            return mb_substr($forste, 0, -1).$andre;
        }

        if (preg_match('/\p{L}-$/u', $forste) && preg_match('/^(\p{Ll}+)/u', $andre, $m)) {
            if (! in_array($m[1], self::ETTER_BINDESTREK, true)) {
                return mb_substr($forste, 0, -1).$andre;
            }
        }

        return $forste.' '.$andre;
    }

    public static function hash(string $tekst): string
    {
        return sha1(self::normaliser($tekst));
    }

    /** Slutter teksten som en hel setning eller en innledning til en liste? */
    public static function erAvsluttet(string $tekst): bool
    {
        return (bool) preg_match('/[.!?:…]["»”’)\]]*$/u', rtrim($tekst));
    }

    public static function begynnerMedSmaBokstav(string $tekst): bool
    {
        return (bool) preg_match('/^\p{Ll}/u', ltrim($tekst));
    }
}
