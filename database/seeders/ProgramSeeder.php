<?php

namespace Database\Seeders;

use App\Enums\Programtype;
use App\Models\Parti;
use App\Models\Program;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('data/programmer.php') as $data) {
            $parti = Parti::query()->where('slug', $data['parti'])->firstOrFail();
            $type = Programtype::from($data['type']);
            $nokler = Program::lagNokler($parti, $type, $data['vedtatt_ar']);

            $felter = [
                'parti_id' => $parti->id,
                'type' => $type,
                'slug' => $nokler['slug'],
                'tittel' => $data['tittel'],
                'periode' => $data['periode'] ?? null,
                'vedtatt_ar' => $data['vedtatt_ar'],
                'gjeldende' => true,
                'sprak' => 'nb',
                'kilde_url' => $data['kilde_url'],
                'kilde_side' => $data['kilde_side'] ?? null,
            ];

            // En kildefil som er valgt i admin, overskrives ikke med null.
            if (($data['kildefil'] ?? null) !== null) {
                $felter['kildefil'] = $data['kildefil'];
            }

            Program::query()->updateOrCreate(['nokkel' => $nokler['nokkel']], $felter);
        }
    }
}
