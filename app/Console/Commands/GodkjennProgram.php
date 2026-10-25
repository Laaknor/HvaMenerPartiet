<?php

namespace App\Console\Commands;

use App\Enums\Utlosning;
use App\Flyt\Prosessflyt;
use App\Models\Program;
use Illuminate\Console\Command;

/** Godkjenner den digitaliserte strukturen. Gjøres vanligvis i admin. */
class GodkjennProgram extends Command
{
    protected $signature = 'program:godkjenn {program* : Programnøkler} {--uten-embeddings : Ikke start embeddings}';

    protected $description = 'Godkjenner strukturen i et digitalisert program, så det kan analyseres';

    public function handle(Prosessflyt $flyt): int
    {
        $programmer = Program::query()->whereIn('nokkel', $this->argument('program'))->get();

        foreach ($programmer as $program) {
            if (! $flyt->godkjennStruktur($program, utlostAv: Utlosning::Kommando, startEmbeddings: ! $this->option('uten-embeddings'))) {
                $this->warn("{$program->nokkel}: status er «{$program->digitalisering_status->etikett()}», hopper over");

                continue;
            }

            $this->info("{$program->nokkel}: godkjent");
        }

        return self::SUCCESS;
    }
}
