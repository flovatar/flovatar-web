<?php

namespace App\Console\Commands;

use App\Jobs\SyncFlovatar;
use App\Models\Flovatar;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshFlovatarMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-flovatar-metadata {flow_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates all the Flovatar metadata from the blockchain!';

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
            $flovatar = Flovatar::whereFlowId($this->argument('flow_id'))->first();
            $this->info('Syncing Flovatar '.$this->argument('flow_id'));
            if($flovatar){
                SyncFlovatar::dispatch($flovatar);
            }
        } else {
            $flovatars = Flovatar::select('flow_id')->whereNotNull('flow_id')->get();

            foreach($flovatars as $flovatarId){
                $flovatar = Flovatar::whereFlowId($flovatarId->flow_id)->first();
                $this->info('Syncing Flovatar '.$flovatar->flow_id);
                SyncFlovatar::dispatch($flovatar);
            }

            $this->info('Synced '.count($flovatars).' Flovatars');
        }

        $this->info('All done!');
    }
}
