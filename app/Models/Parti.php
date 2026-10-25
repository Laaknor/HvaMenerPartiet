<?php

namespace App\Models;

use App\Enums\Programtype;
use App\Sidecache\TommerSidecache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parti extends Model
{
    use HasFactory, TommerSidecache;

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

    /**
     * Har partiet et gjeldende program som gir eget prinsipielt syn? Det er et
     * prinsipprogram eller et kombinert dokument som er delt i to deler.
     */
    public function harPrinsipprogram(): bool
    {
        return $this->programmer
            ->where('gjeldende', true)
            ->contains(fn (Program $p) => $p->type === Programtype::Prinsipprogram || $p->erDelt());
    }

    /** Har partiet et kombinert prinsipp- og handlingsprogram som leses samlet? */
    public function harSamletProgram(): bool
    {
        return ! $this->harPrinsipprogram()
            && $this->programmer->where('gjeldende', true)->contains(fn (Program $p) => $p->erSamlet());
    }
}
