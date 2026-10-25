<?php

namespace App\Feilsoking;

use App\Models\Feilmerking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** «Merk som feil» og «Eksporter feil» i admin. */
class Feilmerker
{
    public const EKSPORTVERSJON = 1;

    public function __construct(
        private Pakkebygger $pakkebygger,
        private Sensurerer $sensurerer,
    ) {}

    public function merk(Model $modell, string $begrunnelse, ?User $bruker = null): Feilmerking
    {
        return Feilmerking::create([
            'gjelder_type' => $modell->getMorphClass(),
            'gjelder_id' => $modell->getKey(),
            'begrunnelse' => $this->sensurerer->renTekst(trim($begrunnelse)),
            'pakke' => $this->pakkebygger->bygg($modell),
            'merket_av' => $bruker?->id,
        ]);
    }

    /**
     * Én fil med alle feilene, til å laste opp i feilsøkingen. Feilene merkes
     * som eksportert, men står åpne til de merkes som løst.
     *
     * @param  Collection<int, Feilmerking>  $feil
     * @return array<string, mixed>
     */
    public function eksport(Collection $feil): array
    {
        $eksport = [
            'eksportversjon' => self::EKSPORTVERSJON,
            'laget' => now()->toIso8601String(),
            'kilde' => config('app.url'),
            'antall' => $feil->count(),
            'feil' => $feil->map(fn (Feilmerking $f) => [
                'id' => $f->id,
                'merket' => $f->created_at?->toIso8601String(),
                'begrunnelse' => $f->begrunnelse,
                'lost' => $f->lost,
                'tidligere_eksportert' => $f->eksportert?->toIso8601String(),
                ...$f->pakke,
            ])->values()->all(),
        ];

        Feilmerking::query()->whereKey($feil->modelKeys())->update(['eksportert' => now()]);

        return $this->sensurerer->rens($eksport);
    }
}
