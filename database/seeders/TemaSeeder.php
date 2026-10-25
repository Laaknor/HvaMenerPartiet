<?php

namespace Database\Seeders;

use App\Enums\Temastatus;
use App\Models\Tema;
use Illuminate\Database\Seeder;

class TemaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('data/temaer.php') as $tema) {
            $eksisterende = Tema::query()->where('slug', $tema['slug'])->first();

            // Ny tekst gir ny embedding.
            if ($eksisterende && $eksisterende->embeddingtekst() !== (new Tema($tema))->embeddingtekst()) {
                $tema['embedding'] = null;
            }

            Tema::query()->updateOrCreate(
                ['slug' => $tema['slug']],
                $tema + ['status' => $eksisterende?->status ?? Temastatus::Godkjent],
            );
        }
    }
}
