<?php

namespace App\Console\Commands;

use App\Models\Program;
use Illuminate\Console\Command;

class ProgramStatus extends Command
{
    protected $signature = 'program:status';

    protected $description = 'Viser status for alle programmer';

    public function handle(): int
    {
        $rader = Program::query()
            ->with('parti')
            ->withCount(['kapitler', 'avsnitt', 'setninger', 'tekstbiter', 'standpunkter'])
            ->orderBy('parti_id')
            ->get()
            ->map(fn (Program $p) => [
                $p->nokkel,
                $p->parti->forkortelse,
                $p->type->etikett(),
                $p->vedtatt_ar ?? '?',
                $p->digitalisering_status->etikett(),
                $p->filhash ? substr($p->filhash, 0, 10) : '–',
                "{$p->kapitler_count}/{$p->avsnitt_count}/{$p->setninger_count}",
                $p->tekstbiter()->whereNotNull('embedding')->count().'/'.$p->tekstbiter_count,
                $p->standpunkter_count,
            ]);

        $this->table(['Nøkkel', 'Parti', 'Type', 'Vedtatt', 'Status', 'Fil', 'Kap/avs/set', 'Embeddings', 'Standpunkt'], $rader);

        return self::SUCCESS;
    }
}
