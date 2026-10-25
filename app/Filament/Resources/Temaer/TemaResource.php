<?php

namespace App\Filament\Resources\Temaer;

use App\Enums\Temastatus;
use App\Filament\Resources\Temaer\Pages\ListTemaer;
use App\Filament\Resources\Temaer\Pages\NyttTema;
use App\Filament\Resources\Temaer\Pages\RedigerTema;
use App\Flyt\Prosessflyt;
use App\Models\Program;
use App\Models\Tema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class TemaResource extends Resource
{
    protected static ?string $model = Tema::class;

    protected static ?string $modelLabel = 'tema';

    protected static ?string $pluralModelLabel = 'temaer';

    protected static ?string $slug = 'temaer';

    protected static ?string $recordTitleAttribute = 'navn';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Prosessflyt';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('navn')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, ?Tema $record) => $record ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('pastand')
                ->label('Påstand')
                ->helperText('Nøytral påstand standpunktet måles mot, for eksempel «Norge bør være medlem av NATO.»')
                ->required()->maxLength(500)->columnSpanFull(),
            Textarea::make('beskrivelse')->rows(2)->columnSpanFull(),
            TagsInput::make('synonymer')->helperText('Brukes i nøkkelordsøket i programtekstene.')->columnSpanFull(),
            Select::make('status')->options(Temastatus::class)->default(Temastatus::Foreslatt)->required()
                ->helperText('Godkjente temaer klassifiseres av seg selv mot alle godkjente programmer.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('standpunkter'))
            ->defaultSort('navn')
            ->columns([
                TextColumn::make('navn')->searchable()->sortable(),
                TextColumn::make('pastand')->label('Påstand')->wrap()->limit(80),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('standpunkter_count')->label('Standpunkter')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Temastatus::class),
            ])
            ->recordActions([
                Action::make('godkjenn')
                    ->label('Godkjenn')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Tema $record) => $record->status === Temastatus::Foreslatt)
                    ->action(fn (Tema $record) => $record->update(['status' => Temastatus::Godkjent])),
                ActionGroup::make([
                    Action::make('klassifiser')
                        ->label('Klassifiser mot alle programmer')
                        ->icon(Heroicon::OutlinedScale)
                        ->visible(fn (Tema $record) => $record->status !== Temastatus::Foreslatt)
                        ->requiresConfirmation()
                        ->modalDescription('Kjører klassifiseringen på nytt for alle godkjente programmer. Standpunkter som endrer seg, sendes tilbake til gjennomgang.')
                        ->action(fn (Tema $record) => self::klassifiser([$record])),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('godkjennValgte')
                    ->label('Godkjenn valgte')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => $records->where('status', Temastatus::Foreslatt)->each->update(['status' => Temastatus::Godkjent]))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /** @param  iterable<Tema>  $temaer */
    private static function klassifiser(iterable $temaer): void
    {
        $programmer = Program::query()->gjeldende()->godkjent()->get();
        $antall = app(Prosessflyt::class)->klassifiser($temaer, $programmer, bruker: auth()->user());

        Notification::make()->title("La {$antall} klassifiseringer i kø")->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemaer::route('/'),
            'create' => NyttTema::route('/nytt'),
            'edit' => RedigerTema::route('/{record}/rediger'),
        ];
    }
}
