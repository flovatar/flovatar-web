<?php

namespace App\Jobs;

use App\Models\DustTemplate;
use Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCollectibleTemplate extends BaseJob
{
    protected $template;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(DustTemplate $template)
    {
        $this->template = $template;
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
        $this->syncCollectibleTemplateFromFlowId($this->template->flow_id, true);
    }
}
