<?php

namespace App\Console\Commands;

use App\Enums\Programdel;
use App\Models\Avsnitt;
use App\Models\Kapittel;
use App\Models\Program;
use App\Models\Tekstbit;
use Illuminate\Console\Command;

/**
 * Merker kapitler i et kombinert dokument som prinsipp- eller handlingsdel
 * når overskriftene ikke var tydelige nok. Senere gjøres dette i Filament.
 */
class MerkProgramdel extends Command
{
    protected $signature = 'program:merk-del
        {program : Programnøkkel}
        {del : prinsipp eller handling}
        {kapittel* : Kapittel-slugger, for eksempel kap-1 kap-2 (underkapitler følger med)}';

    protected $description = 'Merker kapitler i et kombinert dokument som prinsipp- eller handlingsdel';

    public function handle(): int
    {
        $program = Program::query()->where('nokkel', $this->argument('program'))->firstOrFail();
        if (! $program->erKombinert()) {
            $this->error('Bare kombinerte dokumenter deles i prinsipp- og handlingsdel.');

            return self::FAILURE;
        }

        $del = Programdel::from($this->argument('del'));
        $antall = 0;

        foreach ($this->argument('kapittel') as $slug) {
            $ider = Kapittel::query()
                ->where('program_id', $program->id)
                ->where(fn ($q) => $q->where('slug', $slug)->orWhere('slug', 'like', $slug.'-%'))
                ->pluck('id');

            Kapittel::query()->whereIn('id', $ider)->update(['del' => $del]);
            Avsnitt::query()->whereIn('kapittel_id', $ider)->update(['del' => $del]);
            Tekstbit::query()->whereIn('kapittel_id', $ider)->update(['del' => $del]);
            $antall += $ider->count();
        }

        $this->info("Merket {$antall} kapitler som {$del->etikett()}.");

        return self::SUCCESS;
    }
}
