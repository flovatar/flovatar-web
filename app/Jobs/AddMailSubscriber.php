<?php

namespace App\Jobs;

use App\Models\FlovatarTemplate;
use App\Models\Subscriber;
use Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class AddMailSubscriber extends BaseJob
{
    protected $subscriber;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
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
        Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.sender_mail.token'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post(config('services.sender_mail.url'), [
            'email' => $this->subscriber->email,
            'groups' => ['dP76Gw']
        ]);

    }
}
