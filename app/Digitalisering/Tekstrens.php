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

    /**
     * Fjerner henvisninger til sidetall i den trykte utgaven, som «(se side 12)»,
     * «jf. s. 4–5» og «Les mer om dette på side 30.». Sidetallene gir ingen
     * mening på nettsiden.
     */
    public static function fjernSidehenvisninger(string $tekst): string
    {
        $side = '(?:sidene|side|s\.)\s*\d{1,3}(?:\s*(?:[-–]|og|til)\s*\d{1,3})?';

        // Hele setninger som bare viser videre: «Les mer om dette på side 30.»
        $tekst = preg_replace('/(?<=^|[.!?:]\s)\s*(?:les|se) (?:mer|også)\b[^.!?\n]{0,80}?\b'.$side.'\s*[.!]?/iu', '', $tekst);
        // I parentes: «(se side 12)», «(side 12)», «(jf. s. 4)».
        $tekst = preg_replace('/\s*\((?:(?:se|jf\.?|jamfør|les mer på)\s+)?(?:også\s+)?'.$side.'\)/iu', '', $tekst);
        // Uten parentes: «…, se side 12.» og «… (jf. side 4).».
        $tekst = preg_replace('/,?\s+(?:se|jf\.?|jamfør)\s+(?:også\s+)?'.$side.'(?=[.!?,;:)\s]|$)/iu', '', $tekst);

        return trim(preg_replace('/[ \t]+/u', ' ', $tekst));
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
