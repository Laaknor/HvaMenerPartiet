<?php

namespace App\Filament\Resources\Temaforslag;

use App\Filament\Resources\Temaer\TemaResource;
use App\Filament\Resources\Temaforslag\Pages\ListTemaforslag;
use App\Models\Temaforslag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Temaer besøkende har foreslått etter søk uten treff. De mest etterspurte står øverst. */
class TemaforslagResource extends Resource
{
    protected static ?string $model = Temaforslag::class;

    protected static ?string $modelLabel = 'temaforslag';

    protected static ?string $pluralModelLabel = 'temaforslag';

    protected static ?string $slug = 'temaforslag';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'Fra besøkende';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $antall = Temaforslag::where('behandlet', false)->count();

        return $antall ? (string) $antall : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('antall', 'desc')
            ->columns([
                TextColumn::make('sokeord')->label('Forslag')->searchable(),
                TextColumn::make('antall')->label('Antall ganger')->sortable(),
                TextColumn::make('sist_foreslatt')->label('Sist foreslått')->dateTime('d.m.Y H:i')->sortable(),
                IconColumn::make('behandlet')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('behandlet')->default(false),
            ])
            ->recordActions([
                Action::make('lagTema')
                    ->label('Lag tema')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(TemaResource::getUrl('create')),
                Action::make('behandlet')
                    ->label(fn (Temaforslag $record) => $record->behandlet ? 'Åpne igjen' : 'Merk som behandlet')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Temaforslag $record) => $record->update(['behandlet' => ! $record->behandlet])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemaforslag::route('/'),
        ];
    }
}
