<?php

namespace App\Console\Commands;

use App\Jobs\SyncCollectibleTemplate;
use App\Models\DustTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshCollectibleTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-collectible-templates {flow_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates all the Collectible templates from scratch!';

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
            $this->info('Refreshing Collectible Template '.$this->argument('flow_id'));
            $template = DustTemplate::whereFlowId($this->argument('flow_id'))->firstOrFail();
            SyncCollectibleTemplate::dispatch($template);
        } else {
            $templates = DustTemplate::whereNotNull('flow_id')->orderBy('flow_id','desc')->get();

            foreach($templates as $template){
                $this->info('Refreshing Collectible Template '.$template->flow_id);
                SyncCollectibleTemplate::dispatch($template);
            }

            $this->info('Refreshed '.count($templates).' Collectible Templates');
        }

        $this->info('All done!');
    }
}
