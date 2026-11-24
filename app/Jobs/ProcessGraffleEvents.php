<?php

namespace App\Jobs;

use App\Models\DustAccessory;
use App\Models\DustCollectible;
use App\Models\Event;
use App\Models\FlovatarTemplate;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\Pack;
use App\Models\Sale;
use App\Models\SaleOffer;
use Carbon\Carbon;
use File;
use Illuminate\Support\Facades\Storage;


class ProcessGraffleEvents extends BaseJob
{

    protected $event;
    protected $isGraffle;

    public function __construct($event, $isGraffle = true)
    {
        $this->event = $event;
        $this->isGraffle = $isGraffle;
        parent::__construct();
    }


    public function getName(){
        return 'ProcessGraffleEvents';
    }


    /**
     * Execute the job.
     *
     * @return void
     *
     */
    public function handle()
    {

        $event = $this->event;

        $check = false;


        if($event->type == $this->getEventAddress('FlovatarCreated')){
            if(isset($event->flow_metadata['metadata']) && isset($event->flow_metadata['metadata']['creatorAddress'])) {
                $newFlovatar = $this->syncFlovatarFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['metadata']['creatorAddress']);
                if($newFlovatar){
                    $check = true;
                    if(!$newFlovatar->flow_address){
                        $newFlovatar->update(['flow_address' => $event->flow_metadata['metadata']['creatorAddress']]);
                    }
                    $this->updateTemplatesBurnCount($newFlovatar);
                    ExportAllImages::dispatch($event->flow_metadata['id']);
                } else {
                    info('Flovatar Created event failed because creator address was not found!!!!');
                    info(print_r($newFlovatar,true));
                }
            } else {
                info('Flovatar Created event skipped because creator address was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlobotCreated')){
            if(isset($event->flow_metadata['metadata']) && isset($event->flow_metadata['metadata']['creatorAddress'])) {
                $newFlobot = $this->syncFlobotFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['metadata']['creatorAddress']);
                if($newFlobot){
                    $check = true;
                    // found in the creator's collection: that is the owner (the Flobot contract emits no Deposit event;
                    // later transfers arrive as NonFungibleToken.Deposited)
                    if(!$newFlobot->flow_address){
                        $newFlobot->update(['flow_address' => $event->flow_metadata['metadata']['creatorAddress']]);
                    }
                    $this->updateFlobotTemplatesBurnCount($newFlobot);
                    ExportAllImages::dispatch($event->flow_metadata['id'], 'flobot');
                } else {
                    info('Flobot Created event failed because creator address was not found!!!!');
                    info(print_r($newFlobot,true));
                }
            } else {
                info('Flobot Created event skipped because creator address was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarComponentCreated')){
            if(isset($event->flow_metadata['metadata']) && isset($event->flow_metadata['metadata']['id'])) {
                $flovatar = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
                if (!$flovatar) {
                    $flovatar = $this->syncFlovatarComponentFromFlowAddress($event->flow_metadata['id'], config('flovatar.contractAddress'));
                }
                if (!$flovatar) {
                    info('Flovatar Component Created event skipped because Component was not found!!!!');
                }
            } else if(isset($event->flow_metadata['id']) && isset($event->flow_metadata['templateId'])) {
                $this->syncFlovatarComponentFromFlow($event->flow_metadata['id'], $event->flow_metadata);
                $check = true;
            } else {
                info('Flovatar Component Created event skipped because creator address was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarDeposit')){
            $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
            if(!$flovatar){
                $flovatar = $this->syncFlovatarFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
            }
            if($flovatar){
                $flovatar->update(['flow_address' => $event->flow_metadata['to']]);
                $this->cleanupSales($event->flow_metadata['id'], $event->flow_metadata['to'], 'flovatar');
                $check = true;
            } else {
                info('Flovatar Deposit event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarUnlocked3D')){
            $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
            if($flovatar) {
                $check = true;
            } else {
                info('Flovatar 3d Unlocked event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarUpdated') ||
            $event->type == $this->getEventAddress('FlovatarNameSet') ||
            $event->type == $this->getEventAddress('FlovatarPositionChanged') ||
            $event->type == $this->getEventAddress('FlovatarStoryAdded')){
            $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
            if($flovatar) {
                if($this->syncFlovatarFromFlowAddress($flovatar->flow_id, $flovatar->flow_address)){
                    $check = true;
                }
                ExportAllImages::dispatch($event->flow_metadata['id']);
            } else {
                info('Flovatar Updated event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlobotDeposit') || $this->isStandardFlobotDeposit($event)){
            // Flobot.Deposit is declared but never emitted by the Cadence 1.0 contract: transfers/mints arrive as the
            // standard NonFungibleToken.Deposited {type: A.x.Flobot.NFT, id, to}
            $flobot = Flobot::whereFlowId($event->flow_metadata['id'])->first();
            if(!$flobot){
                $flobot = $this->syncFlobotFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
            }
            if($flobot){
                $flobot->update(['flow_address' => $event->flow_metadata['to']]);
                $this->cleanupSales($event->flow_metadata['id'], $event->flow_metadata['to'], 'flobot');
                $check = true;
            } else {
                info('Flobot Deposit event skipped because Flobot was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlobotUpdated')){
            $flobot = Flobot::whereFlowId($event->flow_metadata['id'])->first();
            if($flobot) {
                if($this->syncFlobotFromFlowAddress($flobot->flow_id, $flobot->flow_address)){
                    $check = true;
                }
                ExportAllImages::dispatch($event->flow_metadata['id'], 'flobot');
            } else {
                info('Flobot Updated event skipped because Flobot was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarComponentDeposit')){
            if(isset($event->flow_metadata['to']) && $event->flow_metadata['to']) {
                $flovatar = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
                if (!$flovatar) {
                    $flovatar = $this->syncFlovatarComponentFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
                }
                if ($flovatar) {
                    $flovatar->update(['flow_address' => $event->flow_metadata['to']]);
                    $this->cleanupSales($event->flow_metadata['id'], $event->flow_metadata['to'], 'component');
                    $check = true;
                } else {
                    info('Flovatar Component Deposit event skipped because Component was not found!!!!');
                }
            } else {
                info('Flovatar Component Deposit event skipped because sent to null!!!!');
                $this->event->delete();
                return;
            }
        } else if($event->type == $this->getEventAddress('FlovatarComponentWithdraw')){
            $flovatar = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
            if($flovatar) {
                $flovatar->update(['flow_address' => null]);
                $check = true;
            } else {
                info('Flovatar Component Withdraw event skipped because Component was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarComponentDestroyed')){
            info('Flovatar Component Destroyed check start!!!!');
            $flovatar = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
            if($flovatar) {
                $flovatar->update(['destroyed' => 1]);
                $template = FlovatarTemplate::whereId($flovatar->flovatar_template_id)->first();
                if ($template) {
                    $template->update([
                        'burned' => $template->burned + 1
                    ]);
                }
                $check = true;
            } else {
                info('Flovatar Component Destroyed event skipped because Component was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarPackDeposit')){
            $pack = Pack::whereFlowId($event->flow_metadata['id'])->first();
            if(!$pack){
                $pack = $this->syncFlovatarPackFromFlow($event->flow_metadata['id'], $event->flow_metadata);
            }
            if($pack){
                $pack->update(['flow_address' => $event->flow_metadata['to']]);
                $check = true;
            } else {
                info('Flovatar Pack Deposit event skipped because Pack was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarPackOpened')){
            $pack = Pack::whereFlowId($event->flow_metadata['id'])->first();
            if(!$pack){
                $pack = $this->syncFlovatarPackFromFlow($event->flow_metadata['id'], $event->flow_metadata);
            }
            if($pack) {
                $pack->update(['opened' => 1]);
                $check = true;
            } else {
                info('Flovatar Pack Opened event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarSaleCreated') || ($event->type == $this->getStorefrontEventAddress('StorefrontSaleCreated') && $event->flow_metadata['nftType']['typeID'] == $this->getFlovatarNFTAddress())){
            $isDapper = false;
            $nftId = null;
            $nftAddress = null;
            $price = null;
            $listingId = null;
            if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCreated')){
                //DAPPER
                info('DAPPER Flovatar ON SALE!');
                $nftId = $event->flow_metadata['nftID'];
                $nftAddress = $event->flow_metadata['storefrontAddress'];
                $price = $event->flow_metadata['salePrice'];
                $listingId = $event->flow_metadata['listingResourceID'];
                $isDapper = true;
            } else {
                $nftId = $event->flow_metadata['id'];
                $nftAddress = $event->flow_metadata['address'];
                $price = $event->flow_metadata['price'];
            }


            $flovatar = Flovatar::whereFlowId($nftId)->first();
            if(!$flovatar){
                $flovatar = $this->syncFlovatarFromFlowAddress($nftId, $nftAddress);
            }
            if($flovatar) {
                if($price <= 999999) {
                    SaleOffer::updateOrCreate(
                        [
                            'item_id' => $flovatar->id,
                            'item_type' => 'App\Models\Flovatar',
                            'flow_id' => $nftId
                        ],
                        [
                            'flow_metadata' => $flovatar->flow_metadata,
                            'flow_address' => $nftAddress,
                            'price' => $price,
                            'is_completed' => false,
                            'is_dapper' => $isDapper,
                            'listing_id' => $listingId
                        ]
                    );
                    $check = true;
                }
                if (!$flovatar->flow_address) {
                    $flovatar->update(['flow_address' => $nftAddress]);
                }
            } else {
                info('SaleCreated event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarSalePrice')){

            $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
            if(!$flovatar){
                $flovatar = $this->syncFlovatarFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['address']);
            }
            if($flovatar) {
                $price = $event->flow_metadata['newPrice'];


                if($price <= 999999) {
                    $saleOffer = SaleOffer::whereItemId($flovatar->id)->whereItemType('App\Models\Flovatar')->whereFlowId($event->flow_metadata['id'])->first();

                    if(!$saleOffer){
                        $flovatarF = $this->parseFlowResponse($this->getFlovatarSaleFromChain($event->flow_metadata['id'], $event->flow_metadata['address']));
                        if ($flovatarF && isset($flovatarF['id'])) {
                            SaleOffer::updateOrCreate(
                                [
                                    'item_id' => $flovatar->id,
                                    'item_type' => 'App\Models\Flovatar',
                                    'flow_id' => $event->flow_metadata['id']
                                ],
                                [
                                    'flow_metadata' => $flovatar->flow_metadata,
                                    'flow_address' => $event->flow_metadata['address'],
                                    'price' => $price,
                                    'is_completed' => false
                                ]
                            );

                            $saleOffer = SaleOffer::whereItemId($flovatar->id)->whereItemType('App\Models\Flovatar')->whereFlowId($event->flow_metadata['id'])->first();

                        }
                    }


                    if($saleOffer){
                        $saleOffer->update([
                            'flow_metadata' => $flovatar->flow_metadata,
                            'flow_address' => $event->flow_metadata['address'],
                            'price' => $price,
                            'is_completed' => false
                        ]);
                        $check = true;
                    }
                }
                if (!$flovatar->flow_address) {
                    $flovatar->update(['flow_address' => $event->flow_metadata['address']]);
                }
            } else {
                info('SaleCreated event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarSaleRemoved')){

            $flovatarF = $this->parseFlowResponse($this->getFlovatarSaleFromChain($event->flow_metadata['tokenId'], $event->flow_metadata['address']));
            if(!$flovatarF){
                SaleOffer::isFlovatar()->whereFlowId($event->flow_metadata['tokenId'])->delete();
                $check = true;
            }

        } else if($event->type == $this->getEventAddress('FlovatarSaleCompleted') || ($event->type == $this->getStorefrontEventAddress('StorefrontSaleCompleted')  && $event->flow_metadata['nftType']['typeID'] == $this->getFlovatarNFTAddress())){

            $isDapper = false;
            $nftId = null;
            $nftAddress = null;
            $price = null;
            $listingId = null;
            $isPurchased = true;
            if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCompleted')){
                //DAPPER
                info('DAPPER FLOVATAR SOLD!');
                $nftId = $event->flow_metadata['nftID'];
                $price = $event->flow_metadata['salePrice'];
                $listingId = $event->flow_metadata['listingResourceID'];
                $isDapper = true;
                $isPurchased = $event->flow_metadata['purchased'];
            } else {
                $nftId = $event->flow_metadata['id'];
                $price = $event->flow_metadata['price'];
            }

            $flovatar = Flovatar::whereFlowId($nftId)->first();
            $saleOffer = SaleOffer::isFlovatar()->whereFlowId($nftId)->first();

            if(!isset($event->flow_metadata['from']) && !($isDapper && $saleOffer)){
                info('NOT FOUND FLOVATAR FROM EVENT');
            } else {
                $nftAddress = ($isDapper && $saleOffer) ? $saleOffer->flow_address : $event->flow_metadata['from'];
                if(!$flovatar){
                    $flovatar = $this->syncFlovatarFromFlowAddress($nftId, $nftAddress);
                }
                if (!$flovatar) {
                    if($saleOffer){
                        $flovatar = $saleOffer->item;
                    }
                }

                if($isDapper){
                    SaleOffer::isFlovatar()->whereFlowId($nftId)->delete();
                    $check = true;
                }

                if($flovatar && $isPurchased){
                    Sale::firstOrCreate(
                        [
                            'item_id' => $flovatar->id,
                            'item_type' => 'App\Models\Flovatar',
                            'price' => $price,
                            'flow_buyer_address' => ($isDapper) ? $event->flow_metadata['storefrontAddress'] : $event->flow_metadata['to'],
                            'flow_seller_address' => $nftAddress,
                            'transaction_id' => $event->transaction_id,
                            'is_dapper' => $isDapper
                        ],
                        [
                            'completed_at' => $event->event_date ?? Carbon::now()
                        ]
                    );
                    $check = true;

                } else {
                    info('Sale Completed event skipped because Flovatar was not found!!!!');
                }
            }


        } else if($event->type == $this->getEventAddress('ComponentSaleCreated') || ($event->type == $this->getStorefrontEventAddress('StorefrontSaleCreated')  && $event->flow_metadata['nftType']['typeID'] == $this->getComponentNFTAddress())){

            $isDapper = false;
            $nftId = null;
            $nftAddress = null;
            $price = null;
            $listingId = null;
            $mint = null;
            if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCreated')){
                //DAPPER
                info('DAPPER COMPONENT ON SALE!');
                $nftId = $event->flow_metadata['nftID'];
                $nftAddress = $event->flow_metadata['storefrontAddress'];
                $price = $event->flow_metadata['salePrice'];
                $listingId = $event->flow_metadata['listingResourceID'];
                $isDapper = true;
            } else {
                $nftId = $event->flow_metadata['id'];
                $nftAddress = $event->flow_metadata['address'];
                $price = $event->flow_metadata['price'];
                $mint = isset($event->flow_metadata['mint']) ? $event->flow_metadata['mint'] : null;
            }


            $component = FlovatarComponent::whereFlowId($nftId)->first();
            if (!$component) {
                $component = $this->syncFlovatarComponentFromFlowAddress($nftId, $nftAddress);
            }

            if($component) {

                if($price <= 999999) {
                    SaleOffer::updateOrCreate(
                        [
                            'item_id' => $component->id,
                            'item_type' => 'App\Models\FlovatarComponent',
                            'flow_id' => $nftId
                        ],
                        [
                            'flow_metadata' => $component->flow_metadata,
                            'flow_address' => $nftAddress,
                            'price' => $price,
                            'is_completed' => false,
                            'is_dapper' => $isDapper,
                            'listing_id' => $listingId
                        ]
                    );
                    $check = true;
                }
                if (!$component->flow_address) {
                    $component->update(['flow_address' => $nftAddress]);
                }

                if (!$component->mint) {
                    if($mint){
                        $component->update(['mint' => $mint]);
                    } else {
                        $componentF = $this->parseFlowResponse($this->getFlovatarComponentSaleFromChain($nftId, $nftAddress));
                        if ($componentF && isset($componentF['metadata']['mint']) ) {
                            $component->update(['mint' => $componentF['metadata']['mint']]);
                        }
                    }
                }
            } else {
                info('SaleCompleted event skipped because Component was not found!!!!');
            }

        } else if($event->type == $this->getEventAddress('ComponentSalePrice')){
            $component = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
            if (!$component) {
                $component = $this->syncFlovatarComponentFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['address']);
            }

            if($component) {
                $price = $event->flow_metadata['newPrice'] ;

                if($price <= 999999) {
                    $saleOffer = SaleOffer::whereItemId($component->id)->whereItemType('App\Models\FlovatarComponent')->whereFlowId($event->flow_metadata['id'])->first();

                    if(!$saleOffer){
                        $componentF = $this->parseFlowResponse($this->getFlovatarComponentSaleFromChain($event->flow_metadata['id'], $event->flow_metadata['address']));
                        if ($componentF && isset($componentF['id']) ) {
                            SaleOffer::updateOrCreate(
                                [
                                    'item_id' => $component->id,
                                    'item_type' => 'App\Models\FlovatarComponent',
                                    'flow_id' => $event->flow_metadata['id']
                                ],
                                [
                                    'flow_metadata' => $component->flow_metadata,
                                    'flow_address' => $event->flow_metadata['address'],
                                    'price' => $price,
                                    'is_completed' => false
                                ]
                            );

                            $saleOffer = SaleOffer::whereItemId($component->id)->whereItemType('App\Models\FlovatarComponent')->whereFlowId($event->flow_metadata['id'])->first();

                        }
                    }

                    if($saleOffer){
                        $saleOffer->update([
                            'flow_metadata' => $component->flow_metadata,
                            'flow_address' => $event->flow_metadata['address'],
                            'price' => $price,
                            'is_completed' => false
                        ]);
                        $check = true;
                    }
                }
                if ($component && !$component->flow_address) {
                    $component->update(['flow_address' => $event->flow_metadata['address']]);
                }

                if($component && !$component->mint) {
                    $componentF = $this->parseFlowResponse($this->getFlovatarComponentSaleFromChain($event->flow_metadata['id'], $event->flow_metadata['address']));
                    if ($componentF && isset($componentF['metadata']['mint']) ) {
                        $component->update(['mint' => $componentF['metadata']['mint']]);
                    }
                }

            } else {
                info('SaleCreated event skipped because Component was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('ComponentSaleRemoved')){
            $componentF = $this->parseFlowResponse($this->getFlovatarComponentSaleFromChain($event->flow_metadata['tokenId'], $event->flow_metadata['address']));
            if(!$componentF) {
                SaleOffer::isComponent()->whereFlowId($event->flow_metadata['tokenId'])->delete();
                $check = true;
            }
        } else if($event->type == $this->getEventAddress('ComponentSaleCompleted') || ($event->type == $this->getStorefrontEventAddress('StorefrontSaleCompleted')   && $event->flow_metadata['nftType']['typeID'] == $this->getComponentNFTAddress())){

            $isDapper = false;
            $nftId = null;
            $nftAddress = null;
            $price = null;
            $listingId = null;
            $isPurchased = true;
            if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCompleted')){
                //DAPPER
                info('DAPPER COMPONENT SOLD!');
                $nftId = $event->flow_metadata['nftID'];
                $price = $event->flow_metadata['salePrice'];
                $listingId = $event->flow_metadata['listingResourceID'];
                $isDapper = true;
                $isPurchased = $event->flow_metadata['purchased'];
            } else {
                $nftId = $event->flow_metadata['id'];
                $price = $event->flow_metadata['price'];
            }

            $component = FlovatarComponent::whereFlowId($nftId)->first();
            $saleOffer = SaleOffer::isComponent()->whereFlowId($nftId)->first();

            if(!isset($event->flow_metadata['from']) && !($isDapper && $saleOffer)){
                info('NOT FOUND COMPONENT FROM EVENT');
            } else {
                $nftAddress = ($isDapper && $saleOffer) ? $saleOffer->flow_address : $event->flow_metadata['from'];
                if (!$component) {
                    $component = $this->syncFlovatarComponentFromFlowAddress($nftId, $nftAddress);
                }
                if (!$component) {
                    if ($saleOffer) {
                        $component = $saleOffer->item;
                    }
                }

                if ($isDapper) {
                    SaleOffer::isComponent()->whereFlowId($nftId)->delete();
                    $check = true;
                }

                if ($component && $isPurchased) {
                    Sale::firstOrCreate(
                        [
                            'item_id' => $component->id,
                            'item_type' => 'App\Models\FlovatarComponent',
                            'price' => $price,
                            'flow_buyer_address' => ($isDapper) ? $event->flow_metadata['storefrontAddress'] : $event->flow_metadata['to'],
                            'flow_seller_address' => $nftAddress,
                            'transaction_id' => $event->transaction_id,
                            'is_dapper' => $isDapper
                        ],
                        [
                            'completed_at' => $event->event_date ?? Carbon::now()
                        ]
                    );
                    $check = true;

                } else {
                    info('Sale Completed event skipped because Component was not found!!!!');
                }
            }
        } else if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCreated') && $event->flow_metadata['nftType']['typeID'] == $this->getCollectibleNFTAddress()){
            $isDapper = false;
            $nftId = null;
            $nftAddress = null;
            $price = null;
            $listingId = null;
            if(str_contains($event->flow_metadata['salePaymentVaultType']['typeID'], 'FlowUtilityToken.Vault')){
                //DAPPER
                info('DAPPER Flovatar ON SALE!');
                $isDapper = true;
            } else {
                $isDapper = false;
            }

            $nftId = $event->flow_metadata['nftID'];
            $nftAddress = $event->flow_metadata['storefrontAddress'];
            $price = $event->flow_metadata['salePrice'];
            $listingId = $event->flow_metadata['listingResourceID'];


            $collectible = DustCollectible::whereFlowId($nftId)->first();
            if(!$collectible){
                $collectible = $this->syncCollectibleFromFlowAddress($nftId, $nftAddress);
            }
            if($collectible) {
                if($price <= 999999) {
                    SaleOffer::updateOrCreate(
                        [
                            'item_id' => $collectible->id,
                            'item_type' => 'App\Models\DustCollectible',
                            'flow_id' => $nftId
                        ],
                        [
                            'flow_metadata' => $collectible->flow_metadata,
                            'flow_address' => $nftAddress,
                            'price' => $price,
                            'is_completed' => false,
                            'is_dapper' => $isDapper,
                            'listing_id' => $listingId
                        ]
                    );
                    $check = true;
                }
                if (!$collectible->flow_address) {
                    $collectible->update(['flow_address' => $nftAddress]);
                }
            } else {
                info('SaleCreated event skipped because Dust Collectible was not found!!!!');
            }
        } else if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCompleted')   && $event->flow_metadata['nftType']['typeID'] == $this->getCollectibleNFTAddress()){

            $isDapper = false;
            $nftId = null;
            $nftAddress = null;
            $price = null;
            $listingId = null;
            $isPurchased = true;

            if(str_contains($event->flow_metadata['salePaymentVaultType']['typeID'], 'FlowUtilityToken.Vault')){
                //DAPPER
                info('DAPPER Flovatar ON SALE!');
                $isDapper = true;
            } else {
                $isDapper = false;
            }

            $nftId = $event->flow_metadata['nftID'];
            $price = $event->flow_metadata['salePrice'];
            $listingId = $event->flow_metadata['listingResourceID'];
            $isPurchased = $event->flow_metadata['purchased'];

            $collectible = DustCollectible::whereFlowId($nftId)->first();
            $saleOffer = SaleOffer::whereFlowId($nftId)->whereListingId($listingId)->first();

            if(!$collectible || !$saleOffer){
                info('NOT FOUND COMPONENT FROM EVENT');
            } else {
                // NFTStorefrontV2 events carry no from/to; the seller is the listing owner.
                $nftAddress = $saleOffer->flow_address;
                if (!$collectible) {
                    $collectible = $this->syncCollectibleFromFlowAddress($nftId, $nftAddress);
                }
                if (!$collectible) {
                    if ($saleOffer) {
                        $collectible = $saleOffer->item;
                    }
                }

                SaleOffer::whereFlowId($nftId)->whereListingId($listingId)->delete();
                $check = true;

                if ($collectible && $isPurchased) {
                    Sale::firstOrCreate(
                        [
                            'item_id' => $collectible->id,
                            'item_type' => 'App\Models\DustCollectible',
                            'price' => $price,
                            'flow_buyer_address' => $event->flow_metadata['to'] ?? $event->flow_metadata['storefrontAddress'],
                            'flow_seller_address' => $nftAddress,
                            'transaction_id' => $event->transaction_id,
                            'is_dapper' => $isDapper
                        ],
                        [
                            'completed_at' => $event->event_date ?? Carbon::now()
                        ]
                    );
                    $check = true;

                } else {
                    info('Sale Completed event skipped because Dust Collectible was not found!!!!');
                }
            }
        } else if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCreated') && $event->flow_metadata['nftType']['typeID'] == $this->getFlobotNFTAddress()){

            if(str_contains($event->flow_metadata['salePaymentVaultType']['typeID'], 'FlowUtilityToken.Vault')){
                //DAPPER
                info('DAPPER Flovatar ON SALE!');
                $isDapper = true;
            } else {
                $isDapper = false;
            }

            $nftId = $event->flow_metadata['nftID'];
            $nftAddress = $event->flow_metadata['storefrontAddress'];
            $price = $event->flow_metadata['salePrice'];
            $listingId = $event->flow_metadata['listingResourceID'];


            $collectible = Flobot::whereFlowId($nftId)->first();
            if(!$collectible){
                $collectible = $this->syncFlobotFromFlowAddress($nftId, $nftAddress);
            }
            if($collectible) {
                if($price <= 999999) {
                    SaleOffer::updateOrCreate(
                        [
                            'item_id' => $collectible->id,
                            'item_type' => 'App\Models\Flobot',
                            'flow_id' => $nftId
                        ],
                        [
                            'flow_metadata' => $collectible->flow_metadata,
                            'flow_address' => $nftAddress,
                            'price' => $price,
                            'is_completed' => false,
                            'is_dapper' => $isDapper,
                            'listing_id' => $listingId
                        ]
                    );
                    $check = true;
                }
                if (!$collectible->flow_address) {
                    $collectible->update(['flow_address' => $nftAddress]);
                }
            } else {
                info('SaleCreated event skipped because Dust Collectible was not found!!!!');
            }
        } else if($event->type == $this->getStorefrontEventAddress('StorefrontSaleCompleted')   && $event->flow_metadata['nftType']['typeID'] == $this->getFlobotNFTAddress()){

            if(str_contains($event->flow_metadata['salePaymentVaultType']['typeID'], 'FlowUtilityToken.Vault')){
                //DAPPER
                info('DAPPER Flovatar ON SALE!');
                $isDapper = true;
            } else {
                $isDapper = false;
            }

            $nftId = $event->flow_metadata['nftID'];
            $price = $event->flow_metadata['salePrice'];
            $listingId = $event->flow_metadata['listingResourceID'];
            $isPurchased = $event->flow_metadata['purchased'];

            $collectible = Flobot::whereFlowId($nftId)->first();
            $saleOffer = SaleOffer::whereFlowId($nftId)->whereListingId($listingId)->first();

            if(!$collectible || !$saleOffer){
                info('NOT FOUND COMPONENT FROM EVENT');
            } else {
                // NFTStorefrontV2 events carry no from/to; the seller is the listing owner.
                $nftAddress = $saleOffer->flow_address;
                if (!$collectible) {
                    $collectible = $this->syncFlobotFromFlowAddress($nftId, $nftAddress);
                }
                if (!$collectible) {
                    if ($saleOffer) {
                        $collectible = $saleOffer->item;
                    }
                }

                SaleOffer::whereFlowId($nftId)->whereListingId($listingId)->delete();
                $check = true;

                if ($collectible && $isPurchased) {
                    Sale::firstOrCreate(
                        [
                            'item_id' => $collectible->id,
                            'item_type' => 'App\Models\Flobot',
                            'price' => $price,
                            'flow_buyer_address' => $event->flow_metadata['to'] ?? $event->flow_metadata['storefrontAddress'],
                            'flow_seller_address' => $nftAddress,
                            'transaction_id' => $event->transaction_id,
                            'is_dapper' => $isDapper
                        ],
                        [
                            'completed_at' => $event->event_date ?? Carbon::now()
                        ]
                    );
                    $check = true;

                } else {
                    info('Sale Completed event skipped because Dust Collectible was not found!!!!');
                }
            }
        } else if($event->type == $this->getEventAddress('FlovatarWithdraw')){
            //No need to do anything in this case
            $check = true;
            info('FlovatarWithdraw event skipped because not needed!!!!');
        } else if($event->type == $this->getEventAddress('FlovatarPackCreated') && $this->isGraffle === false){
            $pack = Pack::where('secret', 'like', $event->flow_metadata['prefix'].'%')->whereNull('flow_address')->whereNull('flow_id')->whereNull('flow_metadata')->orderBy('id', 'asc')->first();
            if($pack) {
                $pack->update([
                    'flow_id' => $event->flow_metadata['id'],
                    'flow_address' => config('services.flow.address'),
                    'flow_metadata' => $event->flow_metadata
                ]);
                $check = true;
            } else {
                info('Flovatar Pack Created event skipped because the unassigned Pack was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarTemplateCreated')){
            if(isset($event->flow_metadata['id'])) {
                info('Flovatar Template Created start!!!!');
                $this->syncFlovatarTemplateFromFlowId($event->flow_metadata['id']);
                $check = true;
            } else {
                info('Flovatar Template Created event skipped because template was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleSeriesCreated')){
            if(isset($event->flow_metadata['id'])) {
                info('Collectible Series Created start!!!!');
                $this->syncCollectibleSeriesFromFlowId($event->flow_metadata['id'], true);
                $check = true;
            } else {
                info('Flovatar Collectible Series Created event skipped because series was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleTemplateCreated')){
            if(isset($event->flow_metadata['id'])) {
                info('Collectible Template Created start!!!!');
                $this->syncCollectibleTemplateFromFlowId($event->flow_metadata['id'], true);
                $check = true;
            } else {
                info('Flovatar Collectible Template Created event skipped because series was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleCreated')){
            if(isset($event->flow_metadata['address'])) {
                $newCollectible = $this->syncCollectibleFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['address']);
                if($newCollectible){
                    $check = true;
                    if(!$newCollectible->flow_address) {
                        $newCollectible->update([
                            'flow_address' => $event->flow_metadata['address']
                        ]);
                    }
                    $this->updateCollectibleTemplatesBurnCount($newCollectible);
                    ExportAllImages::dispatch($event->flow_metadata['id'], 'collectible');
                } else {
                    info('Flovatar Collectible Create event failed because creator address was not found!!!!');
                    info($event->flow_metadata['id']);
                    info($event->flow_metadata['address']);
                    info(print_r($newCollectible,true));
                }
            } else {
                info('Flovatar Collectible Created event skipped because creator address was not found!!!!');
                info($event->flow_metadata['id']);
                info($event->flow_metadata['address']);
            }
        } else if($event->type == $this->getEventAddress('CollectibleWithdraw')){
            //No need to do anything in this case
            $check = true;
            info('CollectibleWithdraw event skipped because not needed!!!!');
        } else if($event->type == $this->getEventAddress('CollectibleDeposit')){
            $collectible = DustCollectible::whereFlowId($event->flow_metadata['id'])->first();
            if(!$collectible){
                $collectible = $this->syncCollectibleFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
            }
            if($collectible){
                $collectible->update(['flow_address' => $event->flow_metadata['to']]);
                $this->cleanupSales($event->flow_metadata['id'], $event->flow_metadata['to'], 'collectible');
                $check = true;
            } else {
                info('Collectible Deposit event skipped because Collectible was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleAccessoryCreated')){
            if(isset($event->flow_metadata['metadata']) && isset($event->flow_metadata['metadata']['id'])) {
                $flovatar = DustAccessory::whereFlowId($event->flow_metadata['id'])->first();
                if (!$flovatar) {
                    $flovatar = $this->syncCollectibleAccessoryFromFlowAddress($event->flow_metadata['id'], config('flovatar.contractAddress'));
                }
                if (!$flovatar) {
                    info('Flovatar Collectible Accessory Created event skipped because Accessory was not found!!!!');
                }
            } else {
                info('Flovatar Collectible Accessory Created event skipped because creator address was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleAccessoryWithdraw')){
            $flovatar = DustAccessory::whereFlowId($event->flow_metadata['id'])->first();
            if($flovatar) {
                $flovatar->update(['flow_address' => null]);
                $check = true;
            } else {
                info('Flovatar Collectible Accessory Withdraw event skipped because Accessory was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleAccessoryDeposit')){
            if(isset($event->flow_metadata['to']) && $event->flow_metadata['to']) {
                $flovatar = DustAccessory::whereFlowId($event->flow_metadata['id'])->first();
                if (!$flovatar) {
                    $flovatar = $this->syncCollectibleAccessoryFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
                }
                if ($flovatar) {
                    $flovatar->update(['flow_address' => $event->flow_metadata['to']]);
                    $check = true;
                } else {
                    info('Flovatar Collectible Accessory Deposit event skipped because Accessory was not found!!!!');
                }
            } else {
                info('Flovatar Collectible Accessory Deposit event skipped because sent to null!!!!');
                $this->event->delete();
                return;
            }
        } else if($event->type == $this->getEventAddress('CollectibleAccessoryDestroyed')){
            info('Flovatar Collectible Accessory Destroyed check start!!!!');
            $accessory = DustAccessory::whereFlowId($event->flow_metadata['id'])->first();
            if($accessory) {
                $accessory->update(['destroyed' => 1]);
                $check = true;
            } else {
                info('Flovatar Collectible Accessory Destroyed event skipped because Accessory was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('CollectibleUpdated') ||
                $event->type == $this->getEventAddress('CollectibleNameSet') ||
                $event->type == $this->getEventAddress('CollectiblePositionChanged') ||
                $event->type == $this->getEventAddress('CollectibleStoryAdded') ){
            $collectible = DustCollectible::whereFlowId($event->flow_metadata['id'])->first();
            if($collectible) {
                if($this->syncCollectibleFromFlowAddress($collectible->flow_id, $collectible->flow_address)){
                    $check = true;
                }
                ExportAllImages::dispatch($event->flow_metadata['id'], 'collectible');
            } else {
                info('Flovatar Collectible Updated event skipped because Collectible was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarWithdrawDust') ||
            $event->type == $this->getEventAddress('WalletWithdrawDust') ||
            $event->type == $this->getEventAddress('FlovatarClaimedCommunityDust')) {

            // Locked read-modify-write: the counter file is shared by all workers.
            \Illuminate\Support\Facades\Cache::lock('dust-counter-claimed', 30)->block(30, function () use ($event) {
                $claimedDust = Storage::exists('claimed-dust.txt') ? Storage::get('claimed-dust.txt') : 0;

                if(!$claimedDust){
                    $claimedDust = 0.0;
                } else {
                    $claimedDust = floatval($claimedDust);
                }

                $claimedDust += floatval($event->flow_metadata['amount']);

                Storage::put('claimed-dust.txt', $claimedDust);
            });
            $check = true;

        } else if($event->type == $this->getEventAddress('DustTokensBurned')) {
            \Illuminate\Support\Facades\Cache::lock('dust-counter-burned', 30)->block(30, function () use ($event) {
                $burnedDust = Storage::exists('burned-dust.txt') ? Storage::get('burned-dust.txt') : 0;

                if(!$burnedDust){
                    $burnedDust = 0.0;
                } else {
                    $burnedDust = floatval($burnedDust);
                }

                $burnedDust += floatval($event->flow_metadata['amount']);

                Storage::put('burned-dust.txt', $burnedDust);
            });
            $check = true;
        } else {

            info('Event skipped because not parsed!!!!  '.$event->type);
            return;
        }


        if($check){
            $this->event->update(['synced' => true]);
            // headless static site: re-render the affected item page (also covers query-builder deletes/updates
            // of listings, which fire no model events)
            \App\Support\HeadlessRegen::fromEvent($event->type, $event->flow_metadata);
        }



    }

    /** NonFungibleToken.Deposited of a Flobot NFT into an account collection. */
    protected function isStandardFlobotDeposit($event): bool
    {
        return \App\Services\Flow\FlowEventTypes::isStandardNft((string) $event->type)
            && str_ends_with((string) $event->type, '.NonFungibleToken.Deposited')
            && ($event->flow_metadata['type'] ?? null) === $this->getFlobotNFTAddress()
            && ! empty($event->flow_metadata['to']);
    }
}
