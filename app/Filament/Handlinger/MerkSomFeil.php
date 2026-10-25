<?php

namespace App\Filament\Handlinger;

use App\Feilsoking\Feilmerker;
use App\Filament\Resources\Feilmerkinger\FeilmerkingResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** «Merk som feil» for standpunkter, kjøringer og tilbakemeldinger. */
class MerkSomFeil
{
    public static function make(): Action
    {
        return Action::make('merkSomFeil')
            ->label('Merk som feil')
            ->icon(Heroicon::OutlinedBugAnt)
            ->color('gray')
            ->modalHeading('Merk som feil')
            ->modalDescription('Tar et øyeblikksbilde av vurderingen, kildesetningene, prompten, modellsvarene og jobbloggen. Det kommer med når feilene eksporteres under «Feil».')
            ->modalSubmitActionLabel('Merk')
            ->schema([
                Textarea::make('begrunnelse')
                    ->label('Hvorfor er dette feil?')
                    ->placeholder('For eksempel: Burde vært «delvis for», kildesetningen tar forbehold.')
                    ->required()
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->action(function (Model $record, array $data) {
                app(Feilmerker::class)->merk($record, $data['begrunnelse'], auth()->user());

                Notification::make()
                    ->title('Merket som feil')
                    ->success()
                    ->actions([Action::make('alle')->label('Se alle feil')->url(FeilmerkingResource::getUrl())])
                    ->send();
            });
    }
}
