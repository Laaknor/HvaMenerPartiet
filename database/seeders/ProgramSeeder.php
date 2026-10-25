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
                'gjeldende' => $data['gjeldende'] ?? true,
                'sprak' => 'nb',
                'kilde_url' => $data['kilde_url'],
                'kilde_side' => $data['kilde_side'] ?? null,
            ];

            $program = Program::query()->firstOrNew(['nokkel' => $nokler['nokkel']]);
            $program->fill($felter);

            // Programmet kobles til filen først når den ligger i docs/. En
            // kildefil som er valgt i admin, overskrives ikke med en som mangler.
            if (($data['kildefil'] ?? null) !== null) {
                $forslag = (clone $program)->fill(['kildefil' => $data['kildefil']]);
                if ($forslag->kildefilFinnes()) {
                    $program->kildefil = $data['kildefil'];
                }
            }

            $program->save();
        }
    }
}
