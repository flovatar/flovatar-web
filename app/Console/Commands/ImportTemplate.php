<?php

namespace App\Console\Commands;

use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class ImportTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:import-template {folder} {--replace} {--replacesportvatar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the Template NFT from an existing folder of SVG files';

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

                    $fileContent = Storage::disk('localstorage')->get($file);

                    $fileContent = str_replace('</svg>', '', $fileContent);
                    $fileContent = preg_replace('/<svg[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/<\?xml[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/<!DOCTYPE[^>]*>/', '', $fileContent);
                    $fileContent = preg_replace('/^<rect[^>]*height="1600"[^>]*>/', '', $fileContent);

                    $fileContent = str_replace('  ', ' ', $fileContent);
                    $fileContent = str_replace("\n", '', $fileContent);
                    $fileContent = str_replace('x-link:href', 'href', $fileContent);


                    $arrReplaceSequence = [
                        'cls-',
                        'linear-gradient-',
                        'clippath-',
                        'radial-gradient-',
                        'Gradient_',
                        'Scene-',
                        'filter',
                        'Layer'
                    ];

                    $arrReplaceId = [
                        'linear-gradient',
                        'radial-gradient',
                        'body',
                        'body-shade',
                        'body_ground_shadwo',
                        'outline',
                        'ear',
                        'Shadow',
                        'BallShadow',
                        'sheen',
                        'glow',
                        'brain',
                        'brain-shade',
                        'brain-outline',
                        'shine',
                        'colours',
                        'cyborg-suit',
                        'GLOW_BOD_copy',
                        'body-shade',
                        'gashes',
                        'eye-edge',
                        'eyelid-left',
                        'eyelid-right',
                        'eye-shadow',
                        'eye-shadow-right',
                        'eye-left',
                        'eye-right',
                        'laser-left',
                        'laser-right',
                        'pupil',
                        'pupil-closed',
                        'pupil-glow',
                        'ooze',
                        'ooze-drip',
                        'dribble',
                        'goblin-body',
                        'goblin-shade',
                        'ear-shade',
                        'goblin-outline',
                        'glass-top',
                        'brain-shade',
                        'brain-outline',
                        'spots',
                        'clippath',
                    ];

                    $arrReplaceSportvatar = [
                        'clip-path',
                        'linear-gradient',
                        'radial-gradient',
                    ];

                    if($this->option('replace')){

                        foreach($arrReplaceSequence as $replaceId){
                            $fileContent = $this->replaceStringSequence($replaceId, $i, $fileContent);
                        }
                        foreach($arrReplaceId as $replaceId){
                            $fileContent = $this->replaceStringId($replaceId, $i, $fileContent);
                        }

                    }


                    if($this->option('replacesportvatar')){
                        foreach($arrReplaceSportvatar as $replaceId){
                            $fileContent = $this->replaceStringIdSportvatar($replaceId, $i, $fileContent);
                        }

                    }



                    if($name == 'Rare' && $category == 'boost'){
                        $rarity = 'rare';
                    }
                    if($name == 'Epic' && $category == 'boost'){
                        $rarity = 'epic';
                    }
                    if($name == 'Legendary' && $category == 'boost'){
                        $rarity = 'legendary';
                    }

                    $this->info('Importing '.$category.' - '.$name);
                    FlovatarTemplate::updateOrCreate([
                        'name' => $name,
                        'category' => $category,
                        'series' => 1,
                        'color' => 'default',
                        'rarity' => $rarity,
                        'max_mintable' => $max_mintable
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

    protected function replaceStringIdSportvatar($search, $i, $text){
        $text = str_replace('id="'.$search.'"', 'id="'.$i.'-'.$search.'"', $text);
        $text = str_replace('#'.$search, '#'.$i.'-'.$search, $text);
        return $text;
    }
    protected function replaceString($search, $i, $text){
        return str_replace($search, $search.'-'.$i, $text);
    }
}
