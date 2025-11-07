<?php

namespace App\Console\Commands;

use App\Jobs\SendFlobits;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendSpark extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-spark {address} {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Spark';

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

        $output = "";

        $address = $this->argument('address');
        $quantity = $this->argument('quantity');

        info('Sending '.$quantity.' Sparks to '.$address);
        $this->info('Sending '.$quantity.' Sparks to '.$address);



            $components = FlovatarComponent::whereHas('flovatarTemplate', function(Builder $query) {
                return $query->whereCategory('spark');
            })->whereFlowAddress(config('services.flow.address'))->whereDestroyed(0)->wherePacked(0)->take($quantity)->get();


            if($components){
                info('Sending component '.$components->count().' to '.$address);
                $this->info('Sending component '.$components->count().' to '.$address);
                foreach($components as $component){
                    $component->update([
                        'flow_address' => $address
                    ]);
                    $output = $output.$component->flow_id.' - '.$address."\n";
                }
                SendFlobits::dispatch($components->pluck('flow_id'), $address);
            }


        info('Finished sending Sparks');
        $this->info('Finished sending Sparks');



        return true;
    }
}
