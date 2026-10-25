<?php

namespace App\Console\Commands;

use App\Enums\Digitaliseringsstatus;
use App\Jobs\LagEmbeddings;
use App\Models\Program;
use Illuminate\Console\Command;

/** Godkjenner den digitaliserte strukturen. Senere gjøres dette i Filament. */
class GodkjennProgram extends Command
{
    protected $signature = 'program:godkjenn {program* : Programnøkler} {--uten-embeddings : Ikke start embeddings}';

    protected $description = 'Godkjenner strukturen i et digitalisert program, så det kan analyseres';

    public function handle(): int
    {
        $programmer = Program::query()->whereIn('nokkel', $this->argument('program'))->get();

        foreach ($programmer as $program) {
            if ($program->digitalisering_status !== Digitaliseringsstatus::Digitalisert) {
                $this->warn("{$program->nokkel}: status er «{$program->digitalisering_status->etikett()}», hopper over");

                continue;
            }

            $program->update(['digitalisering_status' => Digitaliseringsstatus::Godkjent, 'godkjent' => now()]);
            $this->info("{$program->nokkel}: godkjent");

            if (! $this->option('uten-embeddings')) {
                LagEmbeddings::dispatch($program);
            }
        }

        return self::SUCCESS;
    }
}
