<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AktiveKjoringer;
use App\Filament\Widgets\Flytoversikt;
use App\Flyt\Prosessplanlegger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Forsiden i admin: hvor programmene står i flyten, hva som kjører nå, og
 * bryteren for automatisk drift.
 */
class Prosessflyt extends Dashboard
{
    protected static ?string $title = 'Prosessflyt';

    protected static ?string $navigationLabel = 'Prosessflyt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public function getWidgets(): array
    {
        return [
            Flytoversikt::class,
            AktiveKjoringer::class,
        ];
    }

    public function getColumns(): int
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        $planlegger = app(Prosessplanlegger::class);
        $pa = $planlegger->erAutomatisk();

        return [
            Action::make('automatisk')
                ->label($pa ? 'Automatisk drift: på' : 'Automatisk drift: av')
                ->icon($pa ? Heroicon::OutlinedPlay : Heroicon::OutlinedPause)
                ->color($pa ? 'success' : 'gray')
                ->tooltip($pa ? 'Neste steg starter av seg selv når det er ledig kapasitet. Klikk for å slå av.' : 'Ingenting starter av seg selv. Klikk for å slå på.')
                ->action(function () use ($planlegger, $pa) {
                    $planlegger->settAutomatisk(! $pa);
                    if (! $pa) {
                        $planlegger->planlegg();
                    }
                    Notification::make()->title(! $pa ? 'Automatisk drift er slått på' : 'Automatisk drift er slått av')->success()->send();
                }),
            Action::make('planlegg')
                ->label('Fyll ledig kapasitet nå')
                ->icon(Heroicon::OutlinedForward)
                ->action(function () use ($planlegger) {
                    $startet = array_sum($planlegger->planlegg());
                    Notification::make()->title($startet ? "Startet {$startet} jobber" : 'Ingenting å starte, eller ingen ledig kapasitet')->success()->send();
                }),
            Action::make('horizon')
                ->label('Horizon')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(url(config('horizon.path', 'horizon')), shouldOpenInNewTab: true),
        ];
    }
}
