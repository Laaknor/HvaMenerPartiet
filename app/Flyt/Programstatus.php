<?php

namespace App\Flyt;

use App\Enums\Digitaliseringsstatus;
use App\Models\Program;
use App\Models\Prosesskjoring;

/** Hvor et program står i flyten, kort fortalt, for oversikten i admin. */
final class Programstatus
{
    public function __construct(
        public readonly string $tekst,
        public readonly string $farge,
        public readonly ?string $detalj = null,
    ) {}

    /**
     * @param  int  $antallTemaer  aktuelle temaer, for å regne ut hvor mange standpunkter som mangler
     */
    public static function for(Program $program, int $antallTemaer): self
    {
        /** @var Prosesskjoring|null $aktiv */
        $aktiv = $program->relationLoaded('aktiveKjoringer')
            ? $program->aktiveKjoringer->first()
            : $program->prosesskjoringer()->aktive()->first();

        if ($aktiv) {
            return new self($aktiv->steg->etikett(), 'info', $aktiv->status->etikett());
        }

        if (! $program->kildefil && ! $program->filsti) {
            return new self('Mangler PDF i docs/', 'gray', 'Legg filen i docs/Partiprogram og velg den på programmet.');
        }

        if ($program->kildefil && ! $program->kildefilFinnes()) {
            return new self('Finner ikke PDF-en', 'danger', 'docs/Partiprogram/'.$program->kildefil);
        }

        return match ($program->digitalisering_status) {
            Digitaliseringsstatus::Registrert => new self('Klar til innlesing', 'gray'),
            Digitaliseringsstatus::LastetNed => new self('Klar til digitalisering', 'gray'),
            Digitaliseringsstatus::Feilet => new self('Feilet', 'danger', $program->digitalisering_merknad),
            Digitaliseringsstatus::Digitalisert => new self('Venter på gjennomgang', 'warning', $program->digitalisering_merknad),
            Digitaliseringsstatus::Godkjent => self::analyse($program, $antallTemaer),
        };
    }

    private static function analyse(Program $program, int $antallTemaer): self
    {
        $biter = $program->tekstbiter_count ?? $program->tekstbiter()->count();
        $medEmbedding = $program->embeddings_count ?? $program->tekstbiter()->whereNotNull('embedding')->count();
        if ($medEmbedding < $biter) {
            return new self('Venter på embeddings', 'gray', "{$medEmbedding} av {$biter} tekstbiter");
        }

        $forventet = $antallTemaer * count($program->kildetyper());
        $standpunkter = $program->standpunkter_count ?? $program->standpunkter()->count();
        if ($standpunkter < $forventet) {
            return new self('Klassifiseres', 'info', "{$standpunkter} av {$forventet} standpunkter");
        }

        return new self('Analysert', 'success', "{$standpunkter} standpunkter");
    }
}
