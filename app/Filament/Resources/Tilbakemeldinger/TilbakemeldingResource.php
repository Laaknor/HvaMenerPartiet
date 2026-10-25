<?php

namespace App\Filament\Resources\Tilbakemeldinger;

use App\Filament\Handlinger\Feilsokingshandling;
use App\Filament\Resources\Standpunkter\StandpunktResource;
use App\Filament\Resources\Tilbakemeldinger\Pages\ListTilbakemeldinger;
use App\Models\Tilbakemelding;
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

/** «Meld fra om feil» fra partikortene på temasidene. */
class TilbakemeldingResource extends Resource
{
    protected static ?string $model = Tilbakemelding::class;

    protected static ?string $modelLabel = 'tilbakemelding';

    protected static ?string $pluralModelLabel = 'tilbakemeldinger';

    protected static ?string $slug = 'tilbakemeldinger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Fra besøkende';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $antall = Tilbakemelding::where('behandlet', false)->count();

        return $antall ? (string) $antall : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tema', 'parti']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Mottatt')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('tema.navn')->label('Tema'),
                TextColumn::make('parti.navn')->label('Parti'),
                TextColumn::make('tekst')->wrap()->limit(300),
                IconColumn::make('behandlet')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('behandlet')->default(false),
            ])
            ->recordActions([
                Action::make('standpunkt')
                    ->label('Åpne standpunktet')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Tilbakemelding $record) => $record->standpunkt_id !== null)
                    ->url(fn (Tilbakemelding $record) => StandpunktResource::getUrl('view', ['record' => $record->standpunkt_id])),
                Action::make('temaside')
                    ->label('Temasiden')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Tilbakemelding $record) => route('tema', $record->tema).'#parti-'.$record->parti_id, shouldOpenInNewTab: true),
                Action::make('behandlet')
                    ->label(fn (Tilbakemelding $record) => $record->behandlet ? 'Åpne igjen' : 'Merk som behandlet')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Tilbakemelding $record) => $record->update(['behandlet' => ! $record->behandlet])),
                Feilsokingshandling::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTilbakemeldinger::route('/'),
        ];
    }
}
