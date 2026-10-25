<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/** En sak sendt til feilsøking fra admin, med en ferdig satt sammen pakke. */
class Feilsokingssak extends Model
{
    protected $table = 'feilsokingssaker';

    protected $fillable = [
        'referanse', 'gjelder_type', 'gjelder_id', 'notat', 'pakke', 'github_issue', 'github_feil',
        'lost', 'sist_hentet', 'opprettet_av',
    ];

    protected function casts(): array
    {
        return [
            'pakke' => 'array',
            'lost' => 'boolean',
            'sist_hentet' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Feilsokingssak $sak) {
            $sak->referanse ??= (string) Str::uuid();
        });
    }

    public function gjelder(): MorphTo
    {
        return $this->morphTo();
    }

    public function oppretter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opprettet_av');
    }

    public function tittel(): string
    {
        return (string) ($this->pakke['tittel'] ?? 'Feilsøking');
    }

    public function githubUrl(): ?string
    {
        $repo = config('hvamenerpartiet.feilsoking.github_repo');

        return $this->github_issue && $repo ? "https://github.com/{$repo}/issues/{$this->github_issue}" : null;
    }

    public function apiUrl(): string
    {
        return url('api/feilsoking/'.$this->referanse);
    }
}
