<?php

namespace App\Jobs;


use Flow;
use Str;

class SendDustToFlovatars extends BaseJob
{

    protected $amounts;
    protected $ids;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($amounts, $ids)
    {
        $this->amounts = $amounts;
        $this->ids =  $ids;
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

        $output = Flow::transaction('./transactions/setup/transfer_dust_to_flovatar_batch.cdc')
            ->argArrayFix($this->amounts)
            ->argArrayInt($this->ids)
            ->signer(config('flovatar.signer'))
            ->run();
        info(print_r($output, true));
    }
}
