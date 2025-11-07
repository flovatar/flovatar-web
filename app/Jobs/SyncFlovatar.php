<?php

namespace App\Jobs;

use App\Models\Flovatar;
use Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncFlovatar extends BaseJob
{
    protected $flovatar;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Flovatar $flovatar)
    {
        $this->flovatar = $flovatar;
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     *
     */
    public function handle()
    {
        $flovatar = $this->syncFlovatarFromFlowAddress($this->flovatar->flow_id, $this->flovatar->flow_address);
    }
}
