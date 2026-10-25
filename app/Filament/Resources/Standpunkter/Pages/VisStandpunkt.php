<?php

namespace App\Filament\Resources\Standpunkter\Pages;

use App\Filament\Handlinger\MerkSomFeil;
use App\Filament\Resources\Standpunkter\StandpunktResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class VisStandpunkt extends ViewRecord
{
    protected static string $resource = StandpunktResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StandpunktResource::godkjenn(),
            StandpunktResource::korriger(),
            StandpunktResource::avvis(),
            ActionGroup::make([
                StandpunktResource::klassifiserPaNytt(),
                MerkSomFeil::make(),
            ]),
        ];
    }
}
