<?php

namespace App\Filament\Resources\Temaer\Pages;

use App\Filament\Resources\Temaer\TemaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemaer extends ListRecords
{
    protected static string $resource = TemaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
