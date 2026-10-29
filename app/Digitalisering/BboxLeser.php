<?php

namespace App\Digitalisering;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Henter tekst med posisjon fra en PDF med poppler sin pdftotext -bbox-layout.
 */
class BboxLeser
{
    /** @return array{sider: int, linjer: list<Linje>} */
    public function les(string $pdfSti): array
    {
        $resultat = Process::timeout(600)->run([
            config('hvamenerpartiet.digitalisering.pdftotext', 'pdftotext'),
            '-bbox-layout',
            '-enc', 'UTF-8',
            $pdfSti,
            '-',
        ]);

        if (! $resultat->successful()) {
            throw new RuntimeException('pdftotext feilet: '.trim($resultat->errorOutput()));
        }

        return $this->tolk($resultat->output());
    }

    /** @return array{sider: int, linjer: list<Linje>} */
    public function tolk(string $xhtml): array
    {
        // Noen PDF-er (for eksempel MDGs) har kontrolltegn i teksten, som ikke er lov i XML.
        $xhtml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xhtml);

        $dom = new DOMDocument;
        $forrige = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xhtml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($forrige);

        if (! $ok) {
            throw new RuntimeException('Kunne ikke lese utdata fra pdftotext.');
        }

        $linjer = [];
        $sidenr = 0;
        $blokknr = 0;

        foreach ($dom->getElementsByTagName('page') as $side) {
            /** @var DOMElement $side */
            $sidenr++;
            $sideHoyde = (float) $side->getAttribute('height');

            foreach ($side->getElementsByTagName('block') as $blokk) {
                /** @var DOMElement $blokk */
                $blokknr++;

                foreach ($blokk->getElementsByTagName('line') as $linje) {
                    /** @var DOMElement $linje */
                    $ord = [];
                    $hoyder = [];
                    foreach ($linje->getElementsByTagName('word') as $w) {
                        /** @var DOMElement $w */
                        $ord[] = $w->textContent;
                        $hoyder[] = [(float) $w->getAttribute('yMin'), (float) $w->getAttribute('yMax')];
                    }
                    $tekst = trim(implode(' ', $ord));
                    if ($tekst === '') {
                        continue;
                    }

                    $yMin = (float) $linje->getAttribute('yMin');
                    $yMax = (float) $linje->getAttribute('yMax');

                    // Punktmerket («a)», «•», «o») er ofte satt i større skrift enn
                    // teksten. Da gjelder høyden til resten av linjen, ellers ser
                    // listepunktet ut som en overskrift.
                    if (count($ord) > 1 && Tekstrens::erPunktmerke($ord[0]) && $hoyder[1][1] > $hoyder[1][0]) {
                        $resten = array_slice($hoyder, 1);
                        $yMin = min(array_column($resten, 0));
                        $yMax = max(array_column($resten, 1));
                    }

                    $linjer[] = new Linje(
                        side: $sidenr,
                        blokk: $blokknr,
                        tekst: $tekst,
                        xMin: (float) $linje->getAttribute('xMin'),
                        yMin: $yMin,
                        xMax: (float) $linje->getAttribute('xMax'),
                        yMax: $yMax,
                        sideHoyde: $sideHoyde,
                    );
                }
            }
        }

        return ['sider' => $sidenr, 'linjer' => $linjer];
    }
}
