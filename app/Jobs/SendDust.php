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

class SendDust extends BaseJob
{

    protected $amount;
    protected $address;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($amount, $address)
    {
        $this->amount = $amount;
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

        $output = Flow::transaction('./transactions/setup/transfer_dust.cdc')
            ->argFix($this->amount)
            ->argAddress($this->address)
            ->signer(config('flovatar.signer'))
            ->run();
        info(print_r($output, true));
    }
}
