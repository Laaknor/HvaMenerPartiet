<?php

namespace App\Digitalisering;

use App\Analyse\Tekstbitbygger;
use App\Enums\Digitaliseringsstatus;
use App\Models\Program;
use RuntimeException;
use Throwable;

/**
 * Hele digitaliseringen av ett program: tekstuttrekk, struktur, setninger
 * med faste ID-er og tekstbiter for gjenfinning. Etterpå venter programmet på
 * manuell kontroll før embeddings og klassifisering kjøres.
 */
class Digitaliserer
{
    public function __construct(
        private BboxLeser $leser,
        private Strukturtolker $tolker,
        private Delmerker $delmerker,
        private ProgramLagrer $lagrer,
        private Tekstbitbygger $tekstbitbygger,
    ) {}

    public function digitaliser(Program $program): Lagringsresultat
    {
        if (! $program->filFinnes()) {
            throw new RuntimeException("Programmet {$program->nokkel} har ingen nedlastet fil.");
        }

        try {
            $uttrekk = $this->leser->les($program->absoluttFilsti());
            $dokument = $this->tolker->tolk($uttrekk['linjer'], $uttrekk['sider']);

            if ($dokument->antallAvsnitt() === 0) {
                throw new RuntimeException('Fant ingen tekst i PDF-en. Den kan være skannet uten tekstlag.');
            }

            $merknader = array_filter([$dokument->utelattBeskrivelse()]);
            if ($program->erKombinert()) {
                $deler = $this->delmerker->merk($dokument);
                if ($deler['umerket'] > 0) {
                    $merknader[] = "{$deler['umerket']} av ".count($dokument->kapitler).' kapitler mangler prinsipp- eller handlingsdel og må merkes manuelt.';
                }
            }

            $resultat = $this->lagrer->lagre($program, $dokument);
            $this->tekstbitbygger->bygg($program);

            if ($resultat->berorteStandpunkter > 0) {
                $merknader[] = "{$resultat->berorteStandpunkter} standpunkter mistet kildesetninger og er sendt tilbake til godkjenning.";
            }

            $program->update([
                'digitalisering_status' => Digitaliseringsstatus::Digitalisert,
                'digitalisering_merknad' => $merknader ? implode("\n", $merknader) : null,
                'antall_sider' => $dokument->antallSider,
                'digitalisert' => now(),
                'godkjent' => null,
            ]);

            return $resultat;
        } catch (Throwable $e) {
            $program->update([
                'digitalisering_status' => Digitaliseringsstatus::Feilet,
                'digitalisering_merknad' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
