<?php

namespace App\Filament\Widgets;

use App\Enums\Digitaliseringsstatus;
use App\Enums\Kjoringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Standpunktstatus;
use App\Filament\Resources\Programmer\ProgramResource;
use App\Filament\Resources\Prosesskjoringer\ProsesskjoringResource;
use App\Filament\Resources\Standpunkter\StandpunktResource;
use App\Flyt\Programmappe;
use App\Flyt\Prosessplanlegger;
use App\Models\Program;
use App\Models\Prosesskjoring;
use App\Models\Standpunkt;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tallene for hvert steg i flyten, fra PDF i docs/ til godkjent standpunkt. */
class Flytoversikt extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '10s';

    protected ?string $heading = 'Flyten';

    protected ?string $description = 'Innlesing fra docs/ → digitalisering og oppsplitting → gjennomgang → embeddings → klassifisering → gjennomgang av standpunkter';

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $planlegger = app(Prosessplanlegger::class);
        $venter = $planlegger->venter();
        $programmer = Program::query()->gjeldende()->get();
        $status = fn (Digitaliseringsstatus $s) => $programmer->where('digitalisering_status', $s)->count();
        $aktive = Prosesskjoring::query()->aktive()->get()->countBy(fn (Prosesskjoring $k) => $k->steg->value);
        $aktivTekst = fn (Prosessteg $s) => ($aktive[$s->value] ?? 0) ? ($aktive[$s->value].' kjører eller i kø') : 'Ingen kjører';

        $utenFil = $programmer->filter(fn (Program $p) => ! $p->kildefilFinnes() && ! $p->filFinnes())->count();
        $ukjente = app(Programmappe::class)->utenProgram();
        $feilet = Prosesskjoring::query()->where('status', Kjoringsstatus::Feilet)->where('ferdig', '>=', now()->subDay())->count();
        $tilGjennomgang = Standpunkt::query()->where('status', Standpunktstatus::Maskinell)->count();
        $godkjente = Standpunkt::query()->whereIn('status', [Standpunktstatus::Godkjent, Standpunktstatus::Korrigert])->count();

        return [
            Stat::make('PDF-er i docs/', $programmer->filter->kildefilFinnes()->count().' av '.$programmer->count())
                ->description(collect([
                    $utenFil ? "{$utenFil} programmer mangler fil" : null,
                    $ukjente ? count($ukjente).' filer uten program: '.implode(', ', array_map('basename', $ukjente)) : null,
                ])->filter()->implode(' · ') ?: 'Alle programmer har fil')
                ->color($utenFil ? 'warning' : 'success')
                ->url(ProgramResource::getUrl()),
            Stat::make('Klar til innlesing', $venter[Prosessteg::Innlesing->value])
                ->description($aktivTekst(Prosessteg::Innlesing)),
            Stat::make('Klar til digitalisering', $venter[Prosessteg::Digitalisering->value])
                ->description($aktivTekst(Prosessteg::Digitalisering)),
            Stat::make('Venter på gjennomgang', $status(Digitaliseringsstatus::Digitalisert))
                ->description('Strukturen må kontrolleres og godkjennes')
                ->color($status(Digitaliseringsstatus::Digitalisert) ? 'warning' : 'gray')
                ->url(ProgramResource::getUrl(parameters: ['tableFilters' => ['digitalisering_status' => ['value' => Digitaliseringsstatus::Digitalisert->value]]])),
            Stat::make('Godkjente programmer', $status(Digitaliseringsstatus::Godkjent).' av '.$programmer->count())
                ->description($status(Digitaliseringsstatus::Feilet) ? $status(Digitaliseringsstatus::Feilet).' programmer feilet' : 'Ingen feilet')
                ->color($status(Digitaliseringsstatus::Feilet) ? 'danger' : 'success'),
            Stat::make('Venter på embeddings', $venter[Prosessteg::Embeddings->value])
                ->description($aktivTekst(Prosessteg::Embeddings)),
            Stat::make('Klassifiseringer igjen', $venter[Prosessteg::Klassifisering->value])
                ->description($aktivTekst(Prosessteg::Klassifisering)),
            Stat::make('Standpunkter til gjennomgang', $tilGjennomgang)
                ->description("{$godkjente} godkjent")
                ->color($tilGjennomgang ? 'warning' : 'success')
                ->url(StandpunktResource::getUrl()),
            Stat::make('Kapasitet i bruk', $planlegger->aktive('import').'/'.$planlegger->kapasitet('import').' import · '.$planlegger->aktive('ollama').'/'.$planlegger->kapasitet('ollama').' Ollama')
                ->description($planlegger->erAutomatisk() ? 'Automatisk drift er på' : 'Automatisk drift er av')
                ->color($planlegger->erAutomatisk() ? 'success' : 'gray')
                ->url(ProsesskjoringResource::getUrl()),
            Stat::make('Feilet siste døgn', $feilet)
                ->color($feilet ? 'danger' : 'success')
                ->url(ProsesskjoringResource::getUrl(parameters: ['tableFilters' => ['status' => ['value' => Kjoringsstatus::Feilet->value]]])),
        ];
    }
}
