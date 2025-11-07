<?php

namespace App\Jobs;

use App\Models\FlovatarComponent;
use App\Models\Pack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Flow;
use Illuminate\Support\Str;

class MintPackFileBatch extends BaseJob
{

    protected $components;
    protected $sparkQuantity;
    protected $series;
    protected $name;
    protected $price;
    protected $packQuantity;
    protected $compPerPack;
    protected $secret;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($components, $price, $name, $series = 1, $sparkQuantity = 1, $packQuantity = 1, $compPerPack = 1, $secret)
    {
        $this->components = $components;
        $this->sparkQuantity = $sparkQuantity;
        $this->series = $series;
        $this->name = $name;
        $this->price = $price;
        $this->packQuantity = $packQuantity;
        $this->compPerPack = $compPerPack;

        $this->secret = $secret;

        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     */
    public function handle()
    {

        //info('Creating Pack with secret '.$secret);


        Flow::transaction('./transactions/create/pack_batch.cdc')
            ->argArrayInt($this->components)
            ->argArrayString($this->secret)
            ->argFix($this->price)
            ->argInt32($this->sparkQuantity)
            ->argInt32($this->series)
            ->argString($this->name)
            ->argInt32($this->packQuantity)
            ->argInt32($this->compPerPack)
            ->signer(config('flovatar.signer'))
            ->debug()
            ->run();

        info('Created Pack Batch in Flow');


        for($i = 0; $i < $this->packQuantity; $i++){
            $pack = Pack::create([
                'secret' => $this->secret[$i],
                'spark_count' => $this->sparkQuantity,
                'series' => $this->series,
                'name' => $this->name
            ]);
            info('Created Pack in DB with secret '.$this->secret[$i].' and ID '.$pack->id);
        }




        foreach($this->components as $componentId){
            $component = FlovatarComponent::whereFlowId($componentId)->first();
            if($component){
                $component->update(['packed' => 1]);
            }
        }

    }

}
