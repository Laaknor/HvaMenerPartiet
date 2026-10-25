<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

/** Gir en bruker tilgang til admin (/admin) og Horizon (/horizon). Lager brukeren om den ikke finnes. */
class GiAdmintilgang extends Command
{
    protected $signature = 'bruker:admin {epost} {--navn= : Navn hvis brukeren skal lages} {--fjern : Fjern tilgangen i stedet}';

    protected $description = 'Gir en bruker tilgang til admin og Horizon';

    public function handle(): int
    {
        $epost = $this->argument('epost');
        $bruker = User::query()->where('email', $epost)->first();

        if (! $bruker) {
            if ($this->option('fjern')) {
                $this->error("Fant ingen bruker med e-post {$epost}.");

                return self::FAILURE;
            }

            $bruker = User::create([
                'name' => $this->option('navn') ?: $epost,
                'email' => $epost,
                'password' => password('Passord for den nye brukeren', required: true),
            ]);
            $this->info("Laget brukeren {$epost}.");
        }

        $bruker->forceFill(['er_admin' => ! $this->option('fjern')])->save();
        $this->info($this->option('fjern') ? "{$epost} har ikke lenger admintilgang." : "{$epost} har nå tilgang til /admin og /horizon.");

        return self::SUCCESS;
    }
}
