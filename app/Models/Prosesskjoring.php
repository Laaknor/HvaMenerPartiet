<?php

namespace App\Models;

use App\Enums\Kildetype;
use App\Enums\Kjoringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Utlosning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Én jobb i prosessflyten, fra den legges i kø til den er ferdig eller feiler. */
class Prosesskjoring extends Model
{
    protected $table = 'prosesskjoringer';

    protected $fillable = [
        'steg', 'program_id', 'tema_id', 'kildetype', 'status', 'utlost_av', 'bruker_id',
        'forsok', 'melding', 'startet', 'ferdig',
    ];

    protected function casts(): array
    {
        return [
            'steg' => Prosessteg::class,
            'kildetype' => Kildetype::class,
            'status' => Kjoringsstatus::class,
            'utlost_av' => Utlosning::class,
            'startet' => 'datetime',
            'ferdig' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class);
    }

    public function bruker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bruker_id');
    }

    public function scopeAktive(Builder $query): void
    {
        $query->whereIn('status', Kjoringsstatus::aktive());
    }

    public function scopeForMal(Builder $query, Prosessteg $steg, ?Program $program, ?Tema $tema = null, ?Kildetype $kildetype = null): void
    {
        $query->where('steg', $steg)
            ->where('program_id', $program?->id)
            ->where('tema_id', $tema?->id)
            ->where('kildetype', $kildetype?->value);
    }

    public function beskrivelse(): string
    {
        return collect([
            $this->program?->nokkel,
            $this->tema?->navn,
            $this->kildetype?->etikett(),
        ])->filter()->implode(' · ');
    }

    public function varighet(): ?string
    {
        if (! $this->startet) {
            return null;
        }

        $sekunder = (int) $this->startet->diffInSeconds($this->ferdig ?? now());

        return $sekunder < 60 ? "{$sekunder} s" : intdiv($sekunder, 60).' min '.($sekunder % 60).' s';
    }
}
