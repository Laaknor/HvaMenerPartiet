<?php

namespace App\Filament\Resources\Programmer\RelationManagers;

use App\Filament\Resources\Prosesskjoringer\ProsesskjoringResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class KjoringerRelationManager extends RelationManager
{
    protected static string $relationship = 'prosesskjoringer';

    protected static ?string $title = 'Kjøringer';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return ProsesskjoringResource::table($table);
    }
}
