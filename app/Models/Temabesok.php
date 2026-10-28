<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Antall besøk på én temaside én dag. */
class Temabesok extends Model
{
    protected $table = 'temabesok';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['tema_id', 'dato', 'antall'];

    protected function casts(): array
    {
        return ['dato' => 'date'];
    }

    /** Teller ett besøk på temaet i dag. */
    public static function tell(Tema $tema): void
    {
        static::query()->upsert(
            [['tema_id' => $tema->id, 'dato' => now()->toDateString(), 'antall' => 1]],
            ['tema_id', 'dato'],
            ['antall' => DB::raw('antall + 1')],
        );
    }

    /**
     * Id-ene til de mest besøkte temaene de siste $dager dagene, mest besøkt først.
     *
     * @return Collection<int, int>
     */
    public static function mestBesokte(int $dager = 30): Collection
    {
        return static::query()
            ->where('dato', '>=', now()->subDays($dager)->toDateString())
            ->groupBy('tema_id')
            ->orderByRaw('SUM(antall) DESC')
            ->orderBy('tema_id')
            ->pluck('tema_id');
    }
}
