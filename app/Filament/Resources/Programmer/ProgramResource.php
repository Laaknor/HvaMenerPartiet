<?php

namespace App\Filament\Resources\Programmer;

use App\Enums\Digitaliseringsstatus;
use App\Enums\Programtype;
use App\Filament\Handlinger\Programhandlinger;
use App\Filament\Resources\Programmer\Pages\ListProgrammer;
use App\Filament\Resources\Programmer\Pages\RedigerProgram;
use App\Filament\Resources\Programmer\Pages\VisProgram;
use App\Filament\Resources\Programmer\RelationManagers\AvsnittRelationManager;
use App\Filament\Resources\Programmer\RelationManagers\KapitlerRelationManager;
use App\Filament\Resources\Programmer\RelationManagers\KjoringerRelationManager;
use App\Flyt\Programmappe;
use App\Flyt\Programstatus;
use App\Flyt\Prosessplanlegger;
use App\Models\Program;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static ?string $modelLabel = 'program';

    protected static ?string $pluralModelLabel = 'programmer';

    protected static ?string $slug = 'programmer';

    protected static ?string $recordTitleAttribute = 'nokkel';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Prosessflyt';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $antall = Program::query()->gjeldende()->where('digitalisering_status', Digitaliseringsstatus::Digitalisert)->count();

        return $antall ? (string) $antall : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Venter på gjennomgang';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program')->columns(2)->schema([
                TextInput::make('tittel')->required()->maxLength(255),
                Select::make('type')->options(Programtype::class)->disabled(),
                TextInput::make('periode')->maxLength(20),
                TextInput::make('vedtatt_ar')->label('Vedtatt år')->numeric()->minValue(1900)->maxValue(2100),
                Toggle::make('gjeldende'),
            ]),
            Section::make('Kilde')->schema([
                Select::make('kildefil')
                    ->label('PDF i docs/Partiprogram')
                    ->helperText('Legg PDF-en i docs/Partiprogram i repoet, så dukker den opp her. Filen leses inn av seg selv når den er ny eller endret.')
                    ->options(fn () => collect(app(Programmappe::class)->filer())->mapWithKeys(fn ($f) => [$f => $f]))
                    ->searchable(),
                TextInput::make('kilde_url')->label('Hentet fra (URL)')->url()->maxLength(2000),
                TextInput::make('kilde_side')->label('Partiets programside')->url()->maxLength(2000),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Flyt')->columns(3)->schema([
                TextEntry::make('flyt')
                    ->label('Står nå')
                    ->badge()
                    ->state(fn (Program $record) => self::status($record)->tekst)
                    ->color(fn (Program $record) => self::status($record)->farge),
                TextEntry::make('digitalisering_status')->label('Digitalisering')->badge(),
                TextEntry::make('type')->badge(),
                TextEntry::make('digitalisering_merknad')->label('Merknad')->placeholder('Ingen')->columnSpanFull(),
            ]),
            Section::make('Kilde')->columns(3)->collapsible()->schema([
                TextEntry::make('kildefil')->label('docs/Partiprogram')->placeholder('Mangler')->columnSpan(2),
                TextEntry::make('lastet_ned')->label('Lest inn')->dateTime('d.m.Y H:i')->placeholder('–'),
                TextEntry::make('filhash')->label('SHA-256')->formatStateUsing(fn (?string $state) => $state ? substr($state, 0, 16).'…' : null)->placeholder('–'),
                TextEntry::make('antall_sider')->label('Sider')->placeholder('–'),
                TextEntry::make('digitalisert')->dateTime('d.m.Y H:i')->placeholder('–'),
                TextEntry::make('godkjent')->label('Struktur godkjent')->dateTime('d.m.Y H:i')->placeholder('–'),
                TextEntry::make('kilde_url')->label('Hentet fra')->url(fn (?string $state) => $state)->openUrlInNewTab()->limit(80)->placeholder('–')->columnSpan(2),
            ]),
            Section::make('Innhold')->columns(5)->schema([
                TextEntry::make('kapitler_count')->label('Kapitler')->state(fn (Program $record) => $record->kapitler()->count()),
                TextEntry::make('avsnitt_count')->label('Avsnitt')->state(fn (Program $record) => $record->avsnitt()->count()),
                TextEntry::make('setninger_count')->label('Setninger')->state(fn (Program $record) => $record->setninger()->count()),
                TextEntry::make('embeddings')->label('Embeddings')->state(fn (Program $record) => $record->tekstbiter()->whereNotNull('embedding')->count().' / '.$record->tekstbiter()->count()),
                TextEntry::make('standpunkter_count')->label('Standpunkter')->state(fn (Program $record) => $record->standpunkter()->count()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $antallTemaer = app(Prosessplanlegger::class)->aktuelleTemaer()->count();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['parti', 'aktiveKjoringer'])->withCount([
                'kapitler', 'setninger', 'tekstbiter', 'standpunkter',
                'tekstbiter as embeddings_count' => fn (Builder $q) => $q->whereNotNull('embedding'),
            ]))
            ->defaultSort('parti_id')
            ->poll('10s')
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('parti.forkortelse')->label('Parti')->sortable(),
                TextColumn::make('tittel')->description(fn (Program $record) => $record->nokkel)->searchable(['tittel', 'nokkel']),
                TextColumn::make('type')->badge()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('kildefil')
                    ->label('PDF')
                    ->state(fn (Program $record) => $record->kildefilFinnes())
                    ->boolean()
                    ->tooltip(fn (Program $record) => $record->kildefil ?: 'Ingen fil i docs/Partiprogram'),
                TextColumn::make('flyt')
                    ->label('Står nå')
                    ->badge()
                    ->state(fn (Program $record) => Programstatus::for($record, $antallTemaer)->tekst)
                    ->color(fn (Program $record) => Programstatus::for($record, $antallTemaer)->farge)
                    ->description(fn (Program $record) => str(Programstatus::for($record, $antallTemaer)->detalj)->limit(45)->toString() ?: null)
                    ->tooltip(fn (Program $record) => Programstatus::for($record, $antallTemaer)->detalj),
                TextColumn::make('setninger_count')->label('Setninger')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('embeddings_count')
                    ->label('Embeddings')
                    ->formatStateUsing(fn (Program $record) => "{$record->embeddings_count} / {$record->tekstbiter_count}")
                    ->toggleable(),
                TextColumn::make('standpunkter_count')->label('Standpunkter')->numeric(),
            ])
            ->filters([
                SelectFilter::make('digitalisering_status')->label('Digitalisering')->options(Digitaliseringsstatus::class),
                SelectFilter::make('parti')->relationship('parti', 'forkortelse'),
            ])
            ->recordActions([
                ViewAction::make()->label('Gå gjennom'),
                ActionGroup::make([
                    ...Programhandlinger::alle(),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function status(Program $program): Programstatus
    {
        return Programstatus::for($program, app(Prosessplanlegger::class)->aktuelleTemaer()->count());
    }

    public static function getRelations(): array
    {
        return [
            KapitlerRelationManager::class,
            AvsnittRelationManager::class,
            KjoringerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgrammer::route('/'),
            'view' => VisProgram::route('/{record}'),
            'edit' => RedigerProgram::route('/{record}/rediger'),
        ];
    }
}
