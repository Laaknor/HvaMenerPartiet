<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** «Meld fra om feil» på et partikort. */
class Tilbakemelding extends Model
{
    protected $table = 'tilbakemeldinger';

    protected $fillable = ['tema_id', 'parti_id', 'standpunkt_id', 'tekst', 'behandlet'];

    protected function casts(): array
    {
        return ['behandlet' => 'boolean'];
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class);
    }

    public function parti(): BelongsTo
    {
        return $this->belongsTo(Parti::class);
    }

    public function standpunkt(): BelongsTo
    {
        return $this->belongsTo(Standpunkt::class);
    }
}
