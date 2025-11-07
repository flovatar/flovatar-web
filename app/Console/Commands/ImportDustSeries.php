<?php

namespace App\Console\Commands;

use App\Models\DustSeries;
use App\Models\DustTemplate;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class ImportDustSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:import-dust-series {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Dust Series from a JSON file';

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
        $file = Storage::disk('localstorage')->get($this->argument('file'));
        $seriesJson = json_decode($file);

        if(isset($seriesJson->name)){
            DustSeries::updateOrCreate([
                'name' => $seriesJson->name
                ],[
                'svg_prefix' => $seriesJson->svgPrefix,
                'svg_suffix' => $seriesJson->svgSuffix,
                'price_increase' => $seriesJson->priceIncrease,
                'layers' => $seriesJson->layers,
                'colors' => $seriesJson->colors,
                'max_mintable' => $seriesJson->maxMintable,
                'flow_id' => null,
                'flow_address' => null
            ]);
            $this->info('Series '.$seriesJson->name.' created!');
        }

        $this->info('All done!');
    }

}
