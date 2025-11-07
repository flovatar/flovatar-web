<?php

namespace App\Console\Commands;

use App\Models\AsteroidTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class ImportAsteroidTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:import-asteroid-template {folder} {--replace} {--fakemint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Asteroid Templates NFT from an existing folder of SVG files';

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


        $i = AsteroidTemplate::count() + 1;
        foreach($files as $file){
            if(!Str::contains($file, ['.DS_Store'])){
                $name = str_replace($this->argument('folder').'/','', $file);
                $folderArr = explode('/', $name);
                if(count($folderArr) == 2){
                    $fullName = str_replace('.svg', '', $folderArr[1]);
                    $fullNameArr = explode('_',$fullName);
                    $name = str_replace('-', ' ', $fullNameArr[0]);
                    $rarity = $fullNameArr[1];
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
                        'linear-gradient-',
                        'clippath-',
                    ];

                    $arrReplaceId = [
                        'clippath',
                        'linear-gradient',
                        'billboard-mask'
                    ];


                    foreach($arrReplaceSequence as $replaceId){
                        $fileContent = $this->replaceStringSequence($replaceId, $i, $fileContent);
                    }
                    foreach($arrReplaceId as $replaceId){
                        $fileContent = $this->replaceStringId($replaceId, $i, $fileContent);
                    }



                    $this->info('Importing '.$category.' - '.$name);
                    AsteroidTemplate::updateOrCreate([
                        'name' => $name,
                        'layer' => $category,
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
            $text = str_replace('"'.$search.$j.'"', '"'.$search.$j.'-ast'.$i.'"', $text);
            $text = str_replace('"#'.$search.$j.'"', '"#'.$search.$j.'-ast'.$i.'"', $text);
            $text = str_replace('(#'.$search.$j.')', '(#'.$search.$j.'-ast'.$i.')', $text);
        }
        return $text;
    }

    protected function replaceStringId($search, $i, $text){
        $text = str_replace('"'.$search.'"', '"'.$search.'-ast'.$i.'"', $text);
        $text = str_replace('"#'.$search.'"', '"#'.$search.'-ast'.$i.'"', $text);
        $text = str_replace('(#'.$search.')', '(#'.$search.'-ast'.$i.')', $text);
        return $text;
    }
    protected function replaceString($search, $i, $text){
        return str_replace($search, $search.'-'.$i, $text);
    }
}
