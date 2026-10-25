<?php

namespace App\Flyt;

use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Enums\Kjoringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Utlosning;
use App\Jobs\DigitaliserProgram;
use App\Jobs\KlassifiserStandpunkt;
use App\Jobs\LagEmbeddings;
use App\Jobs\LesInnProgram;
use App\Models\Program;
use App\Models\Prosesskjoring;
use App\Models\Tema;
use App\Models\User;
use InvalidArgumentException;

/**
 * Starter steg i prosessflyten. Hver start får en rad i prosesskjoringer, så
 * flyten kan følges i admin. Samme steg startes ikke to ganger for samme
 * program (og tema) mens det fortsatt er i kø eller kjører.
 */
class Prosessflyt
{
    public function start(
        Prosessteg $steg,
        Program $program,
        ?Tema $tema = null,
        ?Kildetype $kildetype = null,
        Utlosning $utlostAv = Utlosning::Admin,
        ?User $bruker = null,
        bool $tving = false,
    ): Prosesskjoring {
        if ($steg === Prosessteg::Klassifisering && (! $tema || ! $kildetype)) {
            throw new InvalidArgumentException('Klassifisering trenger tema og kildetype.');
        }

        if ($steg !== Prosessteg::Klassifisering) {
            [$tema, $kildetype] = [null, null];
        }

        $aktiv = Prosesskjoring::query()->aktive()->forMal($steg, $program, $tema, $kildetype)->first();
        if ($aktiv) {
            return $aktiv;
        }

        $kjoring = Prosesskjoring::create([
            'steg' => $steg,
            'program_id' => $program->id,
            'tema_id' => $tema?->id,
            'kildetype' => $kildetype,
            'status' => Kjoringsstatus::IKo,
            'utlost_av' => $utlostAv,
            'bruker_id' => $bruker?->id,
        ]);

        $jobb = match ($steg) {
            Prosessteg::Innlesing => new LesInnProgram($program),
            Prosessteg::Digitalisering => new DigitaliserProgram($program),
            Prosessteg::Embeddings => new LagEmbeddings($program, $tving),
            Prosessteg::Klassifisering => new KlassifiserStandpunkt($tema, $program, $kildetype),
        };

        dispatch($jobb->medKjoring($kjoring));

        return $kjoring->refresh();
    }

    /** Starter en feilet kjøring på nytt, med samme mål. */
    public function provIgjen(Prosesskjoring $kjoring, ?User $bruker = null): Prosesskjoring
    {
        return $this->start($kjoring->steg, $kjoring->program, $kjoring->tema, $kjoring->kildetype, Utlosning::Admin, $bruker);
    }

    /**
     * Godkjenner den digitaliserte strukturen etter gjennomgang, og starter
     * embeddings, som er neste steg.
     */
    public function godkjennStruktur(Program $program, ?User $bruker = null, Utlosning $utlostAv = Utlosning::Admin, bool $startEmbeddings = true): bool
    {
        if ($program->digitalisering_status !== Digitaliseringsstatus::Digitalisert) {
            return false;
        }

        $program->update(['digitalisering_status' => Digitaliseringsstatus::Godkjent, 'godkjent' => now()]);

        if ($startEmbeddings) {
            $this->start(Prosessteg::Embeddings, $program, utlostAv: $utlostAv, bruker: $bruker);
        }

        return true;
    }

    /**
     * Klassifiserer alle aktuelle temaer mot et program, eller ett tema mot
     * alle godkjente programmer. Én jobb per tema, program og kildetype.
     *
     * @param  iterable<Tema>  $temaer
     * @param  iterable<Program>  $programmer
     * @return int antall kjøringer som ble startet eller allerede var aktive
     */
    public function klassifiser(iterable $temaer, iterable $programmer, Utlosning $utlostAv = Utlosning::Admin, ?User $bruker = null): int
    {
        $antall = 0;
        foreach ($temaer as $tema) {
            foreach ($programmer as $program) {
                foreach ($program->kildetyper() as $kildetype) {
                    $this->start(Prosessteg::Klassifisering, $program, $tema, $kildetype, $utlostAv, $bruker);
                    $antall++;
                }
            }
        }

        return $antall;
    }
}
