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
                    foreach ($linje->getElementsByTagName('word') as $w) {
                        $ord[] = $w->textContent;
                    }
                    $tekst = trim(implode(' ', $ord));
                    if ($tekst === '') {
                        continue;
                    }

                    $linjer[] = new Linje(
                        side: $sidenr,
                        blokk: $blokknr,
                        tekst: $tekst,
                        xMin: (float) $linje->getAttribute('xMin'),
                        yMin: (float) $linje->getAttribute('yMin'),
                        xMax: (float) $linje->getAttribute('xMax'),
                        yMax: (float) $linje->getAttribute('yMax'),
                        sideHoyde: $sideHoyde,
                    );
                }
            }
        }

        return ['sider' => $sidenr, 'linjer' => $linjer];
    }
}
