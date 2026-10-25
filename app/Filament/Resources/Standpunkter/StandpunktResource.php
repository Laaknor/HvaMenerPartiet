<?php

namespace App\Filament\Resources\Standpunkter;

use App\Enums\Prosessteg;
use App\Enums\Sikkerhet;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use App\Filament\Handlinger\MerkSomFeil;
use App\Filament\Resources\Standpunkter\Pages\ListStandpunkter;
use App\Filament\Resources\Standpunkter\Pages\VisStandpunkt;
use App\Flyt\Prosessflyt;
use App\Models\Standpunkt;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Gjennomgang av standpunktene. Køen sorteres med uenighet mellom kjøringene
 * og lav sikkerhet først, som i designdokumentet.
 */
class StandpunktResource extends Resource
{
    protected static ?string $model = Standpunkt::class;

    protected static ?string $modelLabel = 'standpunkt';

    protected static ?string $pluralModelLabel = 'standpunkter';

    protected static ?string $slug = 'standpunkter';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Prosessflyt';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $antall = Standpunkt::query()->where('status', Standpunktstatus::Maskinell)->count();

        return $antall ? (string) $antall : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Til gjennomgang';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('tema.navn')->label('Tema'),
                TextEntry::make('program.nokkel')->label('Program'),
                TextEntry::make('kildetype')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('tema.pastand')->label('Påstand')->columnSpanFull(),
                TextEntry::make('verdi')->label('Standpunkt')->badge(),
                TextEntry::make('sikkerhet')->badge(),
                TextEntry::make('uenighet')->label('Uenighet mellom kjøringene')->formatStateUsing(fn (bool $state) => $state ? 'Ja' : 'Nei'),
                TextEntry::make('modell')->placeholder('Ingen LLM-kall'),
                TextEntry::make('begrunnelse')->columnSpanFull(),
                TextEntry::make('merknader')->listWithLineBreaks()->placeholder('Ingen')->columnSpanFull(),
            ]),
            Section::make('Kildesetninger')->schema([
                RepeatableEntry::make('kildesetninger')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('nokkel')->hiddenLabel()->badge()->color('gray'),
                        TextEntry::make('tekst')->hiddenLabel(),
                    ])
                    ->placeholder('Ingen kildesetninger'),
            ]),
            Section::make('Kjøringer')
                ->description('Svaret fra modellen i hver kjøring. Ugyldige svar viser feilen og råsvaret.')
                ->collapsed(fn (Standpunkt $record) => ! $record->uenighet)
                ->schema([
                    RepeatableEntry::make('kjoringer')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('verdi')->label('Standpunkt')->placeholder('–'),
                            TextEntry::make('sikkerhet')->placeholder('–'),
                            TextEntry::make('kildesetninger')->label('Kilder')->listWithLineBreaks()->placeholder('Ingen'),
                            TextEntry::make('gyldig')->formatStateUsing(fn ($state) => $state ? 'Gyldig' : 'Ugyldig')->badge()
                                ->color(fn ($state) => $state ? 'success' : 'danger'),
                            TextEntry::make('handler_om_temaet')->label('Handler om temaet')
                                ->formatStateUsing(fn ($state) => is_bool($state) ? ($state ? 'Ja' : 'Nei') : '–')
                                ->placeholder('–'),
                            TextEntry::make('merknad')->placeholder('–')->columnSpan(3),
                            TextEntry::make('feil')->placeholder('–')->columnSpanFull(),
                            TextEntry::make('ugyldige_kilder')->label('Ugyldige kilder')->listWithLineBreaks()->placeholder('Ingen')->columnSpanFull(),
                            TextEntry::make('rasvar')->label('Råsvar')
                                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string) $state)
                                ->placeholder('–')->columnSpanFull(),
                        ])
                        ->placeholder('Ingen kjøringer (satt uten LLM-kall)'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tema', 'program.parti', 'kildesetninger'])
                ->orderByDesc('uenighet')
                ->orderByRaw("case sikkerhet when 'lav' then 1 when 'middels' then 2 else 3 end"))
            ->columns([
                TextColumn::make('tema.navn')->label('Tema')->searchable(),
                TextColumn::make('program.parti.forkortelse')->label('Parti'),
                TextColumn::make('kildetype')->badge()->toggleable(),
                TextColumn::make('verdi')->label('Standpunkt')->badge(),
                TextColumn::make('sikkerhet')->badge(),
                IconColumn::make('uenighet')->boolean()->trueColor('warning')->falseIcon(null),
                TextColumn::make('utdrag')->state(fn (Standpunkt $record) => $record->utdrag())->wrap()->limit(200)->placeholder('–'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Standpunktstatus::class)->default(Standpunktstatus::Maskinell->value),
                SelectFilter::make('tema')->relationship('tema', 'navn')->searchable()->preload(),
                SelectFilter::make('verdi')->label('Standpunkt')->options(Standpunktverdi::class),
                SelectFilter::make('sikkerhet')->options(Sikkerhet::class),
                TernaryFilter::make('uenighet'),
            ])
            ->recordActions([
                self::godkjenn(),
                ActionGroup::make([
                    ViewAction::make(),
                    self::korriger(),
                    self::avvis(),
                    self::klassifiserPaNytt(),
                    MerkSomFeil::make(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('godkjennValgte')
                    ->label('Godkjenn valgte')
                    ->icon(Heroicon::OutlinedCheck)
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (Standpunkt $s) => self::sett($s, Standpunktstatus::Godkjent)))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function godkjenn(): Action
    {
        return Action::make('godkjenn')
            ->label('Godkjenn')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (Standpunkt $record) => ! $record->status->erGodkjent())
            ->action(function (Standpunkt $record) {
                self::sett($record, Standpunktstatus::Godkjent);
                Notification::make()->title('Godkjent')->success()->send();
            });
    }

    public static function korriger(): Action
    {
        return Action::make('korriger')
            ->label('Korriger standpunkt')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([Select::make('verdi')->label('Riktig standpunkt')->options(Standpunktverdi::class)->required()])
            ->fillForm(fn (Standpunkt $record) => ['verdi' => $record->verdi])
            ->action(function (Standpunkt $record, array $data) {
                self::sett($record, Standpunktstatus::Korrigert, $data['verdi']);
                Notification::make()->title('Korrigert og godkjent')->success()->send();
            });
    }

    public static function avvis(): Action
    {
        return Action::make('avvis')
            ->label('Avvis')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Standpunkt $record) => $record->status !== Standpunktstatus::Avvist)
            ->action(function (Standpunkt $record) {
                self::sett($record, Standpunktstatus::Avvist);
                Notification::make()->title('Avvist')->success()->send();
            });
    }

    public static function klassifiserPaNytt(): Action
    {
        return Action::make('klassifiserPaNytt')
            ->label('Klassifiser på nytt')
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function (Standpunkt $record) {
                app(Prosessflyt::class)->start(Prosessteg::Klassifisering, $record->program, $record->tema, $record->kildetype, bruker: auth()->user());
                Notification::make()->title('Lagt i kø')->success()->send();
            });
    }

    public static function sett(Standpunkt $standpunkt, Standpunktstatus $status, Standpunktverdi|string|null $verdi = null): void
    {
        if ($verdi !== null) {
            $verdi = $verdi instanceof Standpunktverdi ? $verdi : Standpunktverdi::from($verdi);
            if ($verdi !== $standpunkt->verdi) {
                $standpunkt->leggTilMerknad("Korrigert i admin fra «{$standpunkt->verdi->etikett()}» til «{$verdi->etikett()}».");
                $standpunkt->verdi = $verdi;
            }
        }

        $standpunkt->status = $status;
        $standpunkt->godkjent_av = $status->erGodkjent() ? auth()->id() : null;
        $standpunkt->godkjent_tid = $status->erGodkjent() ? now() : null;
        $standpunkt->save();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStandpunkter::route('/'),
            'view' => VisStandpunkt::route('/{record}'),
        ];
    }
}
