<?php

namespace App\Models;

use App\Enums\Programtype;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parti extends Model
{
    use HasFactory;

    protected $table = 'partier';

    protected $fillable = ['navn', 'forkortelse', 'slug', 'farge', 'nettside', 'aktiv_fra', 'aktiv_til'];

    protected function casts(): array
    {
        return [
            'aktiv_fra' => 'date',
            'aktiv_til' => 'date',
        ];
    }

    public function programmer(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /** Har partiet et gjeldende program som gir prinsipielt syn (eget eller kombinert)? */
    public function harPrinsipprogram(): bool
    {
        return $this->programmer
            ->where('gjeldende', true)
            ->contains(fn (Program $p) => in_array($p->type, [Programtype::Prinsipprogram, Programtype::Kombinert], true));
    }
}
