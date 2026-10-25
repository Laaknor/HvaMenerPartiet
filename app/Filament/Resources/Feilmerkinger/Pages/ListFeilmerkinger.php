<?php

namespace App\Filament\Resources\Feilmerkinger\Pages;

use App\Feilsoking\Feilmerker;
use App\Filament\Resources\Feilmerkinger\FeilmerkingResource;
use App\Models\Feilmerking;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListFeilmerkinger extends ListRecords
{
    protected static string $resource = FeilmerkingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eksporter')
                ->label('Eksporter feil')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalHeading('Eksporter feil')
                ->modalDescription('Laster ned én JSON-fil med alle åpne feil og feilsøkingspakkene deres. Last opp filen i feilsøkingen. Hemmeligheter og e-postadresser er fjernet.')
                ->modalSubmitActionLabel('Last ned')
                ->schema([
                    Toggle::make('bare_nye')->label('Bare feil som ikke er eksportert før')->default(false),
                    Toggle::make('med_loste')->label('Ta med løste feil')->default(false),
                ])
                ->action(function (array $data): ?StreamedResponse {
                    $feil = Feilmerking::query()
                        ->when(! ($data['med_loste'] ?? false), fn ($q) => $q->apne())
                        ->when($data['bare_nye'] ?? false, fn ($q) => $q->whereNull('eksportert'))
                        ->orderBy('id')
                        ->get();

                    if ($feil->isEmpty()) {
                        Notification::make()->title('Ingen feil å eksportere')->warning()->send();

                        return null;
                    }

                    $eksport = app(Feilmerker::class)->eksport($feil);
                    $json = json_encode($eksport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

                    return response()->streamDownload(
                        fn () => print ($json),
                        'feil-'.now()->format('Y-m-d-Hi').'.json',
                        ['Content-Type' => 'application/json'],
                    );
                }),
        ];
    }
}
