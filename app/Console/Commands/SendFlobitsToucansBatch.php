<?php

namespace App\Console\Commands;

use App\Jobs\SendToucanFlobits;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendFlobitsToucansBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-flobits-toucans {rarity} {color} {amount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Flobits to Toucans in batch';

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

        $rarity = $this->argument('rarity');
        $color = $this->argument('color');

        info('Sending '.$rarity);
        $this->info('Sending '.$rarity);

        $quantity = $this->argument('amount');
        $address = "0x2a0eccae942667be";

        $components = FlovatarComponent::whereHas('flovatarTemplate', function(Builder $query) use ($rarity, $color) {
            $query->where('category', 'NOT LIKE', 'boost')->where('category', 'NOT LIKE', 'spark');
            if($color != 'all'){
                $query->whereColor($color);
            }
            if($rarity == 'rare' || $rarity == 'common' || $rarity == 'epic' || $rarity == 'legendary' ) {
                $query->whereRarity($rarity);
            }
            return $query;
        })->whereFlowAddress(config('services.flow.address'))->whereDestroyed(0)->wherePacked(0)->inRandomOrder()->take($quantity)->get();


        if($components){
            info('Sending component '.$components->count());
            $this->info('Sending component '.$components->count());
            foreach($components as $component){
                $component->update([
                    'flow_address' => $address
                ]);
                $output = $output.$component->flow_id.' - '.$address."\n";
            }
            SendToucanFlobits::dispatch($components->pluck('flow_id'), $address);
        }


        info('Finished sending components '.$rarity);
        $this->info('Finished sending components '.$rarity);



        return true;
    }
}

