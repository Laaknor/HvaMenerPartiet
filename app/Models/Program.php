<?php

namespace App\Models;

use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Enums\Programdel;
use App\Enums\Programdeling;
use App\Enums\Programtype;
use App\Sidecache\TommerSidecache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Program extends Model
{
    use HasFactory, TommerSidecache;

    protected $table = 'programmer';

    protected $fillable = [
        'parti_id', 'type', 'deling', 'tittel', 'slug', 'nokkel', 'periode', 'vedtatt_ar', 'gjeldende', 'sprak',
        'kilde_url', 'kilde_side', 'kildefil', 'filsti', 'filhash', 'lastet_ned', 'digitalisering_status',
        'digitalisering_merknad', 'antall_sider', 'digitalisert', 'godkjent',
    ];

    protected function casts(): array
    {
        return [
            'type' => Programtype::class,
            'deling' => Programdeling::class,
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

    public function prosesskjoringer(): HasMany
    {
        return $this->hasMany(Prosesskjoring::class)->latest('id');
    }

    public function aktiveKjoringer(): HasMany
    {
        return $this->hasMany(Prosesskjoring::class)->aktive()->latest('id');
    }

    public function scopeGjeldende(Builder $query): void
    {
        $query->where('gjeldende', true);
    }

    public function scopeGodkjent(Builder $query): void
    {
        $query->where('digitalisering_status', Digitaliseringsstatus::Godkjent);
    }

    /**
     * Et kombinert dokument som leses samlet, gir bare ett standpunkt per
     * tema, lagret som arbeidsprogram.
     *
     * @return list<Kildetype>
     */
    public function kildetyper(): array
    {
        if ($this->erKombinert() && ! $this->erDelt()) {
            return [Kildetype::Arbeidsprogram];
        }

        return $this->type->kildetyper();
    }

    public function erKombinert(): bool
    {
        return $this->type === Programtype::Kombinert;
    }

    /**
     * Leses det kombinerte dokumentet som to programmer? Automatisk betyr at
     * det deles bare når minst ett kapittel er merket som prinsippdel.
     */
    public function erDelt(): bool
    {
        if (! $this->erKombinert()) {
            return false;
        }

        return match ($this->deling ?? Programdeling::Automatisk) {
            Programdeling::Delt => true,
            Programdeling::Samlet => false,
            Programdeling::Automatisk => $this->harPrinsippdel(),
        };
    }

    /** Kombinert dokument som leses som ett samlet program. */
    public function erSamlet(): bool
    {
        return $this->erKombinert() && ! $this->erDelt();
    }

    public function harPrinsippdel(): bool
    {
        return $this->kapitler()->where('del', Programdel::Prinsipp)->exists();
    }

    public function filFinnes(): bool
    {
        return $this->filsti !== null && Storage::disk(config('hvamenerpartiet.digitalisering.disk'))->exists($this->filsti);
    }

    public function absoluttFilsti(): string
    {
        return Storage::disk(config('hvamenerpartiet.digitalisering.disk'))->path($this->filsti);
    }

    /** Full sti til kildefilen i docs/Partiprogram, eller null om programmet ikke har en. */
    public function kildefilSti(): ?string
    {
        if (! $this->kildefil) {
            return null;
        }

        $mappe = rtrim(config('hvamenerpartiet.digitalisering.programmappe'), '/');
        $sti = $mappe.'/'.ltrim($this->kildefil, '/');

        // Filnavn med æ, ø og å kan være lagret som NFC eller NFD avhengig av
        // hvilket system som la filen i repoet.
        if (! is_file($sti) && class_exists(\Normalizer::class)) {
            foreach ([\Normalizer::FORM_C, \Normalizer::FORM_D] as $form) {
                $variant = \Normalizer::normalize($sti, $form);
                if ($variant && is_file($variant)) {
                    return $variant;
                }
            }
        }

        return $sti;
    }

    public function kildefilFinnes(): bool
    {
        $sti = $this->kildefilSti();

        return $sti !== null && is_file($sti);
    }

    /** Når filen i docs/ sist ble endret. Horizon-prosessene lever lenge, så stat-hurtigbufferen tømmes. */
    public function kildefilEndretTid(): ?int
    {
        $sti = $this->kildefilSti();
        if ($sti === null) {
            return null;
        }
        clearstatcache(true, $sti);

        return is_file($sti) ? filemtime($sti) : null;
    }

    /** Om filen i docs/ er nyere enn den som er lest inn. */
    public function kildefilErEndret(): bool
    {
        if (! $this->kildefilFinnes()) {
            return false;
        }

        if (! $this->filhash) {
            return true;
        }

        $sti = $this->kildefilSti();
        if ($this->lastet_ned && $this->kildefilEndretTid() <= $this->lastet_ned->getTimestamp()) {
            return false;
        }

        return hash_file('sha256', $sti) !== $this->filhash;
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
