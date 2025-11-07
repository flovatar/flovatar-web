<?php

namespace App\Jobs;

use App\Models\DustTemplate;
use App\Models\GptAssistant;
use Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class SyncGptAssistant extends BaseJob
{
    protected $assistant;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(GptAssistant $assistant)
    {
        $this->assistant = $assistant;
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

        $assistantApi = OpenAI::assistants()->modify($this->assistant->gpt_id,[
            'instructions' => $this->assistant->system_instruction.' '.$this->assistant->user_instruction
        ]);
        //info(print_r($assistantApi,true));

    }
}
