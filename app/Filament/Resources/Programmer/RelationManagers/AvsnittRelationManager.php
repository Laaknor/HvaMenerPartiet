<?php

namespace App\Filament\Resources\Programmer\RelationManagers;

use App\Models\Avsnitt;
use App\Models\Program;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Avsnittene med tekst, for å kontrollere digitaliseringen mot PDF-en. */
class AvsnittRelationManager extends RelationManager
{
    protected static string $relationship = 'avsnitt';

    protected static ?string $title = 'Avsnitt';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Program $program */
        $program = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['hovedkapittel', 'kapittel'])->withCount('setninger'))
            ->defaultSort('rekkefolge')
            ->columns([
                TextColumn::make('id_i_lenke')
                    ->label('ID')
                    ->state(fn (Avsnitt $record) => $record->hovedkapittel->slug.'#a'.$record->nr),
                TextColumn::make('kapittel.tittel')->label('Kapittel')->limit(40)->tooltip(fn (Avsnitt $record) => $record->kapittel->tittel),
                TextColumn::make('side')->formatStateUsing(fn (Avsnitt $record) => $record->til_side && $record->til_side !== $record->side ? "{$record->side}–{$record->til_side}" : (string) $record->side),
                TextColumn::make('tekst')->wrap()->limit(300)->searchable(),
                IconColumn::make('er_liste')->label('Liste')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('setninger_count')->label('Setninger'),
                TextColumn::make('del')->badge()->visible($program->erKombinert()),
            ])
            ->filters([
                SelectFilter::make('hovedkapittel_id')
                    ->label('Hovedkapittel')
                    ->options(fn () => $program->kapitler()->where('niva', 1)->get()->mapWithKeys(fn ($k) => [$k->id => "{$k->slug}: {$k->tittel}"])),
            ])
            ->recordActions([
                Action::make('setninger')
                    ->label('Setninger')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading(fn (Avsnitt $record) => $record->hovedkapittel->slug.'#a'.$record->nr)
                    ->modalSubmitAction(false)
                    ->schema(fn (Avsnitt $record) => $record->setninger->map(fn ($s) => TextEntry::make('s'.$s->id)
                        ->label($s->nokkel)
                        ->state($s->tekst))->all()),
            ]);
    }
}
