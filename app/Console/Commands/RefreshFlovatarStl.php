<?php

namespace App\Console\Commands;

use App\Jobs\ExportStl;
use App\Models\Flovatar;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshFlovatarStl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-flovatar-stl {flow_id?}';

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
            $this->info('Regenerating STL for Flovatar '.$this->argument('flow_id'));
            ExportStl::dispatch($this->argument('flow_id'));
        } else {
            $flovatars = Flovatar::select('flow_id')->whereNotNull('flow_id')->get();

            foreach($flovatars as $flovatar){
                $this->info('Regenerating STL for Flovatar '.$flovatar->flow_id);
                ExportStl::dispatch($flovatar->flow_id);
            }

            $this->info('Regenerated '.count($flovatars).' Flovatar STL');
        }

        $this->info('All done!');
    }
}
