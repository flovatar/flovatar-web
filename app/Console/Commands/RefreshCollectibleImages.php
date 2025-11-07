<?php

namespace App\Console\Commands;

use App\Jobs\ExportAllImages;
use App\Models\DustCollectible;
use App\Models\Flovatar;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RefreshCollectibleImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refresh-collectible-images {flow_id?} {flow_id_max?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates all the Dust Collectible images from scratch!';

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
            $this->info('Regenerating image for Collectible '.$this->argument('flow_id').' - '.$this->argument('flow_id_max'));
            for($i = $this->argument('flow_id'); $i <= $this->argument('flow_id_max'); $i++){
                ExportAllImages::dispatch($i, 'collectible');
            }
        } else if($this->argument('flow_id')){
            $this->info('Regenerating image for Collectible '.$this->argument('flow_id'));
            ExportAllImages::dispatch($this->argument('flow_id'), 'collectible');
        } else {
            $collectibles = DustCollectible::select('flow_id')->whereNotNull('flow_id')->get();

            foreach($collectibles as $collectible){
                $this->info('Regenerating image for Dust Collectible '.$collectible->flow_id);
                ExportAllImages::dispatch($collectible->flow_id, 'collectible');
            }

            $this->info('Regenerated '.count($collectibles).' Collectible Images');
        }

        $this->info('All done!');
    }
}
