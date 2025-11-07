<?php

namespace App\Console\Commands;

use App\Jobs\ExportAllImages;
use App\Models\Flobot;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshFlobotImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-flobot-images {flow_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates all the Flobot images from scratch!';

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
            $this->info('Regenerating image for Flobot '.$this->argument('flow_id'));
            ExportAllImages::dispatch($this->argument('flow_id'), 'flobot');
        } else {
            $flovatars = Flobot::select('flow_id')->whereNotNull('flow_id')->get();

            foreach($flovatars as $flovatar){
                $this->info('Regenerating image for Flobot '.$flovatar->flow_id);
                ExportAllImages::dispatch($flovatar->flow_id, 'flobot');
            }

            $this->info('Regenerated '.count($flovatars).' Flobot Images');
        }

        $this->info('All done!');
    }
}
