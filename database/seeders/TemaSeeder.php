<?php

namespace Database\Seeders;

use App\Enums\Temastatus;
use App\Models\Tema;
use Illuminate\Database\Seeder;

/**
 * Legger inn temaene fra database/data/temaer.php.
 *
 * Kjøres ved hver deploy (db:seed), så den må tåle å kjøres mange ganger:
 *
 * - Nye temaer opprettes som godkjent, og klassifiseres da av prosessflyten.
 * - Status endres aldri for et tema som finnes.
 * - Et felt oppdateres bare når det står slik seederen sist skrev det. Er det
 *   endret i admin siden, beholdes admins versjon, også om datafilen endres.
 *   Temaer fra før seederen husket verdiene, oppdateres som før én gang.
 *
 * Et tema som slettes i admin, opprettes igjen ved neste deploy så lenge det
 * står i datafilen. Fjern det derfra også, eller sett det til «foreslått».
 */
class TemaSeeder extends Seeder
{
    /** Feltene datafilen styrer. Status, slug og embedding styres ikke herfra. */
    public const FELTER = ['navn', 'beskrivelse', 'pastand', 'synonymer', 'forvekslinger'];

    public function run(): void
    {
        $this->leggInn(require database_path('data/temaer.php'));
    }

    /** @param list<array<string, mixed>> $temaer */
    public function leggInn(array $temaer): void
    {
        foreach ($temaer as $data) {
            $tema = Tema::query()->firstOrNew(['slug' => $data['slug']]);
            $forrige = $tema->exists ? $tema->seedverdier : null;

            $ny = [];
            foreach (self::FELTER as $felt) {
                $ny[$felt] = $data[$felt] ?? null;

                $uendret = $forrige === null || self::lik($tema->getAttribute($felt), $forrige[$felt] ?? null);
                if (! $tema->exists || $uendret) {
                    $tema->setAttribute($felt, $ny[$felt]);
                }
            }

            if (! $tema->exists) {
                $tema->status = Temastatus::Godkjent;
            }
            $tema->seedverdier = $ny;

            // Endret tekst nullstiller embeddingen i modellen (Tema::booted).
            $tema->save();
        }
    }

    /** Tom liste og null regnes som like, så et tomt felt i admin ikke telles som en endring. */
    private static function lik(mixed $a, mixed $b): bool
    {
        $a = $a === [] || $a === '' ? null : $a;
        $b = $b === [] || $b === '' ? null : $b;

        return $a === $b;
    }
}
