<?php

namespace App\Models;

use App\Enums\Programdel;
use App\Sidecache\TommerSidecache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avsnitt extends Model
{
    use TommerSidecache;

    protected $table = 'avsnitt';

    protected $fillable = [
        'program_id', 'kapittel_id', 'hovedkapittel_id', 'nr', 'rekkefolge', 'del', 'side', 'til_side',
        'er_liste', 'tekst', 'tekst_hash',
    ];

    protected function casts(): array
    {
        return [
            'del' => Programdel::class,
            'er_liste' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function kapittel(): BelongsTo
    {
        return $this->belongsTo(Kapittel::class);
    }

    public function hovedkapittel(): BelongsTo
    {
        return $this->belongsTo(Kapittel::class, 'hovedkapittel_id');
    }

    public function setninger(): HasMany
    {
        return $this->hasMany(Setning::class)->orderBy('nr');
    }
}
