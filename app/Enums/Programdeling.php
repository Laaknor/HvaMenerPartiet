<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Om et kombinert dokument leses som to programmer (prinsippdel og
 * handlingsdel) eller som ett samlet program.
 */
enum Programdeling: string implements HasLabel
{
    /** Delt bare når minst ett kapittel er merket som prinsippdel. */
    case Automatisk = 'automatisk';
    case Delt = 'delt';
    case Samlet = 'samlet';

    public function etikett(): string
    {
        return match ($this) {
            self::Automatisk => 'Automatisk (delt bare når en prinsippdel er merket)',
            self::Delt => 'Delt i prinsippdel og handlingsdel',
            self::Samlet => 'Ett samlet program',
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }
}
