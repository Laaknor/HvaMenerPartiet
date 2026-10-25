<?php

namespace App\Sidecache;

/**
 * For modeller som vises på den offentlige siden: alle endringer tømmer
 * sidecachen, for eksempel når et standpunkt godkjennes i admin.
 */
trait TommerSidecache
{
    public static function bootTommerSidecache(): void
    {
        $tom = fn () => app(Sidecache::class)->tomEtterCommit();

        static::saved($tom);
        static::deleted($tom);
    }
}
