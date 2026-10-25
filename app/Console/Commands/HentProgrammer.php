<?php

namespace App\Console\Commands;

use App\Digitalisering\Programhenter;
use App\Enums\Prosessteg;
use App\Enums\Utlosning;
use App\Flyt\Prosessflyt;
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
        {--fra-nett : Last ned fra partiets nettside i stedet for å lese fra docs/}
        {--uten-digitalisering : Ikke start digitalisering etter nedlasting (bare med --fra-nett)}';

    protected $description = 'Leser inn programfilene fra docs/Partiprogram (eller laster dem ned med --fra-nett)';

    public function handle(Programhenter $henter, Prosessflyt $flyt): int
    {
        $feil = 0;
        $fraNett = (bool) $this->option('fra-nett');

        foreach ($this->valgteProgrammer() as $program) {
            if (! $fraNett && ! $program->kildefilFinnes()) {
                $this->warn("{$program->nokkel}: ".($program->kildefil
                    ? "finner ikke docs/Partiprogram/{$program->kildefil}"
                    : 'ingen kildefil i docs/Partiprogram'));

                continue;
            }

            if (! $this->option('synkront')) {
                if ($fraNett) {
                    HentProgram::dispatch($program, ! $this->option('uten-digitalisering'));
                } else {
                    $flyt->start(Prosessteg::Innlesing, $program, utlostAv: Utlosning::Kommando);
                }
                $this->line("Lagt i kø: {$program->nokkel}");

                continue;
            }

            try {
                $endret = $fraNett ? $henter->hent($program) : $henter->lesInn($program);
                $this->info("{$program->nokkel}: ".($endret ? 'lest inn' : 'uendret').' ('.substr($program->filhash, 0, 12).')');
            } catch (Throwable $e) {
                $feil++;
                $this->error("{$program->nokkel}: {$e->getMessage()}");
            }
        }

        return $feil ? self::FAILURE : self::SUCCESS;
    }
}
