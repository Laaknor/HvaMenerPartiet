<?php

namespace App\Console\Commands;

use App\Digitalisering\Programhenter;
use App\Models\Program;
use Illuminate\Console\Command;

class ImporterProgramfil extends Command
{
    protected $signature = 'program:importer-fil {program : Programnøkkel, for eksempel sv-ap2025} {fil : Sti til PDF-en}';

    protected $description = 'Importerer en programfil som er lastet ned manuelt';

    public function handle(Programhenter $henter): int
    {
        $program = Program::query()->where('nokkel', $this->argument('program'))->firstOrFail();
        $endret = $henter->importerFil($program, $this->argument('fil'));

        $this->info("{$program->nokkel}: ".($endret ? 'importert' : 'samme fil som før').' ('.substr($program->filhash, 0, 12).')');

        return self::SUCCESS;
    }
}
