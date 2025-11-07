<?php

namespace App\Jobs;

use App\Models\FlovatarTemplate;
use Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MintTemplate extends BaseJob
{
    protected $template;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(FlovatarTemplate $template)
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
        Flow::transaction('./transactions/create/component_template.cdc')
            ->argString($this->template->name)
            ->argString($this->template->category)
            ->argString($this->template->color)
            ->argString(''.$this->template->description)
            ->argString($this->template->svg)
            ->argInt32($this->template->series)
            ->argInt($this->template->max_mintable)
            ->argString($this->template->rarity)
            ->signer(config('flovatar.signer'))
            ->run();
    }
}
