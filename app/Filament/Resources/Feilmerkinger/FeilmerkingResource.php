<?php

namespace App\Filament\Resources\Feilmerkinger;

use App\Filament\Resources\Feilmerkinger\Pages\ListFeilmerkinger;
use App\Models\Feilmerking;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Alt som er merket som feil, med begrunnelse. Eksporteres til feilsøking. */
class FeilmerkingResource extends Resource
{
    protected static ?string $model = Feilmerking::class;

    protected static ?string $modelLabel = 'feil';

    protected static ?string $pluralModelLabel = 'feil';

    protected static ?string $navigationLabel = 'Feil';

    protected static ?string $slug = 'feil';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'Prosessflyt';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $antall = Feilmerking::query()->apne()->count();

        return $antall ? (string) $antall : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('merker'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Merket')->dateTime('d.m. H:i')->sortable(),
                TextColumn::make('tittel')->label('Gjelder')->state(fn (Feilmerking $record) => $record->tittel())->wrap(),
                TextColumn::make('begrunnelse')->label('Hvorfor feil')->wrap()->limit(200),
                TextColumn::make('merker.name')->label('Merket av')->placeholder('–')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('eksportert')->since()->placeholder('Ikke ennå'),
                IconColumn::make('lost')->label('Løst')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('lost')->label('Løst')->default(false),
                TernaryFilter::make('eksportert')->nullable(),
            ])
            ->recordActions([
                Action::make('lost')
                    ->label(fn (Feilmerking $record) => $record->lost ? 'Åpne igjen' : 'Merk som løst')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Feilmerking $record) => $record->update(['lost' => ! $record->lost])),
            ])
            ->toolbarActions([
                BulkAction::make('merkLost')
                    ->label('Merk valgte som løst')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => Feilmerking::query()->whereKey($records->modelKeys())->update(['lost' => true]))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeilmerkinger::route('/'),
        ];
    }
}
