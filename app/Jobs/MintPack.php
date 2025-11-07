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

class MintPack extends BaseJob
{

    protected $quantity;
    protected $sparkQuantity;
    protected $series;
    protected $name;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($quantity = 1, $sparkQuantity = 1, $series = 1, $name = 'Flovatar Pack')
    {
        $this->quantity = $quantity;
        $this->sparkQuantity = $sparkQuantity;
        $this->series = $series;
        $this->name = $name;
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
        for($i=0; $i < $this->quantity; $i++){
            $this->mintPack();
        }
    }

    protected function mintPack()
    {
        $sparks = FlovatarComponent::readyForPack()->whereCategory('spark')->take($this->sparkQuantity)->get();
        $totalComponents = 5;
        $components = FlovatarComponent::readyForPack()->whereNotCategory('spark')->inRandomOrder()->take($totalComponents)->get();



        if($totalComponents != $components->count() || $this->sparkQuantity != $sparks->count()){
            info('Skipping Pack mint because some component is missing');
            info(print_r($sparks,true));
            info(print_r($components,true));
            return false;
        } else {

            $argInt = array_merge($sparks->pluck('flow_id')->toArray(), $components->pluck('flow_id')->toArray());


            $secret = Str::random(32);

            info('Creating Pack with secret '.$secret);



            Flow::transaction('./transactions/create/pack.cdc')
                ->argArrayInt($argInt)
                ->argString($secret)
                ->argFix(''.(floatval(config('flovatar.price')) * $this->sparkQuantity))
                ->argInt32($this->sparkQuantity)
                ->argInt32($this->series)
                ->argString($this->name)
                ->signer(config('flovatar.signer'))
                ->run();

            info('Created Pack in Flow with secret '.$secret);

            $pack = Pack::create([
                'secret' => $secret,
                'spark_count' => $this->sparkQuantity,
                'series' => $this->series,
                'name' => $this->name
            ]);


            info('Created Pack in DB with secret '.$secret.' and ID '.$pack->id);


            $sparks->each(function($spark) {
                $spark->update(['packed' => 1]);
            });
            $components->each(function($component) {
                $component->update(['packed' => 1]);
            });
        }

    }
}
