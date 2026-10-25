<?php

namespace App\Filament\Handlinger;

use App\Digitalisering\Delmerking;
use App\Enums\Digitaliseringsstatus;
use App\Enums\Programdel;
use App\Enums\Prosessteg;
use App\Flyt\Prosessflyt;
use App\Flyt\Prosessplanlegger;
use App\Models\Kapittel;
use App\Models\Program;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Handlingene som styrer flyten for ett program, brukt i tabellen og på programsiden. */
class Programhandlinger
{
    /** @return list<Action> */
    public static function alle(): array
    {
        return [
            self::lesInn(),
            self::digitaliser(),
            self::godkjenn(),
            self::merkDel(),
            self::embeddings(),
            self::klassifiser(),
            self::apnePdf(),
        ];
    }

    public static function lesInn(): Action
    {
        return Action::make('lesInn')
            ->label('Les inn fra docs/')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (Program $record) => $record->kildefilFinnes())
            ->action(fn (Program $record) => self::start(Prosessteg::Innlesing, $record));
    }

    public static function digitaliser(): Action
    {
        return Action::make('digitaliser')
            ->label(fn (Program $record) => $record->digitalisert ? 'Digitaliser på nytt' : 'Digitaliser')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (Program $record) => $record->filFinnes())
            ->requiresConfirmation(fn (Program $record) => $record->digitalisering_status === Digitaliseringsstatus::Godkjent)
            ->modalDescription('Programmet er godkjent. Etter ny digitalisering må strukturen gjennomgås og godkjennes på nytt.')
            ->action(fn (Program $record) => self::start(Prosessteg::Digitalisering, $record));
    }

    public static function godkjenn(): Action
    {
        return Action::make('godkjennStruktur')
            ->label('Godkjenn strukturen')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Program $record) => $record->digitalisering_status === Digitaliseringsstatus::Digitalisert)
            ->requiresConfirmation()
            ->modalHeading('Godkjenn strukturen')
            ->modalDescription('Kapitler, avsnitt og setninger er kontrollert. Embeddings og klassifisering starter etterpå.')
            ->action(function (Program $record) {
                app(Prosessflyt::class)->godkjennStruktur($record, auth()->user());
                Notification::make()->title("{$record->nokkel} er godkjent")->body('Embeddings er lagt i kø.')->success()->send();
            });
    }

    public static function merkDel(): Action
    {
        return Action::make('merkDel')
            ->label('Merk prinsipp- eller handlingsdel')
            ->icon(Heroicon::OutlinedTag)
            ->visible(fn (Program $record) => $record->erKombinert() && $record->kapitler()->exists())
            ->schema(fn (Program $record) => [
                Radio::make('del')
                    ->label('Del')
                    ->options(Programdel::class)
                    ->required(),
                CheckboxList::make('kapitler')
                    ->label('Hovedkapitler (underkapitler følger med)')
                    ->options(Kapittel::query()->where('program_id', $record->id)->where('niva', 1)->orderBy('rekkefolge')->get()
                        ->mapWithKeys(fn (Kapittel $k) => [$k->slug => "{$k->slug}: {$k->tittel}".($k->del ? " ({$k->del->etikett()})" : ' (umerket)')]))
                    ->columns(1)
                    ->required(),
            ])
            ->action(function (Program $record, array $data) {
                $del = $data['del'] instanceof Programdel ? $data['del'] : Programdel::from($data['del']);
                $antall = app(Delmerking::class)->merk($record, $del, $data['kapitler']);
                Notification::make()->title("Merket {$antall} kapitler som {$del->etikett()}")->success()->send();
            });
    }

    public static function embeddings(): Action
    {
        return Action::make('embeddings')
            ->label('Lag embeddings')
            ->icon(Heroicon::OutlinedCpuChip)
            ->visible(fn (Program $record) => $record->digitalisering_status === Digitaliseringsstatus::Godkjent)
            ->action(fn (Program $record) => self::start(Prosessteg::Embeddings, $record));
    }

    public static function klassifiser(): Action
    {
        return Action::make('klassifiser')
            ->label('Klassifiser alle temaer')
            ->icon(Heroicon::OutlinedScale)
            ->visible(fn (Program $record) => $record->digitalisering_status === Digitaliseringsstatus::Godkjent)
            ->requiresConfirmation()
            ->modalDescription('Klassifiserer alle godkjente temaer på nytt for dette programmet, også de som allerede har standpunkt. Standpunkter som endrer seg, sendes tilbake til gjennomgang.')
            ->action(function (Program $record) {
                $antall = app(Prosessflyt::class)->klassifiser(app(Prosessplanlegger::class)->aktuelleTemaer(), [$record], bruker: auth()->user());
                Notification::make()->title("La {$antall} klassifiseringer i kø")->success()->send();
            });
    }

    public static function apnePdf(): Action
    {
        return Action::make('apnePdf')
            ->label('Åpne PDF')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->visible(fn (Program $record) => $record->filFinnes() || $record->kildefilFinnes())
            ->url(fn (Program $record) => route('filament.admin.programfil', $record), shouldOpenInNewTab: true);
    }

    private static function start(Prosessteg $steg, Program $program): void
    {
        $kjoring = app(Prosessflyt::class)->start($steg, $program, bruker: auth()->user());

        Notification::make()
            ->title("{$steg->etikett()}: {$program->nokkel}")
            ->body($kjoring->wasRecentlyCreated ? 'Lagt i kø.' : 'Kjører allerede.')
            ->success()
            ->send();
    }
}
