<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\Pack;
use App\Models\Sale;
use App\Models\SaleOffer;
use Carbon\Carbon;


class ProcessEvents extends BaseJob
{


    protected $event;

    public function __construct($event)
    {
        $this->event = $event;
        parent::__construct();
    }



    public function getName(){
        return 'ProcessEvents';
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
                    $this->updateTemplatesBurnCount($newFlovatar);
                }
            } else {
                info('Flovatar Created event skipped because creator address was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarDeposit')){
            if(isset($event->flow_metadata['to']) && $event->flow_metadata['to']) {
                $flovatar = $this->syncFlovatarFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
                if (!$flovatar) {
                    $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
                }
                if ($flovatar) {
                    $flovatar->update(['flow_address' => $event->flow_metadata['to']]);
                    $check = true;
                } else {
                    info('Flovatar Deposit event skipped because Flovatar was not found!!!!');
                }
            } else {
                info('Flovatar Deposit event skipped because sent to null!!!!');
                $this->event->delete();
                return;
            }
        } else if($event->type == $this->getEventAddress('FlovatarUpdated')){
            $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
            if($flovatar) {
                $this->syncFlovatarFromFlowAddress($flovatar->flow_id, $flovatar->flow_address);
                $check = true;
            } else {
                info('Flovatar Updated event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarComponentCreated')){
            if(isset($event->flow_metadata['id']) && isset($event->flow_metadata['templateId'])) {
                $this->syncFlovatarComponentFromFlow($event->flow_metadata['id'], $event->flow_metadata);
                $check = true;
            } else {
                info('Flovatar Component Created event skipped because template was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarComponentDeposit')){
            if(isset($event->flow_metadata['to']) && $event->flow_metadata['to']) {
                $flovatar = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
                if (!$flovatar) {
                    $flovatar = $this->syncFlovatarComponentFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['to']);
                }
                if ($flovatar) {
                    $flovatar->update(['flow_address' => $event->flow_metadata['to']]);
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
                $check = true;
            } else {
                info('Flovatar Component Destroyed event skipped because Component was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarTemplateCreated')){
            if(isset($event->flow_metadata['id'])) {
                info('Flovatar Template Created start!!!!');
                $this->syncFlovatarTemplateFromFlowId($event->flow_metadata['id']);
                $check = true;
            } else {
                info('Flovatar Template Created event skipped because template was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarPackCreated')){
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
        } else if($event->type == $this->getEventAddress('FlovatarPackDeposit')){
            $pack = $this->syncFlovatarPackFromFlow($event->flow_metadata['id'], $event->flow_metadata);
            if($pack){
                $pack->update(['flow_address' => $event->flow_metadata['to']]);
                $check = true;
            } else {
                info('Flovatar Pack Deposit event skipped because Pack was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarPackOpened')){
            $pack = $this->syncFlovatarPackFromFlow($event->flow_metadata['id'], $event->flow_metadata);
            if($pack) {
                $pack->update(['opened' => 1]);
                $check = true;
            } else {
                info('Flovatar Pack Opened event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarSaleCreated') || $event->type == $this->getEventAddress('FlovatarSalePrice')){
            $flovatar = $this->syncFlovatarFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['address']);
            if($flovatar) {
                $price = ($event->type == $this->getEventAddress('FlovatarSalePrice')) ? $event->flow_metadata['newPrice'] : $event->flow_metadata['price'];
                SaleOffer::updateOrCreate(
                    [
                        'item_id' => $flovatar->id,
                        'item_type' => 'App\Models\Flovatar',
                        'flow_id' => $event->flow_metadata['id'],
                        'flow_address' => $event->flow_metadata['address']
                    ],
                    [
                        'flow_metadata' => $flovatar->flow_metadata,
                        'price' => $price,
                        'is_completed' => false
                    ]
                );
                if (!$flovatar->flow_address) {
                    $flovatar->update(['flow_address' => $event->flow_metadata['address']]);
                }
                $check = true;
            } else {
                info('SaleCreated event skipped because Flovatar was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarSaleRemoved')){
            SaleOffer::isFlovatar()->whereFlowId($event->flow_metadata['tokenId'])->whereFlowAddress($event->flow_metadata['address'])->delete();
            $check = true;

        } else if($event->type == $this->getEventAddress('FlovatarSaleCompleted')){

            $flovatar = $this->syncFlovatarFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['from']);
            if (!$flovatar) {
                $saleOffer = SaleOffer::isFlovatar()->whereFlowId($event->flow_metadata['id'])->whereFlowAddress($event->flow_metadata['from'])->first();
                if($saleOffer){
                    $flovatar = $saleOffer->item;
                }
            }
            if(!$flovatar && $event->flow_metadata['id'] != null){
                $flovatar = Flovatar::whereFlowId($event->flow_metadata['id'])->first();
            }
            if($flovatar){
                Sale::firstOrCreate(
                    [
                        'item_id' => $flovatar->id,
                        'item_type' => 'App\Models\Flovatar',
                        'price' => $event->flow_metadata['price'],
                        'flow_buyer_address' => $event->flow_metadata['to'],
                        'flow_seller_address' => $event->flow_metadata['from']
                    ],
                    [
                        'completed_at' => Carbon::now()
                    ]
                );
                $check = true;
            } else {
                info('Sale Completed event skipped because Flovatar was not found!!!!');
            }

        } else if($event->type == $this->getEventAddress('ComponentSaleCreated') || $event->type == $this->getEventAddress('ComponentSalePrice')){
            $component = $this->syncFlovatarComponentFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['address']);
            if($component) {
                $price = ($event->type == $this->getEventAddress('ComponentSalePrice')) ? $event->flow_metadata['newPrice'] : $event->flow_metadata['price'];
                SaleOffer::updateOrCreate(
                    [
                        'item_id' => $component->id,
                        'item_type' => 'App\Models\FlovatarComponent',
                        'flow_id' => $event->flow_metadata['id'],
                        'flow_address' => $event->flow_metadata['address']
                    ],
                    [
                        'flow_metadata' => $component->flow_metadata,
                        'price' => $price,
                        'is_completed' => false
                    ]
                );
                if (!$component->flow_address) {
                    $component->update(['flow_address' => $event->flow_metadata['address']]);
                }
                $check = true;

            } else {
                info('SaleCreated event skipped because Component was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('ComponentSaleRemoved')){
            SaleOffer::isComponent()->whereFlowId($event->flow_metadata['tokenId'])->whereFlowAddress($event->flow_metadata['address'])->delete();
            $check = true;
        } else if($event->type == $this->getEventAddress('ComponentSaleCompleted')){

            $flovatar = $this->syncFlovatarComponentFromFlowAddress($event->flow_metadata['id'], $event->flow_metadata['from']);
            if (!$flovatar) {
                $saleOffer = SaleOffer::isComponent()->whereFlowId($event->flow_metadata['id'])->whereFlowAddress($event->flow_metadata['from'])->first();
                if($saleOffer){
                    $flovatar = $saleOffer->item;
                }
            }
            if(!$flovatar && $event->flow_metadata['id'] != null){
                $flovatar = FlovatarComponent::whereFlowId($event->flow_metadata['id'])->first();
            }
            if($flovatar){
                Sale::firstOrCreate(
                    [
                        'item_id' => $flovatar->id,
                        'item_type' => 'App\Models\FlovatarComponent',
                        'price' => $event->flow_metadata['price'],
                        'flow_buyer_address' => $event->flow_metadata['to'],
                        'flow_seller_address' => $event->flow_metadata['from']
                    ],
                    [
                        'completed_at' => Carbon::now()
                    ]
                );
                $check = true;
            } else {
                info('SaleCompleted event skipped because Component was not found!!!!');
            }
        } else if($event->type == $this->getEventAddress('FlovatarWithdraw')){
            //No need to do anything in this case
            $check = true;
            info('FlovatarWithdraw event skipped because not needed!!!!');
        } else {
            info('Event skipped because not parsed!!!!  '.$event->type);
            return;
        }

        if($check){
            $this->event->update(['synced' => true]);
        }


    }
}
