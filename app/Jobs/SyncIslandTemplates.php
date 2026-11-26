<?php

namespace App\Jobs;

use App\Services\Islands\IslandChain;
use App\Support\Islands;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Refresh the island template cache (metadata + SVG) and the chain config after admin template/setting events. */
class SyncIslandTemplates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $timeout = 300;

    public function __construct(public array $forceIds = [])
    {
        $this->onQueue('default');
    }

    public function handle(IslandChain $chain): void
    {
        if (! Islands::active()) {
            return;
        }
        $chain->config(true);
        $chain->syncTemplates(false, $this->forceIds);
    }
}
