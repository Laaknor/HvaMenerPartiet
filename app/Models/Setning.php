<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setning extends Model
{
    protected $table = 'setninger';

    protected $fillable = ['program_id', 'avsnitt_id', 'nr', 'nokkel', 'er_punkt', 'tekst', 'tekst_hash'];

    protected function casts(): array
    {
        return ['er_punkt' => 'boolean'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function avsnitt(): BelongsTo
    {
        return $this->belongsTo(Avsnitt::class);
    }
}
