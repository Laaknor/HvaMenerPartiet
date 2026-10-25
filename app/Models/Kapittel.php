<?php

namespace App\Models;

use App\Enums\Programdel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kapittel extends Model
{
    protected $table = 'kapitler';

    protected $fillable = ['program_id', 'overordnet_kapittel_id', 'nr', 'niva', 'rekkefolge', 'tittel', 'slug', 'del', 'side'];

    protected function casts(): array
    {
        return ['del' => Programdel::class];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function overordnet(): BelongsTo
    {
        return $this->belongsTo(self::class, 'overordnet_kapittel_id');
    }

    public function underkapitler(): HasMany
    {
        return $this->hasMany(self::class, 'overordnet_kapittel_id')->orderBy('rekkefolge');
    }

    public function avsnitt(): HasMany
    {
        return $this->hasMany(Avsnitt::class)->orderBy('rekkefolge');
    }

    /** «Forsvar > NATO og allierte» */
    public function overskriftssti(): string
    {
        $deler = [];
        $k = $this;
        while ($k) {
            array_unshift($deler, $k->tittel);
            $k = $k->overordnet;
        }

        return implode(' > ', $deler);
    }
}
