<?php

namespace App\Models;

use App\Enums\Temastatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tema extends Model
{
    protected $table = 'temaer';

    protected $fillable = ['navn', 'slug', 'beskrivelse', 'pastand', 'synonymer', 'overordnet_tema_id', 'status', 'embedding', 'embeddingmodell'];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'synonymer' => 'array',
            'status' => Temastatus::class,
        ];
    }

    public function overordnet(): BelongsTo
    {
        return $this->belongsTo(self::class, 'overordnet_tema_id');
    }

    public function standpunkter(): HasMany
    {
        return $this->hasMany(Standpunkt::class);
    }

    /** Navn og synonymer, brukt til nøkkelordsøk i programtekstene. @return list<string> */
    public function sokeord(): array
    {
        return array_values(array_unique(array_filter([$this->navn, ...($this->synonymer ?? [])])));
    }

    /** Teksten som sendes til embeddingmodellen for gjenfinning. */
    public function embeddingtekst(): string
    {
        $tekst = $this->navn;
        if ($this->beskrivelse) {
            $tekst .= ': '.$this->beskrivelse;
        }
        $tekst .= "\nPåstand: ".$this->pastand;
        if ($this->synonymer) {
            $tekst .= "\nStikkord: ".implode(', ', $this->synonymer);
        }

        return $tekst;
    }
}
