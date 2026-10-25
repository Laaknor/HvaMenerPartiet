<?php

namespace App\Flyt;

use App\Enums\Kjoringsstatus;
use App\Models\Prosesskjoring;
use App\Ollama\Konfigurasjonsfeil;
use Throwable;

/**
 * For jobber i prosessflyten. Jobben oppdaterer kjøringen sin når den
 * starter, blir ferdig eller feiler, og ber planleggeren fylle ledig
 * kapasitet når den er ferdig.
 */
trait SporesSomKjoring
{
    public ?int $kjoringId = null;

    public function medKjoring(Prosesskjoring $kjoring): static
    {
        $this->kjoringId = $kjoring->id;

        return $this;
    }

    /**
     * @param  callable(): (string|null)  $arbeid  returnerer en kort melding om resultatet
     */
    protected function sporet(callable $arbeid): void
    {
        $kjoring = $this->kjoring();
        $kjoring?->update([
            'status' => Kjoringsstatus::Kjorer,
            'startet' => now(),
            'ferdig' => null,
            'forsok' => $this->attempts(),
        ]);

        try {
            $melding = $arbeid();
            $kjoring?->update(['status' => Kjoringsstatus::Ferdig, 'ferdig' => now(), 'melding' => $melding]);
        } catch (Konfigurasjonsfeil $e) {
            // Feil oppsett blir ikke bedre av nye forsøk.
            $kjoring?->update(['status' => Kjoringsstatus::Feilet, 'ferdig' => now(), 'melding' => $e->getMessage()]);
            $this->fail($e);
        } catch (Throwable $e) {
            $siste = $this->attempts() >= ($this->tries ?? 1);
            $kjoring?->update([
                'status' => $siste ? Kjoringsstatus::Feilet : Kjoringsstatus::IKo,
                'ferdig' => $siste ? now() : null,
                'melding' => ($siste ? '' : 'Prøver igjen. ').$e->getMessage(),
            ]);

            throw $e;
        } finally {
            app(Prosessplanlegger::class)->etterJobb();
        }
    }

    /** Kalles av køen når jobben har gitt opp, også ved tidsavbrudd. */
    public function failed(?Throwable $e): void
    {
        $this->kjoring()?->update([
            'status' => Kjoringsstatus::Feilet,
            'ferdig' => now(),
            'melding' => $e?->getMessage() ?: 'Jobben feilet.',
        ]);
    }

    private function kjoring(): ?Prosesskjoring
    {
        return $this->kjoringId ? Prosesskjoring::find($this->kjoringId) : null;
    }
}
