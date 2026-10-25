<?php

namespace App\Console\Commands;

use App\Sidecache\Sidecache;
use Illuminate\Console\Command;

class TomSidecache extends Command
{
    protected $signature = 'sidecache:tom';

    protected $description = 'Tømmer cachen for de offentlige sidene';

    public function handle(Sidecache $cache): int
    {
        $cache->tom();
        $this->components->info('Sidecachen er tømt.');

        return self::SUCCESS;
    }
}
