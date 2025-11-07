<?php

namespace App\Console\Commands;

use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Str;

class RandomizeTerraKitPacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:randomize-terrakit-pack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a JSON with randomized pack distribution';



    // 8000 Components (70 Legendary, 1200 Epic, 6730 Rare)

    protected $packCount = 5000;

    protected $packComponentRareTotal = 4400;
    protected $packComponentEpicTotal = 500;
    protected $packComponentLegendaryTotal = 100;

    protected $packComponentCount = 3;

    protected $packComponentTotal;


    protected $flobotRare;
    protected $flobotEpic;
    protected $flobotLegendary;
    protected $flobotAllCount;

    protected $landRare;
    protected $landEpic;
    protected $landLegendary;
    protected $landAllCount;

    protected $fubRare;
    protected $fubEpic;
    protected $fubLegendary;
    protected $fubAllCount;

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


        $fileName = 'app/randomizer/pack_terrakit_randomized_'.Carbon::now()->format('Y-m-d-H-i-s').'.json';
        Storage::disk('localstorage')->put($fileName, collect($this->randArr)->toJson());
        $this->info('All done! Randomization completed and stored in file ' . $fileName);

        $this->info('Final check for correct distribution');
        $this->finalCheck(true);

        $this->info('Process completed succesfully!');
    }


    protected function reloadFromDb(){
        $this->flobotRare = FlovatarComponent::readyForTerraKitPack(2)->whereCategory('flobotkit')->whereComponentRarity('common')->inRandomOrder()->get();
        $this->flobotEpic = FlovatarComponent::readyForTerraKitPack(2)->whereCategory('flobotkit')->whereComponentRarity('epic')->inRandomOrder()->get();
        $this->flobotLegendary = FlovatarComponent::readyForTerraKitPack(2)->whereCategory('flobotkit')->whereComponentRarity('legendary')->inRandomOrder()->get();

        $this->landRare = FlovatarComponent::readyForTerraKitPack(3)->whereCategory('land')->whereComponentRarity('common')->inRandomOrder()->get();
        $this->landEpic = FlovatarComponent::readyForTerraKitPack(3)->whereCategory('land')->whereComponentRarity('epic')->inRandomOrder()->get();
        $this->landLegendary = FlovatarComponent::readyForTerraKitPack(3)->whereCategory('land')->whereComponentRarity('legendary')->inRandomOrder()->get();

        $this->fubRare = FlovatarComponent::readyForTerraKitPack(3)->whereCategory('fub')->whereComponentRarity('common')->inRandomOrder()->get();
        $this->fubEpic = FlovatarComponent::readyForTerraKitPack(3)->whereCategory('fub')->whereComponentRarity('epic')->inRandomOrder()->get();
        $this->fubLegendary = FlovatarComponent::readyForTerraKitPack(3)->whereCategory('fub')->whereComponentRarity('legendary')->inRandomOrder()->get();

        $this->componentsAllCount = $this->flobotRare->count() + $this->flobotEpic->count() + $this->flobotLegendary->count()
        + $this->landRare->count() + $this->landEpic->count() + $this->landLegendary->count()
        + $this->fubRare->count() + $this->fubEpic->count() + $this->fubLegendary->count();
    }



    protected function preCheck(){
        $this->info('Pack Count: '.$this->packCount);
        $this->info('Flobot Common Available: '.$this->flobotRare->count());
        $this->info('Flobot Epic Available: '.$this->flobotEpic->count());
        $this->info('Flobot Legendary Available: '.$this->flobotLegendary->count());
        $this->info('Land Common Available: '.$this->landRare->count());
        $this->info('Land Epic Available: '.$this->landEpic->count());
        $this->info('Land Legendary Available: '.$this->landLegendary->count());
        $this->info('FUB Common Available: '.$this->fubRare->count());
        $this->info('FUB Epic Available: '.$this->fubEpic->count());
        $this->info('FUB Legendary Available: '.$this->fubLegendary->count());
        $this->info('Component Required: '.$this->packComponentTotal);
        $this->info('Components Available: '.$this->componentsAllCount);

        if($this->componentsAllCount < $this->packComponentTotal){
            $this->error('Not enough Components available!');
            return 0;
        }
        if($this->flobotRare->count() < $this->packComponentRareTotal){
            $this->error('Not enough Common Components available!');
            return 0;
        }
        if($this->flobotEpic->count() < $this->packComponentEpicTotal){
            $this->error('Not enough Epic Components available!');
            return 0;
        }
        if($this->flobotLegendary->count() < $this->packComponentLegendaryTotal){
            $this->error('Not enough Legendary Components available!');
            return 0;
        }
        if($this->landRare->count() < $this->packComponentRareTotal){
            $this->error('Not enough Common Components available!');
            return 0;
        }
        if($this->landEpic->count() < $this->packComponentEpicTotal){
            $this->error('Not enough Epic Components available!');
            return 0;
        }
        if($this->landLegendary->count() < $this->packComponentLegendaryTotal){
            $this->error('Not enough Legendary Components available!');
            return 0;
        }
        if($this->fubRare->count() < $this->packComponentRareTotal){
            $this->error('Not enough Common Components available!');
            return 0;
        }
        if($this->fubEpic->count() < $this->packComponentEpicTotal){
            $this->error('Not enough Epic Components available!');
            return 0;
        }
        if($this->fubLegendary->count() < $this->packComponentLegendaryTotal){
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
                'flobot' => [],
                'land' => [],
                'fub' => []
            ];

            $this->randArr[] = $packArr;
        }


        $this->randomizePacks();
        for($i = 0; $i < $this->packComponentLegendaryTotal; $i++){
            $temp = $this->flobotLegendary->shift();
            $this->randArr[$i]['flobot'][$temp->flow_id] = 'legendary';
        }

        $this->randomizePacks();
        $tempEpicCount = 0;
        for($i = 0; $i < $this->packComponentEpicTotal; $i++){
            $temp = $this->flobotEpic->shift();
            while($tempEpicCount < count($this->randArr) && count($this->randArr[$tempEpicCount]['flobot']) >= 1){
                $tempEpicCount++;
            }
            $this->randArr[$tempEpicCount]['flobot'][$temp->flow_id] = 'epic';
        }

        $this->randomizePacks();
        for($i = 0; $i < $this->packCount; $i++){
            if(count($this->randArr[$i]['flobot']) == 0){
                $temp = $this->flobotRare->shift();
                $this->randArr[$i]['flobot'][$temp->flow_id] = 'rare';
            }
        }




        $this->randomizePacks();
        for($i = 0; $i < $this->packComponentLegendaryTotal; $i++){
            $temp = $this->landLegendary->shift();
            $this->randArr[$i]['land'][$temp->flow_id] = 'legendary';
        }

        $this->randomizePacks();
        $tempEpicCount = 0;
        for($i = 0; $i < $this->packComponentEpicTotal; $i++){
            $temp = $this->landEpic->shift();
            while($tempEpicCount < count($this->randArr) && count($this->randArr[$tempEpicCount]['land']) >= 1){
                $tempEpicCount++;
            }
            $this->randArr[$tempEpicCount]['land'][$temp->flow_id] = 'epic';
        }

        $this->randomizePacks();
        for($i = 0; $i < $this->packCount; $i++){
            if(count($this->randArr[$i]['land']) == 0){
                $temp = $this->landRare->shift();
                $this->randArr[$i]['land'][$temp->flow_id] = 'rare';
            }
        }





        $this->randomizePacks();
        for($i = 0; $i < $this->packComponentLegendaryTotal; $i++){
            $temp = $this->fubLegendary->shift();
            $this->randArr[$i]['fub'][$temp->flow_id] = 'legendary';
        }

        $this->randomizePacks();
        $tempEpicCount = 0;
        for($i = 0; $i < $this->packComponentEpicTotal; $i++){
            $temp = $this->fubEpic->shift();
            while($tempEpicCount < count($this->randArr) && count($this->randArr[$tempEpicCount]['fub']) >= 1){
                $tempEpicCount++;
            }
            $this->randArr[$tempEpicCount]['fub'][$temp->flow_id] = 'epic';
        }

        $this->randomizePacks();
        for($i = 0; $i < $this->packCount; $i++){
            if(count($this->randArr[$i]['fub']) == 0){
                $temp = $this->fubRare->shift();
                $this->randArr[$i]['fub'][$temp->flow_id] = 'rare';
            }
        }


        if($showErrors) {
            $this->info('Flobot Common left ' . $this->flobotRare->count());
            $this->info('Flobot Epic left ' . $this->flobotEpic->count());
            $this->info('Flobot Legendary left ' . $this->flobotLegendary->count());
            $this->info('Land Common left ' . $this->landRare->count());
            $this->info('Land Epic left ' . $this->landEpic->count());
            $this->info('Land Legendary left ' . $this->landLegendary->count());
            $this->info('FUB Common left ' . $this->fubRare->count());
            $this->info('FUB Epic left ' . $this->fubEpic->count());
            $this->info('FUB Legendary left ' . $this->fubLegendary->count());
        }

    }





    protected function finalCheck($showErrors = false){

        $check = true;

        $checkPackFlobotRareCount = 0;
        $checkPackFlobotEpicCount = 0;
        $checkPackFlobotLegendaryCount = 0;

        foreach($this->randArr as $key => $pack) {

            foreach ($pack['flobot'] as $rarity) {
                if ($rarity == 'rare') {
                    $checkPackFlobotRareCount++;
                }
                if ($rarity == 'epic') {
                    $checkPackFlobotEpicCount++;
                }
                if ($rarity == 'legendary') {
                    $checkPackFlobotLegendaryCount++;
                }
            }

            if (count($pack['flobot']) != 1) {
                if ($showErrors) {
                    $this->error('Invalid Flobot count on Pack ' . $key . ' - Found ' . count($pack['flobot']) . ' Components');
                }
                $check = false;
            }
        }



        $checkPackLandRareCount = 0;
        $checkPackLandEpicCount = 0;
        $checkPackLandLegendaryCount = 0;

        foreach($this->randArr as $key => $pack) {

            foreach ($pack['land'] as $rarity) {
                if ($rarity == 'rare') {
                    $checkPackLandRareCount++;
                }
                if ($rarity == 'epic') {
                    $checkPackLandEpicCount++;
                }
                if ($rarity == 'legendary') {
                    $checkPackLandLegendaryCount++;
                }
            }

            if (count($pack['land']) != 1) {
                if ($showErrors) {
                    $this->error('Invalid Land count on Pack ' . $key . ' - Found ' . count($pack['land']) . ' Components');
                }
                $check = false;
            }
        }



        $checkPackFubRareCount = 0;
        $checkPackFubEpicCount = 0;
        $checkPackFubLegendaryCount = 0;

        foreach($this->randArr as $key => $pack) {

            foreach ($pack['fub'] as $rarity) {
                if ($rarity == 'rare') {
                    $checkPackFubRareCount++;
                }
                if ($rarity == 'epic') {
                    $checkPackFubEpicCount++;
                }
                if ($rarity == 'legendary') {
                    $checkPackFubLegendaryCount++;
                }
            }

            if (count($pack['fub']) != 1) {
                if ($showErrors) {
                    $this->error('Invalid Fub count on Pack ' . $key . ' - Found ' . count($pack['fub']) . ' Components');
                }
                $check = false;
            }
        }


        if($showErrors) {

            if ($checkPackFlobotRareCount != $this->packComponentRareTotal) {
                $this->error('Invalid Total Common Flobot for Packs. Found ' . $checkPackFlobotRareCount . ' - Expected ' . $this->packComponentRareTotal . ' = Diff ' . ($checkPackFlobotRareCount - $this->packComponentRareTotal));
            }
            if ($checkPackFlobotEpicCount != $this->packComponentEpicTotal) {
                $this->error('Invalid Total Epic Flobot for Packs. Found ' . $checkPackFlobotEpicCount . ' - Expected ' . $this->packComponentEpicTotal . ' = Diff ' . ($checkPackFlobotEpicCount - $this->packComponentEpicTotal));
            }
            if ($checkPackFlobotLegendaryCount != $this->packComponentLegendaryTotal) {
                $this->error('Invalid Total Legendary Flobot for Packs. Found ' . $checkPackFlobotLegendaryCount . ' - Expected ' . $this->packComponentLegendaryTotal . ' = Diff ' . ($checkPackFlobotLegendaryCount - $this->packComponentLegendaryTotal));
            }

            if ($checkPackLandRareCount != $this->packComponentRareTotal) {
                $this->error('Invalid Total Common Land for Packs. Found ' . $checkPackLandRareCount . ' - Expected ' . $this->packComponentRareTotal . ' = Diff ' . ($checkPackLandRareCount - $this->packComponentRareTotal));
            }
            if ($checkPackLandEpicCount != $this->packComponentEpicTotal) {
                $this->error('Invalid Total Epic Land for Packs. Found ' . $checkPackLandEpicCount . ' - Expected ' . $this->packComponentEpicTotal . ' = Diff ' . ($checkPackLandEpicCount - $this->packComponentEpicTotal));
            }
            if ($checkPackLandLegendaryCount != $this->packComponentLegendaryTotal) {
                $this->error('Invalid Total Legendary Land for Packs. Found ' . $checkPackLandLegendaryCount . ' - Expected ' . $this->packComponentLegendaryTotal . ' = Diff ' . ($checkPackLandLegendaryCount - $this->packComponentLegendaryTotal));
            }

            if ($checkPackFubRareCount != $this->packComponentRareTotal) {
                $this->error('Invalid Total Common FUB for Packs. Found ' . $checkPackFubRareCount . ' - Expected ' . $this->packComponentRareTotal . ' = Diff ' . ($checkPackFubRareCount - $this->packComponentRareTotal));
            }
            if ($checkPackFubEpicCount != $this->packComponentEpicTotal) {
                $this->error('Invalid Total Epic FUB for Packs. Found ' . $checkPackFubEpicCount . ' - Expected ' . $this->packComponentEpicTotal . ' = Diff ' . ($checkPackFubEpicCount - $this->packComponentEpicTotal));
            }
            if ($checkPackFubLegendaryCount != $this->packComponentLegendaryTotal) {
                $this->error('Invalid Total Legendary FUB for Packs. Found ' . $checkPackFubLegendaryCount . ' - Expected ' . $this->packComponentLegendaryTotal . ' = Diff ' . ($checkPackFubLegendaryCount - $this->packComponentLegendaryTotal));
            }

            $this->info('All checks completed!');
        }


        if(abs($checkPackFlobotRareCount - $this->packComponentRareTotal) > 0){
            $check = false;
        }
        if(abs($checkPackFlobotEpicCount - $this->packComponentEpicTotal) > 0){
            $check = false;
        }
        if(abs($checkPackFlobotLegendaryCount - $this->packComponentLegendaryTotal) > 0){
            $check = false;
        }


        if(abs($checkPackLandRareCount - $this->packComponentRareTotal) > 0){
            $check = false;
        }
        if(abs($checkPackLandEpicCount - $this->packComponentEpicTotal) > 0){
            $check = false;
        }
        if(abs($checkPackLandLegendaryCount - $this->packComponentLegendaryTotal) > 0){
            $check = false;
        }


        if(abs($checkPackFubRareCount - $this->packComponentRareTotal) > 0){
            $check = false;
        }
        if(abs($checkPackFubEpicCount - $this->packComponentEpicTotal) > 0){
            $check = false;
        }
        if(abs($checkPackFubLegendaryCount - $this->packComponentLegendaryTotal) > 0){
            $check = false;
        }

        return $check;
    }


}
