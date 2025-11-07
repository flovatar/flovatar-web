<?php

namespace App\Console\Commands;

use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class RandomizeFlobitPacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:randomize-flobit-pack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a JSON with randomized pack distribution';



    // 8000 Components (70 Legendary, 1200 Epic, 6730 Rare)

    protected $packCount = 1000;

    protected $packComponentRareTotal = 6730;
    protected $packComponentEpicTotal = 1200;
    protected $packComponentLegendaryTotal = 70;

    protected $packComponentCount = 8;

    protected $packComponentTotal;


    protected $componentsRare;
    protected $componentsEpic;
    protected $componentsLegendary;
    protected $componentsAllCount;

    protected $randArr = [];



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->packComponentTotal = $this->packComponentCount * $this->packCount;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $this->reloadFromDb();

        $this->preCheck();


        if (!$this->confirm('Continue with the randomization?', true)) {
            return 0;
        }

        $this->maxVariance = 0; //intval($this->ask('What is the maximum Variance accepted?'));

        $finalCheck = false;


        while(!$finalCheck){
            $this->info('Randomizing array');
            $this->randomizeArray(true);
            $this->info('Intermediate check');
            $finalCheck = $this->finalCheck(true);
        }


        $fileName = 'app/randomizer/pack_flobits_randomized_'.Carbon::now()->format('Y-m-d-H-i-s').'.json';
        Storage::disk('localstorage')->put($fileName, collect($this->randArr)->toJson());
        $this->info('All done! Randomization completed and stored in file ' . $fileName);

        $this->info('Final check for correct distribution');
        $this->finalCheck(true);

        $this->info('Process completed succesfully!');
    }


    protected function reloadFromDb(){
        $this->componentsRare = FlovatarComponent::readyForFlobitPack()->whereComponentRarity('rare')->inRandomOrder()->get();
        $this->componentsEpic = FlovatarComponent::readyForFlobitPack()->whereComponentRarity('epic')->inRandomOrder()->get();
        $this->componentsLegendary = FlovatarComponent::readyForFlobitPack()->whereComponentRarity('legendary')->inRandomOrder()->get();

        $this->componentsAllCount = $this->componentsRare->count() + $this->componentsEpic->count() + $this->componentsLegendary->count();
    }



    protected function preCheck(){
        $this->info('Pack Count: '.$this->packCount);
        $this->info('Components Rare Available: '.$this->componentsRare->count());
        $this->info('Components Epic Available: '.$this->componentsEpic->count());
        $this->info('Components Legendary Available: '.$this->componentsLegendary->count());
        $this->info('Component Required: '.$this->packComponentTotal);
        $this->info('Components Available: '.$this->componentsAllCount);

        if($this->componentsAllCount < $this->packComponentTotal){
            $this->error('Not enough Components available!');
            return 0;
        }
        if($this->componentsRare->count() < $this->packComponentRareTotal){
            $this->error('Not enough Rare Components available!');
            return 0;
        }
        if($this->componentsEpic->count() < $this->packComponentEpicTotal){
            $this->error('Not enough Epic Components available!');
            return 0;
        }
        if($this->componentsLegendary->count() < $this->packComponentLegendaryTotal){
            $this->error('Not enough Legendary Components available!');
            return 0;
        }
    }


    protected function randomizePacks(){
        $packs = collect($this->randArr);

        // Using cryptographically secure pseudo-random generator
        // https://www.php.net/manual/en/function.random-int.php
        $shuffleAmount = random_int(100,1000);

        for($i=0; $i < $shuffleAmount; $i++){
            $packs = $packs->shuffle();
        }

        $this->randArr = $packs->toArray();
    }



    protected function randomizeArray($showErrors = false){


        $this->reloadFromDb();


        $this->randArr = [];


        for($i = 0; $i < $this->packCount; $i++){
            $packArr = [
                'components' => []
            ];

            $this->randArr[] = $packArr;
        }


        $this->randomizePacks();
        for($i = 0; $i < $this->packComponentLegendaryTotal; $i++){
            $temp = $this->componentsLegendary->shift();
            $this->randArr[$i]['components'][$temp->flow_id] = 'legendary';
        }

        $this->randomizePacks();
        $tempEpicCount = 0;
        for($i = 0; $i < $this->packCount - $this->packComponentLegendaryTotal; $i++){
            $temp = $this->componentsEpic->shift();
            while($tempEpicCount < count($this->randArr) && count($this->randArr[$tempEpicCount]['components']) >= 1){
                $tempEpicCount++;
            }
            $this->randArr[$tempEpicCount]['components'][$temp->flow_id] = 'epic';
        }


        $this->randomizePacks();
        $remainingEpic = $this->componentsEpic->count();
        for($i = 0; $i < $remainingEpic; $i++){
            $temp = $this->componentsEpic->shift();
            $this->randArr[$i]['components'][$temp->flow_id] = 'epic';
        }

        $this->randomizePacks();
        for($i = 0; $i < $this->packCount; $i++){
            for ($j = count($this->randArr[$i]['components']); $j < $this->packComponentCount && $this->componentsRare->count() > 0; $j++) {
                $temp = $this->componentsRare->shift();
                $this->randArr[$i]['components'][$temp->flow_id] = 'rare';
            }
        }


        if($showErrors) {
            $this->info('Components Rare left ' . $this->componentsRare->count());
            $this->info('Components Epic left ' . $this->componentsEpic->count());
            $this->info('Components Legendary left ' . $this->componentsLegendary->count());
        }

    }





    protected function finalCheck($showErrors = false){

        $check = true;

        $checkPackComponentRareCount = 0;
        $checkPackComponentEpicCount = 0;
        $checkPackComponentLegendaryCount = 0;

        foreach($this->randArr as $key => $pack) {

            foreach ($pack['components'] as $rarity) {
                if ($rarity == 'rare') {
                    $checkPackComponentRareCount++;
                }
                if ($rarity == 'epic') {
                    $checkPackComponentEpicCount++;
                }
                if ($rarity == 'legendary') {
                    $checkPackComponentLegendaryCount++;
                }
            }

            if (count($pack['components']) != $this->packComponentCount) {
                if ($showErrors) {
                    $this->error('Invalid Component count on Pack ' . $key . ' - Found ' . count($pack['components']) . ' Components');
                }
                $check = false;
            }
        }


        if($showErrors) {

            if ($checkPackComponentRareCount != $this->packComponentRareTotal) {
                $this->error('Invalid Total Rare Component for Packs. Found ' . $checkPackComponentRareCount . ' - Expected ' . $this->packComponentRareTotal . ' = Diff ' . ($checkPackComponentRareCount - $this->packComponentRareTotal));
            }
            if ($checkPackComponentEpicCount != $this->packComponentEpicTotal) {
                $this->error('Invalid Total Epic Component for Packs. Found ' . $checkPackComponentEpicCount . ' - Expected ' . $this->packComponentEpicTotal . ' = Diff ' . ($checkPackComponentEpicCount - $this->packComponentEpicTotal));
            }
            if ($checkPackComponentLegendaryCount != $this->packComponentLegendaryTotal) {
                $this->error('Invalid Total Legendary Component for Packs. Found ' . $checkPackComponentLegendaryCount . ' - Expected ' . $this->packComponentLegendaryTotal . ' = Diff ' . ($checkPackComponentLegendaryCount - $this->packComponentLegendaryTotal));
            }

            $this->info('All checks completed!');
        }


        if(abs($checkPackComponentRareCount - $this->packComponentRareTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackComponentEpicCount - $this->packComponentEpicTotal) > $this->maxVariance){
            $check = false;
        }
        if(abs($checkPackComponentLegendaryCount - $this->packComponentLegendaryTotal) > $this->maxVariance){
            $check = false;
        }

        return $check;
    }


}
