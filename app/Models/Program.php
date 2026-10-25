<?php

namespace App\Models;

use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Enums\Programtype;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Program extends Model
{
    use HasFactory;

    protected $table = 'programmer';

    protected $fillable = [
        'parti_id', 'type', 'tittel', 'slug', 'nokkel', 'periode', 'vedtatt_ar', 'gjeldende', 'sprak',
        'kilde_url', 'kilde_side', 'filsti', 'filhash', 'lastet_ned', 'digitalisering_status',
        'digitalisering_merknad', 'antall_sider', 'digitalisert', 'godkjent',
    ];

    protected function casts(): array
    {
        return [
            'type' => Programtype::class,
            'digitalisering_status' => Digitaliseringsstatus::class,
            'gjeldende' => 'boolean',
            'lastet_ned' => 'datetime',
            'digitalisert' => 'datetime',
            'godkjent' => 'datetime',
        ];
    }

    public function parti(): BelongsTo
    {
        return $this->belongsTo(Parti::class);
    }

    public function kapitler(): HasMany
    {
        return $this->hasMany(Kapittel::class)->orderBy('rekkefolge');
    }

    public function avsnitt(): HasMany
    {
        return $this->hasMany(Avsnitt::class)->orderBy('rekkefolge');
    }

    public function setninger(): HasMany
    {
        return $this->hasMany(Setning::class);
    }

    public function tekstbiter(): HasMany
    {
        return $this->hasMany(Tekstbit::class);
    }

    public function standpunkter(): HasMany
    {
        return $this->hasMany(Standpunkt::class);
    }

    public function scopeGjeldende(Builder $query): void
    {
        $query->where('gjeldende', true);
    }

    public function scopeGodkjent(Builder $query): void
    {
        $query->where('digitalisering_status', Digitaliseringsstatus::Godkjent);
    }

    /** @return list<Kildetype> */
    public function kildetyper(): array
    {
        return $this->type->kildetyper();
    }

    public function erKombinert(): bool
    {
        return $this->type === Programtype::Kombinert;
    }

    public function filFinnes(): bool
    {
        return $this->filsti !== null && Storage::disk(config('hvamenerpartiet.digitalisering.disk'))->exists($this->filsti);
    }

    public function absoluttFilsti(): string
    {
        return Storage::disk(config('hvamenerpartiet.digitalisering.disk'))->path($this->filsti);
    }

    /** Lager slug og nøkkel ut fra parti, type og år. */
    public static function lagNokler(Parti $parti, Programtype $type, ?int $ar): array
    {
        $ar ??= 0;
        $slug = match ($type) {
            Programtype::Arbeidsprogram => "arbeidsprogram-{$ar}",
            Programtype::Prinsipprogram => $ar ? "prinsipprogram-{$ar}" : 'prinsipprogram',
            Programtype::Kombinert => "program-{$ar}",
        };

        return [
            'slug' => $slug,
            'nokkel' => $parti->slug.'-'.$type->kode().($ar ?: ''),
        ];
    }
}
