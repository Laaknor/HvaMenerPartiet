<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Viser PDF-en for et program i admin, så strukturen kan kontrolleres mot originalen. */
class ProgramfilController extends Controller
{
    public function __invoke(Program $program): BinaryFileResponse
    {
        // Den innleste kopien er den som er digitalisert. Finnes den ikke ennå, vises filen i docs/.
        $sti = match (true) {
            $program->filFinnes() => $program->absoluttFilsti(),
            $program->kildefilFinnes() => $program->kildefilSti(),
            default => abort(404, 'Programmet har ingen PDF.'),
        };

        return response()->file($sti, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$program->nokkel.'.pdf"',
        ]);
    }
}
