<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Prosesskjoringer\ProsesskjoringResource;
use App\Models\Prosesskjoring;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** De siste kjøringene, med de som kjører nå øverst. */
class AktiveKjoringer extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Siste kjøringer';

    public function table(Table $table): Table
    {
        return ProsesskjoringResource::table($table)
            ->query(Prosesskjoring::query()
                ->orderByRaw("case status when 'kjorer' then 0 when 'i_ko' then 1 else 2 end")
                ->orderByDesc('id'))
            ->defaultSort(null)
            ->filters([])
            ->paginated([10, 25, 50]);
    }
}
