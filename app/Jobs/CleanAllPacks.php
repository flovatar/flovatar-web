<?php

namespace App\Jobs;


use App\Models\Pack;
use Carbon\Carbon;
use Flow;

class CleanAllPacks extends BaseJob
{


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
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

        $packsAvailable = $this->parseFlowResponse(Flow::script('./scripts/get_packs.cdc')->argAddress(config('services.flow.address'))->run());

        if($packsAvailable && is_array($packsAvailable)){

            info(count($packsAvailable));
            $count1 = Pack::whereIn('flow_id', $packsAvailable)->whereNotNull('user_id')->update([
                'user_id' => null,
                'assigned_at' => null
            ]);

            $count2 = Pack::whereNotIn('flow_id', $packsAvailable)->whereNull('user_id')->update([
                'user_id' => 1,
                'assigned_at' => Carbon::now()
            ]);

            info('UPDATED PACKS '.$count1. ' - '.$count2);

        }

    }

}
