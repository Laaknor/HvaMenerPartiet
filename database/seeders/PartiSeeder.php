<?php

namespace Database\Seeders;

use App\Models\Parti;
use Illuminate\Database\Seeder;

class PartiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('data/partier.php') as $parti) {
            Parti::query()->updateOrCreate(['slug' => $parti['slug']], $parti);
        }
    }
}
