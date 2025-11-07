<?php

namespace App\Console\Commands;

use App\Jobs\SendFlobit;
use App\Jobs\SendFlobitsInbox;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendFlobitById extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-flobit-by-id {id} {address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Flobit by ID';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id');
        $address = $this->argument('address');

        info('Send '.$id.' to '.$address);
        $this->info('Send '.$id.' to '.$address);

        $component = FlovatarComponent::whereFlowId($id)->whereFlowAddress(config('services.flow.address'))->whereDestroyed(0)->wherePacked(0)->first();

        if($component){
            info('Sending component '.$component->flow_id.' to '.$address);
            $this->info('Sending component '.$component->flow_id.' to '.$address);
            $component->update([
                'flow_address' => $address
            ]);
            SendFlobitsInbox::dispatch([$component->flow_id], $address);
        } else {
            $this->error('Cannot find component!!!');
        }

        info('Finished sending component');
        $this->info('Finished sending component');

        return true;
    }
}
