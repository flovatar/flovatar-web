<?php

namespace App\Jobs;


use Flow;
use Str;

class SendDustToFlovatar extends BaseJob
{

    protected $amount;
    protected $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($amount, $id)
    {
        $this->amount = $amount;
        $this->id =  $id;
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

        $output = Flow::transaction('./transactions/setup/transfer_dust_to_flovatar.cdc')
            ->argArrayFix($this->amount)
            ->argArrayInt($this->id)
            ->signer(config('flovatar.signer'))
            ->run();
        info(print_r($output, true));
    }
}
