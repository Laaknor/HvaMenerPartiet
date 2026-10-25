<?php

namespace App\Feilsoking;

use App\Models\Feilsokingssak;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/** «Send til feilsøking»: lagrer pakken og lager GitHub-issue om det er satt opp. */
class Feilsoking
{
    public function __construct(
        private Pakkebygger $pakkebygger,
        private GithubIssue $github,
    ) {}

    public function send(Model $modell, ?string $notat = null, ?User $bruker = null, bool $issue = true): Feilsokingssak
    {
        $sak = Feilsokingssak::create([
            'gjelder_type' => $modell->getMorphClass(),
            'gjelder_id' => $modell->getKey(),
            'notat' => $notat ?: null,
            'pakke' => $this->pakkebygger->bygg($modell, $notat),
            'opprettet_av' => $bruker?->id,
        ]);

        if ($issue && GithubIssue::erSattOpp()) {
            try {
                $sak->update(['github_issue' => $this->github->opprett($sak)]);
            } catch (Throwable $e) {
                // Pakken er lagret uansett og kan hentes via API-et.
                Log::warning('Kunne ikke lage GitHub-issue for feilsøkingssak', ['sak' => $sak->id, 'feil' => $e->getMessage()]);
                $sak->update(['github_feil' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }

        return $sak;
    }
}
