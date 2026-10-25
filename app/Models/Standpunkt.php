<?php

namespace App\Models;

use App\Enums\Kildetype;
use App\Enums\Sikkerhet;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Standpunkt extends Model
{
    protected $table = 'standpunkter';

    protected $fillable = [
        'tema_id', 'program_id', 'kildetype', 'verdi', 'sikkerhet', 'begrunnelse', 'uenighet', 'kjoringer',
        'merknader', 'modell', 'prompt_versjon', 'status', 'godkjent_av', 'godkjent_tid',
    ];

    protected function casts(): array
    {
        return [
            'kildetype' => Kildetype::class,
            'verdi' => Standpunktverdi::class,
            'sikkerhet' => Sikkerhet::class,
            'status' => Standpunktstatus::class,
            'uenighet' => 'boolean',
            'kjoringer' => 'array',
            'merknader' => 'array',
            'godkjent_tid' => 'datetime',
        ];
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function godkjenner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'godkjent_av');
    }

    public function kildesetninger(): BelongsToMany
    {
        return $this->belongsToMany(Setning::class, 'standpunkt_kilder')
            ->withPivot('rekkefolge')
            ->orderByPivot('rekkefolge');
    }

    /**
     * Utdraget er alltid ordrett: det bygges fra kildesetningene i databasen,
     * aldri fra modellens egen tekst.
     *
     * @return Collection<int, Setning>
     */
    public function utdragssetninger(): Collection
    {
        return $this->kildesetninger->take((int) config('hvamenerpartiet.analyse.utdrag_setninger', 3));
    }

    public function utdrag(): string
    {
        return $this->utdragssetninger()->pluck('tekst')->implode(' ');
    }

    public function leggTilMerknad(string $merknad): void
    {
        $this->merknader = [...($this->merknader ?? []), $merknad];
    }
}
