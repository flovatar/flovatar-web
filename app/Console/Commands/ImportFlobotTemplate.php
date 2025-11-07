<?php

namespace App\Console\Commands;

use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class ImportFlobotTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:import-flobot-template {folder} {--replace}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Flobot Template NFT from an existing folder of SVG files';

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

        $i = FlovatarTemplate::count() + 1;
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
                    if($max_mintable <= 10){
                        $rarity = 'legendary';
                    } else if($max_mintable <= 50){
                        $rarity = 'epic';
                    } else if($max_mintable <= 250){
                        $rarity = 'rare';
                    }
                    $category = $folderArr[0];


                    if($name == 'Rare' && $category == 'boost'){
                        $rarity = 'rare';
                    }
                    if($name == 'Epic' && $category == 'boost'){
                        $rarity = 'epic';
                    }
                    if($name == 'Legendary' && $category == 'boost'){
                        $rarity = 'legendary';
                    }


                    if($category == 'land' || $category == 'flobotkit' || $category == 'fub'){
                        if($max_mintable <= 400){
                            $rarity = 'legendary';
                        } else if($max_mintable <= 2000){
                            $rarity = 'epic';
                        }
                    }


                    $fileContent = Storage::disk('localstorage')->get($file);

                    $fileContent = str_replace('</svg>', '', $fileContent);
                    $fileContent = preg_replace('/<svg[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/<\?xml[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/<!DOCTYPE[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/^<rect[^>]*height="1600"[^>]*>/', '', $fileContent);

                    $fileContent = str_replace('  ', ' ', $fileContent);
                    $fileContent = str_replace("\n", '', $fileContent);
                    $fileContent = str_replace('x-link:href', 'href', $fileContent);



                    $arrReplaceFlobot = [
                        'clip-path',
                        'clip-path-2',
                        'clip-path-3',
                        'clip-path-4',
                        'clip-path-5',
                        'clip-path-6',
                        'clip-path-7',
                        'linear-gradient',
                        'linear-gradient-2',
                        'linear-gradient-3',
                        'linear-gradient-4',
                        'linear-gradient-5',
                        'linear-gradient-6',
                        'linear-gradient-7',
                        'radial-gradient',
                        'radial-gradient-2',
                        'radial-gradient-3',
                        'radial-gradient-3',
                        'radial-gradient-4',
                        'radial-gradient-5',
                        'radial-gradient-6',
                        'radial-gradient-7',
                        'mask_'
                    ];

                    if($this->option('replace')){

                        foreach($arrReplaceFlobot as $replaceId){
                            $fileContent = $this->replaceStringIdFlobot($replaceId, $i, $fileContent);
                        }

                    }





                    $this->info('Importing '.$category.' - '.$name);
                    FlovatarTemplate::updateOrCreate([
                        'name' => $name,
                        'category' => $category,
                        'series' => ($category == 'fub' || $category == 'land') ? 3 : 2,
                        'color' => 'default',
                        'max_mintable' => $max_mintable,
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

    protected function replaceStringIdFlobot($search, $i, $text){
        $text = str_replace('"'.$search.'"', '"'.$i.'-'.$search.'"', $text);
        $text = str_replace('"#'.$search.'"', '"#'.$i.'-'.$search.'"', $text);
        $text = str_replace('(#'.$search.')', '(#'.$i.'-'.$search.')', $text);
        return $text;
    }
    protected function replaceString($search, $i, $text){
        return str_replace($search, $search.'-'.$i, $text);
    }
}
