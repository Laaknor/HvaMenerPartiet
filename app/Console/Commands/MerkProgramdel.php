<?php

namespace App\Console\Commands;

use App\Digitalisering\Delmerking;
use App\Enums\Programdel;
use App\Models\Program;
use Illuminate\Console\Command;

/**
 * Merker kapitler i et kombinert dokument som prinsipp- eller handlingsdel
 * når overskriftene ikke var tydelige nok. Kan også gjøres i admin.
 */
class MerkProgramdel extends Command
{
    protected $signature = 'program:merk-del
        {program : Programnøkkel}
        {del : prinsipp eller handling}
        {kapittel* : Kapittel-slugger, for eksempel kap-1 kap-2 (underkapitler følger med)}';

    protected $description = 'Merker kapitler i et kombinert dokument som prinsipp- eller handlingsdel';

    public function handle(Delmerking $delmerking): int
    {
        $program = Program::query()->where('nokkel', $this->argument('program'))->firstOrFail();
        if (! $program->erKombinert()) {
            $this->error('Bare kombinerte dokumenter deles i prinsipp- og handlingsdel.');

            return self::FAILURE;
        }

        $del = Programdel::from($this->argument('del'));
        $antall = $delmerking->merk($program, $del, $this->argument('kapittel'));

        $this->info("Merket {$antall} kapitler som {$del->etikett()}.");

        return self::SUCCESS;
    }
}
