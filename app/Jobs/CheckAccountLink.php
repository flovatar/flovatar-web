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

class CheckAccountLink extends BaseJob
{

    protected $amount;
    protected $address;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($address)
    {
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

        $results = Flow::script('./scripts/check_account_links.cdc')
            ->argAddress($this->address)
            ->run();

        $resJson = json_decode($results);
        if($resJson && isset($resJson->type) && $resJson->type == 'Bool' && isset($resJson->value) && $resJson->value === true){
            info('ACCOUNT WITH LINK PROBLEM! '.$this->address);
        } else {
            //info('ACCOUNT '.$this->address.' CHECK RESULT: '.$results);
        }
    }
}
