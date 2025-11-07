<?php

namespace App\Console\Commands;

use App\Jobs\MintPackFileBatch as MintPackJob;
use App\Models\FlovatarComponent;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MintPack extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:mint-pack {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new pack';

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
        info('Creating Packs');
        $this->info('Creating Packs');


        $file = Storage::get('randomizer/'.$this->argument('file'));

        $packArr = json_decode($file);

        $this->mintPackBatch($packArr->standard, config('flovatar.price'), 'Genesis Standard Pack', 4);
        $this->mintPackBatch($packArr->deluxe, config('flovatar.price2'), 'Genesis Deluxe Pack', 6);


        $this->info('All done!');
    }


    /*
     *
     *

components: [UInt64],
randomString: [String],
price: [UFix64],
sparkCount: UInt32,
series: UInt32,
name: [String],
packCount: UInt32,
compPerPack: UInt32
    ''.floatval($this->price)

    __construct($components, $price, $name, $series = 1, $sparkQuantity = 1, $packQuantity = 1, $compPerPack = 1)
     */



    protected function mintPackBatch($packs, $price, $name, $compPerPack){

        $packCount = 10;

        $i = 0;
        $components = [];
        $counter = 0;
        foreach($packs as $pack){
            $sparkComp = FlovatarComponent::whereFlowId($pack->spark)->first();

            if(!$sparkComp->packed){
                $components[] = $pack->spark;
                foreach($pack->components as $key => $val){
                    $components[] = $key;
                }
                foreach($pack->boost as $key => $val){
                    $components[] = $key;
                }


                $i++;
                if($i >= $packCount){

                    info('Creating Batch Pack '.$name);
                    //info(print_r($components, true));



                    MintPackJob::dispatch($components, ''.floatval($price), $name, 1, 1, $i, $compPerPack, $this->getSecret($i));
                    $i=0;
                    $components = [];
                }
            }
            $counter++;

        }

        if(count($components) > 0 && $i > 0){
            MintPackJob::dispatch($components, ''.floatval($price), $name, 1, 1, $i, $compPerPack, $this->getSecret($i));
        }

    }


    protected function getSecret($count){
        $secret = [];

        for($j = 0; $j < $count; $j++){
            $secret[] = Str::random(32);
        }
        return $secret;
    }

    protected function objToArr($obj){
        $arr = [];
        foreach($obj as $key => $val){
            $arr[] = $key;
        }
        return $arr;
    }
}
