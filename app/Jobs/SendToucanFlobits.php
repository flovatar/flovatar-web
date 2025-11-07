<?php

namespace App\Jobs;

use App\Models\FlovatarComponent;
use App\Models\Pack;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Flow;
use Str;

class SendToucanFlobits extends BaseJob
{

    protected $components;
    protected $address;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($components, $address)
    {
        $this->components = $components;
        $this->address =  Str::of($address)->trim();
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

        $output = Flow::transaction('./transactions/act/toucans_send_nft.cdc')
            ->argAddress($this->address)
            ->argString('FlovatarDAO')
            ->argArrayInt($this->components)
            ->argString("FlovatarComponent")
            ->argString("DAO Treasury refill")
            ->signer(config('flovatar.signer'))
            ->run();
        info(print_r($output, true));
    }
}

/*
 * projectOwner: 0x2a0eccae942667be
 * projectId: FlovatarDAO
 * nftIDs: [1,2,3]
 * collectionIdentifier: FlovatarComponent
 * message: DAO Treasury refill
 */
