<?php

namespace App\Console\Commands;

use App\Jobs\SendFlobitsInbox;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendFlobitsByTemplateToInbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-flobits-by-template {template} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Flobits in batch to Inbox';

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

        $output = "";

        $template = $this->argument('template');

        info('Sending '.$template);
        $this->info('Sending '.$template);

        $file = Storage::disk('localstorage')->get('airdrop/'.$this->argument('file'));

        info('Reading file '.$this->argument('file'));
        $this->info('Reading file '.$this->argument('file'));

        $addressArray = explode("\n", $file);


        foreach($addressArray as $address){


            $components = FlovatarComponent::where('flovatar_template_id', $template)->whereFlowAddress(config('services.flow.address'))->whereDestroyed(0)->wherePacked(0)->inRandomOrder()->take(1)->get();


            if($components){
                info('Sending component '.$components->count().' to '.$address);
                $this->info('Sending component '.$components->count().' to '.$address);
                foreach($components as $component){
                    $component->update([
                        'flow_address' => $address
                    ]);
                    $output = $output.$component->flow_id.' - '.$address."\n";
                }
                SendFlobitsInbox::dispatch($components->pluck('flow_id'), $address);
            }
        }

        Storage::disk('localstorage')->put('airdrop/archive/Completed-'.$this->argument('file'), $output);

        info('Finished sending components '.$template);
        $this->info('Finished sending components '.$template);



        return true;
    }
}
