<?php

namespace App\Filament\Resources\Feilsokingssaker;

use App\Filament\Resources\Feilsokingssaker\Pages\ListFeilsokingssaker;
use App\Models\Feilsokingssak;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Saker sendt til feilsøking, med lenke til issuen og status. */
class FeilsokingssakResource extends Resource
{
    protected static ?string $model = Feilsokingssak::class;

    protected static ?string $modelLabel = 'feilsøkingssak';

    protected static ?string $pluralModelLabel = 'feilsøkingssaker';

    protected static ?string $slug = 'feilsoking';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'Prosessflyt';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $antall = Feilsokingssak::where('lost', false)->count();

        return $antall ? (string) $antall : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('oppretter'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sendt')->dateTime('d.m. H:i')->sortable(),
                TextColumn::make('tittel')->state(fn (Feilsokingssak $record) => $record->tittel())->wrap(),
                TextColumn::make('notat')->wrap()->limit(160)->placeholder('–'),
                TextColumn::make('github_issue')->label('Issue')->formatStateUsing(fn ($state) => "#{$state}")
                    ->url(fn (Feilsokingssak $record) => $record->githubUrl(), shouldOpenInNewTab: true)
                    ->placeholder(fn (Feilsokingssak $record) => $record->github_feil ? 'Feilet' : '–')
                    ->tooltip(fn (Feilsokingssak $record) => $record->github_feil),
                TextColumn::make('sist_hentet')->label('Hentet')->since()->placeholder('Ikke ennå'),
                TextColumn::make('referanse')->copyable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('lost')->label('Løst')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('lost')->label('Løst')->default(false),
            ])
            ->recordActions([
                Action::make('lost')
                    ->label(fn (Feilsokingssak $record) => $record->lost ? 'Åpne igjen' : 'Merk som løst')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Feilsokingssak $record) => $record->update(['lost' => ! $record->lost])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeilsokingssaker::route('/'),
        ];
    }
}
