<?php

namespace App\Console\Commands;

use App\Jobs\MintDustSeries as MintDustSeriesJob;
use App\Models\DustSeries;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;

class MintDustSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:mint-dust-series';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates the Dust Series from the DB';

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
        $series = DustSeries::whereNull('flow_id')->get();

        foreach($series as $item){
            info('Creating NFT for Series '.$item->id);
            $this->info('Creating NFT for Series '.$item->id);
            MintDustSeriesJob::dispatch($item);
        }


        $this->info('All done!');
    }
}
