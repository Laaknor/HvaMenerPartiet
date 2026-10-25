<?php

namespace App\Console\Commands;

use App\Flyt\Prosessplanlegger;
use Illuminate\Console\Command;

/** Kjøres hvert minutt av Laravel-planleggeren (schedule:work eller cron). */
class PlanleggFlyt extends Command
{
    protected $signature = 'flyt:planlegg
        {--tving : Planlegg selv om automatisk drift er slått av}
        {--pa : Slå på automatisk drift}
        {--av : Slå av automatisk drift}';

    protected $description = 'Starter neste steg i prosessflyten når det er ledig kapasitet';

    public function handle(Prosessplanlegger $planlegger): int
    {
        if ($this->option('pa') || $this->option('av')) {
            $planlegger->settAutomatisk((bool) $this->option('pa'));
            $this->info('Automatisk drift er '.($this->option('pa') ? 'slått på.' : 'slått av.'));
        }

        if (! $planlegger->erAutomatisk() && ! $this->option('tving')) {
            $this->line('Automatisk drift er slått av. Bruk --tving for å planlegge likevel.');

            return self::SUCCESS;
        }

        $startet = array_filter($planlegger->planlegg());
        if ($startet === []) {
            $this->line('Ingenting å starte, eller ingen ledig kapasitet.');
        }
        foreach ($startet as $steg => $antall) {
            $this->info("Startet {$antall} × {$steg}");
        }

        return self::SUCCESS;
    }
}
