<?php

namespace App\Console\Commands;

use App\Models\Program;
use Illuminate\Console\Command;

/**
 * Viser den digitaliserte strukturen for kontroll før godkjenning. Erstattes
 * av side-om-side-kontrollen i Filament.
 */
class VisProgram extends Command
{
    protected $signature = 'program:vis
        {program : Programnøkkel}
        {--kapittel= : Vis bare ett kapittel (slug, for eksempel kap-4)}
        {--setninger : Vis hver setning med fast ID}';

    protected $description = 'Viser kapitler, avsnitt og setninger i et digitalisert program';

    public function handle(): int
    {
        $program = Program::query()->where('nokkel', $this->argument('program'))->with('parti')->firstOrFail();

        $this->info("{$program->parti->navn}: {$program->tittel} ({$program->type->etikett()})");
        $this->line("Status: {$program->digitalisering_status->etikett()}, {$program->antall_sider} sider");
        if ($program->digitalisering_merknad) {
            $this->warn($program->digitalisering_merknad);
        }
        $this->newLine();

        $kapitler = $program->kapitler()
            ->with(['avsnitt.setninger'])
            ->when($this->option('kapittel'), fn ($q, $slug) => $q->where('slug', 'like', $slug.'%'))
            ->get();

        foreach ($kapitler as $kapittel) {
            $del = $kapittel->del ? " [{$kapittel->del->etikett()}]" : '';
            $this->line(str_repeat('  ', $kapittel->niva - 1)."<comment>{$kapittel->slug}</comment> {$kapittel->tittel}{$del} (s. {$kapittel->side})");

            foreach ($kapittel->avsnitt as $avsnitt) {
                $innrykk = str_repeat('  ', $kapittel->niva);
                if ($this->option('setninger')) {
                    foreach ($avsnitt->setninger as $s) {
                        $this->line($innrykk."<fg=gray>{$s->nokkel}</> ".($s->er_punkt ? '• ' : '').$s->tekst);
                    }
                } else {
                    $this->line($innrykk."<fg=gray>a{$avsnitt->nr} s.{$avsnitt->side}</> ".mb_strimwidth(str_replace("\n", ' ', $avsnitt->tekst), 0, 110, '…'));
                }
            }
        }

        return self::SUCCESS;
    }
}
