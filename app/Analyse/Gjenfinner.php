<?php

namespace App\Analyse;

use App\Enums\Kildetype;
use App\Enums\Programdel;
use App\Models\Program;
use App\Models\Tekstbit;
use App\Models\Tema;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Finner de mest relevante tekstbitene i ett program for ett tema, med
 * likhetssøk på embeddings kombinert med søk på navn og synonymer.
 * Biter som bare treffer et forvekslingsord (et nærliggende begrep som
 * ikke er temaet, som «atomvåpen» for atomkraft), regnes ikke som treff.
 * Likhet regnes i PHP; med noen tusen biter per parti trengs ingen
 * vektordatabase.
 */
class Gjenfinner
{
    /** @return list<Treff> sortert etter poeng, best først */
    public function finn(Tema $tema, Program $program, Kildetype $kildetype): array
    {
        if (! $tema->embedding) {
            throw new RuntimeException("Temaet «{$tema->navn}» mangler embedding.");
        }

        $temavektor = Vektor::pakkUt($tema->embedding);
        $terskel = (float) config('hvamenerpartiet.analyse.terskel');
        $antall = (int) config('hvamenerpartiet.analyse.antall_biter');
        $monstre = $this->sokemonstre($tema->sokeord());
        $forvekslinger = $this->sokemonstre($tema->forvekslingsord());

        $biter = $this->biter($program, $kildetype);
        if ($biter->isNotEmpty() && $biter->contains(fn (Tekstbit $b) => $b->embedding === null)) {
            throw new RuntimeException("Programmet {$program->nokkel} mangler embeddings. Kjør program:embeddings først.");
        }

        $treff = [];
        foreach ($biter as $bit) {
            $tekst = $bit->overskriftssti."\n".$bit->tekst;
            $likhet = Vektor::likhet($temavektor, Vektor::pakkUt($bit->embedding));
            $nokkelord = $this->harNokkelord($tekst, $monstre);

            // Likheten kommer da trolig fra det nærliggende begrepet, ikke fra temaet.
            if (! $nokkelord && $this->harNokkelord($tekst, $forvekslinger)) {
                continue;
            }

            if ($likhet >= $terskel || $nokkelord) {
                $treff[] = new Treff($bit, $likhet, $nokkelord);
            }
        }

        usort($treff, fn (Treff $a, Treff $b) => $b->poeng() <=> $a->poeng());

        return array_slice($treff, 0, $antall);
    }

    /** @return Collection<int, Tekstbit> */
    private function biter(Program $program, Kildetype $kildetype)
    {
        return Tekstbit::query()
            ->where('program_id', $program->id)
            ->when($program->erDelt(), function ($q) use ($kildetype) {
                // Umerkede avsnitt i et kombinert dokument regnes som handlingsdel.
                $kildetype->programdel() === Programdel::Handling
                    ? $q->where(fn ($q) => $q->where('del', Programdel::Handling)->orWhereNull('del'))
                    : $q->where('del', Programdel::Prinsipp);
            })
            ->get();
    }

    /**
     * Forkortelser i store bokstaver («EU», «NATO») matches med nøyaktig
     * skrivemåte, så «EU» ikke treffer «Europa». Andre ord matches som
     * ordbegynnelse uten hensyn til store og små bokstaver, så «atomkraft»
     * treffer «atomkraftverk».
     *
     * @param  list<string>  $sokeord
     * @return list<string>
     */
    public function sokemonstre(array $sokeord): array
    {
        return array_values(array_map(function (string $ord) {
            $ord = trim($ord);
            $sitert = preg_quote($ord, '/');
            if (mb_strlen($ord) <= 5 && preg_match('/^[\p{Lu}\d]+$/u', $ord)) {
                return '/(?<![\p{L}\d])'.$sitert.'(?!(?!s)\p{L})/u';
            }

            return '/(?<![\p{L}\d])'.$sitert.'/iu';
        }, array_filter($sokeord, fn ($o) => trim($o) !== '')));
    }

    /** @param list<string> $monstre */
    public function harNokkelord(string $tekst, array $monstre): bool
    {
        foreach ($monstre as $monster) {
            if (preg_match($monster, $tekst)) {
                return true;
            }
        }

        return false;
    }
}
