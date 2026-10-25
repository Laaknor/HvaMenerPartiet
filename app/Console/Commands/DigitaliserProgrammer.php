<?php

namespace App\Console\Commands;

use App\Digitalisering\Digitaliserer;
use App\Jobs\DigitaliserProgram;
use Illuminate\Console\Command;
use Throwable;

class DigitaliserProgrammer extends Command
{
    use VelgerProgrammer;

    protected $signature = 'program:digitaliser
        {program?* : Programnøkkel eller parti}
        {--alle : Alle gjeldende programmer med nedlastet fil}
        {--synkront : Kjør her og nå i stedet for i køen}';

    protected $description = 'Gjør programfilene om til kapitler, avsnitt og setninger med faste ID-er';

    public function handle(Digitaliserer $digitaliserer): int
    {
        $feil = 0;

        foreach ($this->valgteProgrammer() as $program) {
            if (! $program->filFinnes()) {
                $this->warn("{$program->nokkel}: ingen fil, kjør program:hent først");

                continue;
            }

            if (! $this->option('synkront')) {
                DigitaliserProgram::dispatch($program);
                $this->line("Lagt i kø: {$program->nokkel}");

                continue;
            }

            try {
                $r = $digitaliserer->digitaliser($program);
                $this->info("{$program->nokkel}: {$r->kapitler} kapitler, {$r->avsnitt} avsnitt, {$r->setninger} setninger"
                    ." ({$r->beholdt} beholdt ID, {$r->nye} nye, {$r->fjernet} fjernet)");
                if ($program->digitalisering_merknad) {
                    $this->warn('  '.$program->digitalisering_merknad);
                }
            } catch (Throwable $e) {
                $feil++;
                $this->error("{$program->nokkel}: {$e->getMessage()}");
            }
        }

        if (! $feil && $this->option('synkront')) {
            $this->newLine();
            $this->line('Kontroller strukturen med program:vis og godkjenn med program:godkjenn.');
        }

        return $feil ? self::FAILURE : self::SUCCESS;
    }
}
