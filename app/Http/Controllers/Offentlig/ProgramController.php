<?php

namespace App\Http\Controllers\Offentlig;

use App\Enums\Digitaliseringsstatus;
use App\Http\Controllers\Controller;
use App\Models\Kapittel;
use App\Models\Parti;
use App\Models\Program;
use Illuminate\View\View;

/**
 * De digitaliserte programmene som lesbare sider. Hvert avsnitt og hver
 * setning har en fast ID, så standpunktene kan lenke rett til sitatet:
 * /program/sv/arbeidsprogram-2025/kap-4#a12-s3-s5
 */
class ProgramController extends Controller
{
    public function index(): View
    {
        return view('offentlig.programmer', [
            'partier' => Parti::query()
                ->with(['programmer' => fn ($q) => $q->where('digitalisering_status', Digitaliseringsstatus::Godkjent)->orderByDesc('gjeldende')->orderBy('type')])
                ->orderBy('navn')
                ->get(),
        ]);
    }

    public function vis(Parti $parti, string $program): View
    {
        $program = $this->finn($parti, $program);

        return view('offentlig.program', [
            'parti' => $parti,
            'program' => $program,
            'kapitler' => $this->hovedkapitler($program),
        ]);
    }

    public function kapittel(Parti $parti, string $program, string $kapittel): View
    {
        $program = $this->finn($parti, $program);
        $kapitler = $this->hovedkapitler($program);
        $kap = $kapitler->firstWhere('slug', $kapittel) ?? abort(404);

        $avsnitt = $program->avsnitt()
            ->where('hovedkapittel_id', $kap->id)
            ->with(['setninger', 'kapittel'])
            ->get();

        $indeks = $kapitler->search(fn (Kapittel $k) => $k->id === $kap->id);

        return view('offentlig.kapittel', [
            'parti' => $parti,
            'program' => $program,
            'kapitler' => $kapitler,
            'kapittel' => $kap,
            'avsnitt' => $avsnitt,
            'forrige' => $kapitler->get($indeks - 1),
            'neste' => $kapitler->get($indeks + 1),
        ]);
    }

    private function finn(Parti $parti, string $slug): Program
    {
        return $parti->programmer()
            ->where('slug', $slug)
            ->where('digitalisering_status', Digitaliseringsstatus::Godkjent)
            ->orderByRaw("case when sprak = 'nb' then 0 else 1 end")
            ->firstOrFail()
            ->setRelation('parti', $parti);
    }

    private function hovedkapitler(Program $program)
    {
        return $program->kapitler()->whereNull('overordnet_kapittel_id')->get();
    }
}
