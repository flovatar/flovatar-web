<?php

namespace App\Jobs;

use App\Services\Islands\IslandBelt;
use App\Support\Islands;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Builds the missing texture cells / atlas pages of the WebGL belt (App\Services\Islands\IslandBelt::warm()). */
class WarmIslandBelt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;

    public $timeout = 120;

    public function handle(IslandBelt $belt): void
    {
        if (! Islands::active()) {
            return;
        }
        $r = $belt->warm();
        if (($r['pending'] ?? 0) > 0) {
            self::dispatch()->delay(5);
        }
    }
}
