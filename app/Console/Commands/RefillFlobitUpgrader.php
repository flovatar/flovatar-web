<?php

namespace App\Console\Commands;

use App\Jobs\RefillFlobits;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RefillFlobitUpgrader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:refill-flobit-upgrader {rarity} {color} {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refills the Flobit Upgrader';

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

        info('Refilling '.$rarity);
        $this->info('Refilling '.$rarity);

            $components = FlovatarComponent::whereHas('flovatarTemplate', function(Builder $query) use ($rarity, $color) {
                $query->where('category', 'NOT LIKE', 'boost')->where('category', 'NOT LIKE', 'spark');
                if($color != 'all'){
                    $query->whereColor($color);
                }
                if($rarity == 'rare' || $rarity == 'common' || $rarity == 'epic' || $rarity == 'legendary' ) {
                    $query->whereRarity($rarity);
                }
                return $query;
            })->whereFlowAddress(config('services.flow.address'))->whereDestroyed(0)->wherePacked(0)->inRandomOrder()->take(intval($this->argument('quantity')))->get();


            if($components){
                info('Refilling component '.$components->count());
                $this->info('Refilling component '.$components->count());
                foreach($components as $component){
                    $component->update([
                        'packed' => 1
                    ]);
                    $output = $output.$component->flow_id."\n";
                }
                RefillFlobits::dispatch($components->pluck('flow_id'));
            }


        info('Finished refilling components '.$rarity);
            info(print_r($components->pluck('flow_id'), true));
        $this->info('Finished refilling components '.$rarity);



        return true;
    }
}
