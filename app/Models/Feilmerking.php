<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Noe som er merket som feil i admin, med begrunnelse og feilsøkingspakke. */
class Feilmerking extends Model
{
    protected $table = 'feilmerkinger';

    protected $fillable = ['gjelder_type', 'gjelder_id', 'begrunnelse', 'pakke', 'lost', 'eksportert', 'merket_av'];

    protected function casts(): array
    {
        return [
            'pakke' => 'array',
            'lost' => 'boolean',
            'eksportert' => 'datetime',
        ];
    }

    public function gjelder(): MorphTo
    {
        return $this->morphTo();
    }

    public function merker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merket_av');
    }

    public function scopeApne(Builder $query): void
    {
        $query->where('lost', false);
    }

    public function tittel(): string
    {
        return (string) ($this->pakke['tittel'] ?? 'Feil');
    }
}
