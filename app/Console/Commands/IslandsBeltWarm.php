<?php

namespace App\Console\Commands;

use App\Services\Islands\IslandBelt;
use App\Support\Islands;
use Illuminate\Console\Command;

/** Texture cells + atlas pages of the WebGL belt on /islands (no-op when nothing changed). */
class IslandsBeltWarm extends Command
{
    protected $signature = 'islands:belt-warm {--max=400 : render downloads per run} {--budget=45 : seconds}';

    protected $description = 'Build the missing texture cells and atlas pages of the /islands WebGL belt';

    public function handle(IslandBelt $belt): int
    {
        if (! Islands::active()) {
            $this->line('islands: off');

            return self::SUCCESS;
        }
        $r = $belt->warm((int) $this->option('max'), (float) $this->option('budget'));
        $this->line('belt: '.json_encode($r));

        return self::SUCCESS;
    }
}
