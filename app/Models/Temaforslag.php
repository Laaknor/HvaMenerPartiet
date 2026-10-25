<?php

namespace App\Models;

use App\Sok\Temasok;
use Illuminate\Database\Eloquent\Model;

/** Et tema en besøkende har foreslått etter et søk uten treff. */
class Temaforslag extends Model
{
    protected $table = 'temaforslag';

    protected $fillable = ['sokeord', 'normalisert', 'antall', 'sist_foreslatt', 'behandlet'];

    protected function casts(): array
    {
        return [
            'sist_foreslatt' => 'datetime',
            'behandlet' => 'boolean',
        ];
    }

    /** Lagrer forslaget, eller teller opp et likt forslag. */
    public static function registrer(string $sokeord): self
    {
        $sokeord = trim(preg_replace('/\s+/u', ' ', $sokeord));
        $forslag = self::firstOrNew(['normalisert' => Temasok::normaliser($sokeord)]);

        if ($forslag->exists) {
            $forslag->antall++;
        } else {
            $forslag->sokeord = $sokeord;
        }
        $forslag->sist_foreslatt = now();
        $forslag->save();

        return $forslag;
    }
}
