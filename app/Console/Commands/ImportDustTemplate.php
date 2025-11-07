<?php

namespace App\Console\Commands;

use App\Models\DustSeries;
use App\Models\DustTemplate;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class ImportDustTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:import-dust-template {folder} {--replace} {--fakemint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Dust Template NFT from an existing folder of SVG files';

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
        $files = Storage::disk('localstorage')->allFiles($this->argument('folder'));

        /*
         *
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->longText('svg_prefix')->nullable();
            $table->longText('svg_suffix')->nullable();
            $table->float('price_increase')->default(0)->index();
            $table->json('layers')->nullable();
            $table->json('colors')->nullable();
            $table->integer('max_mintable')->nullable();
            $table->integer('flow_id')->nullable()->unique();
            $table->json('flow_metadata')->nullable();
         */
        if(DustSeries::count() < 1){
            DustSeries::create([
                'name' => 'Psyche Likee',
                'svg_prefix' => config('flovatar.svgOpenCollectible'),
                'svg_suffix' => config('flovatar.svgCloseCollectible'),
                'price_increase' => 0.05,
                'layers' => [],
                'colors' => [],
                'max_mintable' => 20000,
                'flow_id' => 1,
                'flow_address' => config('flovatar.contractAddress')
            ]);
        }

        $i = DustTemplate::count() + 1;
        foreach($files as $file){
            if(!Str::contains($file, ['.DS_Store'])){
                $name = str_replace($this->argument('folder').'/','', $file);
                $folderArr = explode('/', $name);
                if(count($folderArr) == 2){
                    $fullName = str_replace('.svg', '', $folderArr[1]);
                    $fullNameArr = explode('_',$fullName);
                    $name = str_replace('-', ' ', $fullNameArr[0]);
                    $max_mintable = intval($fullNameArr[1]);
                    $rarity = 'common';
                    $price = 50;
                    if($max_mintable <= 10){
                        $rarity = 'legendary';
                        $price = 6250;
                    } else if($max_mintable <= 50){
                        $rarity = 'epic';
                        $price = 1250;
                    } else if($max_mintable <= 250){
                        $rarity = 'rare';
                        $price = 250;
                    }
                    $category = $folderArr[0];



                    $fileContent = Storage::disk('localstorage')->get($file);

                    $fileContent = str_replace('</svg>', '', $fileContent);
                    $fileContent = preg_replace('/<svg[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/<\?xml[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/<!DOCTYPE[^>]*>/', '', $fileContent);
                    $fileContent = str_replace('  ', ' ', $fileContent);
                    $fileContent = str_replace("\n", '', $fileContent);
                    $fileContent = str_replace('x-link:href', 'href', $fileContent);



                    $arrReplaceSequence = [
                        'cls-',
                        'linear-gradient-',
                        'radial-gradient-',
                        'Gradient_',
                        'Scene-',
                        'filter',
                        'Layer'
                    ];

                    $arrReplaceId = [
                        'linear-gradient',
                        'radial-gradient'
                    ];

                    if($this->option('replace')){

                        foreach($arrReplaceSequence as $replaceId){
                            $fileContent = $this->replaceStringSequence($replaceId, $i, $fileContent);
                        }
                        foreach($arrReplaceId as $replaceId){
                            $fileContent = $this->replaceStringId($replaceId, $i, $fileContent);
                        }

                    }


                    $this->info('Importing '.$category.' - '.$name);
                    DustTemplate::updateOrCreate([
                        'name' => $name,
                        'layer' => $category,
                        'base_price' => $price,
                        'current_price' => $price,
                        'dust_series_id' => DustSeries::orderBy('flow_id', 'desc')->firstOrFail()->flow_id,
                        'max_mintable' => $max_mintable,
                        'flow_id' => $this->option('fakemint') ? $i : null,
                        'flow_address' => $this->option('fakemint') ? config('flovatar.contractAddress') : null,
                        'rarity' => $rarity
                    ],
                        [
                            'svg' => $fileContent
                        ]
                    );
                    $i++;
                }

            }
        }

        $this->info('All done!');
    }

    protected function replaceStringSequence($search, $i, $text){
        for($j = 100; $j >= 0; $j--) {
            $text = str_replace($search.$j, $search.$j.'-'.$i, $text);
        }
        return $text;
    }

    protected function replaceStringId($search, $i, $text){
        $text = str_replace('"'.$search.'"', '"'.$search.'-'.$i.'"', $text);
        $text = str_replace('"#'.$search.'"', '"#'.$search.'-'.$i.'"', $text);
        $text = str_replace('(#'.$search.')', '(#'.$search.'-'.$i.')', $text);
        return $text;
    }
    protected function replaceString($search, $i, $text){
        return str_replace($search, $search.'-'.$i, $text);
    }
}
