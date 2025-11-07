<?php

namespace App\Console\Commands;

use App\Jobs\SyncGptAssistant;
use App\Models\DustTemplate;
use App\Models\GptAssistant;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshGptAssistants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-assistants {flow_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates all the Gpt Assistants!';

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
            $this->info('Refreshing Assistant '.$this->argument('flow_id'));
            $assistant = GptAssistant::whereFlowId($this->argument('flow_id'))->firstOrFail();
            SyncGptAssistant::dispatch($assistant);
        } else {
            $assistants = GptAssistant::whereNotNull('flow_id')->whereNotNull('gpt_id')->orderBy('flow_id','desc')->get();

            foreach($assistants as $assistant){
                $this->info('Refreshing Assistant '.$assistant->flow_id);
                SyncGptAssistant::dispatch($assistant);
            }

            $this->info('Refreshed '.count($assistants).' Assistants!');
        }

        $this->info('All done!');
    }
}
