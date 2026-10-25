<?php

namespace App\Filament\Resources\Prosesskjoringer\Pages;

use App\Filament\Resources\Prosesskjoringer\ProsesskjoringResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListProsesskjoringer extends ListRecords
{
    protected static string $resource = ProsesskjoringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('horizon')->label('Åpne Horizon')->url(url(config('horizon.path', 'horizon')), shouldOpenInNewTab: true)->color('gray'),
        ];
    }
}
