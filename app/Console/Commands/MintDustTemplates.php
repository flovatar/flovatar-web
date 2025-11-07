<?php

namespace App\Console\Commands;

use App\Jobs\MintDustTemplate as MintDustTemplateJob;
use App\Models\DustSeries;
use App\Models\DustTemplate;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;

class MintDustTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:mint-dust-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates the Dust Templates from the DB';

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
        $series = DustTemplate::whereNull('flow_id')->whereDustSeriesId(1)->get();

        foreach($series as $item){
            info('Creating NFT for Dust Template '.$item->id);
            $this->info('Creating NFT for Dust Template '.$item->id);
            MintDustTemplateJob::dispatch($item);
        }


        $this->info('All done! '.count($series));
    }
}
