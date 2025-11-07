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

class MintDustTemplate extends BaseJob
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

        Flow::transaction('./transactions/create/dust_template.cdc')
            ->argString($this->template->name)
            ->argString(''.$this->template->description)
            ->argInt($this->template->dustSeries->flow_id)
            ->argInt32($this->template->layer)
            ->argString($this->template->rarity)
            ->argFix($this->template->base_price)
            ->argString($this->template->svg)
            ->argInt($this->template->max_mintable)
            ->signer(config('flovatar.signer'))
            ->run();
    }
}
