<?php

namespace App\Filament\Handlinger;

use App\Feilsoking\Feilsoking;
use App\Feilsoking\GithubIssue;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** «Send til feilsøking» for standpunkter, kjøringer og tilbakemeldinger. */
class Feilsokingshandling
{
    public static function make(): Action
    {
        return Action::make('sendTilFeilsoking')
            ->label('Send til feilsøking')
            ->icon(Heroicon::OutlinedBugAnt)
            ->color('gray')
            ->modalHeading('Send til feilsøking')
            ->modalDescription('Lager en pakke med vurderingen, kildesetningene, prompten, modellsvarene og jobbloggen. Hemmeligheter og e-postadresser tas aldri med.')
            ->modalSubmitActionLabel('Send')
            ->schema([
                Textarea::make('notat')
                    ->label('Hva ser feil ut?')
                    ->helperText(GithubIssue::erSattOpp()
                        ? 'Står i GitHub-issuen, som er offentlig. Ikke skriv personopplysninger her.'
                        : 'GitHub-issue er ikke satt opp. Pakken kan hentes via API-et.')
                    ->rows(4)
                    ->maxLength(2000),
                Toggle::make('issue')
                    ->label('Lag GitHub-issue')
                    ->default(true)
                    ->visible(fn () => GithubIssue::erSattOpp()),
            ])
            ->action(function (Model $record, array $data) {
                $sak = app(Feilsoking::class)->send($record, $data['notat'] ?? null, auth()->user(), (bool) ($data['issue'] ?? false));

                $varsel = Notification::make()->title('Sendt til feilsøking');

                if ($sak->github_feil) {
                    $varsel->warning()->body('Pakken er lagret, men GitHub-issuen feilet: '.$sak->github_feil);
                } elseif ($url = $sak->githubUrl()) {
                    $varsel->success()->body("Issue #{$sak->github_issue} er laget.")
                        ->actions([Action::make('apne')->label('Åpne issuen')->url($url, shouldOpenInNewTab: true)]);
                } else {
                    $varsel->success()->body("Referanse: {$sak->referanse}");
                }

                $varsel->send();
            });
    }
}
