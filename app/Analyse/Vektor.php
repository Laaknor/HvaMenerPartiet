<?php

namespace App\Analyse;

/**
 * Embeddings lagres som binære float32-vektorer (BLOB) og normaliseres ved
 * lagring, slik at cosinuslikhet blir et vanlig prikkprodukt.
 */
final class Vektor
{
    /** @param list<float> $vektor */
    public static function pakk(array $vektor): string
    {
        return pack('g*', ...self::normaliser($vektor));
    }

    /** @return list<float> */
    public static function pakkUt(string $binar): array
    {
        return array_values(unpack('g*', $binar));
    }

    /**
     * @param  list<float>  $vektor
     * @return list<float>
     */
    public static function normaliser(array $vektor): array
    {
        $lengde = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vektor)));
        if ($lengde == 0.0) {
            return $vektor;
        }

        return array_map(fn ($x) => $x / $lengde, $vektor);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function likhet(array $a, array $b): float
    {
        $sum = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }
}
