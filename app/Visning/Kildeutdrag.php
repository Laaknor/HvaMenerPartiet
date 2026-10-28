<?php

namespace App\Visning;

use App\Analyse\Kildelenke;
use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Models\Program;
use App\Models\Standpunkt;

/** Det ordrette utdraget for ett standpunkt, med lenker til kilden. */
final readonly class Kildeutdrag
{
    public function __construct(
        public Standpunkt $standpunkt,
        public Program $program,
        public string $utdrag,
        /** Lenke til de siterte setningene i det digitaliserte programmet. */
        public ?string $lenke,
        public ?int $side,
        /** Originalen (PDF) på riktig side, som reserve. */
        public ?string $pdfLenke,
    ) {}

    public static function fra(Standpunkt $standpunkt): self
    {
        $program = $standpunkt->program;
        $setninger = $standpunkt->utdragssetninger();
        $side = Kildelenke::side($setninger);

        // Programsidene viser bare godkjente programmer.
        $lenke = $program->digitalisering_status === Digitaliseringsstatus::Godkjent ? Kildelenke::for($setninger) : null;

        $pdf = null;
        if ($program->kilde_url) {
            $pdf = $program->kilde_url.($side && ! str_contains($program->kilde_url, '#') ? "#page={$side}" : '');
        }

        return new self($standpunkt, $program, $standpunkt->utdrag(), $lenke, $side, $pdf);
    }

    /**
     * Første setning av utdraget, til den korte raden på temasiden. Er den
     * lengre enn $maks tegn, kuttes den ved et ordskille og får «…». Hele
     * utdraget vises når raden åpnes.
     */
    public function kort(int $maks = 160): string
    {
        $forste = trim((string) $this->standpunkt->utdragssetninger()->first()?->tekst);
        if ($forste === '') {
            return '';
        }

        $kuttet = $this->standpunkt->utdragssetninger()->count() > 1;
        if (mb_strlen($forste) > $maks) {
            $forste = rtrim(preg_replace('/\s+\S*$/u', '', mb_substr($forste, 0, $maks)), ' ,;:–-');
            $kuttet = true;
        }

        return $kuttet ? $forste.' …' : $forste;
    }

    /** Er det korte utdraget hele utdraget? */
    public function erKort(): bool
    {
        return $this->kort() === $this->utdrag;
    }

    /** «Arbeidsprogram 2025–2029» eller «Prinsipprogram (vedtatt 2019)». */
    public function kildenavn(): string
    {
        $tittel = $this->program->tittel;

        // Et kombinert dokument som er delt, har ett navn, men to deler.
        if ($this->program->erDelt()) {
            $tittel .= $this->standpunkt->kildetype === Kildetype::Prinsipprogram ? ', prinsippdelen' : ', handlingsdelen';
        }

        return $tittel;
    }

    /** Navn på Matomo-hendelsen: «Parti program år s. side». */
    public function sporingsnavn(): string
    {
        $ar = $this->program->vedtatt_ar ? " {$this->program->vedtatt_ar}" : '';

        return "{$this->program->parti->navn} {$this->standpunkt->kildetype->etikett()}{$ar}".($this->side ? " s. {$this->side}" : '');
    }
}
