<?php

namespace App\Console\Commands;

use App\Jobs\MoveStl;
use App\Models\Flovatar;
use Illuminate\Console\Command;
use Carbon\Carbon;

class MoveFlovatarStl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:move-stl {flow_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates all the Flovatar images from scratch!';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if($this->argument('flow_id')){
            $this->info('Moving STL for Flovatar '.$this->argument('flow_id'));
            MoveStl::dispatch($this->argument('flow_id'));
        }

        $this->info('All done!');
    }
}
