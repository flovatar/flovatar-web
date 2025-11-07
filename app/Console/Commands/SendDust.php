<?php

namespace App\Console\Commands;

use App\Jobs\SendDust as SendDustJob;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendDust extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-dust {amount} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Dust';

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

        $amount = $this->argument('amount');

        info('Sending '.$amount);
        $this->info('Sending '.$amount);

        $file = Storage::disk('localstorage')->get('airdrop/'.$this->argument('file'));

        info('Reading file '.$this->argument('file'));
        $this->info('Reading file '.$this->argument('file'));

        $addressArray = explode("\n", $file);


        foreach($addressArray as $address){
            info('Sending '.$amount.' DUST to '.$address);
            $this->info('Sending '.$amount.' DUST to '.$address);
            SendDustJob::dispatch($amount, $address);
            $output = $amount.' - '.$address."\n";
        }

        Storage::disk('localstorage')->put('airdrop/archive/Completed-'.$this->argument('file'), $output);

        info('Finished sending DUST '.$amount);
        $this->info('Finished sending DUST '.$amount);

        return true;
    }
}
