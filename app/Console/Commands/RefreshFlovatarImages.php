<?php

namespace App\Console\Commands;

use App\Jobs\ExportAllImages;
use App\Models\Flovatar;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshFlovatarImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-flovatar-images {flow_id?} {flow_id_max?}';

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
        if($this->argument('flow_id') && $this->argument('flow_id_max')){
            $this->info('Regenerating image for Flovatar '.$this->argument('flow_id').' - '.$this->argument('flow_id_max'));
            for($i = $this->argument('flow_id'); $i <= $this->argument('flow_id_max'); $i++){
                ExportAllImages::dispatch($i);
            }
        } else if($this->argument('flow_id')){
            $this->info('Regenerating image for Flovatar '.$this->argument('flow_id'));
            ExportAllImages::dispatch($this->argument('flow_id'));
        } else {
            $flovatars = Flovatar::select('flow_id')->whereNotNull('flow_id')->orderBy('flow_id','desc')->get();

            foreach($flovatars as $flovatar){
                $this->info('Regenerating image for Flovatar '.$flovatar->flow_id);
                ExportAllImages::dispatch($flovatar->flow_id);
            }

            $this->info('Regenerated '.count($flovatars).' Flovatar Images');
        }

        $this->info('All done!');
    }
}
