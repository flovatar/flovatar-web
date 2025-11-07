<?php

namespace App\Jobs;

use App\Models\DustTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Flow;

class MintDustAccessory extends BaseJob
{
    protected $template;
    protected $quantity;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(DustTemplate $template, $quantity = 1)
    {
        $this->template = $template;
        $this->quantity = $quantity;
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->quantity === 1){
            Flow::transaction('./transactions/create/dust_accessory.cdc')
                ->argInt($this->template->flow_id)
                ->signer(config('flovatar.signer'))
                ->run();
        } else {
            $res = Flow::transaction('./transactions/create/dust_accessory_batch.cdc')
                ->argInt($this->template->flow_id)
                ->argInt($this->quantity)
                ->signer(config('flovatar.signer'))
                ->run();
            info('minted dust accessory from templated '.$this->template->flow_id.' with this quantity '.$this->quantity);
            info(print_r($res,true));
        }
    }
}
