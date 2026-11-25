<?php

namespace App\Jobs;

use App\Models\DustAccessory;
use App\Models\DustCollectible;
use App\Models\DustSeries;
use App\Models\DustTemplate;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\FlovatarTemplate;
use App\Models\FlovatarComponent;
use App\Models\Pack;
use App\Models\SaleOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Flow;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected $eventsList;
    protected $eventsBaseList;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->eventsList = [
            'FlovatarTemplateCreated' => 'FlovatarComponentTemplate.Created',
            'FlovatarComponentCreated' => 'FlovatarComponent.Created',
            'FlovatarComponentWithdraw' => 'FlovatarComponent.Withdraw',
            'FlovatarComponentDeposit' => 'FlovatarComponent.Deposit',
            'FlovatarComponentDestroyed' => 'FlovatarComponent.Destroyed',
            'FlovatarPackCreated' => 'FlovatarPack.Created',
            'FlovatarPackDeposit' => 'FlovatarPack.Deposit',
            'FlovatarPackOpened' => 'FlovatarPack.Opened',
            'FlovatarCreated' => 'Flovatar.Created',
            'FlobotCreated' => 'Flobot.Created',
            'FlovatarWithdraw' => 'Flovatar.Withdraw',
            'FlovatarDeposit' => 'Flovatar.Deposit',
            'FlobotDeposit' => 'Flobot.Deposit',
            'FlovatarUpdated' => 'Flovatar.Updated',
            'FlovatarNameSet' => 'Flovatar.NameSet',
            'FlovatarPositionChanged' => 'Flovatar.PositionChanged',
            'FlovatarStoryAdded' => 'Flovatar.StoryAdded',
            'FlovatarUnlocked3D' => 'Flovatar.Unlocked3DFile',
            'FlobotUpdated' => 'Flobot.Updated',
            'FlovatarSaleCreated' => 'FlovatarMarketplace.FlovatarForSale',
            'FlovatarSaleCompleted' => 'FlovatarMarketplace.FlovatarPurchased',
            'FlovatarSalePrice' => 'FlovatarMarketplace.FlovatarPriceChanged',
            'FlovatarSaleRemoved' => 'FlovatarMarketplace.FlovatarSaleWithdrawn',
            'ComponentSaleCreated' => 'FlovatarMarketplace.FlovatarComponentForSale',
            'ComponentSaleCompleted' => 'FlovatarMarketplace.FlovatarComponentPurchased',
            'ComponentSalePrice' => 'FlovatarMarketplace.FlovatarComponentPriceChanged',
            'ComponentSaleRemoved' => 'FlovatarMarketplace.FlovatarComponentSaleWithdrawn',
            'StorefrontSaleCreated' => 'NFTStorefrontV2.ListingAvailable',
            'StorefrontSaleCompleted' => 'NFTStorefrontV2.ListingCompleted',
            'SaleRoyalty' => 'FlovatarMarketplace.RoyaltyPaid',
            'CollectibleSeriesCreated' => 'FlovatarDustCollectibleTemplate.CreatedSeries',
            'CollectibleTemplateCreated' => 'FlovatarDustCollectibleTemplate.Created',
            'CollectibleCreated' => 'FlovatarDustCollectible.Created',
            'CollectibleWithdraw' => 'FlovatarDustCollectible.Withdraw',
            'CollectibleDeposit' => 'FlovatarDustCollectible.Deposit',
            'CollectibleUpdated' => 'FlovatarDustCollectible.Updated',
            'CollectibleNameSet' => 'FlovatarDustCollectible.NameSet',
            'CollectiblePositionChanged' => 'FlovatarDustCollectible.PositionChanged',
            'CollectibleStoryAdded' => 'FlovatarDustCollectible.StoryAdded',
            'CollectibleAccessoryCreated' => 'FlovatarDustCollectibleAccessory.Created',
            'CollectibleAccessoryWithdraw' => 'FlovatarDustCollectibleAccessory.Withdraw',
            'CollectibleAccessoryDeposit' => 'FlovatarDustCollectibleAccessory.Deposit',
            'CollectibleAccessoryDestroyed' => 'FlovatarDustCollectibleAccessory.Destroyed',
            'FlovatarWithdrawDust' => 'FlovatarInbox.FlovatarWithdrawDust',
            'WalletWithdrawDust' => 'FlovatarInbox.WalletWithdrawDust',
            'FlovatarClaimedCommunityDust' => 'FlovatarInbox.FlovatarClaimedCommunityDust',
            'DustTokensBurned' => 'FlovatarDustToken.TokensBurned',
        ];
        $this->eventsBaseList = [
            'FlovatarTemplateCreated' => 'FlovatarComponentTemplate.Created',
            'FlovatarComponentCreated' => 'FlovatarComponent.Created',
            'FlovatarPackCreated' => 'FlovatarPack.Created',
            'CollectibleSeriesCreated' => 'FlovatarDustCollectibleTemplate.CreatedSeries',
            'CollectibleTemplateCreated' => 'FlovatarDustCollectibleTemplate.Created',
            'CollectibleAccessoryCreated' => 'FlovatarDustCollectibleAccessory.Created',
            'CollectibleCreated' => 'FlovatarDustCollectible.Created',
        ];
    }

    public function getName(){
        return 'BaseJob';
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

    }

    protected function cleanupSales($id, $address, $type='flovatar')
    {

        // (the old chained ternaries mapped 'flobot'/'collectible' to FlovatarComponent: a Flobot deposit deleted Flobit offers)
        $type = [
            'flovatar' => 'App\Models\Flovatar',
            'flobot' => 'App\Models\Flobot',
            'collectible' => 'App\Models\DustCollectible',
        ][$type] ?? 'App\Models\FlovatarComponent';
        SaleOffer::whereFlowId($id)->where('flow_address', '!=', $address)->whereItemType($type)->delete();
    }

    protected function getFlovatarFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_flovatar.cdc')->argAddress($address)->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Flovatar!!!');
        }

        return $results;
    }

    protected function getFlobotFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_flobot.cdc')->argAddress($address)->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Flobot!!!');
        }

        return $results;
    }

    protected function getFlovatarComponentFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_component.cdc')->argAddress($address)->argInt($id)->run();
            info(print_r($results,true));
        } catch (ProcessFailedException $e) {
            info('Process failed Flovatar Component!!!');
        }

        return $results;
    }
    protected function getFlovatarSaleFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_flovatar_sale.cdc')->argAddress($address)->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Flovatar Sale!!!');
        }

        return $results;
    }

    protected function getFlovatarComponentSaleFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_component_sale.cdc')->argAddress($address)->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Flovatar Component Sale!!!');
        }

        return $results;
    }

    protected function getFlovatarTemplateFromChain($id)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_component_template.cdc')->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Flovatar Template!!!');
        }

        return $results;
    }

    protected function getFlovatarPackFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_pack.cdc')->argAddress($address)->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Flovatar Pack!!!');
        }

        return $results;
    }

    protected function syncFlovatarFromFlow($id, $flowMeta){
        if(isset($flowMeta['id']) && isset($flowMeta['metadata'])) {
            $flovatar = Flovatar::updateOrCreate(
                ['flow_id' => $id],
                [
                    'flow_metadata' => $flowMeta,
                    'name' => isset($flowMeta['name']) ? $flowMeta['name'] : null,
                    'series' => $flowMeta['metadata']['series'],
                    'svg' => $flowMeta['metadata']['svg'],
                    'rare_count' => $flowMeta['metadata']['rareCount'],
                    'epic_count' => $flowMeta['metadata']['epicCount'],
                    'legendary_count' => $flowMeta['metadata']['legendaryCount']
                ]);
            info('Updated Flovatar with Flow data! ' . $flovatar->id);
            return $flovatar;
        } else {
            return null;
        }
    }

    protected function getTemplateSvg($id){
        $template = FlovatarTemplate::whereFlowId($id)->first();
        return $template ? $template->svg : '';
    }
    protected function syncFlobotFromFlow($id, $flowMeta){
        if(isset($flowMeta['id']) && isset($flowMeta['metadata'])) {
            $svg = '';
            info('SYNCING FLOBOT FROM FLOW');
            info(print_r($flowMeta['metadata'], true));
            if(isset($flowMeta['metadata']['components'])){
                $svg .= $this->getTemplateSvg($flowMeta['metadata']['components']['legs']);
                $svg .= $this->getTemplateSvg($flowMeta['metadata']['components']['body']);
                $svg .= $this->getTemplateSvg($flowMeta['metadata']['components']['arms']);
                $svg .= $this->getTemplateSvg($flowMeta['metadata']['components']['head']);
                $svg .= $this->getTemplateSvg($flowMeta['metadata']['components']['face']);
            }
            $flobot = Flobot::updateOrCreate(
                ['flow_id' => $id],
                [
                    'flow_metadata' => $flowMeta,
                    'name' => isset($flowMeta['name']) ? $flowMeta['name'] : null,
                    'series' => $flowMeta['metadata']['series'],
                    'svg' => $svg,
                    'rarity' => $flowMeta['metadata']['rarity']
                ]);
            info('Updated Flobot with Flow data! ' . $flobot->id);
            return $flobot;
        } else {
            return null;
        }
    }
    protected function syncFlovatarFromFlowAddress($id, $address)
    {

        $flovatarF = $this->parseFlowResponse($this->getFlovatarFromChain($id, $address));
        if($flovatarF && isset($flovatarF['id']) && isset($flovatarF['metadata'])){
            return $this->syncFlovatarFromFlow($flovatarF['id'], $flovatarF);
        } else {
            $flovatarF = $this->parseFlowResponse($this->getFlovatarSaleFromChain($id, $address));
            if($flovatarF && isset($flovatarF['id']) && isset($flovatarF['metadata'])){
                return $this->syncFlovatarFromFlow($flovatarF['id'], $flovatarF);
            }
        }

        info('Unable to update Flovatar because it was not found on chain!');
        return null;
    }
    protected function syncFlobotFromFlowAddress($id, $address)
    {

        $flobotF = $this->parseFlowResponse($this->getFlobotFromChain($id, $address));
        if($flobotF && isset($flobotF['id']) && isset($flobotF['metadata'])){
            return $this->syncFlobotFromFlow($flobotF['id'], $flobotF);
        }

        info('Unable to update Flobot because it was not found on chain!');
        return null;
    }

    protected function syncFlovatarComponentFromFlow($id, $flowMeta){
        $flovatarTemplate = FlovatarTemplate::whereFlowId($flowMeta['templateId'])->first();
        if(!$flovatarTemplate){
            info('Unable to update Flovatar Component because the Template was not found!');
            return null;
        }
        $flovatarComponent =  FlovatarComponent::updateOrCreate(
            [
                'flow_id' => $id,
                'flovatar_template_id' => $flovatarTemplate->id
            ],
            [
                'flow_metadata' => $flowMeta,
            ]
        );
        //'flow_address' => config('services.flow.address'),

        if($flowMeta['mint']){
            $flovatarComponent->update(['mint' => $flowMeta['mint']]);
        }
        if(!$flovatarComponent->flow_address){
            $flovatarComponent->update(['flow_address' => config('services.flow.address')]);
        }
        //info('Updated Flovatar Component with Flow data! ' . $flovatarComponent->id);
        return $flovatarComponent;
    }
    protected function syncFlovatarComponentFromFlowAddress($id, $address)
    {
        if($id && $address) {
            $component = FlovatarComponent::whereFlowId($id)->whereFlowAddress($address)->first();
            if ($component) {
                return $component;
            } else {
                $componentF = $this->parseFlowResponse($this->getFlovatarComponentFromChain($id, $address));
                //info('---------------- GET FLOVATAR COMPONENT FROM CHAIN ------------');
                //info(print_r($componentF,true));
                if ($componentF && isset($componentF['id']) && isset($componentF['templateId'])) {
                    return $this->syncFlovatarComponentFromFlow($componentF['id'], $componentF);
                } else {
                    $componentF = $this->parseFlowResponse($this->getFlovatarComponentSaleFromChain($id, $address));
                    if ($componentF && isset($componentF['id']) && isset($componentF['templateId'])) {
                        return $this->syncFlovatarComponentFromFlow($componentF['id'], $componentF);
                    }
                }
            }
        } else {
            info('Sync skipped because address or id is null!');
            return null;
        }
        info('Unable to update Flovatar Component because it was not found on chain!');
        //info($id);
        //info($address);
        return null;
    }


    protected function syncFlovatarTemplateFromFlow($id, $flowMeta){
        $flovatarTemplate = FlovatarTemplate::whereFlowId($id)->first();
        if(!$flovatarTemplate){
            info('Flovatar Template Created - get by name, category and color!!!!');
            $flovatarTemplate = FlovatarTemplate::whereNull('flow_id')->where('name', 'like', $flowMeta['name'])->where('category', 'like', $flowMeta['category'])->where('color', 'like', $flowMeta['color'])->first();
        } else {
            info('Flovatar Template Created - found by ID!!!!');
        }
        if($flovatarTemplate){
            info('Flovatar Template Created - found template!!!!');
            $flovatarTemplate->update([
                'name' => $flowMeta['name'],
                'color' => $flowMeta['color'],
                'series' => $flowMeta['series'],
                'category' => $flowMeta['category'],
                'svg' => $flowMeta['svg'],
                'description' => $flowMeta['description'],
                'max_mintable' => $flowMeta['maxMintableComponents'],
                'flow_id' => $id,
                'flow_metadata' => $flowMeta,
                'flow_address' => config('services.flow.address')
            ]);
        } else {

            info('Flovatar Template Created - make new template!!!!');
            $flovatarTemplate = FlovatarTemplate::create(
                [
                    'name' => $flowMeta['name'],
                    'color' => $flowMeta['color'],
                    'series' => $flowMeta['series'],
                    'category' => $flowMeta['category'],
                    'svg' => $flowMeta['svg'],
                    'description' => $flowMeta['description'],
                    'max_mintable' => $flowMeta['maxMintableComponents'],
                    'flow_id' => $id,
                    'flow_metadata' => $flowMeta,
                    'flow_address' => config('services.flow.address')
                ]
            );
        }
        info('Updated Flovatar Template with Flow data! ' . $flovatarTemplate->id);
        return $flovatarTemplate;
    }
    protected function syncFlovatarTemplateFromFlowId($id)
    {
        $flovatarTemplate = FlovatarTemplate::whereFlowId($id)->first();
        if($flovatarTemplate){
            return $flovatarTemplate;
        } else {
            info('Flovatar Template Created - Get from chain!!!!');
            $flovatarF = $this->parseFlowResponse($this->getFlovatarTemplateFromChain($id));
            if($flovatarF && isset($flovatarF['id'])){
                info('Flovatar Template Created - Found!!!!'.$flovatarF['id']);
                //info(print_r($flovatarF, true));
                return $this->syncFlovatarTemplateFromFlow($flovatarF['id'], $flovatarF);
            }
        }
        return null;
    }





    protected function getCollectibleSeriesFromChain($id)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_collectible_series.cdc')->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Collectible Series!!!');
        }

        return $results;
    }

    protected function syncCollectibleSeriesFromFlow($id, $flowMeta){
        $collectibleSeries = DustSeries::whereFlowId($id)->first();
        if(!$collectibleSeries){
            info('Collectible Series Created - get by name, category and color!!!!');
            $collectibleSeries = DustSeries::whereNull('flow_id')->where('name', 'like', $flowMeta['name'])->first();
        } else {
            info('Collectible Series Created - found by ID!!!!');
        }
        if($collectibleSeries){
            info('Collectible Series Created - found series!!!!');
            $collectibleSeries->update([
                'name' => $flowMeta['name'],
                'description' => $flowMeta['description'],
                'svg_prefix' => $flowMeta['svgPrefix'],
                'svg_suffix' => $flowMeta['svgSuffix'],
                'price_increase' => $flowMeta['priceIncrease'],
                'layers' => $flowMeta['layers'],
                'colors' => $flowMeta['colors'],
                'max_mintable' => $flowMeta['maxMintable'],
                'minted' => isset($flowMeta['totalMintedCollectibles']) ? $flowMeta['totalMintedCollectibles'] : 0,
                'flow_id' => $id,
                'flow_address' => config('services.flow.address'),
                'flow_metadata' => $flowMeta
            ]);
        } else {
            info('Collectible Series Created - make new series!!!!');
            $collectibleSeries = DustSeries::create(
                [
                    'name' => $flowMeta['name'],
                    'description' => $flowMeta['description'],
                    'svg_prefix' => $flowMeta['svgPrefix'],
                    'svg_suffix' => $flowMeta['svgSuffix'],
                    'price_increase' => $flowMeta['priceIncrease'],
                    'layers' => $flowMeta['layers'],
                    'colors' => $flowMeta['colors'],
                    'max_mintable' => $flowMeta['maxMintable'],
                    'minted' => isset($flowMeta['totalMintedCollectibles']) ? $flowMeta['totalMintedCollectibles'] : 0,
                    'flow_id' => $id,
                    'flow_address' => config('services.flow.address'),
                    'flow_metadata' => $flowMeta
                ]
            );
        }
        info('Updated Collectible Series with Flow data! ' . $collectibleSeries->id);
        return $collectibleSeries;
    }
    protected function syncCollectibleSeriesFromFlowId($id, $forceUpdate = false)
    {
        $collectibleSeries = DustSeries::whereFlowId($id)->first();
        if($collectibleSeries && !$forceUpdate){
            return $collectibleSeries;
        } else {
            info('Collectible Series Created - Get from chain!!!!');
            $flovatarF = $this->parseFlowResponse($this->getCollectibleSeriesFromChain($id));
            if($flovatarF && isset($flovatarF['id'])){
                info('Collectible Series Created - Found!!!!'.$flovatarF['id']);
                //info(print_r($flovatarF, true));
                return $this->syncCollectibleSeriesFromFlow($flovatarF['id'], $flovatarF);
            }
        }
        return null;
    }






    protected function getCollectibleTemplateFromChain($id)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_collectible_template.cdc')->argInt($id)->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Collectible Template!!!');
        }

        return $results;
    }

    protected function syncCollectibleTemplateFromFlow($id, $flowMeta){
        $collectibleTemplate = DustTemplate::whereFlowId($id)->first();
        $collectibleSeries = DustSeries::whereFlowId($flowMeta['series'])->first();
        if(!$collectibleSeries) {
            info('Collectible Template Created - SERIES NOT FOUND!!!!!!!!!');
        }
        $seriesId = $collectibleSeries ? $collectibleSeries->id : $flowMeta['series'];
        if(!$collectibleTemplate){
            info('Collectible Template Created - get by name, category and color!!!!');
            $collectibleTemplate = DustTemplate::whereNull('flow_id')->where('name', 'like', $flowMeta['name'])->where('rarity', 'like', $flowMeta['rarity'])->where('dust_series_id', 'like', $seriesId)->where('layer', 'like', $flowMeta['layer'])->first();
        } else {
            info('Collectible Template Created - found by ID!!!!');
        }
        if($collectibleTemplate){
            info('Collectible Template Created - found series!!!!');
            $collectibleTemplate->update([
                'name' => $flowMeta['name'],
                'description' => $flowMeta['description'],
                'layer' => $flowMeta['layer'],
                'rarity' => $flowMeta['rarity'],
                'base_price' => $flowMeta['basePrice'],
                'current_price' => $flowMeta['currentPrice'],
                'svg' => $flowMeta['svg'],
                'max_mintable' => $flowMeta['maxMintableComponents'],
                'minted' => $flowMeta['totalMintedComponents'],
                'dust_series_id' => $seriesId,
                'flow_id' => $id,
                'flow_address' => config('services.flow.address'),
                'flow_metadata' => $flowMeta
            ]);
        } else {
            info('Collectible Template Created - make new series!!!!');
            $collectibleTemplate = DustTemplate::create(
                [
                    'name' => $flowMeta['name'],
                    'description' => $flowMeta['description'],
                    'layer' => $flowMeta['layer'],
                    'rarity' => $flowMeta['rarity'],
                    'base_price' => $flowMeta['basePrice'],
                    'current_price' => $flowMeta['currentPrice'],
                    'svg' => $flowMeta['svg'],
                    'max_mintable' => $flowMeta['maxMintableComponents'],
                    'minted' => $flowMeta['totalMintedComponents'],
                    'dust_series_id' => $seriesId,
                    'flow_id' => $id,
                    'flow_address' => config('services.flow.address'),
                    'flow_metadata' => $flowMeta
                ]
            );
        }
        info('Updated Collectible Template with Flow data! ' . $collectibleTemplate->id);
        return $collectibleTemplate;
    }
    protected function syncCollectibleTemplateFromFlowId($id, $forceUpdate = false)
    {
        $collectibleTemplate = DustTemplate::whereFlowId($id)->first();
        if($collectibleTemplate && !$forceUpdate){
            return $collectibleTemplate;
        } else {
            info('Collectible Template Created - Get from chain!!!!');
            $flovatarF = $this->parseFlowResponse($this->getCollectibleTemplateFromChain($id));
            if($flovatarF && isset($flovatarF['id'])){
                info('Collectible Template Created - Found!!!!'.$flovatarF['id']);
                //info(print_r($flovatarF, true));
                return $this->syncCollectibleTemplateFromFlow($flovatarF['id'], $flovatarF);
            }
        }
        return null;
    }



    protected function getCollectibleFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_collectible.cdc')->argAddress($address)->argInt($id)->debug()->run();
        } catch (ProcessFailedException $e) {
            info('Process failed Get Collectible!!!');
        }


        //info(print_r($results,true));

        return $results;
    }
    protected function syncCollectibleFromFlow($id, $flowMeta){

        if(isset($flowMeta['id']) && isset($flowMeta['metadata']) && isset($flowMeta['series'])) {
            $collectibleSeries = DustSeries::whereFlowId($flowMeta['series'])->first();
            if(!$collectibleSeries) {
                info('Collectible Created - SERIES NOT FOUND!!!!!!!!!');
            }
            $seriesId = $collectibleSeries ? $collectibleSeries->id : $flowMeta['series'];
            $rarities = $this->countCollectibleRarities($flowMeta['layers']);
            $collectible = DustCollectible::updateOrCreate(
                ['flow_id' => $id],
                [
                    'flow_metadata' => $flowMeta,
                    'name' => isset($flowMeta['name']) ? $flowMeta['name'] : null,
                    'mint' => $flowMeta['mint'],
                    'svg' => $flowMeta['svg'],
                    'dust_series_id' => $flowMeta['series'],
                    'rare_count' => $rarities['rare'],
                    'epic_count' => $rarities['epic'],
                    'legendary_count' => $rarities['legendary']
                ]);
            info('Updated Collectible with Flow data! ' . $collectible->id);
            return $collectible;
        } else {
            info('NOT Updated Collectible with Flow data! id, metatada or series not found ');
            info(print_r($flowMeta,true));
            return null;
        }
    }
    protected function syncCollectibleFromFlowAddress($id, $address)
    {
        $collectible = $this->parseFlowResponse($this->getCollectibleFromChain($id, $address));
        if($collectible && isset($collectible['id']) && isset($collectible['metadata'])){
            return $this->syncCollectibleFromFlow($collectible['id'], $collectible);
        }

        info('Unable to update Collectible because it was not found on chain!');
        return null;
    }
    protected function countCollectibleRarities($layers){
        $rarities = [
            'rare' => 0,
            'epic' => 0,
            'legendary' => 0
        ];
        foreach ($layers as $layer) {
            if ($layer && isset($layer->value) && $layer->value > 0) {
                $collectibleTemplate = DustTemplate::whereFlowId($layer->value)->first();
                if($collectibleTemplate && ($collectibleTemplate->rarity == 'rare' || $collectibleTemplate->rarity == 'epic' || $collectibleTemplate->rarity == 'legendary')){
                    $rarities[$collectibleTemplate->rarity] = $rarities[$collectibleTemplate->rarity] + 1;
                }
            }
        }
        return $rarities;
    }







    protected function getCollectibleAccessoryFromChain($id, $address)
    {
        $results = null;
        try {
            $results = Flow::script('./scripts/get_collectible_accessory.cdc')->argAddress($address)->argInt($id)->run();
            info(print_r($results,true));
        } catch (ProcessFailedException $e) {
            info('Process failed Collectible Accessory!!!');
        }

        return $results;
    }
    protected function syncCollectibleAccessoryFromFlow($id, $flowMeta){
        $dustTemplate = DustTemplate::whereFlowId($flowMeta['templateId'])->first();
        if(!$dustTemplate){
            info('Unable to update Collectible Accessory because the Template was not found!');
            return null;
        }
        $dustAccessory =  DustAccessory::updateOrCreate(
            [
                'flow_id' => $id,
                'dust_template_id' => $dustTemplate->id
            ],
            [
                'flow_metadata' => $flowMeta,
            ]
        );
        //'flow_address' => config('services.flow.address'),

        if($flowMeta['mint']){
            $dustAccessory->update(['mint' => $flowMeta['mint']]);
        }
        if(!$dustAccessory->flow_address){
            $dustAccessory->update(['flow_address' => config('services.flow.address')]);
        }
        //info('Updated Flovatar Component with Flow data! ' . $flovatarComponent->id);
        return $dustAccessory;
    }
    protected function syncCollectibleAccessoryFromFlowAddress($id, $address)
    {
        if($id && $address) {
            $dustAccessory = DustAccessory::whereFlowId($id)->whereFlowAddress($address)->first();
            if ($dustAccessory) {
                return $dustAccessory;
            } else {
                $accessoryF = $this->parseFlowResponse($this->getCollectibleAccessoryFromChain($id, $address));
                //info('---------------- GET FLOVATAR COMPONENT FROM CHAIN ------------');
                //info(print_r($componentF,true));
                if ($accessoryF && isset($accessoryF['id']) && isset($accessoryF['templateId'])) {
                    return $this->syncCollectibleAccessoryFromFlow($accessoryF['id'], $accessoryF);
                }
            }
        } else {
            info('Sync skipped because address or id is null!');
            return null;
        }
        info('Unable to update Flovatar Component because it was not found on chain!');
        //info($id);
        //info($address);
        return null;
    }








    protected function syncFlovatarPackFromFlow($id, $flowMeta){
        $flovatarPack = Pack::updateOrCreate(
            ['flow_id' => $id],
            ['flow_metadata' => $flowMeta]
        );
        info('Updated Flovatar Pack with Flow data! ' . $flovatarPack->id);
        return $flovatarPack;
    }






    protected function parseFlowFields($items){
        $outArr = [];
        //dd($items);
        foreach($items as $item){

            if(isset($item->value) && isset($item->value->type)){

                $value = $item->value;
                if($value->type == 'Optional') {
                    if(isset($value->value->value)){
                        $value = $value->value;
                    } else {
                        $value = null;
                    }
                }

                if(isset($item->name) && isset($value->value)) {

                    if($value->type == 'Dictionary' && is_array($value->value)){
                        $outArr[$item->name] = [];
                        foreach($value->value as $val){
                            if(isset($val->key) && isset($val->key->value) && isset($val->value) && isset($val->value->value)){
                                if(isset($val->value->value->id) && isset($val->value->value->fields) && $val->value->value->id == $this->getLayersAddress()){
                                    info('PARSING LAYERS!');
                                    $outArr[$item->name][$val->key->value] = $this->parseFlowFields($val->value->value->fields);
                                } else {
                                    $outArr[$item->name][$val->key->value] = $val->value->value;
                                }
                            } else {
                                info('UNSUPPORTED PARSING!!!! PLEASE CHECK 1');
                            }
                        }
                    } else if(isset($value->type) && $value->type == 'Struct' && isset($value->value->fields) && is_array($value->value->fields)) {
                        $outArr[$item->name] = $this->parseFlowFields($value->value->fields);
                    } else {
                        $outArr[$item->name] = $value->value;
                    }
                } else if($value && isset($value->fields) && is_array($value->fields)) {
                    $outArr[] = $this->parseFlowFields($value->fields);
                } else if(isset($item->name)) {
                    $outArr[$item->name] = $value;
                } else {
                    info('UNSUPPORTED PARSING!!!! PLEASE CHECK 3');
                }

            } else {
                info('UNSUPPORTED PARSING!!!! PLEASE CHECK 4');
            }
        }
        return $outArr;
    }


    protected function parseFlowResponse($response){
        if(!$response){
            return null;
        }
        $items = json_decode($response);
        if($items && isset($items->type) && isset($items->value)){
            $value = $items->value;
            if($items->type == 'Optional') {
                if(isset($value->value->value) || (isset($value->value->fields) && is_array($value->value->fields))){
                    $value = $value->value;
                } else {
                    $value = null;
                }
            }

            if(is_array($value)){
                $outArr = [];
                foreach($value as $item){
                    if(isset($item->value) && isset($item->value->fields) && is_array($item->value->fields)){
                        $outArr[] = $this->parseFlowFields($item->value->fields);
                    } else if(isset($item->value)){
                        $outArr[] = $item->value;
                    }
                }
                return $outArr;
            } else if(isset($value->fields) && is_array($value->fields)){
                return $this->parseFlowFields($value->fields);
            } else {
                info(print_r($items, true));
                info('UNSUPPORTED PARSING!!!! PLEASE CHECK');
            }
        } else if($items && isset($items->type) && $items->type == 'Optional' && $items->value == null){
            info('Returned Optional null');
        } else {
            info(print_r($items, true));
            info('UNSUPPORTED PARSING!!!! PLEASE CHECK 2');
        }

        return null;

    }


    protected function parseFlowEvents($response){

        if(!$response){
            return null;
        }
        $items = json_decode($response);

        //dd($items);
        $outArr = [];

        if(is_array($items)){
            foreach($items as $item){
                if(
                    isset($item->blockID) &&
                    isset($item->transactionId) &&
                    isset($item->index) &&
                    isset($item->type) &&
                    isset($item->values) &&
                    isset($item->values->value) &&
                    isset($item->values->value->fields) &&
                    is_array($item->values->value->fields)
                ){
                    $eventArr = [];
                    $eventArr['blockID'] = $item->blockID;
                    $eventArr['transactionId'] = $item->transactionId;
                    $eventArr['index'] = $item->index;
                    $eventArr['type'] = $item->type;
                    $eventArr['values'] = $this->parseFlowFields($item->values->value->fields);
                    $outArr[] = $eventArr;
                }
            }
        } else {
            info('UNSUPPORTED EVENT PARSING!!!! PLEASE CHECK');
        }
        return $outArr;

    }


    protected function updateTemplatesBurnCount($newFlovatar){

        if(isset($newFlovatar->flow_metadata['metadata'])){
            if(isset($newFlovatar->flow_metadata['metadata']['components'])) {
                foreach ($newFlovatar->flow_metadata['metadata']['components'] as $component) {
                    if ($component && $component > 0) {
                        $template = FlovatarTemplate::whereFlowId($component)->first();
                        if ($template) {
                            $template->update([
                                'burned' => $template->burned + 1
                            ]);
                        }
                    }
                }
            }
            if(isset($newFlovatar->flow_metadata['metadata']['legendaryCount']) && $newFlovatar->flow_metadata['metadata']['legendaryCount'] > 0){
                $template = FlovatarTemplate::whereCategory('boost')->whereRarity('legendary')->first();
                if ($template) {
                    $template->update([
                        'burned' => $template->burned + $newFlovatar->flow_metadata['metadata']['legendaryCount']
                    ]);
                }
            }
            if(isset($newFlovatar->flow_metadata['metadata']['epicCount']) && $newFlovatar->flow_metadata['metadata']['epicCount'] > 0){
                $template = FlovatarTemplate::whereCategory('boost')->whereRarity('epic')->first();
                if ($template) {
                    $template->update([
                        'burned' => $template->burned + $newFlovatar->flow_metadata['metadata']['epicCount']
                    ]);
                }
            }
            if(isset($newFlovatar->flow_metadata['metadata']['rareCount']) && $newFlovatar->flow_metadata['metadata']['rareCount'] > 0){
                $template = FlovatarTemplate::whereCategory('boost')->whereRarity('rare')->first();
                if ($template) {
                    $template->update([
                        'burned' => $template->burned + $newFlovatar->flow_metadata['metadata']['rareCount']
                    ]);
                }
            }
            $template = FlovatarTemplate::whereCategory('spark')->whereRarity('common')->first();
            if ($template) {
                $template->update([
                    'burned' => $template->burned + 1
                ]);
            }
        }
    }
    protected function updateFlobotTemplatesBurnCount($newFlobot){

        if(isset($newFlobot->flow_metadata['metadata'])){
            if(isset($newFlobot->flow_metadata['metadata']['components'])) {
                foreach ($newFlobot->flow_metadata['metadata']['components'] as $component) {
                    if ($component && $component > 0) {
                        $template = FlovatarTemplate::whereFlowId($component)->first();
                        if ($template) {
                            $template->update([
                                'burned' => $template->burned + 1
                            ]);
                        }
                    }
                }
            }
            $template = FlovatarTemplate::whereCategory('flobotkit')->whereRarity($newFlobot->flow_metadata['metadata']['rarity'])->first();
            if ($template) {
                $template->update([
                    'burned' => $template->burned + 1
                ]);
            }
        }
    }
    protected function updateCollectibleTemplatesBurnCount($newCollectible){

        if(isset($newCollectible->flow_metadata['layers'])){
            info('Update Collectible Template Burn Count');
            //info(print_r($newCollectible->flow_metadata['layers'], true));
            foreach ($newCollectible->flow_metadata['layers'] as $layer) {
                if ($layer && isset($layer['value']) && $layer['value'] > 0) {
                    info($layer['value']);
                    $this->syncCollectibleTemplateFromFlowId($layer['value'], true);
                }
            }
        }
        if(isset($newCollectible->flow_metadata['series'])){
            $this->syncCollectibleSeriesFromFlowId($newCollectible->flow_metadata['series'], true);
        }
    }



    protected function getLatestBlockHeight(){
        $block = Flow::getLatestBlock();
        $items = json_decode($block);
        return ($items && isset($items->height)) ? $items->height : null;
    }


    protected function getEventAddress($type){
        return isset($this->eventsList[$type]) ? 'A.'.str_replace('0x', '', config('services.flow.address')).'.'.$this->eventsList[$type] : null;
    }
    protected function getStorefrontEventAddress($type){
        return isset($this->eventsList[$type]) ? 'A.'.str_replace('0x', '', config('services.flow.storefrontaddress')).'.'.$this->eventsList[$type] : null;
    }

    protected function getFlovatarNFTAddress(){
        return 'A.'.str_replace('0x', '', config('services.flow.address')).'.Flovatar.NFT';
    }
    protected function getComponentNFTAddress(){
        return 'A.'.str_replace('0x', '', config('services.flow.address')).'.FlovatarComponent.NFT';
    }
    protected function getCollectibleNFTAddress(){
        return 'A.'.str_replace('0x', '', config('services.flow.address')).'.FlovatarDustCollectible.NFT';
    }
    protected function getFlobotNFTAddress(){
        return 'A.'.str_replace('0x', '', config('services.flow.address')).'.Flobot.NFT';
    }
    protected function getLayersAddress(){
        return 'A.'.str_replace('0x', '', config('services.flow.address')).'.FlovatarDustCollectibleTemplate.Layer';
    }

}
