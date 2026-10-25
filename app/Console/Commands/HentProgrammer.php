<?php

namespace App\Console\Commands;

use App\Digitalisering\Programhenter;
use App\Jobs\HentProgram;
use Illuminate\Console\Command;
use Throwable;

class HentProgrammer extends Command
{
    use VelgerProgrammer;

    protected $signature = 'program:hent
        {program?* : Programnøkkel eller parti}
        {--alle : Alle gjeldende programmer}
        {--synkront : Kjør her og nå i stedet for i køen}
        {--uten-digitalisering : Ikke start digitalisering etter nedlasting}';

    protected $description = 'Laster ned programfilene fra partienes nettsider';

    public function handle(Programhenter $henter): int
    {
        $feil = 0;

        foreach ($this->valgteProgrammer() as $program) {
            if (! $this->option('synkront')) {
                HentProgram::dispatch($program, ! $this->option('uten-digitalisering'));
                $this->line("Lagt i kø: {$program->nokkel}");

                continue;
            }

            try {
                $endret = $henter->hent($program);
                $this->info("{$program->nokkel}: ".($endret ? 'lastet ned' : 'uendret').' ('.substr($program->filhash, 0, 12).')');
            } catch (Throwable $e) {
                $feil++;
                $this->error("{$program->nokkel}: {$e->getMessage()}");
            }
        }

        return $feil ? self::FAILURE : self::SUCCESS;
    }
}
