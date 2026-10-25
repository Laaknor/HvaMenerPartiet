<?php

namespace App\Http\Controllers;

use App\Models\Feilsokingssak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kun lesing. Bak Feilsokingstoken-middlewaren. */
class FeilsokingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $saker = Feilsokingssak::query()
            ->when(! $request->boolean('alle'), fn ($q) => $q->where('lost', false))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Feilsokingssak $sak) => [
                'referanse' => $sak->referanse,
                'tittel' => $sak->tittel(),
                'type' => $sak->pakke['type'] ?? null,
                'notat' => $sak->notat,
                'lost' => $sak->lost,
                'github_issue' => $sak->githubUrl(),
                'laget' => $sak->created_at?->toIso8601String(),
                'url' => $sak->apiUrl(),
            ]);

        return $this->svar(['saker' => $saker]);
    }

    public function vis(Feilsokingssak $sak): JsonResponse
    {
        $sak->forceFill(['sist_hentet' => now()])->saveQuietly();

        return $this->svar([
            'referanse' => $sak->referanse,
            'lost' => $sak->lost,
            'github_issue' => $sak->githubUrl(),
            'pakke' => $sak->pakke,
        ]);
    }

    private function svar(array $data): JsonResponse
    {
        return response()
            ->json($data, options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
