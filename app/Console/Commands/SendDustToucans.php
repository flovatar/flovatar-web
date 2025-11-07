<?php

namespace App\Console\Commands;

use App\Jobs\SendToucansDust as SendToucansDustJob;
use App\Models\FlovatarComponent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SendDustToucans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flovatar:send-dust-toucans {amount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Dust to Toucans';

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


        $address = "0x2a0eccae942667be";

        info('Sending '.$amount.' DUST to '.$address);
        $this->info('Sending '.$amount.' DUST to '.$address);
        SendToucansDustJob::dispatch($amount, $address);
        $output = $amount.' - '.$address."\n";

        info('Finished sending DUST '.$amount);
        $this->info('Finished sending DUST '.$amount);

        return true;
    }
}
