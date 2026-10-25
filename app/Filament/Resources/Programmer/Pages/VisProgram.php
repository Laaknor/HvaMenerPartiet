<?php

namespace App\Filament\Resources\Programmer\Pages;

use App\Filament\Handlinger\Programhandlinger;
use App\Filament\Resources\Programmer\ProgramResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Gjennomgang av et program: status, struktur, avsnitt og kjøringer. */
class VisProgram extends ViewRecord
{
    protected static string $resource = ProgramResource::class;

    public function getTitle(): string
    {
        return $this->record->parti->forkortelse.': '.$this->record->tittel;
    }

    protected function getHeaderActions(): array
    {
        return [
            Programhandlinger::godkjenn(),
            Programhandlinger::apnePdf(),
            ActionGroup::make([
                Programhandlinger::lesInn(),
                Programhandlinger::digitaliser(),
                Programhandlinger::merkDel(),
                Programhandlinger::embeddings(),
                Programhandlinger::klassifiser(),
                EditAction::make(),
            ]),
        ];
    }
}
