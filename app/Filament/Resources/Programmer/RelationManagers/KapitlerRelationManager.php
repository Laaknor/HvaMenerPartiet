<?php

namespace App\Filament\Resources\Programmer\RelationManagers;

use App\Digitalisering\Delmerking;
use App\Enums\Programdel;
use App\Models\Kapittel;
use App\Models\Program;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Kapittelstrukturen, for gjennomgang av oppsplittingen. */
class KapitlerRelationManager extends RelationManager
{
    protected static string $relationship = 'kapitler';

    protected static ?string $title = 'Kapitler';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Program $program */
        $program = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('avsnitt'))
            ->defaultSort('rekkefolge')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                TextColumn::make('slug')->label('ID'),
                TextColumn::make('tittel')
                    ->formatStateUsing(fn (Kapittel $record, string $state) => str_repeat('— ', max(0, $record->niva - 1)).$state)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('niva')->label('Nivå'),
                TextColumn::make('side'),
                TextColumn::make('avsnitt_count')->label('Avsnitt'),
                TextColumn::make('del')->badge()->placeholder($program->erKombinert() ? 'Umerket' : '–')->visible($program->erKombinert()),
            ])
            ->filters([
                SelectFilter::make('niva')->label('Nivå')->options([1 => 'Hovedkapitler', 2 => 'Nivå 2', 3 => 'Nivå 3']),
                SelectFilter::make('del')->options(Programdel::class)->visible($program->erKombinert()),
            ])
            ->toolbarActions($program->erKombinert() ? [
                BulkAction::make('merkDel')
                    ->label('Merk som prinsipp- eller handlingsdel')
                    ->schema([Radio::make('del')->options(Programdel::class)->required()])
                    ->action(function (Collection $records, array $data) use ($program) {
                        $del = $data['del'] instanceof Programdel ? $data['del'] : Programdel::from($data['del']);
                        $antall = app(Delmerking::class)->merk($program, $del, $records->pluck('slug')->all());
                        Notification::make()->title("Merket {$antall} kapitler som {$del->etikett()}")->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ] : []);
    }
}
