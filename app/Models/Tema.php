<?php

namespace App\Models;

use App\Enums\Temastatus;
use App\Sidecache\TommerSidecache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tema extends Model
{
    use TommerSidecache;

    protected $table = 'temaer';

    protected $fillable = ['navn', 'slug', 'beskrivelse', 'pastand', 'synonymer', 'forvekslinger', 'overordnet_tema_id', 'status', 'embedding', 'embeddingmodell'];

    protected $hidden = ['embedding', 'seedverdier'];

    protected function casts(): array
    {
        return [
            'synonymer' => 'array',
            'forvekslinger' => 'array',
            'seedverdier' => 'array',
            'status' => Temastatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Ny tekst gir ny embedding neste gang temaet klassifiseres.
        static::saving(function (Tema $tema) {
            if ($tema->exists && $tema->isDirty(['navn', 'beskrivelse', 'pastand', 'synonymer'])) {
                $tema->embedding = null;
                $tema->embeddingmodell = null;
            }
        });
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

    /**
     * Nærliggende begreper som lett forveksles med temaet, men som ikke er
     * det samme (for eksempel «atomvåpen» for atomkraft). Tas ikke med i
     * embeddingen, så de ikke trekker likhetssøket mot seg.
     *
     * @return list<string>
     */
    public function forvekslingsord(): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $this->forvekslinger ?? []))));
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
