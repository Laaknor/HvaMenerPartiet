<?php

namespace App\Filament\Resources\Prosesskjoringer;

use App\Enums\Kjoringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Utlosning;
use App\Filament\Resources\Prosesskjoringer\Pages\ListProsesskjoringer;
use App\Flyt\Prosessflyt;
use App\Models\Prosesskjoring;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Alle jobber i flyten, med status og feilmelding. */
class ProsesskjoringResource extends Resource
{
    protected static ?string $model = Prosesskjoring::class;

    protected static ?string $modelLabel = 'kjøring';

    protected static ?string $pluralModelLabel = 'kjøringer';

    protected static ?string $slug = 'kjoringer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Prosessflyt';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $antall = Prosesskjoring::query()->aktive()->count();

        return $antall ? (string) $antall : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'I kø eller kjører';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['program', 'tema', 'bruker']))
            ->defaultSort('id', 'desc')
            ->poll('5s')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('steg')->badge(),
                TextColumn::make('mal')->label('Gjelder')->state(fn (Prosesskjoring $record) => $record->beskrivelse()),
                TextColumn::make('status')->badge(),
                TextColumn::make('utlost_av')
                    ->label('Startet av')
                    ->badge()
                    ->formatStateUsing(fn (Prosesskjoring $record) => $record->bruker?->name ?? $record->utlost_av->etikett()),
                TextColumn::make('created_at')->label('Lagt i kø')->dateTime('d.m. H:i:s')->sortable(),
                TextColumn::make('varighet')->state(fn (Prosesskjoring $record) => $record->varighet())->placeholder('–'),
                TextColumn::make('melding')->wrap()->limit(160)->tooltip(fn (Prosesskjoring $record) => $record->melding)->placeholder('–'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Kjoringsstatus::class),
                SelectFilter::make('steg')->options(Prosessteg::class),
                SelectFilter::make('utlost_av')->label('Startet av')->options(Utlosning::class),
            ])
            ->recordActions([
                Action::make('provIgjen')
                    ->label('Prøv igjen')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Prosesskjoring $record) => $record->status === Kjoringsstatus::Feilet && $record->program)
                    ->action(function (Prosesskjoring $record) {
                        app(Prosessflyt::class)->provIgjen($record, auth()->user());
                        Notification::make()->title('Lagt i kø på nytt')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProsesskjoringer::route('/'),
        ];
    }
}
