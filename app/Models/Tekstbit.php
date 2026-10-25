<?php

namespace App\Models;

use App\Enums\Programdel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tekstbit extends Model
{
    protected $table = 'tekstbiter';

    protected $fillable = [
        'program_id', 'kapittel_id', 'fra_avsnitt_id', 'til_avsnitt_id', 'del', 'overskriftssti', 'tekst',
        'antall_ord', 'embedding', 'embeddingmodell',
    ];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return ['del' => Programdel::class];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function kapittel(): BelongsTo
    {
        return $this->belongsTo(Kapittel::class);
    }

    public function fraAvsnitt(): BelongsTo
    {
        return $this->belongsTo(Avsnitt::class, 'fra_avsnitt_id');
    }

    public function tilAvsnitt(): BelongsTo
    {
        return $this->belongsTo(Avsnitt::class, 'til_avsnitt_id');
    }

    /** Teksten som sendes til embeddingmodellen: overskriftssti og tekst. */
    public function embeddingtekst(): string
    {
        return $this->overskriftssti."\n\n".$this->tekst;
    }
}
